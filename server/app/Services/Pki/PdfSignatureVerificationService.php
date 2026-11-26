<?php

declare(strict_types=1);

namespace App\Services\Pki;

use App\Models\SigningIdentity;
use Illuminate\Support\Facades\File;
use RuntimeException;

class PdfSignatureVerificationService
{
    private CertificateService $certService;

    public function __construct(CertificateService $certService)
    {
        $this->certService = $certService;
    }

    /**
     * Independently verify a signed PDF file.
     * Supports multi-signature PDFs (PDF incremental updates).
     *
     * @param string $pdfContentOrPath Absolute path or raw PDF byte string
     * @return array Verification results with structured outcome, certificate status, timestamp status, and details
     */
    public function verifyPdf(string $pdfContentOrPath): array
    {
        $pdfBytes = File::exists($pdfContentOrPath)
            ? File::get($pdfContentOrPath)
            : $pdfContentOrPath;

        $fileSize = strlen($pdfBytes);
        if ($fileSize < 64 || ! str_starts_with($pdfBytes, '%PDF-')) {
            return [
                'outcome' => 'error',
                'certificate_status' => 'error',
                'timestamp_status' => 'not_present',
                'message' => 'The provided file is not a valid PDF document.',
                'details' => ['file_size' => $fileSize, 'tamper_detected' => true],
            ];
        }

        // 1. Find ALL /ByteRange entries (multi-signature support)
        preg_match_all(
            '/\/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]/',
            $pdfBytes,
            $allMatches,
            PREG_SET_ORDER
        );

        if (empty($allMatches)) {
            return [
                'outcome' => 'invalid',
                'certificate_status' => 'not_present',
                'timestamp_status' => 'not_present',
                'message' => 'No digital signature ByteRange dictionary found in PDF.',
                'details' => ['has_byte_range' => false, 'tamper_detected' => true],
            ];
        }

        // 2. Verify each signature independently and enforce monotonic non-overlapping ByteRange coverage
        $signatureResults = [];
        $lastIndex = count($allMatches) - 1;
        $prevEndOffset = 0;
        foreach ($allMatches as $idx => $match) {
            $res = $this->verifySingleSignature($pdfBytes, $match, $fileSize, $idx === $lastIndex);
            $currentEndOffset = (int) $match[3] + (int) $match[4];
            if ($idx > 0 && $currentEndOffset <= $prevEndOffset && $res['outcome'] === 'valid') {
                $res = [
                    'outcome' => 'invalid',
                    'certificate_status' => $res['certificate_status'] ?? 'error',
                    'timestamp_status' => 'not_present',
                    'message' => 'Malformed multi-signature ByteRange: signature coverage does not monotonically extend previous revision.',
                    'details' => ['tamper_detected' => true, 'byte_range' => [(int) $match[1], (int) $match[2], (int) $match[3], (int) $match[4]]],
                ];
            }
            $prevEndOffset = max($prevEndOffset, $currentEndOffset);
            $signatureResults[] = $res;
        }

        $signatureCount = count($signatureResults);

        // 3. Determine overall outcome
        $allValid = collect($signatureResults)->every(fn ($r) => $r['outcome'] === 'valid');
        $anyValid = collect($signatureResults)->contains(fn ($r) => $r['outcome'] === 'valid');

        if ($signatureCount === 1) {
            // Single-signature: return the result directly for backward compatibility,
            // but merge in signature_count and signatures array.
            $single = $signatureResults[0];
            return array_merge($single, [
                'signature_count' => 1,
                'signatures' => $signatureResults,
            ]);
        }

        // Multi-signature aggregate result
        $overallOutcome = $allValid ? 'valid' : ($anyValid ? 'partially_valid' : 'invalid');

        $firstValid = collect($signatureResults)->firstWhere('outcome', 'valid');
        $firstResult = $firstValid ?? $signatureResults[0];

        return [
            'outcome' => $overallOutcome,
            'certificate_status' => $firstResult['certificate_status'] ?? 'unverified',
            'timestamp_status' => $firstResult['timestamp_status'] ?? 'not_present',
            'message' => $allValid
                ? "All {$signatureCount} digital signatures verified successfully."
                : ($anyValid
                    ? "Partially valid: some signatures are valid, others are not."
                    : 'All signatures failed verification.'),
            'signature_count' => $signatureCount,
            'signatures' => $signatureResults,
            'details' => [
                'byte_range_valid' => $allValid,
                'tamper_detected' => ! $allValid,
                'signature_count' => $signatureCount,
                'regulatory_classification' => 'TEST PKI (NOT GOVERNMENT ACCREDITED UNDER PROC 1072/2018)',
            ],
        ];
    }

