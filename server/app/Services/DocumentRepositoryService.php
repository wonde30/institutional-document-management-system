<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\DigitizationRecord;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentRelation;
use App\Models\DocumentRelationType;
use App\Models\DocumentVersion;
use App\Models\FileObject;
use App\Models\NumberingPolicy;
use App\Models\OrganizationUnit;
use App\Models\PhysicalCustodyEvent;
use App\Models\PhysicalOriginal;
use App\Models\User;
use App\Models\VersionFile;
use App\Services\EthiopianDateService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentRepositoryService
{
    /**
     * Allocate the next sequential reference number atomically via reference_sequences.
     */
    public function allocateAnnualReference(
        string $sequenceKey,
        string $prefix = 'WU',
        int $padLength = 4,
        ?int $baselineFloor = null
    ): string {
        $year = date('Y');
        $normalizedKey = strtoupper($sequenceKey);
        $normalizedPrefix = strtoupper($prefix);

        $allocator = function () use ($normalizedKey, $normalizedPrefix, $year, $padLength, $baselineFloor): string {
            $seq = DB::table('reference_sequences')
                ->where('sequence_key', $normalizedKey)
                ->where('period_key', $year)
                ->lockForUpdate()
                ->first();

            if (! $seq) {
                $refPrefix = "{$normalizedPrefix}/{$normalizedKey}/{$year}/";
                $existingRefs = DB::table('documents')
                    ->where('reference_number', 'like', $refPrefix.'%')
                    ->pluck('reference_number');

                $maxSuffix = 0;
                foreach ($existingRefs as $existingRef) {
                    $tail = substr((string) $existingRef, strlen($refPrefix));
                    if ($tail !== '' && ctype_digit($tail)) {
                        $maxSuffix = max($maxSuffix, (int) $tail);
                    }
                }

                $seedNext = max(1, max($maxSuffix, $baselineFloor ?? 0) + 1);

                DB::table('reference_sequences')->insertOrIgnore([
                    'sequence_key' => $normalizedKey,
                    'period_key' => $year,
                    'next_value' => $seedNext,
                ]);

                $seq = DB::table('reference_sequences')
                    ->where('sequence_key', $normalizedKey)
                    ->where('period_key', $year)
                    ->lockForUpdate()
                    ->first();
            }

            $seqVal = (int) $seq->next_value;
            $candidate = sprintf('%s/%s/%s/%0'.$padLength.'d', $normalizedPrefix, $normalizedKey, $year, $seqVal);

            while (DB::table('documents')->where('reference_number', $candidate)->exists()) {
                $seqVal++;
                $candidate = sprintf('%s/%s/%s/%0'.$padLength.'d', $normalizedPrefix, $normalizedKey, $year, $seqVal);
            }

            DB::table('reference_sequences')
                ->where('id', $seq->id)
                ->update(['next_value' => $seqVal + 1]);

            return $candidate;
        };

        return DB::transactionLevel() > 0
            ? $allocator()
            : DB::transaction($allocator, 5);
    }

    /**
     * Generate a provisional reference number for draft records without burning official sequence numbers.
     */
    public function generateDraftReferenceNumber(string $sequenceKey = 'DOC'): string
    {
        $currentYear = date('Y');
        $random = strtoupper(Str::random(8));

        return sprintf('DRAFT/%s/%s/%s', strtoupper($sequenceKey), $currentYear, $random);
    }

    /**
     * Generate an atomic reference number for a sequence key (e.g. DOC, CORR),
     * driven by configurable NumberingPolicy if configured.
     */
    public function generateReferenceNumber(string $sequenceKey = 'DOC', ?int $unitId = null, ?int $categoryId = null): string
    {
        return DB::transaction(function () use ($sequenceKey, $unitId, $categoryId) {
            $policy = NumberingPolicy::where('active', 1)
                ->where(function ($q) use ($sequenceKey) {
                    $q->where('code', strtolower($sequenceKey))
                      ->orWhere('code', strtoupper($sequenceKey));
                })
                ->first()
                ?? NumberingPolicy::where('active', 1)->first();

            $currentYear = date('Y');

            if (! $policy) {
                // Canonical baseline fallback
                $seq = DB::table('reference_sequences')
                    ->where('sequence_key', $sequenceKey)
                    ->where('period_key', $currentYear)
                    ->lockForUpdate()
                    ->first();

                if (! $seq) {
                    DB::table('reference_sequences')->insert([
                        'sequence_key' => $sequenceKey,
                        'period_key' => $currentYear,
                        'next_value' => 2,
                    ]);
                    $seqVal = 1;
                } else {
                    $seqVal = $seq->next_value;
                    DB::table('reference_sequences')
                        ->where('id', $seq->id)
                        ->update(['next_value' => $seqVal + 1]);
                }

                return sprintf('WU/%s/%s/%06d', $sequenceKey, $currentYear, $seqVal);
            }

            // Configurable Numbering Policy
            $prefix = ! empty($policy->prefix) ? $policy->prefix : 'WU';

            // Resolve year component
            $yearStr = null;
            if ($policy->year_format === 'ethiopian_year') {
                $eth = EthiopianDateService::fromGregorian((int) date('Y'), (int) date('n'), (int) date('j'));
                $yearStr = (string) $eth['year'];
            } elseif ($policy->year_format === 'calendar_year') {
                $yearStr = $currentYear;
            }

            // Resolve period key for sequence reset
            $periodKey = match ($policy->reset_policy) {
                'monthly' => date('Y-m'),
                'never' => 'all_time',
                default => $yearStr ?? $currentYear,
            };

            $seqKey = $policy->code ?: $sequenceKey;

            $seq = DB::table('reference_sequences')
                ->where('sequence_key', $seqKey)
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->first();

            if (! $seq) {
                DB::table('reference_sequences')->insert([
                    'sequence_key' => $seqKey,
                    'period_key' => $periodKey,
                    'next_value' => 2,
                ]);
                $seqVal = 1;
            } else {
                $seqVal = $seq->next_value;
                DB::table('reference_sequences')
                    ->where('id', $seq->id)
                    ->update(['next_value' => $seqVal + 1]);
            }

            $length = max(3, min(10, $policy->sequence_length ?: 6));
            $formattedSeq = sprintf("%0{$length}d", $seqVal);

            $parts = [$prefix];

            if ($policy->include_unit_code && $unitId) {
                $unitCode = OrganizationUnit::where('id', $unitId)->value('code');
                if ($unitCode) {
                    $parts[] = $unitCode;
                }
            }

            if ($policy->include_category_code && $categoryId) {
                $catCode = DocumentCategory::where('id', $categoryId)->value('code');
                if ($catCode) {
                    $parts[] = strtoupper($catCode);
                }
            } else {
                $parts[] = strtoupper($sequenceKey);
            }

            if ($yearStr !== null) {
                $parts[] = $yearStr;
            }

            $parts[] = $formattedSeq;

            return implode('/', $parts);
        });
    }

    /**
     * Register a new document with an optional initial file or deferred scanning state.
     *
     * @param array<string, mixed> $attributes
     */
    public function registerDocument(array $attributes, ?UploadedFile $file = null, ?User $actor = null): Document
    {
        $actorId = $actor ? $actor->id : (int) ($attributes['created_by'] ?? 1);
        $now = now()->format('Y-m-d H:i:s.u');
        $referenceNumber = $attributes['reference_number'] ?? $this->generateReferenceNumber(
            $attributes['sequence_key'] ?? 'DOC',
            isset($attributes['owner_unit_id']) ? (int) $attributes['owner_unit_id'] : null,
            isset($attributes['category_id']) ? (int) $attributes['category_id'] : null
        );

        return DB::transaction(function () use ($attributes, $file, $actorId, $now, $referenceNumber) {
            $document = Document::create([
                'reference_number' => $referenceNumber,
                'title' => $attributes['title'],
                'summary' => $attributes['summary'] ?? null,
                'category_id' => $attributes['category_id'],
                'confidentiality_id' => $attributes['confidentiality_id'],
                'owner_unit_id' => $attributes['owner_unit_id'],
                'custodian_id' => $attributes['custodian_id'] ?? null,
                'created_by' => $actorId,
                'document_date' => $attributes['document_date'] ?? now()->toDateString(),
                'registered_at' => $now,
                'lifecycle' => $attributes['lifecycle'] ?? 'active',
                'lock_version' => 0,
            ]);

            $ingestionMethod = $attributes['ingestion_method'] ?? ($file ? 'digital_upload' : 'manually_registered');

            // 1. Create Initial DocumentVersion (Version 1)
            $version = DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => 1,
                'ingestion_method' => $ingestionMethod,
                'template_version_id' => $attributes['template_version_id'] ?? null,
                'template_data' => isset($attributes['template_data']) ? json_decode(json_encode($attributes['template_data']), true) : null,
                'created_by' => $actorId,
                'change_note' => $attributes['change_note'] ?? 'Initial registered version',
                'created_at' => $now,
            ]);

            // 2. Attach file if provided
            if ($file) {
                $this->attachFileToVersion($version, $file, $actorId, 'primary', 1);
            }

            // 3. Register physical original if requested (e.g. for deferred scan or paper intake)
            if (! empty($attributes['has_physical_original']) || ! empty($attributes['physical_reference'])) {
                $this->registerPhysicalOriginal($document, [
                    'physical_reference' => $attributes['physical_reference'] ?? $referenceNumber,
                    'retention_requirement' => $attributes['retention_requirement'] ?? 'required',
                    'retention_policy_version_id' => $attributes['retention_policy_version_id'] ?? null,
                    'storage_location_id' => $attributes['storage_location_id'] ?? null,
                ], $actorId);
            }

            // 4. Audit trail
            AuditEvent::create([
                'actor_id' => $actorId,
                'action' => 'document.registered',
                'target_type' => 'document',
                'target_id' => (string) $document->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => [
                    'reference_number' => $referenceNumber,
                    'title' => $document->title,
                    'ingestion_method' => $ingestionMethod,
                ],
            ]);

            return $document;
        });
    }

    /**
     * Upload and bind a file to a document version.
     */
    public function attachFileToVersion(
        DocumentVersion $version,
        UploadedFile|string $file,
        int $actorId,
        string $purpose = 'primary',
        int $displayOrder = 1,
        ?string $originalFilename = null,
        ?string $mimeType = null
    ): VersionFile {
        $currentYear = date('Y');
        $fileSecurity = app(FileSecurityService::class);

        if ($file instanceof UploadedFile) {
            $fileObject = $fileSecurity->storeUploadedFile(
                $file,
                'documents/'.$currentYear,
                $actorId,
                $purpose
            );
        } else {
            // Raw binary content string: sanitize filename, inspect/store safely
            $now = now()->format('Y-m-d H:i:s.u');
            $disk = config('filesystems.default', 'local');
            $hash = hash('sha256', $file);
            $filename = $fileSecurity->sanitizeFilename($originalFilename ?? 'document_'.time().'.pdf');
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION)) ?: 'pdf';
            $detectedMime = $mimeType ?? 'application/pdf';
            $size = strlen($file);
            $storedPath = 'documents/'.$currentYear.'/'.Str::uuid().'.'.$extension;
            if (! Storage::disk($disk)->put($storedPath, $file)) {
                throw new \RuntimeException('Failed to write generated document content to storage.');
            }

            $fileObject = FileObject::create([
                'storage_disk' => $disk,
                'storage_key' => $storedPath,
                'original_filename' => $filename,
                'detected_mime' => $detectedMime,
                'byte_size' => $size,
                'sha256' => $hash,
                'uploaded_by' => $actorId,
                'uploaded_at' => $now,
                'scan_status' => 'clean',
                'scanned_at' => $now,
                'storage_status' => 'ready',
            ]);
        }

        return VersionFile::create([
            'document_version_id' => $version->id,
            'file_object_id' => $fileObject->id,
            'purpose' => $purpose,
            'display_order' => $displayOrder,
        ]);
    }

    /**
     * Create a new immutable version for an existing document.
     *
     * @param array<string, mixed> $attributes
     */
    public function createVersion(
        Document $document,
        array $attributes,
        UploadedFile|string|null $file = null,
        ?User $actor = null
    ): DocumentVersion {
        $actorId = $actor ? $actor->id : (int) ($attributes['created_by'] ?? 1);
        $now = now()->format('Y-m-d H:i:s.u');

        return DB::transaction(function () use ($document, $attributes, $file, $actorId, $now) {
            $latestVersion = DB::table('document_versions')
                ->where('document_id', $document->id)
                ->max('version_number') ?? 0;

            $nextVersionNumber = $latestVersion + 1;
            $ingestionMethod = $attributes['ingestion_method'] ?? ($file ? 'digital_upload' : 'manually_registered');

            $version = DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => $nextVersionNumber,
                'ingestion_method' => $ingestionMethod,
                'template_version_id' => $attributes['template_version_id'] ?? null,
                'template_data' => isset($attributes['template_data']) ? json_decode(json_encode($attributes['template_data']), true) : null,
                'created_by' => $actorId,
                'change_note' => $attributes['change_note'] ?? "Incremented to version {$nextVersionNumber}",
                'created_at' => $now,
            ]);

            if ($file) {
                $this->attachFileToVersion(
                    $version,
                    $file,
                    $actorId,
                    $attributes['purpose'] ?? 'primary',
                    1,
                    $attributes['original_filename'] ?? null,
                    $attributes['mime_type'] ?? null
                );
            }

            $document->increment('lock_version');

            AuditEvent::create([
                'actor_id' => $actorId,
                'action' => 'document.version_created',
                'target_type' => 'document_version',
                'target_id' => (string) $version->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => [
                    'document_id' => $document->id,
                    'version_number' => $nextVersionNumber,
                    'ingestion_method' => $ingestionMethod,
                ],
            ]);

            return $version;
        });
    }

    /**
     * Register a physical paper original for a document (Scenario A / B intake).
     *
     * @param array<string, mixed> $data
     */
    public function registerPhysicalOriginal(Document $document, array $data, int $actorId): PhysicalOriginal
    {
        $now = now()->format('Y-m-d H:i:s.u');

        return DB::transaction(function () use ($document, $data, $actorId, $now) {
            $original = PhysicalOriginal::create([
                'document_id' => $document->id,
                'physical_reference' => $data['physical_reference'] ?? $document->reference_number,
                'retention_requirement' => $data['retention_requirement'] ?? 'required',
                'retention_policy_version_id' => $data['retention_policy_version_id'] ?? null,
                'created_by' => $actorId,
                'created_at' => $now,
            ]);

            PhysicalCustodyEvent::create([
                'physical_original_id' => $original->id,
                'location_id' => $data['storage_location_id'] ?? null,
                'custodian_id' => $data['custodian_id'] ?? $actorId,
                'action' => 'register',
                'actor_id' => $actorId,
                'occurred_at' => $now,
                'reason' => $data['custody_reason'] ?? 'Initial physical intake and custody registration',
            ]);

            return $original;
        });
    }

    /**
     * Scenario B: Deferred Scanning Workflow.
     * Uploads the scanned file as Version 2 without modifying Version 1,
     * and links the physical original to the newly scanned version.
     */
    public function digitizePaperOriginal(
        Document $document,
        UploadedFile|string $file,
        int $actorId,
        ?int $physicalOriginalId = null,
        ?string $verificationNote = null
    ): DocumentVersion {
        $now = now()->format('Y-m-d H:i:s.u');

        return DB::transaction(function () use ($document, $file, $actorId, $physicalOriginalId, $verificationNote, $now) {
            // 1. Create Version 2 with ingestion_method = scanned_paper
            $newVersion = $this->createVersion($document, [
                'ingestion_method' => 'scanned_paper',
                'change_note' => 'Scanned physical original upload (Deferred Digitization)',
                'created_by' => $actorId,
            ], $file, User::find($actorId));

            // 2. Find or associate physical original
            $original = $physicalOriginalId
                ? PhysicalOriginal::where('document_id', $document->id)->findOrFail($physicalOriginalId)
                : PhysicalOriginal::where('document_id', $document->id)->latest('id')->first();

            if ($original) {
                DigitizationRecord::create([
                    'physical_original_id' => $original->id,
                    'document_version_id' => $newVersion->id,
                    'digitized_by' => $actorId,
                    'digitized_at' => $now,
                    'verification_status' => 'accepted',
                    'verified_by' => $actorId,
                    'verified_at' => $now,
                    'verification_note' => $verificationNote ?? 'Verified matching physical paper original',
                ]);

                // Also record custody event that scan occurred
                PhysicalCustodyEvent::create([
                    'physical_original_id' => $original->id,
                    'location_id' => null,
                    'custodian_id' => $actorId,
                    'action' => 'transfer',
                    'actor_id' => $actorId,
                    'occurred_at' => $now,
                    'reason' => 'Digitized and linked to document version '.$newVersion->version_number,
                ]);
            }

            return $newVersion;
        });
    }

    /**
     * Link two documents in 3NF with canonical relationship type (e.g. responds_to, references, supersedes).
     */
    public function linkRelation(Document $source, Document $target, string $relationTypeCode): DocumentRelation
    {
        $relType = DocumentRelationType::where('code', $relationTypeCode)->firstOrFail();

        return DocumentRelation::firstOrCreate([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'relation_type_id' => $relType->id,
        ]);
    }
}
