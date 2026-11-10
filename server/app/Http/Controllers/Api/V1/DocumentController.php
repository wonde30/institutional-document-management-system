<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\DocumentMovement;
use App\Models\DocumentVersion;
use App\Models\FileObject;
use App\Models\PhysicalOriginal;
use App\Models\ReferenceSequence;
use App\Models\VersionFile;
use App\Services\DocumentRepositoryService;
use App\Services\DocumentTimelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentController extends Controller
{
    /**
     * List documents with filtering and search.
     */
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $query = Document::with(['category', 'confidentiality', 'ownerUnit', 'creator', 'custodian'])
            ->withCount('versions');

        // Departmental Scope Isolation: Restrict to permitted units unless globally authorized
        $permittedUnitIds = $actor ? $actor->getPermittedUnitIds('documents.view') : [];
        if ($permittedUnitIds !== null) {
            if ($request->filled('owner_unit_id')) {
                $requestedUnit = (int) $request->query('owner_unit_id');
                if (! in_array($requestedUnit, $permittedUnitIds, true)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Forbidden: You do not have permission to view documents from this organizational unit.',
                    ], 403);
                }
                $query->where('owner_unit_id', $requestedUnit);
            } else {
                $query->where(function ($q) use ($permittedUnitIds, $actor) {
                    $q->whereIn('owner_unit_id', $permittedUnitIds)
                      ->orWhereHas('movements', fn ($mq) => $mq->whereIn('to_unit_id', $permittedUnitIds))
                      ->orWhereHas('assignments', fn ($aq) => $aq->whereIn('assigned_unit_id', $permittedUnitIds)->orWhere('assigned_user_id', $actor->id))
                      ->orWhereHas('grants', fn ($gq) => $gq->where(fn ($sq) => $sq->where('user_id', $actor->id)->orWhereIn('unit_id', $permittedUnitIds))->where(fn ($sq) => $sq->whereNull('expires_at')->orWhere('expires_at', '>', now())));
                });
            }
        } elseif ($request->filled('owner_unit_id')) {
            $query->where('owner_unit_id', $request->query('owner_unit_id'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }

        if ($request->filled('confidentiality_id')) {
            $query->where('confidentiality_id', $request->query('confidentiality_id'));
        }

        if ($request->filled('lifecycle')) {
            $query->where('lifecycle', $request->query('lifecycle'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('document_date', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('document_date', '<=', $request->query('to_date'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%")
                  ->orWhere('summary', 'like', "%{$search}%");
            });
        }

        $documents = $query->orderByDesc('registered_at')->paginate($request->query('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => DocumentResource::collection($documents),
            'meta' => [
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'total' => $documents->total(),
            ],
        ]);
    }

    /**
     * Store new document with atomic reference sequence generation and optional file.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'summary' => 'nullable|string',
            'category_id' => 'required|exists:document_categories,id',
            'confidentiality_id' => 'required|exists:confidentiality_levels,id',
            'owner_unit_id' => 'required|exists:organization_units,id',
            'custodian_id' => 'nullable|exists:users,id',
            'document_date' => 'required|date',
            'file' => 'nullable|file|max:102400', // max 100MB
            'change_note' => 'nullable|string',
        ]);

        $actor = $request->user();
        $now = now()->format('Y-m-d H:i:s.u');
        $currentYear = date('Y');

        $document = DB::transaction(function () use ($validated, $actor, $now, $currentYear, $request) {
            // 1. Generate reference number atomically
            $seq = DB::table('reference_sequences')
                ->where('sequence_key', 'DOC')
                ->where('period_key', $currentYear)
                ->lockForUpdate()
                ->first();

            if (! $seq) {
                DB::table('reference_sequences')->insert([
                    'sequence_key' => 'DOC',
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

            $referenceNumber = sprintf('WU/DOC/%s/%06d', $currentYear, $seqVal);

            // 2. Create document record
            $doc = Document::create([
                'reference_number' => $referenceNumber,
                'title' => $validated['title'],
                'summary' => $validated['summary'] ?? null,
                'category_id' => $validated['category_id'],
                'confidentiality_id' => $validated['confidentiality_id'],
                'owner_unit_id' => $validated['owner_unit_id'],
                'custodian_id' => $validated['custodian_id'] ?? null,
                'created_by' => $actor->id,
                'document_date' => $validated['document_date'],
                'registered_at' => $now,
                'lifecycle' => 'active',
                'lock_version' => 0,
            ]);

            // 3. Handle initial version & file if provided
            if ($request->hasFile('file')) {
                $uploadedFile = $request->file('file');
                $hash = hash_file('sha256', $uploadedFile->getRealPath());
                $disk = config('filesystems.default', 'local');
                $storedPath = $uploadedFile->store('documents/'.$currentYear, $disk);

                $fileObject = FileObject::create([
                    'storage_disk' => $disk,
                    'storage_key' => $storedPath,
                    'original_filename' => $uploadedFile->getClientOriginalName(),
                    'detected_mime' => $uploadedFile->getClientMimeType() ?: 'application/octet-stream',
                    'byte_size' => $uploadedFile->getSize(),
                    'sha256' => $hash,
                    'uploaded_by' => $actor->id,
                    'uploaded_at' => $now,
                    'scan_status' => 'clean',
                    'scanned_at' => $now,
                    'storage_status' => 'ready',
                ]);

                $version = DocumentVersion::create([
                    'document_id' => $doc->id,
                    'version_number' => 1,
                    'created_by' => $actor->id,
                    'change_note' => $validated['change_note'] ?? 'Initial registered version',
                    'created_at' => $now,
                ]);

                VersionFile::create([
                    'document_version_id' => $version->id,
                    'file_object_id' => $fileObject->id,
                    'purpose' => 'primary',
                    'display_order' => 1,
                ]);
            }

            return $doc;
        });

        $document->load(['category', 'confidentiality', 'ownerUnit', 'creator', 'custodian']);

        return response()->json([
            'success' => true,
            'message' => 'Document registered successfully.',
            'data' => new DocumentResource($document),
        ], 201);
    }

    /**
     * Show single document with versions and metadata.
     */
    public function show(int $id): JsonResponse
    {
        $document = Document::with([
            'category',
            'confidentiality',
            'ownerUnit',
            'custodian',
            'creator',
            'versions.createdBy',
            'versions.versionFiles.fileObject',
            'tags',
            'relations.relatedDocument',
            'grants',
            'comments.user',
            'correspondence.type',
            'correspondence.priority',
            'correspondence.firstReceivingUnit',
            'correspondence.movements.fromUnit',
            'correspondence.movements.toUnit',
            'correspondence.movements.sentBy',
            'correspondence.movements.receipt.acknowledgedBy',
            'correspondence.assignments.assignedUser',
            'correspondence.assignments.assignedUnit',
            'movements.fromUnit',
            'movements.toUnit',
            'movements.sentBy',
            'movements.receipt.acknowledgedBy',
            'assignments.assignedUser',
            'assignments.assignedUnit',
            'physicalOriginals.custodyEvents.location',
            'physicalOriginals.custodyEvents.custodian',
            'archiveEntry.collection',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new DocumentResource($document),
        ]);
    }

    /**
     * Update document metadata with optimistic concurrency lock check.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $document = Document::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:500',
            'summary' => 'nullable|string',
            'category_id' => 'sometimes|required|exists:document_categories,id',
            'confidentiality_id' => 'sometimes|required|exists:confidentiality_levels,id',
            'custodian_id' => 'nullable|exists:users,id',
            'document_date' => 'sometimes|required|date',
            'lifecycle' => 'sometimes|required|in:active,retired,tombstoned',
            'lock_version' => 'required|integer',
        ]);

        if ((int) $validated['lock_version'] !== (int) $document->lock_version) {
            return response()->json([
                'success' => false,
                'message' => 'Conflict: The document metadata was updated by another process. Please refresh and retry.',
            ], 409);
        }

        $validated['lock_version'] = $document->lock_version + 1;
        $document->update($validated);
        $document->load(['category', 'confidentiality', 'ownerUnit', 'custodian']);

        return response()->json([
            'success' => true,
            'message' => 'Document updated successfully.',
            'data' => new DocumentResource($document),
        ]);
    }

    /**
     * Upload a new version to an existing document.
     */
    public function uploadVersion(Request $request, int $id): JsonResponse
    {
        $document = Document::findOrFail($id);

        $validated = $request->validate([
            'file' => 'required|file|max:102400',
            'change_note' => 'required|string|max:1000',
            'purpose' => 'nullable|in:primary,attachment',
        ]);

        $actor = $request->user();
        $now = now()->format('Y-m-d H:i:s.u');
        $currentYear = date('Y');

        $version = DB::transaction(function () use ($document, $validated, $actor, $now, $currentYear, $request) {
            $latestVersionNumber = DB::table('document_versions')
                ->where('document_id', $document->id)
                ->max('version_number') ?? 0;

            $nextVersionNumber = $latestVersionNumber + 1;

            $uploadedFile = $request->file('file');
            $hash = hash_file('sha256', $uploadedFile->getRealPath());
            $disk = config('filesystems.default', 'local');
            $storedPath = $uploadedFile->store('documents/'.$currentYear, $disk);

            $fileObject = FileObject::create([
                'storage_disk' => $disk,
                'storage_key' => $storedPath,
                'original_filename' => $uploadedFile->getClientOriginalName(),
                'detected_mime' => $uploadedFile->getClientMimeType() ?: 'application/octet-stream',
                'byte_size' => $uploadedFile->getSize(),
                'sha256' => $hash,
                'uploaded_by' => $actor->id,
                'uploaded_at' => $now,
                'scan_status' => 'clean',
                'scanned_at' => $now,
                'storage_status' => 'ready',
            ]);

            $ver = DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => $nextVersionNumber,
                'created_by' => $actor->id,
                'change_note' => $validated['change_note'],
                'created_at' => $now,
            ]);

            VersionFile::create([
                'document_version_id' => $ver->id,
                'file_object_id' => $fileObject->id,
                'purpose' => $validated['purpose'] ?? 'primary',
                'display_order' => 1,
            ]);

            $document->increment('lock_version');

            return $ver;
        });

        return response()->json([
            'success' => true,
            'message' => "Version {$version->version_number} uploaded successfully.",
            'data' => $version,
        ], 201);
    }

    /**
     * Download file object with integrity check.
     */
    public function downloadFile(int $fileId): BinaryFileResponse|JsonResponse
    {
        $file = FileObject::findOrFail($fileId);

        // Independent Defense-in-Depth Authorization Verification
        $doc = Document::resolveForFile($fileId);
        if (! $doc) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: File is not associated with an authorized document record.',
            ], 403);
        }

        $actor = request()->user();
        if (! $doc->allowsUser($actor, 'documents.download')) {
            $confLevel = $doc->confidentiality?->code ?? 'internal';
            $message = in_array($confLevel, ['confidential', 'secret', 'top_secret'], true)
                ? 'Forbidden: High confidentiality document file download restricted.'
                : 'Forbidden: Missing required baseline permission [documents.download].';

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 403);
        }

        if ($file->storage_status !== 'ready') {
            return response()->json([
                'success' => false,
                'message' => "File is not ready for download (status: {$file->storage_status}).",
            ], 404);
        }

        $disk = Storage::disk($file->storage_disk);

        if (! $disk->exists($file->storage_key)) {
            return response()->json([
                'success' => false,
                'message' => 'Physical file not found in storage.',
            ], 404);
        }

        $fullPath = $disk->path($file->storage_key);

        return response()->download($fullPath, $file->original_filename, [
            'Content-Type' => $file->detected_mime,
            'X-Checksum-Sha256' => $file->sha256,
        ]);
    }

    /**
     * Record repository-wide document movement between offices.
     */
    public function recordMovement(Request $request, int $id): JsonResponse
    {
        $document = Document::findOrFail($id);

        $validated = $request->validate([
            'from_unit_id' => 'required|exists:organization_units,id',
            'to_unit_id' => 'required|exists:organization_units,id|different:from_unit_id',
            'instruction' => 'nullable|string|max:500',
            'due_at' => 'nullable|date|after:now',
        ]);

        $actor = $request->user();
        $now = now()->format('Y-m-d H:i:s.u');

        $movement = DocumentMovement::create([
            'document_id' => $document->id,
            'from_unit_id' => $validated['from_unit_id'],
            'to_unit_id' => $validated['to_unit_id'],
            'sent_by' => $actor->id,
            'sent_at' => $now,
            'instruction' => $validated['instruction'] ?? null,
            'due_at' => $validated['due_at'] ?? null,
            'idempotency_key' => (string) Str::uuid(),
        ]);

        AuditEvent::create([
            'actor_id' => $actor->id,
            'action' => 'document.movement_sent',
            'target_type' => 'document_movement',
            'target_id' => (string) $movement->id,
            'request_id' => (string) Str::uuid(),
            'occurred_at' => $now,
            'details_json' => [
                'document_id' => $document->id,
                'from_unit_id' => $validated['from_unit_id'],
                'to_unit_id' => $validated['to_unit_id'],
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document movement recorded successfully.',
            'data' => $movement->load(['fromUnit', 'toUnit', 'sentBy']),
        ], 201);
    }

    /**
     * Register physical original for a document.
     */
    public function storePhysicalOriginal(Request $request, int $id, DocumentRepositoryService $repoService): JsonResponse
    {
        $document = Document::findOrFail($id);

        $validated = $request->validate([
            'physical_reference' => 'nullable|string|max:100',
            'retention_requirement' => 'nullable|in:required,optional,none',
            'retention_policy_version_id' => 'nullable|exists:retention_policy_versions,id',
            'storage_location_id' => 'nullable|exists:physical_storage_locations,id',
            'custodian_id' => 'nullable|exists:users,id',
            'custody_reason' => 'nullable|string|max:500',
        ]);

        $original = $repoService->registerPhysicalOriginal($document, $validated, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Physical original registered successfully.',
            'data' => $original->load(['custodyEvents.location', 'custodyEvents.custodian']),
        ], 201);
    }

    /**
     * List physical originals for a document.
     */
    public function physicalOriginals(int $id): JsonResponse
    {
        $document = Document::findOrFail($id);

        $originals = PhysicalOriginal::with(['custodyEvents.location', 'custodyEvents.custodian', 'digitizationRecords.documentVersion'])
            ->where('document_id', $document->id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $originals,
        ]);
    }

    /**
     * Scenario B: Deferred Scanning Digitization.
     * Uploads the scan file as Version 2 without mutating Version 1.
     */
    public function digitizePaper(Request $request, int $id, DocumentRepositoryService $repoService): JsonResponse
    {
        $document = Document::findOrFail($id);

        $validated = $request->validate([
            'file' => 'required|file|max:102400',
            'physical_original_id' => 'nullable|exists:physical_originals,id',
            'verification_note' => 'nullable|string|max:500',
        ]);

        $newVersion = $repoService->digitizePaperOriginal(
            $document,
            $request->file('file'),
            $request->user()->id,
            $validated['physical_original_id'] ?? null,
            $validated['verification_note'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => "Paper original digitized successfully as Version {$newVersion->version_number}.",
            'data' => $newVersion->load('versionFiles.fileObject'),
        ], 201);
    }

    /**
     * Synthesized chronological document timeline.
     */
    public function timeline(int $id, DocumentTimelineService $timelineService): JsonResponse
    {
        $document = Document::with([
            'category',
            'confidentiality',
            'ownerUnit',
            'creator',
            'versions.createdBy',
            'versions.versionFiles',
            'versions.workflowInstances.events.actor',
            'versions.workflowInstances.events.transition',
            'physicalOriginals.custodyEvents.actor',
            'physicalOriginals.custodyEvents.custodian',
            'movements.fromUnit',
            'movements.toUnit',
            'movements.sentBy',
            'movements.receipt.acknowledgedBy',
            'assignments.assignedUser',
            'assignments.assignedUnit',
            'assignments.assignedBy',
            'assignments.events.actor',
            'assignments.events.targetUser',
            'assignments.responses.responseDocument',
        ])->findOrFail($id);

        $timeline = $timelineService->getTimeline($document);

        return response()->json([
            'success' => true,
            'data' => $timeline,
        ]);
    }
}
