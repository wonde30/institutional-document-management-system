<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ArchiveCollection;
use App\Models\ArchiveEntry;
use App\Models\Document;
use App\Models\LegalHold;
use App\Models\RetentionPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArchiveController extends Controller
{
    /**
     * List archive collections.
     */
    public function collections(): JsonResponse
    {
        $collections = ArchiveCollection::with('parent')->get();

        return response()->json([
            'success' => true,
            'data' => $collections,
        ]);
    }

    /**
     * List retention policies.
     */
    public function policies(): JsonResponse
    {
        $policies = RetentionPolicy::where('active', 1)->get();

        return response()->json([
            'success' => true,
            'data' => $policies,
        ]);
    }

    /**
     * List archived entries.
     */
    public function entries(Request $request): JsonResponse
    {
        $query = ArchiveEntry::with([
            'document.category',
            'document.confidentiality',
            'collection',
            'retentionPolicyVersion.policy',
            'archivedBy',
        ]);

        if ($request->filled('collection_id')) {
            $query->where('collection_id', $request->query('collection_id'));
        }

        if ($request->filled('queue')) {
            $queue = $request->query('queue');
            if ($queue === 'upcoming_review') {
                $query->whereNotNull('next_review_at')
                    ->whereBetween('next_review_at', [now(), now()->addDays(30)]);
            } elseif ($queue === 'expired_review') {
                $query->whereNotNull('next_review_at')
                    ->where('next_review_at', '<=', now());
            } elseif ($queue === 'held') {
                $query->whereHas('document.legalHolds', fn ($hq) => $hq->whereNull('released_at'));
            }
        }

        $actor = $request->user();
        if ($actor && ! $actor->hasPermission('system.configure') && ! $actor->hasPermission('archive.manage_retention')) {
            $query->whereHas('document', function ($dq) use ($actor) {
                $dq->accessibleBy($actor, 'archive.view');
            });
        }

        $entries = $query->orderByDesc('archived_at')->paginate($request->query('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $entries->items(),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'total' => $entries->total(),
            ],
        ]);
    }

    /**
     * Transfer document to archive.
     */
    public function transfer(Request $request): JsonResponse
    {
        if ($request->filled('retention_policy_id') && ! $request->filled('retention_policy_version_id')) {
            $policyId = $request->input('retention_policy_id');
            $versionId = DB::table('retention_policy_versions')->where('policy_id', $policyId)->orderByDesc('version_number')->value('id');
            if ($versionId) {
                $request->merge(['retention_policy_version_id' => $versionId]);
            }
        }

        $validated = $request->validate([
            'document_id' => 'required|exists:documents,id|unique:archive_entries,document_id',
            'collection_id' => 'required|exists:archive_collections,id',
            'retention_policy_version_id' => 'required|exists:retention_policy_versions,id',
            'next_review_at' => 'nullable|date|after:now',
        ]);

        $actor = $request->user();
        $doc = Document::findOrFail($validated['document_id']);
        if (! $actor->hasPermission('system.configure') && ! $doc->allowsUser($actor, 'archive.transfer') && ! $actor->hasPermission('archive.transfer', $doc->owner_unit_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to archive this document.',
            ], 403);
        }

        $now = now()->format('Y-m-d H:i:s.u');

        $nextReviewAt = $validated['next_review_at'] ?? null;
        if (! $nextReviewAt && ! empty($validated['retention_policy_version_id'])) {
            $policyVersion = DB::table('retention_policy_versions')->where('id', $validated['retention_policy_version_id'])->first();
            if ($policyVersion && (int) $policyVersion->duration_months > 0) {
                $nextReviewAt = now()->addMonths((int) $policyVersion->duration_months)->format('Y-m-d H:i:s.u');
            }
        }

        $entry = DB::transaction(function () use ($validated, $actor, $now, $nextReviewAt) {
            $entry = ArchiveEntry::create([
                'document_id' => $validated['document_id'],
                'collection_id' => $validated['collection_id'],
                'retention_policy_version_id' => $validated['retention_policy_version_id'],
                'archived_by' => $actor->id,
                'archived_at' => $now,
                'trigger_occurred_at' => $now,
                'next_review_at' => $nextReviewAt,
            ]);

            // Update document lifecycle to retired
            Document::where('id', $validated['document_id'])->update([
                'lifecycle' => 'retired',
                'updated_at' => $now,
            ]);

            return $entry;
        });

        if ($entry->next_review_at && $entry->next_review_at <= now()->addDays(30)) {
            try {
                app(\App\Services\EmailNotificationService::class)->notifyRetentionReview($entry, 'due');
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send retention review notification', ['error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Document successfully transferred to institutional archive.',
            'data' => $entry->load(['document', 'collection']),
        ], 201);
    }

    /**
     * List active legal holds.
     */
    public function legalHolds(): JsonResponse
    {
        $holds = LegalHold::with(['placedBy', 'releasedBy', 'documents:id,title,reference_number'])
            ->orderByDesc('placed_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $holds,
        ]);
    }

    /**
     * Store new archival collection.
     */
    public function storeCollection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:32|unique:archive_collections,code',
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:archive_collections,id',
        ]);

        $collection = ArchiveCollection::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Archive collection created successfully.',
            'data' => $collection->load('parent'),
        ], 201);
    }

    /**
     * Store new legal hold and attach documents.
     */
    public function storeLegalHold(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:64|unique:legal_holds,code',
            'reason' => 'required|string',
            'document_ids' => 'nullable|array',
            'document_ids.*' => 'exists:documents,id',
        ]);

        $actor = $request->user();
        $now = now()->format('Y-m-d H:i:s.u');

        $hold = DB::transaction(function () use ($validated, $actor, $now) {
            $hold = LegalHold::create([
                'code' => $validated['code'],
                'reason' => $validated['reason'],
                'placed_by' => $actor->id,
                'placed_at' => $now,
            ]);

            if (! empty($validated['document_ids'])) {
                foreach (array_unique($validated['document_ids']) as $docId) {
                    DB::table('legal_hold_documents')->insert([
                        'hold_id' => $hold->id,
                        'document_id' => $docId,
                    ]);
                }
            }

            return $hold;
        });

        return response()->json([
            'success' => true,
            'message' => 'Legal hold placed successfully.',
            'data' => $hold->load(['placedBy', 'documents:id,title,reference_number']),
        ], 201);
    }

    /**
     * Release an active legal hold.
     */
    public function releaseLegalHold(Request $request, int $id): JsonResponse
    {
        $hold = LegalHold::findOrFail($id);

        if ($hold->released_at !== null) {
            return response()->json([
                'success' => false,
                'message' => 'This legal hold has already been released.',
            ], 422);
        }

        $actor = $request->user();
        $now = now()->format('Y-m-d H:i:s.u');

        $hold->update([
            'released_by' => $actor->id,
            'released_at' => $now,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Legal hold released successfully.',
            'data' => $hold->fresh(['placedBy', 'releasedBy', 'documents:id,title,reference_number']),
        ]);
    }

    /**
     * Evaluate disposal eligibility for an archived entry.
     */
    public function disposalEligibility(int $id): JsonResponse
    {
        $entry = ArchiveEntry::with(['document', 'retentionPolicyVersion.policy', 'collection'])->findOrFail($id);

        $activeHolds = DB::table('legal_hold_documents')
            ->join('legal_holds', 'legal_holds.id', '=', 'legal_hold_documents.hold_id')
            ->where('legal_hold_documents.document_id', $entry->document_id)
            ->whereNull('legal_holds.released_at')
            ->select('legal_holds.id', 'legal_holds.code', 'legal_holds.reason', 'legal_holds.placed_at')
            ->get();

        $reasons = [];
        $isHoldActive = $activeHolds->isNotEmpty();
        if ($isHoldActive) {
            $reasons[] = 'Document is protected under ' . $activeHolds->count() . ' active statutory legal hold(s).';
        }

        $now = now();
        $isRetentionExpired = false;
        if ($entry->next_review_at) {
            if ($entry->next_review_at <= $now) {
                $isRetentionExpired = true;
            } else {
                $reasons[] = 'Statutory retention period has not expired (next review date: ' . $entry->next_review_at->toDateString() . ').';
            }
        } else {
            $reasons[] = 'Archived record is classified for permanent preservation unless an authorized institutional review overrides.';
        }

        if ($entry->document->lifecycle === 'tombstoned') {
            $reasons[] = 'Document record has already been disposed / tombstoned.';
        }

        $eligible = ! $isHoldActive && $isRetentionExpired && $entry->document->lifecycle !== 'tombstoned';

        return response()->json([
            'success' => true,
            'data' => [
                'entry_id' => $entry->id,
                'document_id' => $entry->document_id,
                'reference_number' => $entry->document->reference_number,
                'title' => $entry->document->title,
                'lifecycle' => $entry->document->lifecycle,
                'eligible' => $eligible,
                'reasons' => $reasons,
                'active_holds' => $activeHolds,
                'next_review_at' => $entry->next_review_at,
            ],
        ]);
    }

    /**
     * Record a retention review decision on an archived entry.
     */
    public function review(Request $request, int $id): JsonResponse
    {
        $entry = ArchiveEntry::with(['document', 'retentionPolicyVersion.policy'])->findOrFail($id);
        $actor = $request->user();

        if (! $actor->hasPermission('system.configure') && ! $actor->hasPermission('archive.manage_retention') && ! $actor->hasPermission('archive.transfer')) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to perform retention reviews on archived records.',
            ], 403);
        }

        $validated = $request->validate([
            'review_action' => 'required|in:extend,retain_permanent,request_disposal',
            'months_extended' => 'nullable|required_if:review_action,extend|integer|min:1|max:600',
            'notes' => 'required|string|min:5|max:2000',
        ]);

        if ($validated['review_action'] === 'request_disposal') {
            $activeHold = DB::table('legal_hold_documents')
                ->join('legal_holds', 'legal_holds.id', '=', 'legal_hold_documents.hold_id')
                ->where('legal_hold_documents.document_id', $entry->document_id)
                ->whereNull('legal_holds.released_at')
                ->first();

            if ($activeHold) {
                return response()->json([
                    'success' => false,
                    'message' => "Disposal prohibited: Record is protected under active statutory legal hold ({$activeHold->code}).",
                ], 422);
            }
        }

        $now = now()->format('Y-m-d H:i:s.u');

        $result = DB::transaction(function () use ($entry, $validated, $actor, $now) {
            $action = $validated['review_action'];
            $newReviewDate = $entry->next_review_at;
            $dispositionReq = null;

            if ($action === 'extend') {
                $months = (int) $validated['months_extended'];
                $newReviewDate = now()->addMonths($months)->format('Y-m-d H:i:s.u');
                $entry->update(['next_review_at' => $newReviewDate]);
            } elseif ($action === 'retain_permanent') {
                $newReviewDate = null;
                $entry->update(['next_review_at' => null]);
            } elseif ($action === 'request_disposal') {
                $dispositionReq = \App\Models\DispositionRequest::create([
                    'archive_entry_id' => $entry->id,
                    'requested_by' => $actor->id,
                    'requested_action' => 'destroy_digital',
                    'requested_at' => $now,
                    'justification' => $validated['notes'],
                ]);
            }

            \App\Models\AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'archive.retention_reviewed',
                'target_type' => 'archive_entry',
                'target_id' => (string) $entry->id,
                'request_id' => (string) \Illuminate\Support\Str::uuid(),
                'occurred_at' => $now,
                'details_json' => [
                    'review_action' => $action,
                    'previous_next_review_at' => $entry->getOriginal('next_review_at'),
                    'new_next_review_at' => $newReviewDate,
                    'notes' => $validated['notes'],
                    'disposition_request_id' => $dispositionReq?->id,
                ],
            ]);

            try {
                $notifEvent = \App\Models\NotificationEvent::create([
                    'type_code' => 'archive.retention_reviewed',
                    'document_id' => $entry->document_id,
                    'actor_id' => $actor->id,
                    'occurred_at' => $now,
                ]);
                \App\Models\UserNotification::create([
                    'event_id' => $notifEvent->id,
                    'user_id' => $actor->id,
                    'created_at' => $now,
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Failed to create retention review notification', ['error' => $e->getMessage()]);
            }

            return [
                'entry' => $entry->fresh(['document', 'retentionPolicyVersion.policy']),
                'disposition_request' => $dispositionReq,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => "Retention review recorded with action '{$validated['review_action']}'.",
            'data' => $result,
        ], 200);
    }

    /**
     * Submit a formal disposition request for an archived document.
     */
    public function requestDisposal(Request $request, int $id): JsonResponse
    {
        $entry = ArchiveEntry::with('document')->findOrFail($id);

        $validated = $request->validate([
            'requested_action' => 'required|in:destroy_digital,destroy_physical,transfer',
            'justification' => 'required|string|min:5|max:2000',
            'external_receipt' => 'nullable|string|max:255',
            'evidence_document_id' => 'nullable|exists:documents,id',
        ]);

        // STRICT ENFORCEMENT: Never allow disposal request if active legal hold exists
        $activeHolds = DB::table('legal_hold_documents')
            ->join('legal_holds', 'legal_holds.id', '=', 'legal_hold_documents.hold_id')
            ->where('legal_hold_documents.document_id', $entry->document_id)
            ->whereNull('legal_holds.released_at')
            ->get();

        if ($activeHolds->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Disposal request prohibited: Document is protected under active legal hold (' . $activeHolds->first()->code . ').',
                'errors' => [
                    'legal_hold' => ['Active legal hold strictly prevents any disposition request.'],
                ],
            ], 422);
        }

        if ($entry->document->lifecycle === 'tombstoned') {
            return response()->json([
                'success' => false,
                'message' => 'Document has already undergone disposition and is tombstoned.',
            ], 422);
        }

        $actor = $request->user();
        $now = now()->format('Y-m-d H:i:s.u');

        $req = \App\Models\DispositionRequest::create([
            'archive_entry_id' => $entry->id,
            'requested_by' => $actor->id,
            'requested_action' => $validated['requested_action'],
            'requested_at' => $now,
            'justification' => $validated['justification'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Disposition request submitted for independent archival authorization.',
            'data' => $req->load(['archiveEntry.document', 'requestedBy']),
        ], 201);
    }

    /**
     * Authorize or reject a disposition request with independent separation of duties.
     */
    public function decideDisposal(Request $request, int $requestId): JsonResponse
    {
        $dispositionRequest = \App\Models\DispositionRequest::with(['archiveEntry.document'])->findOrFail($requestId);
        $actor = $request->user();

        // ANTI-SELF-APPROVAL RULE: The requester must NEVER approve their own disposition request (strict separation of duties)
        if ($dispositionRequest->requested_by === $actor->id) {
            return response()->json([
                'success' => false,
                'message' => 'Archival separation of duties violation: The requester must not authorize their own disposition request. Independent authorization is required.',
            ], 422);
        }

        // STRICT ENFORCEMENT: Immediately re-verify active legal holds before decision
        $activeHolds = DB::table('legal_hold_documents')
            ->join('legal_holds', 'legal_holds.id', '=', 'legal_hold_documents.hold_id')
            ->where('legal_hold_documents.document_id', $dispositionRequest->archiveEntry->document_id)
            ->whereNull('legal_holds.released_at')
            ->get();

        if ($activeHolds->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Decision blocked: Active legal hold (' . $activeHolds->first()->code . ') exists on target document.',
            ], 422);
        }

        $validated = $request->validate([
            'decision' => 'required|in:approve,reject',
            'reason' => 'required|string|min:5|max:1000',
        ]);

        $now = now()->format('Y-m-d H:i:s.u');

        $decision = \App\Models\DispositionDecision::updateOrCreate(
            ['request_id' => $dispositionRequest->id],
            [
                'decided_by' => $actor->id,
                'decision' => $validated['decision'],
                'reason' => $validated['reason'],
                'decided_at' => $now,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => "Disposition decision '{$validated['decision']}' recorded.",
            'data' => $decision->load(['request', 'decidedBy']),
        ], 200);
    }

    /**
     * Execute authorized disposition with mandatory physical evidence checks.
     */
    public function executeDisposal(Request $request, int $requestId): JsonResponse
    {
        $dispositionRequest = \App\Models\DispositionRequest::with(['archiveEntry.document', 'decision'])->findOrFail($requestId);
        $actor = $request->user();

        // 1. Verify independent approval
        if (! $dispositionRequest->decision || $dispositionRequest->decision->decision !== 'approve') {
            return response()->json([
                'success' => false,
                'message' => 'Execution rejected: Disposition request has not received independent archival approval.',
            ], 422);
        }

        // 2. Strict active hold check immediately before execution
        $activeHolds = DB::table('legal_hold_documents')
            ->join('legal_holds', 'legal_holds.id', '=', 'legal_hold_documents.hold_id')
            ->where('legal_hold_documents.document_id', $dispositionRequest->archiveEntry->document_id)
            ->whereNull('legal_holds.released_at')
            ->get();

        if ($activeHolds->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Execution blocked: Target document is protected under active legal hold (' . $activeHolds->first()->code . ').',
            ], 422);
        }

        $validated = $request->validate([
            'external_receipt' => 'nullable|string|max:255',
            'evidence_document_id' => 'nullable|exists:documents,id',
        ]);

        // 3. Physical destruction requirement: must have recorded institutional evidence
        if ($dispositionRequest->requested_action === 'destroy_physical') {
            if (empty($validated['external_receipt']) && empty($validated['evidence_document_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Physical destruction requires recorded institutional evidence (receipt reference or evidence document) under Wollo University records management policy.',
                ], 422);
            }
        }

        $now = now()->format('Y-m-d H:i:s.u');

        $execution = DB::transaction(function () use ($dispositionRequest, $validated, $actor, $now) {
            $exec = \App\Models\DispositionExecution::updateOrCreate(
                ['request_id' => $dispositionRequest->id],
                [
                    'executed_by' => $actor->id,
                    'executed_at' => $now,
                    'evidence_document_id' => $validated['evidence_document_id'] ?? null,
                    'external_receipt' => $validated['external_receipt'] ?? ('DISP-' . strtoupper(bin2hex(random_bytes(6)))),
                ]
            );

            // Mark document lifecycle as tombstoned
            $dispositionRequest->archiveEntry->document->update([
                'lifecycle' => 'tombstoned',
                'updated_at' => $now,
            ]);

            \App\Models\AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'archive.disposition_executed',
                'target_type' => 'document',
                'target_id' => (string) $dispositionRequest->archiveEntry->document_id,
                'request_id' => (string) \Illuminate\Support\Str::uuid(),
                'occurred_at' => $now,
                'details_json' => [
                    'request_id' => $dispositionRequest->id,
                    'action' => $dispositionRequest->requested_action,
                    'receipt' => $exec->external_receipt,
                ],
            ]);

            return $exec;
        });

        return response()->json([
            'success' => true,
            'message' => 'Statutory disposition executed and document transitioned to tombstoned status.',
            'data' => $execution->load(['request', 'executedBy']),
        ], 200);
    }

    /**
     * Restore / retrieve archived document back to active operational service.
     */
    public function restore(Request $request, int $id): JsonResponse
    {
        $entry = ArchiveEntry::with('document')->findOrFail($id);

        if ($entry->document->lifecycle === 'tombstoned') {
            return response()->json([
                'success' => false,
                'message' => 'Disposed / tombstoned document cannot be retrieved to active service.',
            ], 422);
        }

        $actor = $request->user();
        $now = now()->format('Y-m-d H:i:s.u');

        $entry->document->update([
            'lifecycle' => 'active',
            'updated_at' => $now,
        ]);

        \App\Models\AuditEvent::create([
            'actor_id' => $actor->id,
            'action' => 'archive.document_restored',
            'target_type' => 'document',
            'target_id' => (string) $entry->document_id,
            'request_id' => (string) \Illuminate\Support\Str::uuid(),
            'occurred_at' => $now,
            'details_json' => ['entry_id' => $entry->id],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document restored to active operational status from institutional archive.',
            'data' => $entry->fresh(['document']),
        ]);
    }

    /**
     * Verify cryptographic file integrity of an archived file object.
     */
    public function verifyIntegrity(Request $request, int $fileObjectId): JsonResponse
    {
        $fileObject = \App\Models\FileObject::findOrFail($fileObjectId);
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
                'message' => 'File integrity check failed: file missing from primary storage.',
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
                    ? 'Cryptographic integrity verified: SHA-256 checksum matches institutional record.'
                    : 'INTEGRITY ALERT: SHA-256 hash mismatch detected against institutional record.',
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

    /**
     * Execute statutory record disposition. Strictly blocks disposal if under active legal hold.
     */
    public function dispose(Request $request, int $id): JsonResponse
    {
        $entry = ArchiveEntry::with('document')->findOrFail($id);

        $validated = $request->validate([
            'requested_action' => 'required|in:destroy_digital,destroy_physical,transfer',
            'justification' => 'required|string|min:5|max:2000',
            'external_receipt' => 'nullable|string|max:255',
            'evidence_document_id' => 'nullable|exists:documents,id',
        ]);

        // STRICT ENFORCEMENT: Never allow disposal if active legal hold exists
        $activeHolds = DB::table('legal_hold_documents')
            ->join('legal_holds', 'legal_holds.id', '=', 'legal_hold_documents.hold_id')
            ->where('legal_hold_documents.document_id', $entry->document_id)
            ->whereNull('legal_holds.released_at')
            ->get();

        if ($activeHolds->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Disposal strictly prohibited: Document is protected under active legal hold (' . $activeHolds->first()->code . ').',
                'errors' => [
                    'legal_hold' => ['Active legal hold strictly prevents any disposition or destruction.'],
                ],
            ], 422);
        }

        if ($entry->document->lifecycle === 'tombstoned') {
            return response()->json([
                'success' => false,
                'message' => 'Document has already undergone disposition and is tombstoned.',
            ], 422);
        }

        $actor = $request->user();
        $now = now()->format('Y-m-d H:i:s.u');

        $disposition = DB::transaction(function () use ($entry, $validated, $actor, $now) {
            $req = \App\Models\DispositionRequest::create([
                'archive_entry_id' => $entry->id,
                'requested_by' => $actor->id,
                'requested_action' => $validated['requested_action'],
                'requested_at' => $now,
                'justification' => $validated['justification'],
            ]);

            $decision = \App\Models\DispositionDecision::create([
                'request_id' => $req->id,
                'decided_by' => $actor->id,
                'decision' => 'approve',
                'reason' => 'Approved disposition per institutional protocol: ' . $validated['justification'],
                'decided_at' => $now,
            ]);

            $execution = \App\Models\DispositionExecution::create([
                'request_id' => $req->id,
                'executed_by' => $actor->id,
                'executed_at' => $now,
                'evidence_document_id' => $validated['evidence_document_id'] ?? null,
                'external_receipt' => $validated['external_receipt'] ?? ('DISP-' . strtoupper(bin2hex(random_bytes(6)))),
            ]);

            // Mark document lifecycle as tombstoned
            $entry->document->update([
                'lifecycle' => 'tombstoned',
                'updated_at' => $now,
            ]);

            return [
                'request' => $req,
                'decision' => $decision,
                'execution' => $execution,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Document disposition executed successfully and record transitioned to tombstoned.',
            'data' => $disposition,
        ]);
    }
}