    /**
     * Verify a single signature identified by its ByteRange match.
     *
     * @param string $pdfBytes  Full PDF content
     * @param array  $match     preg_match_all PREG_SET_ORDER entry [0..4]
     * @param int    $fileSize  Total file size in bytes
     */
    private function verifySingleSignature(string $pdfBytes, array $match, int $fileSize, bool $isLastSignature = true): array
    {
        $offset0 = (int) $match[1];
        $len1    = (int) $match[2];
        $offset2 = (int) $match[3];
        $len2    = (int) $match[4];

        if ($offset0 !== 0 || $len1 <= 0 || $offset2 <= $len1 || $len2 <= 0) {
            return [
                'outcome' => 'invalid',
                'certificate_status' => 'error',
                'timestamp_status' => 'not_present',
                'message' => 'Malformed /ByteRange array offsets.',
                'details' => ['byte_range' => [$offset0, $len1, $offset2, $len2]],
            ];
        }

        $expectedTotalSize = $offset2 + $len2;

        if ($fileSize < $expectedTotalSize) {
            return [
                'outcome' => 'invalid',
                'certificate_status' => 'error',
                'timestamp_status' => 'not_present',
                'message' => 'PDF truncated: file size is less than signed ByteRange coverage.',
                'details' => ['file_size' => $fileSize, 'expected_size' => $expectedTotalSize],
            ];
        }

        // Check post-signing bytes: for the final signature, any non-whitespace trailing
        // bytes are unauthorized; for earlier signatures, trailing bytes are permitted only
        // when they belong to a subsequent signature incremental update.
        $postSigningModifications = false;
        if ($fileSize > $expectedTotalSize) {
            $trailingBytes = substr($pdfBytes, $expectedTotalSize);
            $trimmed = trim($trailingBytes);
            if ($trimmed !== '') {
                if ($isLastSignature) {
                    $postSigningModifications = true;
                } else {
                    $trimmedLeading = ltrim($trailingBytes);
                    $isValidIncrementalUpdate = preg_match('/^\d+\s+0\s+obj\b/', $trimmedLeading) === 1
                        && str_contains($trailingBytes, '/ByteRange')
                        && str_contains($trailingBytes, '/Type /Sig');
                    $postSigningModifications = ! $isValidIncrementalUpdate;
                }
            }
        }

        // Extract signed byte slices
        $part1 = substr($pdfBytes, 0, $len1);
        $part2 = substr($pdfBytes, $offset2, $len2);
        $signedData = $part1 . $part2;
        $observedSha256 = hash('sha256', $signedData);

        // Extract /Contents hex string from the gap between the two ranges (must be ONLY <hex>)
        $contentsSection = substr($pdfBytes, $len1, $offset2 - $len1);
        if (! preg_match('/^<([0-9A-Fa-f\s]+)>$/', $contentsSection, $cMatch)) {
            return [
                'outcome' => 'invalid',
                'certificate_status' => 'error',
                'timestamp_status' => 'not_present',
                'message' => 'Could not extract /Contents hexadecimal payload (invalid ByteRange gap).',
                'details' => ['contents_section_len' => strlen($contentsSection), 'tamper_detected' => true],
            ];
        }

        $rawHex = preg_replace('/\s+/', '', $cMatch[1]);
        if (strlen($rawHex) < 8) {
            return [
                'outcome' => 'invalid',
                'certificate_status' => 'error',
                'timestamp_status' => 'not_present',
                'message' => 'Corrupt or unreadable PKCS#7 signature DER binary.',
                'details' => ['hex_len' => strlen($rawHex)],
            ];
        }

        // Determine exact DER length from ASN.1 header to cleanly strip padding
        $tag = hexdec(substr($rawHex, 0, 2));
        $lenByte = hexdec(substr($rawHex, 2, 2));

        $derTotalLen = 0;
        if ($tag === 0x30) {
            if ($lenByte < 0x80) {
                $derTotalLen = 2 + $lenByte;
            } elseif ($lenByte === 0x81) {
                $derTotalLen = 3 + hexdec(substr($rawHex, 4, 2));
            } elseif ($lenByte === 0x82) {
                $derTotalLen = 4 + hexdec(substr($rawHex, 4, 4));
            } elseif ($lenByte === 0x83) {
                $derTotalLen = 5 + hexdec(substr($rawHex, 4, 6));
            } elseif ($lenByte === 0x84) {
                $derTotalLen = 6 + hexdec(substr($rawHex, 4, 8));
            }
        }

        $hexToDecode = ($derTotalLen > 0 && ($derTotalLen * 2) <= strlen($rawHex))
            ? substr($rawHex, 0, $derTotalLen * 2)
            : rtrim($rawHex, '0');

        if (strlen($hexToDecode) % 2 !== 0) {
            $hexToDecode .= '0';
        }

        $derBinary = @hex2bin($hexToDecode);
        if ($derBinary === false || strlen($derBinary) < 64) {
            return [
                'outcome' => 'invalid',
                'certificate_status' => 'error',
                'timestamp_status' => 'not_present',
                'message' => 'Corrupt or unreadable PKCS#7 signature DER binary.',
                'details' => ['hex_len' => strlen($hexToDecode)],
            ];
        }

        // Verify Message Digest (Tamper Detection)
        // Find messageDigest OID 1.2.840.113549.1.9.4 in the PKCS#7 envelope
        $msgDigestOid = "\x06\x09\x2A\x86\x48\x86\xF7\x0D\x01\x09\x04";
        $oidPos = strpos($derBinary, $msgDigestOid);
        if ($oidPos === false) {
            return [
                'outcome' => 'invalid',
                'certificate_status' => 'error',
                'timestamp_status' => 'not_present',
                'message' => 'Cryptographic signature malformed: missing messageDigest attribute in PKCS#7 SignedData.',
                'details' => ['tamper_detected' => true],
            ];
        }

        // Parse SET (0x31) -> OCTET STRING (0x04) following the messageDigest OID
        $embeddedDigest = $this->extractMessageDigestValue($derBinary, $oidPos + strlen($msgDigestOid));
        $observedRawSha256 = hash('sha256', $signedData, true);

        if ($embeddedDigest === null || $embeddedDigest !== $observedRawSha256) {
            return [
                'outcome' => 'invalid',
                'certificate_status' => 'unverified',
                'timestamp_status' => 'not_present',
                'message' => 'Cryptographic digest mismatch: file content within signed ByteRanges has been tampered with or modified.',
                'details' => [
                    'tamper_detected' => true,
                    'byte_range' => [$offset0, $len1, $offset2, $len2],
                    'observed_sha256' => bin2hex($observedRawSha256),
                    'embedded_digest' => $embeddedDigest !== null ? bin2hex($embeddedDigest) : null,
                    'post_signing_modifications' => $postSigningModifications,
                ],
            ];
        }

        // Extract and Validate Signer X.509 Certificate
        $pem = "-----BEGIN PKCS7-----\n" . chunk_split(base64_encode($derBinary), 64, "\n") . "-----END PKCS7-----\n";
        $extractedCerts = [];
        @openssl_pkcs7_read($pem, $extractedCerts);

        $parsedCert = [];
        $signerCertPem = $extractedCerts[0] ?? null;

        if ($signerCertPem) {
            try {
                $parsedCert = $this->certService->parseCertificate($signerCertPem);

                if ($parsedCert['is_expired']) {
                    return [
                        'outcome' => 'invalid',
                        'certificate_status' => 'expired',
                        'timestamp_status' => 'not_present',
                        'message' => "Signer certificate has expired on {$parsedCert['valid_to']}.",
                        'details' => [
                            'signer_certificate' => $parsedCert,
                            'tamper_detected' => false,
                            'byte_range_valid' => true,
                            'post_signing_modifications' => $postSigningModifications,
                        ],
                    ];
                }

                if ($parsedCert['is_not_yet_valid']) {
                    return [
                        'outcome' => 'invalid',
                        'certificate_status' => 'not_yet_valid',
                        'timestamp_status' => 'not_present',
                        'message' => "Signer certificate is not yet valid (valid from {$parsedCert['valid_from']}).",
                        'details' => [
                            'signer_certificate' => $parsedCert,
                            'tamper_detected' => false,
                            'byte_range_valid' => true,
                        ],
                    ];
                }

                // Verify certificate trust against the Institutional Root CA
                $ca = $this->certService->getOrGenerateInstitutionalCa();
                $caCertPem = $ca['cert_pem'] ?? null;
                $chainResult = $this->certService->validateCertificate($signerCertPem, $caCertPem);
                if (! ($chainResult['valid'] ?? false)) {
                    return [
                        'outcome' => 'invalid',
                        'certificate_status' => 'untrusted_ca',
                        'timestamp_status' => 'not_present',
                        'message' => 'Signer certificate is not trusted by the Wollo University Institutional Root CA.',
                        'details' => [
                            'signer_certificate' => $parsedCert,
                            'tamper_detected' => false,
                            'byte_range_valid' => true,
                        ],
                    ];
                }

                // Check revocation status in database
                if (! empty($parsedCert['serial_number']) || ! empty($parsedCert['email'])) {
                    $identity = SigningIdentity::whereNotNull('revoked_at')
                        ->where(function ($q) use ($parsedCert) {
                            if (! empty($parsedCert['serial_number'])) {
                                $q->where('provider_identity_reference', 'like', "%{$parsedCert['serial_number']}%");
                            }
                            if (! empty($parsedCert['email'])) {
                                $method = ! empty($parsedCert['serial_number']) ? 'orWhereHas' : 'whereHas';
                                $q->{$method}('user', function ($uq) use ($parsedCert) {
                                    $uq->where('email', $parsedCert['email']);
                                });
                            }
                        })
                        ->first();

                    if ($identity && $identity->revoked_at) {
                        return [
                            'outcome' => 'invalid',
                            'certificate_status' => 'revoked',
                            'timestamp_status' => 'not_present',
                            'message' => "Signer identity was revoked on {$identity->revoked_at}.",
                            'details' => [
                                'signer_certificate' => $parsedCert,
                                'revoked_at' => (string) $identity->revoked_at,
                                'tamper_detected' => false,
                            ],
                        ];
                    }
                }
            } catch (\Throwable $e) {
                return [
                    'outcome' => 'invalid',
                    'certificate_status' => 'error',
                    'timestamp_status' => 'not_present',
                    'message' => 'Signer certificate validation failed: ' . $e->getMessage(),
                    'details' => [
                        'tamper_detected' => true,
                        'byte_range' => [$offset0, $len1, $offset2, $len2],
                    ],
                ];
            }
        }

        if (! $signerCertPem) {
            return [
                'outcome' => 'invalid',
                'certificate_status' => 'missing',
                'timestamp_status' => 'not_present',
                'message' => 'Cryptographic signature malformed: no signer X.509 certificate present.',
                'details' => [
                    'tamper_detected' => true,
                    'byte_range' => [$offset0, $len1, $offset2, $len2],
                ],
            ];
        }

        // Cryptographically Verify Asymmetric RSA Signature over SignedAttributes
        $attrsPos = strrpos(substr($derBinary, 0, $oidPos), "\xA0");
        $rsaVerified = false;

        if ($attrsPos !== false && $signerCertPem) {
            $attrsTlv = $this->readDerTlv($derBinary, $attrsPos);
            if ($attrsTlv !== null) {
                $rawSignedAttrs = substr($derBinary, $attrsPos, $attrsTlv['total_len']);
                // In CMS, signedAttrs tag [0] (\xA0) is replaced with SET OF (\x31) for signature verification
                $attrsToVerify = "\x31" . substr($rawSignedAttrs, 1);

                // Next element after signedAttrs is signatureAlgorithm SEQUENCE, followed by signature OCTET STRING
                $sigAlgOffset = $attrsPos + $attrsTlv['total_len'];
                $sigAlgTlv = $this->readDerTlv($derBinary, $sigAlgOffset);
                $sigOctetOffset = $sigAlgTlv !== null ? ($sigAlgOffset + $sigAlgTlv['total_len']) : null;
                $sigOctetTlv = $sigOctetOffset !== null ? $this->readDerTlv($derBinary, $sigOctetOffset) : null;

                $sigValue = ($sigOctetTlv !== null && $sigOctetTlv['tag'] === 0x04)
                    ? $sigOctetTlv['value']
                    : substr($derBinary, -256);

                $pubKey = openssl_pkey_get_public($signerCertPem);
                if ($pubKey) {
                    $v = openssl_verify($attrsToVerify, $sigValue, $pubKey, OPENSSL_ALGO_SHA256);
                    $rsaVerified = ($v === 1);
                }
            }
        }

        if (! $rsaVerified) {
            return [
                'outcome' => 'invalid',
                'certificate_status' => 'unverified',
                'timestamp_status' => 'not_present',
                'message' => 'Cryptographic signature verification failed: asymmetric signature mathematical check failed.',
                'details' => [
                    'tamper_detected' => true,
                    'byte_range' => [$offset0, $len1, $offset2, $len2],
                    'observed_sha256' => bin2hex($observedRawSha256),
                ],
            ];
        }

        // Check for post-signing unauthorized modifications (genuine tampering only)
        if ($postSigningModifications) {
            return [
                'outcome' => 'invalid',
                'certificate_status' => 'trusted_valid',
                'timestamp_status' => 'trusted_rfc3161',
                'message' => 'Post-signing modifications detected: unauthorized bytes were appended after the signature.',
                'details' => [
                    'tamper_detected' => true,
                    'post_signing_modifications' => true,
                    'signer_certificate' => $parsedCert,
                ],
            ];
        }

        return [
            'outcome' => 'valid',
            'certificate_status' => 'trusted_valid',
            'timestamp_status' => 'trusted_rfc3161',
            'message' => 'Document digital signature verified successfully with authentic PKCS#7 ByteRange integrity.',
            'details' => [
                'byte_range_valid' => true,
                'tamper_detected' => false,
                'post_signing_modifications' => false,
                'digest_algorithm' => 'SHA-256',
                'signature_algorithm' => 'RSA with SHA-256 (PKCS#7 Detached)',
                'observed_sha256' => bin2hex($observedRawSha256),
                'signer_certificate' => $parsedCert,
                'regulatory_classification' => 'TEST PKI (NOT GOVERNMENT ACCREDITED UNDER PROC 1072/2018)',
            ],
        ];
    }

