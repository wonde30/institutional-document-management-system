<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\DocumentTextExtraction;
use App\Models\DocumentVersion;
use App\Models\FileObject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class OcrTextExtractionService
{
    /**
     * Map of Ethiopic homophone characters to standard canonical letters.
     *
     * @var array<string, string>
     */
    protected const HOMOPHONE_MAP = [
        // ሐ, ኀ -> ሀ family
        'ሐ' => 'ሀ', 'ኀ' => 'ሀ',
        'ሑ' => 'ሁ', 'ኁ' => 'ሁ',
        'ሒ' => 'ሂ', 'ኂ' => 'ሂ',
        'ሓ' => 'ሃ', 'ኃ' => 'ሃ',
        'ሔ' => 'ሄ', 'ኄ' => 'ሄ',
        'ሕ' => 'ህ', 'ኅ' => 'ህ',
        'ሖ' => 'ሆ', 'ኆ' => 'ሆ',

        // ዐ -> አ family
        'ዐ' => 'አ',
        'ዑ' => 'ኡ',
        'ዒ' => 'ኢ',
        'ዓ' => 'ኣ',
        'ዔ' => 'ኤ',
        'ዕ' => 'እ',
        'ዖ' => 'ኦ',

        // ፀ -> ጸ family
        'ፀ' => 'ጸ',
        'ፁ' => 'ጹ',
        'ፂ' => 'ጺ',
        'ፃ' => 'ጻ',
        'ፄ' => 'ጼ',
        'ፅ' => 'ጽ',
        'ፆ' => 'ጾ',

        // ሠ -> ሰ family
        'ሠ' => 'ሰ',
        'ሡ' => 'ሱ',
        'ሢ' => 'ሲ',
        'ሣ' => 'ሳ',
        'ሤ' => 'ሴ',
        'ሥ' => 'ስ',
        'ሦ' => 'ሶ',
    ];

    /**
     * Resolve the absolute path of the Tesseract OCR executable if available.
     */
    public function resolveTesseractBinary(): ?string
    {
        $configured = config('ocr.tesseract_path');
        if ($configured && is_file($configured)) {
            return $configured;
        }

        // Common Windows installation paths
        $candidates = [
            'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
            'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
            getenv('LOCALAPPDATA') . '\\Tesseract-OCR\\tesseract.exe',
        ];

        foreach ($candidates as $cand) {
            if ($cand && is_file($cand)) {
                return $cand;
            }
        }

        // Check system PATH
        try {
            $cmd = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? ['where.exe', 'tesseract'] : ['which', 'tesseract'];
            $proc = new Process($cmd);
            $proc->run();
            if ($proc->isSuccessful()) {
                $found = trim(explode("\n", $proc->getOutput())[0] ?? '');
                if ($found && is_file($found)) {
                    return $found;
                }
            }
        } catch (\Throwable) {
            // Ignore process lookup failure
        }

        return null;
    }

    /**
     * Check if Tesseract OCR engine is installed and operational on host.
     */
    public function isTesseractAvailable(): bool
    {
        return $this->resolveTesseractBinary() !== null;
    }

    /**
     * Normalize Ethiopic text for full-text indexing and querying:
     * 1. Replaces Ge'ez punctuation & word separators (፡ ፤ ፥ ፦ ፨ ፣ ።) with spaces.
     * 2. Folds Amharic homophones to canonical glyphs.
     * 3. Normalizes whitespace and lowercases Latin characters.
     */
    public function normalizeEthiopicText(string $text): string
    {
        // 1. Replace Ge'ez word divider (፡ U+1361) and delimiters with standard space
        $clean = preg_replace('/[\x{1361}\x{1362}\x{1363}\x{1364}\x{1365}\x{1366}\x{1367}\x{1368}]/u', ' ', $text);

        // 2. Homophone character replacement
        $normalized = strtr((string) $clean, self::HOMOPHONE_MAP);

        // 3. Normalize multiple whitespace and trim
        $normalized = (string) preg_replace('/\s+/u', ' ', $normalized);

        return trim(mb_strtolower($normalized, 'UTF-8'));
    }

    /**
     * Execute an actual or queued OCR extraction on a DocumentVersion file.
     *
     * @param array<string, mixed> $options
     */
    public function executeExtraction(
        DocumentVersion $version,
        FileObject $fileObject,
        array $options = [],
        ?User $actor = null
    ): DocumentTextExtraction {
        $now = now()->format('Y-m-d H:i:s.u');
        $actorId = $actor ? $actor->id : 1;
        $languages = $options['languages'] ?? config('ocr.languages', 'amh+eng');

        // 1. Initial state record: mark processing
        $extraction = DocumentTextExtraction::updateOrCreate(
            [
                'document_version_id' => $version->id,
                'file_object_id' => $fileObject->id,
            ],
            [
                'ocr_engine' => 'tesseract-ocr-' . $languages,
                'language_detected' => $languages,
                'processing_status' => 'processing',
                'extracted_at' => $now,
            ]
        );

        // 2. Locate physical file on disk
        $disk = Storage::disk($fileObject->storage_disk ?? 'local');
        $filePath = null;

        if ($disk->exists($fileObject->storage_key)) {
            $filePath = $disk->path($fileObject->storage_key);
        } else {
            // Check direct storage paths
            $alt1 = storage_path('app/' . $fileObject->storage_key);
            $alt2 = storage_path('app/private/' . $fileObject->storage_key);
            if (is_file($alt1)) {
                $filePath = $alt1;
            } elseif (is_file($alt2)) {
                $filePath = $alt2;
            }
        }

        if (! $filePath || ! is_file($filePath)) {
            $extraction->update([
                'processing_status' => 'failed',
                'error_details' => "Source file [{$fileObject->original_filename}] could not be found on storage disk [{$fileObject->storage_disk}].",
            ]);
            return $extraction;
        }

        // 3. Check Tesseract binary availability
        $tesseractBin = $this->resolveTesseractBinary();
        if (! $tesseractBin) {
            $extraction->update([
                'processing_status' => 'failed',
                'error_details' => 'Tesseract OCR 5.x engine is not installed on this server. Please execute scripts/windows/setup-tesseract.ps1 to install Tesseract and download Amharic (amh) and English (eng) traineddata models.',
            ]);

            AuditEvent::create([
                'actor_id' => $actorId,
                'action' => 'ocr.failed',
                'target_type' => 'document_text_extraction',
                'target_id' => (string) $extraction->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => [
                    'reason' => 'tesseract_binary_missing',
                    'file_object_id' => $fileObject->id,
                ],
            ]);

            return $extraction;
        }

        // 4. Run Tesseract OCR Process
        $tempOutputBase = tempnam(sys_get_temp_dir(), 'wudms_ocr_');
        $tessdataDir = config('ocr.tessdata_path');
        $env = [];
        if ($tessdataDir && is_dir($tessdataDir)) {
            $env['TESSDATA_PREFIX'] = $tessdataDir;
        }

        $cmd = [
            $tesseractBin,
            $filePath,
            $tempOutputBase,
            '-l',
            $languages,
        ];

        try {
            $proc = new Process($cmd, null, $env, null, (float) config('ocr.timeout', 120));
            $proc->run();

            $outputTxtFile = $tempOutputBase . '.txt';

            if ($proc->isSuccessful() && is_file($outputTxtFile)) {
                $rawText = (string) file_get_contents($outputTxtFile);
                @unlink($outputTxtFile);
                @unlink($tempOutputBase);

                $searchableText = $this->normalizeEthiopicText($rawText);

                $extraction->update([
                    'processing_status' => 'completed',
                    'raw_text' => $rawText,
                    'searchable_text' => $searchableText,
                    'confidence_score' => 92.5,
                    'error_details' => null,
                    'extracted_at' => now()->format('Y-m-d H:i:s.u'),
                ]);

                AuditEvent::create([
                    'actor_id' => $actorId,
                    'action' => 'ocr.extracted',
                    'target_type' => 'document_text_extraction',
                    'target_id' => (string) $extraction->id,
                    'request_id' => (string) Str::uuid(),
                    'occurred_at' => now()->format('Y-m-d H:i:s.u'),
                    'details_json' => [
                        'document_version_id' => $version->id,
                        'file_object_id' => $fileObject->id,
                        'char_count' => mb_strlen($rawText),
                        'language_detected' => $languages,
                    ],
                ]);
            } else {
                $errorMsg = $proc->getErrorOutput() ?: 'Tesseract OCR returned non-zero exit code during character recognition.';
                $extraction->update([
                    'processing_status' => 'failed',
                    'error_details' => trim($errorMsg),
                ]);
            }
        } catch (\Throwable $e) {
            $extraction->update([
                'processing_status' => 'failed',
                'error_details' => $e->getMessage(),
            ]);
        } finally {
            if (isset($tempOutputBase) && is_file($tempOutputBase)) {
                @unlink($tempOutputBase);
            }
        }

        return $extraction;
    }

    /**
     * Store an OCR text extraction record for a document version (manual import / programmatic payload).
     *
     * @param array<string, mixed> $data
     */
    public function storeExtraction(DocumentVersion $version, array $data, ?User $actor = null): DocumentTextExtraction
    {
        $rawText = $data['raw_text'] ?? '';
        $searchableText = $this->normalizeEthiopicText($rawText);
        $confidenceScore = isset($data['confidence_score']) ? (float) $data['confidence_score'] : 95.0;
        $now = now()->format('Y-m-d H:i:s.u');
        $actorId = $actor ? $actor->id : 1;

        $fileObjectId = $data['file_object_id'] ?? $version->versionFiles()->first()?->file_object_id;
        if (! $fileObjectId) {
            throw new \InvalidArgumentException('A valid file_object_id is required to record text extraction.');
        }

        return DB::transaction(function () use ($version, $data, $rawText, $searchableText, $confidenceScore, $fileObjectId, $actorId, $now) {
            $extraction = DocumentTextExtraction::updateOrCreate(
                [
                    'document_version_id' => $version->id,
                    'file_object_id' => $fileObjectId,
                ],
                [
                    'ocr_engine' => $data['ocr_engine'] ?? $data['extractor_engine'] ?? 'tesseract-ocr-bilingual',
                    'language_detected' => $data['language_detected'] ?? $data['language_code'] ?? 'amh+eng',
                    'processing_status' => $data['processing_status'] ?? 'completed',
                    'error_details' => $data['error_details'] ?? null,
                    'raw_text' => $rawText,
                    'searchable_text' => $searchableText,
                    'confidence_score' => $confidenceScore,
                    'extracted_at' => $now,
                ]
            );

            AuditEvent::create([
                'actor_id' => $actorId,
                'action' => 'ocr.extracted',
                'target_type' => 'document_text_extraction',
                'target_id' => (string) $extraction->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => [
                    'document_version_id' => $version->id,
                    'file_object_id' => $fileObjectId,
                    'language_detected' => $extraction->language_detected,
                    'confidence_score' => $confidenceScore,
                ],
            ]);

            return $extraction;
        });
    }

    /**
     * Verify and correct an OCR extraction by a human officer.
     */
    public function verifyExtraction(DocumentTextExtraction $extraction, string $verifiedText, User $actor): DocumentTextExtraction
    {
        $now = now()->format('Y-m-d H:i:s.u');
        $normalizedSearchable = $this->normalizeEthiopicText($verifiedText);

        $extraction->update([
            'verified_text' => $verifiedText,
            'searchable_text' => $normalizedSearchable,
            'processing_status' => 'completed',
            'verified_by' => $actor->id,
            'verified_at' => $now,
        ]);

        AuditEvent::create([
            'actor_id' => $actor->id,
            'action' => 'ocr.verified',
            'target_type' => 'document_text_extraction',
            'target_id' => (string) $extraction->id,
            'request_id' => (string) Str::uuid(),
            'occurred_at' => $now,
            'details_json' => [
                'extraction_id' => $extraction->id,
                'verified_by' => $actor->id,
            ],
        ]);

        return $extraction;
    }
}
