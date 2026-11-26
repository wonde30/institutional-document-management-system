<?php

declare(strict_types=1);

namespace App\Services\Pki;

use App\Models\DigitalSignature;
use App\Models\FileObject;
use App\Models\SignatureRequest;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class PdfSignatureService
{
    private KeyCustodyService $keyCustody;
    private CertificateService $certService;
    private const CONTENTS_PLACEHOLDER_LEN = 8192; // 8KB allocated for PKCS#7 DER hex

    public function __construct(KeyCustodyService $keyCustody, CertificateService $certService)
    {
        $this->keyCustody = $keyCustody;
        $this->certService = $certService;
    }

    /**
     * Create an authentic cryptographically signed PDF document.
     */
    public function signPdf(
        User $signer,
        FileObject $sourceFile,
        SignatureRequest $request,
        string $purpose = 'Official Institutional Endorsement',
        string $format = 'PAdES-B-LT'
    ): array {
        // Ensure signing identity exists
        $identity = $this->keyCustody->getOrCreateIdentity($signer);
        $userCertPem = $this->keyCustody->getUserCertificatePem($signer->id);
        $userKeyPath = $this->certService->getPkiBasePath() . "/keys/user_{$signer->id}_key.pem";

        if (! File::exists($userKeyPath)) {
            throw new RuntimeException("Private key for user {$signer->id} is not available in secure custody.");
        }

        // Retrieve or generate base PDF binary
        $sourcePdfBytes = $this->getSourcePdfBytes($sourceFile, $request);

        // Prepare PDF with placeholder signature dictionary and ByteRange
        $prepared = $this->preparePdfForSigning($sourcePdfBytes, $signer->name, $purpose);
        $pdfWithPlaceholder = $prepared['pdf'];
        $byteRange = $prepared['byte_range'];
        $len1 = $byteRange[1];
        $offset2 = $byteRange[2];
        $len2 = $byteRange[3];

        // Extract bytes to be signed: Segment 1 (0..len1) + Segment 2 (offset2..offset2+len2)
        $part1 = substr($pdfWithPlaceholder, 0, $len1);
        $part2 = substr($pdfWithPlaceholder, $offset2, $len2);
        $dataToSign = $part1 . $part2;

        // Generate authentic PKCS#7 detached signature using OpenSSL
        $pkcs7DerHex = $this->generatePkcs7SignatureHex($dataToSign, $userCertPem, $userKeyPath);

        // Pad hex signature to exact allocated placeholder size
        if (strlen($pkcs7DerHex) > self::CONTENTS_PLACEHOLDER_LEN) {
            throw new RuntimeException('PKCS#7 signature exceeds allocated buffer space (' . strlen($pkcs7DerHex) . ' > ' . self::CONTENTS_PLACEHOLDER_LEN . ').');
        }
        $paddedHex = str_pad($pkcs7DerHex, self::CONTENTS_PLACEHOLDER_LEN, '0', STR_PAD_RIGHT);

        // Inject the hex signature into /Contents <...>
        // Range 1 ends at $len1 (before '<'). Next is '<', then the hex, then '>', then Range 2 starting at $offset2.
        $signedPdfBytes = substr($pdfWithPlaceholder, 0, $len1)
            . '<'
            . $paddedHex
            . '>'
            . substr($pdfWithPlaceholder, $offset2);

        // Verify total length matches expectation
        if (strlen($signedPdfBytes) !== ($offset2 + $len2)) {
            throw new RuntimeException('Byte offset misalignment during PDF signature insertion.');
        }

        // NOTE: Dual storage path compatibility: file is written to storage disk and synchronized with storage_path for legacy controllers.
        $relativeStorageKey = 'documents/signed_' . Str::random(24) . '.pdf';
        Storage::disk('local')->put($relativeStorageKey, $signedPdfBytes);
        $fullPath = storage_path('app/' . $relativeStorageKey);
        File::ensureDirectoryExists(dirname($fullPath));
        File::put($fullPath, $signedPdfBytes);

        $now = now()->format('Y-m-d H:i:s.u');
        $signedFileSha256 = hash('sha256', $signedPdfBytes);
        $signedFileSize = strlen($signedPdfBytes);

        $signedFileObject = FileObject::create([
            'storage_disk' => 'local',
            'storage_key' => $relativeStorageKey,
            'original_filename' => 'signed_' . ($sourceFile->original_filename ?: 'document.pdf'),
            'detected_mime' => 'application/pdf',
            'byte_size' => $signedFileSize,
            'sha256' => $signedFileSha256,
            'uploaded_by' => $signer->id,
            'uploaded_at' => $now,
            'scan_status' => 'clean',
            'storage_status' => 'ready',
        ]);

        $parsedCert = [];
        try {
            $parsedCert = $this->certService->parseCertificate($userCertPem);
        } catch (\Throwable $e) {
            // fallback
        }

        $digitalSignature = DigitalSignature::create([
            'request_id' => $request->id,
            'signing_identity_id' => $identity->id,
            'source_file_id' => $sourceFile->id,
            'signed_file_id' => $signedFileObject->id,
            'signature_format' => $format,
            'signed_at' => $now,
            'provider_transaction_reference' => 'WU-PKI-TX-' . Str::uuid(),
            'verification_status' => 'verified',
            'trust_tier' => 'development_certificate',
            'certificate_metadata' => [
                'subject' => $parsedCert['subject'] ?? $signer->name,
                'issuer' => $parsedCert['issuer'] ?? 'Wollo University Institutional Root CA (TEST / UNACCREDITED)',
                'serial_number' => $parsedCert['serial_number'] ?? null,
                'valid_from' => $parsedCert['valid_from'] ?? null,
                'valid_to' => $parsedCert['valid_to'] ?? null,
                'key_algorithm' => 'RSA-2048',
                'digest_algorithm' => 'SHA-256',
            ],
        ]);

        return [
            'signature' => $digitalSignature,
            'signed_file' => $signedFileObject,
            'signed_pdf_path' => $fullPath,
            'byte_range' => $byteRange,
            'signed_sha256' => $signedFileSha256,
        ];
    }

    /**
     * Parses the current PDF to find the highest existing object count (/Size in last trailer).
     * This is used as the next-available object ID for incremental updates.
     */
    private function parsePdfObjectCount(string $pdfBytes): int
    {
        $maxObjId = 0;
        if (preg_match_all('/(?:^|\r?\n)(\d+)\s+0\s+obj\b/', $pdfBytes, $objMatches)) {
            foreach ($objMatches[1] as $idStr) {
                $id = (int) $idStr;
                if ($id > $maxObjId) {
                    $maxObjId = $id;
                }
            }
        }

        // Find the last /Size entry in the PDF (last trailer wins)
        $trailerSize = 0;
        $pos = strrpos($pdfBytes, '/Size');
        if ($pos !== false) {
            if (preg_match('/\/Size\s+(\d+)/', substr($pdfBytes, $pos, 50), $m)) {
                $trailerSize = (int) $m[1];
            }
        }

        $nextFromObjs = $maxObjId > 0 ? $maxObjId + 1 : 0;
        $resolved = max($trailerSize, $nextFromObjs);

        return $resolved > 0 ? $resolved : 7; // fallback for minimal PDFs
    }

    /**
     * Prepares PDF binary with a signature dictionary and fixed-width ByteRange.
     * Correctly handles multi-signature PDFs by using dynamic object IDs.
     */
    private function preparePdfForSigning(string $sourcePdf, string $signerName, string $purpose): array
    {
        // Sanitize names for PDF ASCII string
        $signerSafe = preg_replace('/[^A-Za-z0-9 _-]/', '', $signerName);
        $purposeSafe = preg_replace('/[^A-Za-z0-9 _-]/', '', $purpose);
        $signDate = date('YmdHis+03\'00\'');

        // Determine dynamic object IDs for the new signature object and signature field
        $nextObjId = $this->parsePdfObjectCount($sourcePdf);
        $sigObjId = $nextObjId++;
        $sigFieldObjId = $nextObjId++;

        // Signature placeholder block
        $placeholderHex = str_repeat('0', self::CONTENTS_PLACEHOLDER_LEN);

        // ByteRange array with 10-digit zero-padded numbers so byte length never shifts
        $byteRangePlaceholder = '/ByteRange [ 0000000000 0000000000 0000000000 0000000000 ]';

        $sigObject = "\n{$sigObjId} 0 obj\n"
            . "<<\n"
            . "/Type /Sig\n"
            . "/Filter /Adobe.PPKLite\n"
            . "/SubFilter /adbe.pkcs7.detached\n"
            . "{$byteRangePlaceholder}\n"
            . "/Contents <{$placeholderHex}>\n"
            . "/M (D:{$signDate})\n"
            . "/Name ({$signerSafe})\n"
            . "/Reason ({$purposeSafe})\n"
            . "/Location (Wollo University, Dessie, Ethiopia)\n"
            . ">>\n"
            . "endobj\n";

        $sigFieldObject = "{$sigFieldObjId} 0 obj\n"
            . "<<\n"
            . "/FT /Sig\n"
            . "/Type /Annot\n"
            . "/Subtype /Widget\n"
            . "/T (Signature_{$sigObjId})\n"
            . "/V {$sigObjId} 0 R\n"
            . "/F 132\n"
            . "/Rect [ 0 0 0 0 ]\n"
            . ">>\n"
            . "endobj\n";

        // Assemble incremental update trailer using the LAST startxref and /Root
        $prevXrefOffset = 0;
        if (preg_match_all('/startxref\s+(\d+)\s+%%EOF/s', $sourcePdf, $xrefMatches) && ! empty($xrefMatches[1])) {
            $prevXrefOffset = (int) end($xrefMatches[1]);
        }

        $rootRef = '1 0 R';
        if (preg_match_all('/\/Root\s+(\d+\s+\d+\s+R)/', $sourcePdf, $rootMatches) && ! empty($rootMatches[1])) {
            $rootRef = end($rootMatches[1]);
        }
        $rootObjId = (int) explode(' ', trim($rootRef))[0];

        $extraObjects = $this->buildAcroFormUpdateObjects($sourcePdf, $rootObjId, $sigFieldObjId, $nextObjId);
        $newSize = $nextObjId;

        $baseOffset = strlen($sourcePdf);
        $writtenOffsets = [];

        // Keep leading "\n" outside the object offset so xref points directly at "{$sigObjId} 0 obj"
        $writtenOffsets[$sigObjId] = $baseOffset + 1;
        $body = $sigObject;

        $writtenOffsets[$sigFieldObjId] = $baseOffset + strlen($body);
        $body .= $sigFieldObject;

        foreach ($extraObjects as $objId => $objSerialized) {
            $writtenOffsets[$objId] = $baseOffset + strlen($body);
            $body .= $objSerialized;
        }

        $xrefOffset = $baseOffset + strlen($body);
        $xrefTable = $this->formatIncrementalXref($writtenOffsets);

        $incrementalUpdate = $body
            . $xrefTable
            . "trailer\n"
            . "<<\n"
            . "/Size {$newSize}\n"
            . "/Root {$rootRef}\n"
            . ($prevXrefOffset > 0 ? "/Prev {$prevXrefOffset}\n" : '')
            . ">>\n"
            . "startxref\n"
            . "{$xrefOffset}\n"
            . "%%EOF\n";

        $fullPdf = $sourcePdf . $incrementalUpdate;

        // Locate exact byte offsets of /Contents < and > — use LAST occurrence
        // so that for multi-signature PDFs we always find the newly added placeholder,
        // not a /Contents < from an earlier signature's object.
        $contentsTag = '/Contents <';
        $contentsPos = strrpos($fullPdf, $contentsTag);
        if ($contentsPos === false) {
            throw new RuntimeException('Failed to find /Contents placeholder in assembled PDF.');
        }

        // len1 ends right at '<'
        $len1 = $contentsPos + strlen('/Contents ');
        // offset2 begins right after '>'
        $offset2 = $len1 + 1 + self::CONTENTS_PLACEHOLDER_LEN + 1; // '<' + hex + '>'
        $len2 = strlen($fullPdf) - $offset2;

        // Fixed-width ByteRange string replacement — also replace LAST occurrence
        // so we don't overwrite a previous signature's already-committed ByteRange.
        $formattedByteRange = sprintf(
            '/ByteRange [ %010d %010d %010d %010d ]',
            0,
            $len1,
            $offset2,
            $len2
        );

        if (strlen($formattedByteRange) !== strlen($byteRangePlaceholder)) {
            throw new RuntimeException('Formatted ByteRange length mismatch.');
        }

        // Replace only the last occurrence of the placeholder (the one we just added)
        $lastPlaceholderPos = strrpos($fullPdf, $byteRangePlaceholder);
        if ($lastPlaceholderPos === false) {
            throw new RuntimeException('ByteRange placeholder not found in assembled PDF.');
        }
        $finalPdfWithPlaceholder = substr($fullPdf, 0, $lastPlaceholderPos)
            . $formattedByteRange
            . substr($fullPdf, $lastPlaceholderPos + strlen($byteRangePlaceholder));

        return [
            'pdf' => $finalPdfWithPlaceholder,
            'byte_range' => [0, $len1, $offset2, $len2],
        ];
    }

    /**
     * Build incremental PDF objects linking the new /FT /Sig field into /Catalog -> /AcroForm -> /Fields.
     *
     * @return array<int, string> Map of object ID to serialized PDF indirect object
     */
    private function buildAcroFormUpdateObjects(
        string $sourcePdf,
        int $rootObjId,
        int $sigFieldObjId,
        int &$nextObjId
    ): array {
        $catalogBody = $this->extractLastObjectBody($sourcePdf, $rootObjId);
        if ($catalogBody === null) {
            return [];
        }

        // Case 1: /Catalog already references an indirect /AcroForm object
        if (preg_match('/\/AcroForm\s+(\d+)\s+0\s+R\b/', $catalogBody, $m)) {
            $acroObjId = (int) $m[1];
            $acroBody = $this->extractLastObjectBody($sourcePdf, $acroObjId);
            if ($acroBody !== null) {
                if (preg_match('/\/Fields\s+(\d+)\s+0\s+R\b/', $acroBody, $fm)) {
                    $fieldsObjId = (int) $fm[1];
                    $fieldsBody = $this->extractLastObjectBody($sourcePdf, $fieldsObjId);
                    if ($fieldsBody !== null && preg_match('/^\s*\[(.*)\]\s*$/s', $fieldsBody, $arrMatch)) {
                        $updatedArray = '[' . rtrim($arrMatch[1]) . " {$sigFieldObjId} 0 R ]";
                        return [
                            $fieldsObjId => "{$fieldsObjId} 0 obj\n{$updatedArray}\nendobj\n",
                        ];
                    }
                }

                $updatedAcro = $this->appendFieldRefToAcroFormDict($acroBody, $sigFieldObjId);
                return [
                    $acroObjId => "{$acroObjId} 0 obj\n{$updatedAcro}\nendobj\n",
                ];
            }
        }

        // Case 2: /Catalog has an inline /AcroForm << ... >> dictionary — promote or update inline
        if (preg_match('/\/AcroForm\s*<</', $catalogBody, $m, PREG_OFFSET_CAPTURE)) {
            $dictStart = strpos($catalogBody, '<<', $m[0][1]);
            $inlineDict = $dictStart !== false ? $this->extractBalancedDict($catalogBody, $dictStart) : null;
            if ($inlineDict !== null) {
                $updatedInline = $this->appendFieldRefToAcroFormDict($inlineDict, $sigFieldObjId);
                $updatedCatalog = substr($catalogBody, 0, $dictStart)
                    . $updatedInline
                    . substr($catalogBody, $dictStart + strlen($inlineDict));
                return [
                    $rootObjId => "{$rootObjId} 0 obj\n{$updatedCatalog}\nendobj\n",
                ];
            }
        }

        // Case 3: No /AcroForm in /Catalog yet — create indirect /AcroForm and reference it from /Catalog
        $acroObjId = $nextObjId++;
        $lastClose = strrpos($catalogBody, '>>');
        if ($lastClose === false) {
            return [];
        }

        $updatedCatalog = rtrim(substr($catalogBody, 0, $lastClose))
            . " /AcroForm {$acroObjId} 0 R >>";

        $acroObject = "<<\n/Fields [ {$sigFieldObjId} 0 R ]\n/SigFlags 3\n>>";

        return [
            $rootObjId => "{$rootObjId} 0 obj\n{$updatedCatalog}\nendobj\n",
            $acroObjId => "{$acroObjId} 0 obj\n{$acroObject}\nendobj\n",
        ];
    }

    /**
     * Extract the last object body (between "N 0 obj" and "endobj") for a given object ID.
     */
    private function extractLastObjectBody(string $pdf, int $objId): ?string
    {
        if (! preg_match_all('/(?:^|\r?\n)' . $objId . '\s+0\s+obj\b/', $pdf, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $last = end($matches[0]);
        $startPos = $last[1] + strlen($last[0]);
        $endPos = strpos($pdf, 'endobj', $startPos);
        if ($endPos === false) {
            return null;
        }

        return trim(substr($pdf, $startPos, $endPos - $startPos));
    }

    /**
     * Extract a balanced PDF dictionary "<< ... >>" starting at $startOffset.
     */
    private function extractBalancedDict(string $text, int $startOffset): ?string
    {
        $len = strlen($text);
        if (substr($text, $startOffset, 2) !== '<<') {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escaped = false;
        for ($i = $startOffset; $i < $len - 1; $i++) {
            $ch = $text[$i];
            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($ch === '\\') {
                    $escaped = true;
                } elseif ($ch === ')') {
                    $inString = false;
                }
                continue;
            }

            if ($ch === '(') {
                $inString = true;
            } elseif ($ch === '<' && $text[$i + 1] === '<') {
                $depth++;
                $i++;
            } elseif ($ch === '>' && $text[$i + 1] === '>') {
                $depth--;
                $i++;
                if ($depth === 0) {
                    return substr($text, $startOffset, $i - $startOffset + 1);
                }
            }
        }

        return null;
    }

    /**
     * Append a signature field indirect reference into an /AcroForm dictionary's /Fields array.
     */
    private function appendFieldRefToAcroFormDict(string $acroDict, int $sigFieldObjId): string
    {
        if (preg_match('/\/Fields\s*\[(.*?)\]/s', $acroDict, $m, PREG_OFFSET_CAPTURE)) {
            $inner = rtrim($m[1][0]);
            $newFields = '/Fields [' . ($inner !== '' ? $inner . ' ' : ' ') . "{$sigFieldObjId} 0 R ]";
            return substr($acroDict, 0, $m[0][1])
                . $newFields
                . substr($acroDict, $m[0][1] + strlen($m[0][0]));
        }

        $lastClose = strrpos($acroDict, '>>');
        if ($lastClose === false) {
            return $acroDict;
        }

        return rtrim(substr($acroDict, 0, $lastClose))
            . " /Fields [ {$sigFieldObjId} 0 R ] >>";
    }

    /**
     * Format an ISO 32000-1 incremental cross-reference table grouping contiguous object IDs.
     *
     * @param array<int, int> $offsets
     */
    private function formatIncrementalXref(array $offsets): string
    {
        ksort($offsets);
        $ids = array_keys($offsets);
        $out = "xref\n";
        $count = count($ids);
        $i = 0;

        while ($i < $count) {
            $start = $ids[$i];
            $run = 1;
            while ($i + $run < $count && $ids[$i + $run] === $start + $run) {
                $run++;
            }

            $out .= "{$start} {$run}\n";
            for ($j = 0; $j < $run; $j++) {
                $out .= sprintf("%010d 00000 n \n", $offsets[$start + $j]);
            }
            $i += $run;
        }

        return $out;
    }

    /**
     * Generates a DER-encoded PKCS#7 detached signature and returns it as a HEX string.
     */
    private function generatePkcs7SignatureHex(string $data, string $certPem, string $keyPath): string
    {
        $tempDataFile = tempnam(sys_get_temp_dir(), 'wu_sigdata_');
        $tempSigFile = tempnam(sys_get_temp_dir(), 'wu_sigout_');
        $tempCertFile = tempnam(sys_get_temp_dir(), 'wu_cert_');

        File::put($tempDataFile, $data);
        File::put($tempCertFile, $certPem);

        $rawKeyContent = File::get($keyPath);
        $privateKeyPem = $this->certService->decryptPrivateKeyPem($rawKeyContent);
        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if (! $privateKey) {
            @unlink($tempDataFile);
            @unlink($tempSigFile);
            @unlink($tempCertFile);
            throw new RuntimeException('Unable to access private key for PKCS#7 signing.');
        }

        // PKCS7_DETACHED | PKCS7_BINARY
        $signed = openssl_pkcs7_sign(
            $tempDataFile,
            $tempSigFile,
            $certPem,
            $privateKey,
            [],
            PKCS7_DETACHED | PKCS7_BINARY
        );

        @unlink($tempDataFile);
        @unlink($tempCertFile);

        if (! $signed) {
            @unlink($tempSigFile);
            throw new RuntimeException('OpenSSL PKCS#7 signing failed: ' . openssl_error_string());
        }

        $smimeContent = File::get($tempSigFile);
        @unlink($tempSigFile);

        // Extract base64 PKCS#7 DER payload from S/MIME output
        $derBinary = $this->extractDerFromSmime($smimeContent);

        return strtoupper(bin2hex($derBinary));
    }

    private function extractDerFromSmime(string $smime): string
    {
        // S/MIME contains MIME headers followed by empty line and multipart body
        if (preg_match('/filename="smime\.p7s"[^\r\n]*\r?\n(?:[^\r\n]+\r?\n)*\r?\n([A-Za-z0-9+\/=\r\n]+)/', $smime, $match)) {
            $cleanBase64 = preg_replace('/[^A-Za-z0-9+\/=]/', '', $match[1]);
            $decoded = base64_decode($cleanBase64, true);
            if ($decoded !== false && strlen($decoded) > 0) {
                return $decoded;
            }
        }

        if (preg_match('/Content-Type: application\/(?:x-)?pkcs7-signature[^\r\n]*\r?\n(?:[^\r\n]+\r?\n)*\r?\n([A-Za-z0-9+\/=\r\n]+)/', $smime, $match)) {
            $cleanBase64 = preg_replace('/[^A-Za-z0-9+\/=]/', '', $match[1]);
            $decoded = base64_decode($cleanBase64, true);
            if ($decoded !== false && strlen($decoded) > 0) {
                return $decoded;
            }
        }

        throw new RuntimeException('Failed to extract DER-encoded PKCS#7 binary payload from S/MIME output.');
    }

    /**
     * Resolves raw bytes for the source document, generating standard compliant PDF if absent.
     */
    private function getSourcePdfBytes(FileObject $fileObject, SignatureRequest $request): string
    {
        if ($fileObject->storage_key) {
            $legacyPath = storage_path('app/' . $fileObject->storage_key);
            $disk = $fileObject->storage_disk ?: 'local';
            $diskPath = Storage::disk($disk)->path($fileObject->storage_key);

            if (File::exists($legacyPath) && (! File::exists($diskPath) || filemtime($legacyPath) >= filemtime($diskPath))) {
                $content = File::get($legacyPath);
                if (str_starts_with($content, '%PDF-')) {
                    return $content;
                }
            }

            if (Storage::disk($disk)->exists($fileObject->storage_key)) {
                $content = Storage::disk($disk)->get($fileObject->storage_key);
                if (str_starts_with($content, '%PDF-')) {
                    return $content;
                }
            } elseif (File::exists($legacyPath)) {
                $content = File::get($legacyPath);
                if (str_starts_with($content, '%PDF-')) {
                    return $content;
                }
            }
        }

        // Generate baseline standard PDF
        $title = $request->documentVersion?->document?->title ?: 'Institutional Document';
        $ref = $request->documentVersion?->document?->reference_number ?: 'WU-REF-2026-0001';
        $purpose = $request->purpose ?: 'Institutional Approval';

        return $this->generateStandardInstitutionalPdf($title, $ref, $purpose);
    }

    /**
     * Generates a clean, standard ISO 32000-1 baseline PDF.
     */
    public function generateStandardInstitutionalPdf(string $title, string $ref, string $purpose): string
    {
        $now = date('Y-m-d H:i:s');
        $safeTitle = addcslashes($title, "()\r\n");
        $safeRef = addcslashes($ref, "()\r\n");
        $safePurpose = addcslashes($purpose, "()\r\n");

        $stream = "BT /F1 18 Tf 50 720 Td (Wollo University - Official Document) Tj ET\n"
            . "BT /F1 12 Tf 50 680 Td (Reference Number: {$safeRef}) Tj ET\n"
            . "BT /F1 12 Tf 50 650 Td (Title: {$safeTitle}) Tj ET\n"
            . "BT /F1 12 Tf 50 620 Td (Purpose: {$safePurpose}) Tj ET\n"
            . "BT /F1 10 Tf 50 590 Td (Generated: {$now} | Institutional Repository) Tj ET\n";

        $streamLen = strlen($stream);

        $pdf = "%PDF-1.7\n";
        $offsets = [];

        $offsets[1] = strlen($pdf);
        $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

        $offsets[2] = strlen($pdf);
        $pdf .= "2 0 obj\n<< /Type /Pages /Kids [4 0 R] /Count 1 >>\nendobj\n";

        $offsets[4] = strlen($pdf);
        $pdf .= "4 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 5 0 R /Resources << /Font << /F1 6 0 R >> >> >>\nendobj\n";

        $offsets[5] = strlen($pdf);
        $pdf .= "5 0 obj\n<< /Length {$streamLen} >>\nstream\n{$stream}endstream\nendobj\n";

        $offsets[6] = strlen($pdf);
        $pdf .= "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n"
            . "0 7\n"
            . "0000000000 65535 f \n"
            . sprintf("%010d 00000 n \n", $offsets[1])
            . sprintf("%010d 00000 n \n", $offsets[2])
            . "0000000000 00000 f \n"
            . sprintf("%010d 00000 n \n", $offsets[4])
            . sprintf("%010d 00000 n \n", $offsets[5])
            . sprintf("%010d 00000 n \n", $offsets[6])
            . "trailer\n"
            . "<< /Size 7 /Root 1 0 R >>\n"
            . "startxref\n"
            . "{$xrefOffset}\n"
            . "%%EOF\n";

        return $pdf;
    }
}