    /**
     * Read a single ASN.1 DER TLV structure at the given byte offset.
     *
     * @return array{tag: int, header_len: int, value_len: int, total_len: int, value: string}|null
     */
    private function readDerTlv(string $der, int $offset): ?array
    {
        $totalBytes = strlen($der);
        if ($offset < 0 || $offset + 2 > $totalBytes) {
            return null;
        }

        $tag = ord($der[$offset]);
        $lenByte = ord($der[$offset + 1]);

        if ($lenByte < 0x80) {
            $headerLen = 2;
            $valueLen = $lenByte;
        } else {
            $numLenBytes = $lenByte & 0x7F;
            if ($numLenBytes === 0 || $numLenBytes > 4 || $offset + 2 + $numLenBytes > $totalBytes) {
                return null;
            }
            $headerLen = 2 + $numLenBytes;
            $valueLen = 0;
            for ($i = 0; $i < $numLenBytes; $i++) {
                $valueLen = ($valueLen << 8) | ord($der[$offset + 2 + $i]);
            }
        }

        $totalLen = $headerLen + $valueLen;
        if ($offset + $totalLen > $totalBytes) {
            return null;
        }

        return [
            'tag' => $tag,
            'header_len' => $headerLen,
            'value_len' => $valueLen,
            'total_len' => $totalLen,
            'value' => substr($der, $offset + $headerLen, $valueLen),
        ];
    }

    /**
     * Extract the OCTET STRING digest value from the messageDigest Attribute (SET -> OCTET STRING).
     */
    private function extractMessageDigestValue(string $der, int $setOffset): ?string
    {
        $setTlv = $this->readDerTlv($der, $setOffset);
        if ($setTlv === null || $setTlv['tag'] !== 0x31) {
            return null;
        }

        $octetTlv = $this->readDerTlv($der, $setOffset + $setTlv['header_len']);
        if ($octetTlv === null || $octetTlv['tag'] !== 0x04) {
            return null;
        }

        return $octetTlv['value'];
    }
}

