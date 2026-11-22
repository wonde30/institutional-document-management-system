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
use App\Models\WorkflowDefinition;
use App\Services\DocumentRepositoryService;
use App\Services\DocumentTimelineService;
use App\Services\Settings\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentController extends Controller
{
    /**
     * The largest upload accepted, expressed in kilobytes for the validator.
     */
    protected function maxUploadSizeKilobytes(): int
    {
        $megabytes = app(SettingsService::class)->value('documents.max_upload_size_mb');

        if (! is_numeric($megabytes) || (int) $megabytes < 1) {
            return 100 * 1024;
        }

        return (int) $megabytes * 1024;
    }

    /**
     * Resolve the classification applied to a document.
     *
     * An explicit level always wins. Otherwise the level configured for the
     * owning unit is used, then the global level, and finally the internal
     * level. The public level is never applied as a default.
     */
    protected function resolveConfidentialityId(?int $requested, ?int $ownerUnitId): int
    {
        if ($requested) {
            return $requested;
        }

        $code = app(SettingsService::class)->effectiveValue(
            'documents.default_confidentiality_level_code',
            $ownerUnitId
        );

        if (! is_string($code) || ! in_array($code, ['internal', 'confidential', 'restricted'], true)) {
            $code = 'internal';
        }

        $level = DB::table('confidentiality_levels')
            ->where('code', $code)
            ->where('active', 1)
            ->value('id');

        return (int) ($level ?? DB::table('confidentiality_levels')->where('code', 'internal')->value('id'));
    }
    /**
     * List documents with filtering and search.
     */
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $query = Document::accessibleBy($actor, 'documents.view')
            ->with(['category', 'confidentiality', 'ownerUnit', 'creator', 'custodian', 'currentVersion'])
            ->withCount('versions');

        // Departmental Scope Isolation: Restrict to permitted units unless globally authorized
        $permittedUnitIds = $actor ? $actor->getPermittedUnitIds('documents.view') : [];
        if ($request->filled('owner_unit_id')) {
            $requestedUnit = (int) $request->query('owner_unit_id');
            if ($permittedUnitIds !== null && ! in_array($requestedUnit, $permittedUnitIds, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: You do not have permission to view documents from this organizational unit.',
                ], 403);
            }
            $query->where('owner_unit_id', $requestedUnit);
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

        $sortBy = $request->query('sort_by', 'registered_at');
        $sortOrder = strtolower((string) $request->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['registered_at', 'document_date', 'title', 'reference_number', 'lifecycle'];
        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'registered_at';
        }

        $documents = $query->orderBy($sortBy, $sortOrder)->paginate($request->query('per_page', 25));

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
            'confidentiality_id' => 'nullable|exists:confidentiality_levels,id',
            'owner_unit_id' => 'required|exists:organization_units,id',
            'custodian_id' => 'nullable|exists:users,id',
            'document_date' => 'required|date',
            'file' => 'nullable|file|max:'.$this->maxUploadSizeKilobytes(),
            'change_note' => 'nullable|string',
            'workflow_definition_id' => 'nullable|exists:workflow_definitions,id',
        ]);

        $actor = $request->user();
        $now = now()->format('Y-m-d H:i:s.u');
        $currentYear = date('Y');

        // Resolve dynamic approval workflow definition (explicit selection or automatic category mapping)
        $workflowDef = null;
        if (! empty($validated['workflow_definition_id'])) {
            $workflowDef = \App\Models\WorkflowDefinition::where('id', $validated['workflow_definition_id'])
                ->where('active', 1)
                ->first();
            if (! $workflowDef) {
                return response()->json([
                    'success' => false,
                    'message' => 'The selected workflow definition is inactive or does not exist.',
                ], 422);
            }
        } else {
            $workflowDef = \App\Models\WorkflowDefinition::where('document_category_id', $validated['category_id'])
                ->where('active', 1)
                ->first();
        }

        // When no classification is supplied, the configured policy applies.
        $confidentialityId = $this->resolveConfidentialityId(
            isset($validated['confidentiality_id']) ? (int) $validated['confidentiality_id'] : null,
            (int) $validated['owner_unit_id']
        );

        $document = DB::transaction(function () use ($validated, $actor, $now, $currentYear, $request, $confidentialityId, $workflowDef) {
            // 1. Generate reference number atomically via canonical repository sequence engine
            $referenceNumber = app(\App\Services\DocumentRepositoryService::class)->generateReferenceNumber(
                'DOC',
                (int) $validated['owner_unit_id'],
                (int) $validated['category_id']
            );

            // 2. Create document record
            $doc = Document::create([
                'reference_number' => $referenceNumber,
                'title' => $validated['title'],
                'summary' => $validated['summary'] ?? null,
                'category_id' => $validated['category_id'],
                'confidentiality_id' => $confidentialityId,
                'owner_unit_id' => $validated['owner_unit_id'],
                'custodian_id' => $validated['custodian_id'] ?? null,
                'created_by' => $actor->id,
                'document_date' => $validated['document_date'],
                'registered_at' => $now,
                'lifecycle' => 'active',
                'lock_version' => 0,
            ]);

            // 3. Handle initial version & file if provided, or create version for workflow pinning
            $version = null;
            if ($request->hasFile('file')) {
                $fileObject = app(\App\Services\FileSecurityService::class)->storeUploadedFile(
                    $request->file('file'),
                    'documents/'.$currentYear,
                    $actor->id
                );

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
            } elseif ($workflowDef) {
                $version = DocumentVersion::create([
                    'document_id' => $doc->id,
                    'version_number' => 1,
                    'created_by' => $actor->id,
                    'change_note' => $validated['change_note'] ?? 'Initial registered version for workflow execution',
                    'created_at' => $now,
                ]);
            }

            // 4. Start workflow instance if applicable
            if ($workflowDef && $version) {
                app(\App\Services\WorkflowEngineService::class)->startInstance(
                    $workflowDef->id,
                    $version->id,
                    null,
                    $actor
                );
            }

            return $doc;
        });

        $document->load([
            'category',
            'confidentiality',
            'ownerUnit',
            'creator',
            'custodian',
            'versions.createdBy',
            'versions.versionFiles.fileObject',
            'versions.workflowInstances.workflowVersion.workflowDefinition',
            'versions.workflowInstances.tasks.state',
            'versions.workflowInstances.tasks.assignedUser',
            'versions.workflowInstances.tasks.assignedUnit',
            'versions.workflowInstances.tasks.requiredRole',
        ]);

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
            'versions.workflowInstances.workflowVersion.workflowDefinition',
            'versions.workflowInstances.startedBy',
            'versions.workflowInstances.tasks.state',
            'versions.workflowInstances.tasks.assignedUser',
            'versions.workflowInstances.tasks.assignedUnit',
            'versions.workflowInstances.tasks.requiredRole',
            'versions.workflowInstances.tasks.decision.decidedBy',
            'tags',
            'outgoingRelations.relationType',
            'outgoingRelations.targetDocument',
            'incomingRelations.relationType',
            'incomingRelations.sourceDocument',
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
        $actor = $request->user();

        if ($actor && ! $actor->hasPermission('system.configure') && ! $actor->hasPermission('documents.update', $document->owner_unit_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to update documents in this organizational unit.',
            ], 403);
        }

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
            'file' => 'required|file|max:'.$this->maxUploadSizeKilobytes(),
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

            $fileObject = app(\App\Services\FileSecurityService::class)->storeUploadedFile(
                $request->file('file'),
                'documents/'.$currentYear,
                $actor->id,
                $validated['purpose'] ?? 'primary'
            );

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
        $hasDocDownload = $doc->allowsUser($actor, 'documents.download');
        $hasCorrAccess = false;
        if ($doc->correspondence) {
            $ownerUnitId = (int) $doc->owner_unit_id;
            $hasCorrAccess = $actor->hasPermission('correspondence.view', $ownerUnitId)
                || $actor->hasPermission('correspondence.manage', $ownerUnitId)
                || $actor->hasPermission('correspondence.dispatch', $ownerUnitId);
        }

        if (! $hasDocDownload && ! $hasCorrAccess) {
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

        // Security Hardening: Audit confidential document access
        // Ensure confidentiality relationship is loaded
        if (! $doc->relationLoaded('confidentiality')) {
            $doc->load('confidentiality');
        }
        
        $confLevel = $doc->confidentiality?->code ?? 'public';
        if ($confLevel !== 'public') {
            try {
                AuditService::recordDocumentEvent(
                    action: 'document.confidential_access',
                    actorId: $actor->id,
                    documentId: $doc->id,
                    details: [
                        'file_object_id' => $file->id,
                        'original_filename' => $file->original_filename,
                        'classification_level' => $confLevel,
                        'sha256' => $file->sha256,
                        'byte_size' => $file->byte_size,
                        'access_type' => 'download',
                    ],
                    request: request()
                );
            } catch (\Throwable $e) {
                // Log but don't fail the download
                \Log::error('Failed to record confidential access audit', [
                    'document_id' => $doc->id,
                    'file_id' => $file->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // General file download audit (all documents, including public)
        try {
            AuditService::recordDocumentEvent(
                action: 'documents.downloaded',
                actorId: $actor->id,
                documentId: $doc->id,
                details: [
                    'file_object_id' => $file->id,
                    'original_filename' => $file->original_filename,
                    'sha256' => $file->sha256,
                    'byte_size' => $file->byte_size,
                ],
                request: request()
            );
        } catch (\Throwable $e) {
            \Log::error('Failed to record download audit', [
                'document_id' => $doc->id,
                'file_id' => $file->id,
                'error' => $e->getMessage()
            ]);
        }

        return app(\App\Services\FileSecurityService::class)->createDownloadResponse($file);
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

    /**
     * Compare two immutable versions of a document.
     */
    public function compareVersions(Request $request, int $id): JsonResponse
    {
        $document = Document::findOrFail($id);

        $validated = $request->validate([
            'version_a' => 'required|integer',
            'version_b' => 'required|integer|different:version_a',
        ]);

        $versionA = DocumentVersion::with(['createdBy', 'versionFiles.fileObject', 'templateVersion.template'])
            ->where('document_id', $document->id)
            ->where('id', $validated['version_a'])
            ->firstOrFail();

        $versionB = DocumentVersion::with(['createdBy', 'versionFiles.fileObject', 'templateVersion.template'])
            ->where('document_id', $document->id)
            ->where('id', $validated['version_b'])
            ->firstOrFail();

        $filesA = $versionA->versionFiles->map(fn ($vf) => [
            'id' => $vf->file_object_id,
            'filename' => $vf->fileObject?->original_filename,
            'byte_size' => $vf->fileObject?->byte_size,
            'sha256' => $vf->fileObject?->sha256,
            'mime' => $vf->fileObject?->detected_mime,
            'purpose' => $vf->purpose,
        ]);

        $filesB = $versionB->versionFiles->map(fn ($vf) => [
            'id' => $vf->file_object_id,
            'filename' => $vf->fileObject?->original_filename,
            'byte_size' => $vf->fileObject?->byte_size,
            'sha256' => $vf->fileObject?->sha256,
            'mime' => $vf->fileObject?->detected_mime,
            'purpose' => $vf->purpose,
        ]);

        $isContentIdentical = false;
        $hashesA = $filesA->pluck('sha256')->filter()->sort()->values()->all();
        $hashesB = $filesB->pluck('sha256')->filter()->sort()->values()->all();
        if (! empty($hashesA) && $hashesA === $hashesB) {
            $isContentIdentical = true;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'document_id' => $document->id,
                'version_a' => [
                    'id' => $versionA->id,
                    'version_number' => $versionA->version_number,
                    'change_note' => $versionA->change_note,
                    'created_at' => $versionA->created_at?->toISOString(),
                    'creator' => $versionA->createdBy ? [
                        'id' => $versionA->createdBy->id,
                        'name' => $versionA->createdBy->name,
                    ] : null,
                    'files' => $filesA,
                ],
                'version_b' => [
                    'id' => $versionB->id,
                    'version_number' => $versionB->version_number,
                    'change_note' => $versionB->change_note,
                    'created_at' => $versionB->created_at?->toISOString(),
                    'creator' => $versionB->createdBy ? [
                        'id' => $versionB->createdBy->id,
                        'name' => $versionB->createdBy->name,
                    ] : null,
                    'files' => $filesB,
                ],
                'comparison' => [
                    'content_identical' => $isContentIdentical,
                    'files_count_diff' => count($filesB) - count($filesA),
                    'version_delta' => $versionB->version_number - $versionA->version_number,
                ],
            ],
        ]);
    }

    /**
     * Add a relationship link between documents.
     */
    public function addRelation(Request $request, int $id): JsonResponse
    {
        $document = Document::findOrFail($id);

        $validated = $request->validate([
            'target_document_id' => 'required|exists:documents,id|different:id',
            'relation_type' => 'nullable|string|in:references,supersedes,supplements,responds_to',
            'relation_type_id' => 'nullable|exists:document_relation_types,id',
        ]);

        $relTypeId = $validated['relation_type_id'] ?? null;
        if (! $relTypeId) {
            $code = $validated['relation_type'] ?? 'references';
            $relType = \App\Models\DocumentRelationType::firstOrCreate(
                ['code' => $code],
                ['name' => ucfirst(str_replace('_', ' ', $code))]
            );
            $relTypeId = $relType->id;
        }

        $relation = \App\Models\DocumentRelation::firstOrCreate([
            'source_id' => $document->id,
            'target_id' => $validated['target_document_id'],
            'relation_type_id' => $relTypeId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document relationship established successfully.',
            'data' => $relation->load(['relationType', 'targetDocument']),
        ], 201);
    }

    /**
     * Remove a relationship link between documents.
     */
    public function removeRelation(Request $request, int $id, int $targetId): JsonResponse
    {
        $query = \App\Models\DocumentRelation::where('source_id', $id)->where('target_id', $targetId);
        if ($request->filled('relation_type_id')) {
            $query->where('relation_type_id', $request->input('relation_type_id'));
        }
        $query->delete();

        return response()->json([
            'success' => true,
            'message' => 'Document relationship removed.',
        ]);
    }

    /**
     * Verify cryptographic file integrity of a document file object.
     */
    public function verifyFileIntegrity(Request $request, int $id, int $fileId): JsonResponse
    {
        $document = Document::findOrFail($id);

        // Verify the file belongs to a version of this document
        \App\Models\VersionFile::whereHas('documentVersion', fn ($q) => $q->where('document_id', $document->id))
            ->where('file_object_id', $fileId)
            ->firstOrFail();

        $fileObject = \App\Models\FileObject::findOrFail($fileId);
        $disk = $fileObject->storage_disk ?: config('filesystems.default', 'local');
        $now = now()->format('Y-m-d H:i:s.u');

        $storage = \Illuminate\Support\Facades\Storage::disk($disk);
        if (! $storage->exists($fileObject->storage_key)) {
            $check = \App\Models\FileIntegrityCheck::create([
                'file_object_id' => $fileObject->id,
                'checked_at' => $now,
                'observed_sha256' => null,
                'outcome' => 'missing',
                'error_code' => 'FILE_NOT_FOUND_ON_DISK',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Integrity verification failed: primary file missing from storage.',
                'data' => $check,
            ], 404);
        }

        try {
            $content = $storage->get($fileObject->storage_key);
            $hash = hash('sha256', $content);
            $outcome = hash_equals($fileObject->sha256, $hash) ? 'match' : 'mismatch';

            $check = \App\Models\FileIntegrityCheck::create([
                'file_object_id' => $fileObject->id,
                'checked_at' => $now,
                'observed_sha256' => $hash,
                'outcome' => $outcome,
                'error_code' => $outcome === 'match' ? null : 'SHA256_MISMATCH',
            ]);

            return response()->json([
                'success' => $outcome === 'match',
                'message' => $outcome === 'match'
                    ? 'Cryptographic integrity verified: SHA-256 matches institutional record.'
                    : 'INTEGRITY ALERT: Stored file SHA-256 differs from registered checksum.',
                'data' => [
                    'check' => $check,
                    'expected_sha256' => $fileObject->sha256,
                    'observed_sha256' => $hash,
                    'outcome' => $outcome,
                ],
            ]);
        } catch (\Throwable $e) {
            $check = \App\Models\FileIntegrityCheck::create([
                'file_object_id' => $fileObject->id,
                'checked_at' => $now,
                'observed_sha256' => null,
                'outcome' => 'error',
                'error_code' => substr($e->getMessage(), 0, 100),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'File integrity check error: ' . $e->getMessage(),
                'data' => $check,
            ], 500);
        }
    }
}
