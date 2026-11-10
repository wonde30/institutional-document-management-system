<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CorrespondenceResource;
use App\Models\AuditEvent;
use App\Models\CommunicationParty;
use App\Models\Correspondence;
use App\Models\CorrespondenceDispatch;
use App\Models\CorrespondenceParticipant;
use App\Models\CorrespondenceType;
use App\Models\Document;
use App\Models\DocumentAssignment;
use App\Models\DocumentMovement;
use App\Models\DocumentRelation;
use App\Models\DocumentRelationType;
use App\Models\DocumentVersion;
use App\Models\MovementReceipt;
use App\Models\OfficeProcedureVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CorrespondenceController extends Controller
{
    /**
     * List correspondences with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $query = Correspondence::with([
            'document',
            'type',
            'priority',
            'firstReceivingUnit',
            'registrar',
            'movements.fromUnit',
            'movements.toUnit',
            'movements.sentBy',
            'movements.receipt.acknowledgedBy',
            'assignments.assignedUser',
            'assignments.assignedUnit',
        ]);

        // Departmental Scope Isolation for Correspondence:
        $permittedUnitIds = $actor ? $actor->getPermittedUnitIds('correspondence.view') : [];
        if ($permittedUnitIds !== null) {
            $query->where(function ($q) use ($permittedUnitIds) {
                $q->whereHas('document', fn ($dq) => $dq->whereIn('owner_unit_id', $permittedUnitIds))
                  ->orWhereIn('first_receiving_unit_id', $permittedUnitIds)
                  ->orWhereHas('movements', fn ($mq) => $mq->whereIn('to_unit_id', $permittedUnitIds))
                  ->orWhereHas('assignments', fn ($aq) => $aq->whereIn('assigned_unit_id', $permittedUnitIds));
            });
        }

        if ($request->filled('type_id')) {
            $query->where('type_id', $request->query('type_id'));
        }

        if ($request->filled('priority_id')) {
            $query->where('priority_id', $request->query('priority_id'));
        }

        if ($request->filled('first_receiving_unit_id')) {
            $query->where('first_receiving_unit_id', $request->query('first_receiving_unit_id'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('external_reference', 'like', "%{$search}%")
                  ->orWhereHas('document', fn ($dq) => $dq->where('title', 'like', "%{$search}%")->orWhere('reference_number', 'like', "%{$search}%"));
            });
        }

        $records = $query->orderByDesc('registered_at')->paginate($request->query('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => CorrespondenceResource::collection($records),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'total' => $records->total(),
            ],
        ]);
    }

    /**
     * Store new correspondence with canonical document registration.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'summary' => 'nullable|string',
            'category_id' => 'required|exists:document_categories,id',
            'confidentiality_id' => 'required|exists:confidentiality_levels,id',
            'owner_unit_id' => 'required|exists:organization_units,id',
            'type_id' => 'required|exists:correspondence_types,id',
            'priority_id' => 'required|exists:priorities,id',
            'purpose_code' => 'required|string|max:64',
            'external_reference' => 'nullable|string|max:160',
            'correspondence_date' => 'required|date',
            'first_receiving_unit_id' => 'nullable|exists:organization_units,id',
            'requested_due_at' => 'nullable|date',
            'sender_unit_id' => 'nullable|exists:organization_units,id',
            'to_unit_id' => 'nullable|exists:organization_units,id',
        ]);

        $actor = $request->user();
        $now = now()->format('Y-m-d H:i:s.u');
        $currentYear = date('Y');

        // 1. Duplicate detection: prevent duplicate disconnected records
        if (! empty($validated['external_reference'])) {
            $duplicate = Correspondence::where('external_reference', $validated['external_reference'])
                ->where('type_id', $validated['type_id'])
                ->first();
            if ($duplicate) {
                return response()->json([
                    'success' => false,
                    'message' => "Duplicate correspondence detected: external reference '{$validated['external_reference']}' is already registered as Correspondence #{$duplicate->id}. Maintain one correspondence identity instead of creating disconnected records.",
                ], 422);
            }
        }

        // 2. Receipt path procedure enforcement
        $receivingUnitId = $validated['first_receiving_unit_id'] ?? $validated['owner_unit_id'];
        $proc = OfficeProcedureVersion::where('unit_id', $receivingUnitId)
            ->where('correspondence_type_id', $validated['type_id'])
            ->orderByDesc('version_number')
            ->first();

        if ($proc && $proc->receipt_mode === 'via_registry' && ! empty($proc->registry_unit_id)) {
            if ($validated['first_receiving_unit_id'] != $proc->registry_unit_id) {
                return response()->json([
                    'success' => false,
                    'message' => "Policy violation: Confirmed office procedure mandates incoming correspondence for this office to be received via designated registry unit ID {$proc->registry_unit_id}.",
                ], 422);
            }
        }

        $correspondence = DB::transaction(function () use ($validated, $actor, $now, $currentYear, $receivingUnitId) {
            // 1. Generate reference number atomically
            $seq = DB::table('reference_sequences')
                ->where('sequence_key', 'CORR')
                ->where('period_key', $currentYear)
                ->lockForUpdate()
                ->first();

            if (! $seq) {
                DB::table('reference_sequences')->insert([
                    'sequence_key' => 'CORR',
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

            $referenceNumber = sprintf('WU/CORR/%s/%06d', $currentYear, $seqVal);

            // 2. Create document record
            $doc = Document::create([
                'reference_number' => $referenceNumber,
                'title' => $validated['title'],
                'summary' => $validated['summary'] ?? null,
                'category_id' => $validated['category_id'],
                'confidentiality_id' => $validated['confidentiality_id'],
                'owner_unit_id' => $validated['owner_unit_id'],
                'custodian_id' => $actor->id,
                'created_by' => $actor->id,
                'document_date' => $validated['correspondence_date'],
                'registered_at' => $now,
                'lifecycle' => 'active',
                'lock_version' => 0,
            ]);

            DocumentVersion::create([
                'document_id' => $doc->id,
                'version_number' => 1,
                'created_by' => $actor->id,
                'created_at' => $now,
                'change_note' => 'Initial registered version',
            ]);

            // 3. Create correspondence record
            $receivingUnitId = $validated['first_receiving_unit_id'] ?? null;
            $receivedAt = $receivingUnitId ? $now : null;

            $corr = Correspondence::create([
                'document_id' => $doc->id,
                'type_id' => $validated['type_id'],
                'priority_id' => $validated['priority_id'],
                'purpose_code' => $validated['purpose_code'],
                'external_reference' => $validated['external_reference'] ?? null,
                'correspondence_date' => $validated['correspondence_date'],
                'first_receiving_unit_id' => $receivingUnitId,
                'received_at' => $receivedAt,
                'requested_due_at' => $validated['requested_due_at'] ?? null,
                'registered_by' => $actor->id,
                'registered_at' => $now,
            ]);

            // 4. Create communication parties if provided
            if (! empty($validated['sender_unit_id'])) {
                $party = CommunicationParty::firstOrCreate([
                    'unit_id' => $validated['sender_unit_id'],
                    'user_id' => null,
                    'external_party_id' => null,
                ]);

                CorrespondenceParticipant::firstOrCreate([
                    'correspondence_id' => $corr->id,
                    'party_id' => $party->id,
                    'participation_type' => 'sender',
                ]);
            }

            if (! empty($validated['to_unit_id'])) {
                $party = CommunicationParty::firstOrCreate([
                    'unit_id' => $validated['to_unit_id'],
                    'user_id' => null,
                    'external_party_id' => null,
                ]);

                CorrespondenceParticipant::firstOrCreate([
                    'correspondence_id' => $corr->id,
                    'party_id' => $party->id,
                    'participation_type' => 'to',
                ]);
            }

            return $corr;
        });

        $correspondence->load(['document', 'type', 'priority', 'firstReceivingUnit', 'registrar']);

        return response()->json([
            'success' => true,
            'message' => 'Correspondence registered successfully.',
            'data' => new CorrespondenceResource($correspondence),
        ], 201);
    }

    /**
     * Show single correspondence with full movement trail and assignments.
     */
    public function show(int $id): JsonResponse
    {
        $correspondence = Correspondence::with([
            'document.category',
            'document.confidentiality',
            'type',
            'priority',
            'firstReceivingUnit',
            'registrar',
            'participants.party',
            'movements.fromUnit',
            'movements.toUnit',
            'movements.sentBy',
            'movements.receipt.acknowledgedBy',
            'assignments.assignedUser',
            'assignments.assignedUnit',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new CorrespondenceResource($correspondence),
        ]);
    }

    /**
     * Record internal movement / forwarding of document between units.
     */
    public function recordMovement(Request $request, int $id): JsonResponse
    {
        $corr = Correspondence::findOrFail($id);

        $validated = $request->validate([
            'from_unit_id' => 'required|exists:organization_units,id',
            'to_unit_id' => 'required|exists:organization_units,id|different:from_unit_id',
            'instruction' => 'nullable|string|max:500',
            'due_at' => 'nullable|date|after:now',
        ]);

        $actor = $request->user();
        $now = now()->format('Y-m-d H:i:s.u');

        $movement = DocumentMovement::create([
            'document_id' => $corr->document_id,
            'from_unit_id' => $validated['from_unit_id'],
            'to_unit_id' => $validated['to_unit_id'],
            'sent_by' => $actor->id,
            'sent_at' => $now,
            'instruction' => $validated['instruction'] ?? null,
            'due_at' => $validated['due_at'] ?? null,
            'idempotency_key' => (string) Str::uuid(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document movement recorded successfully.',
            'data' => $movement->load(['fromUnit', 'toUnit', 'sentBy']),
        ], 201);
    }

    /**
     * Acknowledge receipt of a document movement.
     */
    public function acknowledgeMovement(Request $request, int $id, int $movementId): JsonResponse
    {
        $corr = Correspondence::findOrFail($id);
        $movement = DocumentMovement::where('document_id', $corr->document_id)->findOrFail($movementId);
        $actor = $request->user();

        // Check if movement is already acknowledged
        $existingReceipt = MovementReceipt::where('movement_id', $movement->id)->first();
        if ($existingReceipt) {
            return response()->json([
                'success' => false,
                'message' => 'Movement has already been acknowledged.',
                'data' => $existingReceipt->load('acknowledgedBy'),
            ], 422);
        }

        $now = now()->format('Y-m-d H:i:s.u');

        $receipt = DB::transaction(function () use ($movement, $actor, $now, $corr) {
            $rcpt = MovementReceipt::create([
                'movement_id' => $movement->id,
                'acknowledged_by' => $actor->id,
                'acknowledged_at' => $now,
            ]);

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'correspondence.movement_acknowledged',
                'target_type' => 'movement_receipt',
                'target_id' => (string) $movement->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => [
                    'correspondence_id' => $corr->id,
                    'movement_id' => $movement->id,
                    'acknowledged_by' => $actor->id,
                ],
            ]);

            return $rcpt;
        });

        return response()->json([
            'success' => true,
            'message' => 'Correspondence movement acknowledged successfully.',
            'data' => $receipt->load(['movement', 'acknowledgedBy']),
        ], 200);
    }

    /**
     * Assign correspondence task or directive to an officer.
     */
    public function assign(Request $request, int $id): JsonResponse
    {
        $corr = Correspondence::findOrFail($id);

        $validated = $request->validate([
            'assigned_user_id' => 'required|exists:users,id',
            'assigned_unit_id' => 'required|exists:organization_units,id',
            'instruction' => 'required|string|max:1000',
            'due_at' => 'nullable|date|after:now',
        ]);

        $actor = $request->user();
        $now = now()->format('Y-m-d H:i:s.u');

        $assignment = DocumentAssignment::create([
            'document_id' => $corr->document_id,
            'assigned_user_id' => $validated['assigned_user_id'],
            'assigned_unit_id' => $validated['assigned_unit_id'],
            'assigned_by' => $actor->id,
            'assigned_at' => $now,
            'due_at' => $validated['due_at'] ?? null,
            'instruction' => $validated['instruction'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Correspondence assigned successfully.',
            'data' => $assignment->load(['assignedUser', 'assignedUnit', 'assignedBy']),
        ], 201);
    }

    /**
     * Dispatch official outgoing correspondence under confirmed policy rules.
     */
    public function dispatch(Request $request, int $id): JsonResponse
    {
        $corr = Correspondence::with(['document.ownerUnit', 'document.versions', 'type'])->findOrFail($id);
        $actor = $request->user();
        $ownerUnitId = (int) $corr->document->owner_unit_id;

        if (! $actor->hasPermission('correspondence.dispatch', $ownerUnitId)) {
            return response()->json([
                'success' => false,
                'message' => "Forbidden: You do not have permission [correspondence.dispatch] for originating office ID {$ownerUnitId}.",
            ], 403);
        }

        // Support frontend client parameter aliases
        if ($request->filled('dispatch_method_id') && ! $request->filled('method_id')) {
            $request->merge(['method_id' => $request->input('dispatch_method_id')]);
        }
        if ($request->filled('tracking_number') && ! $request->filled('tracking_reference')) {
            $request->merge(['tracking_reference' => $request->input('tracking_number')]);
        }
        if (! $request->filled('document_version_id')) {
            $latestVersionId = $corr->document->versions()->latest('version_number')->value('id');
            if ($latestVersionId) {
                $request->merge(['document_version_id' => $latestVersionId]);
            }
        }

        $validated = $request->validate([
            'document_version_id' => 'required|exists:document_versions,id',
            'method_id' => 'required|exists:dispatch_methods,id',
            'recipient_party_id' => 'nullable|exists:communication_parties,id',
            'tracking_reference' => 'nullable|string|max:160',
        ]);

        $proc = OfficeProcedureVersion::where('unit_id', $ownerUnitId)
            ->where('correspondence_type_id', $corr->type_id)
            ->orderByDesc('version_number')
            ->first();

        // 1. Unconfirmed procedure: Block dispatch and record policy as unresolved
        if (! $proc || empty($proc->confirmed_at)) {
            return response()->json([
                'success' => false,
                'message' => "Policy decision unresolved: Office procedure for outgoing correspondence has not been confirmed for originating office '{$corr->document->ownerUnit->name}'. Unrestricted dispatch is prohibited until confirmed by Senate/Secretariat.",
            ], 422);
        }

        // 2. Blocked issue mode
        if ($proc->issue_mode === 'blocked') {
            return response()->json([
                'success' => false,
                'message' => "Policy restriction: Outgoing correspondence issue is blocked for this office under confirmed procedure.",
            ], 403);
        }

        // 3. Workflow issue mode: Requires Department Head approval of the EXACT document version
        if ($proc->issue_mode === 'workflow') {
            $targetVersionId = (int) $validated['document_version_id'];

            $isApproved = DB::table('workflow_task_decisions as wtd')
                ->join('workflow_tasks as wt', 'wtd.task_id', '=', 'wt.id')
                ->join('workflow_instances as wi', 'wt.instance_id', '=', 'wi.id')
                ->where('wi.document_version_id', $targetVersionId)
                ->where('wtd.decision', 'approve')
                ->exists();

            if (! $isApproved) {
                // Check if an earlier version was approved to verify material change reuse prevention
                $earlierApproved = DB::table('workflow_task_decisions as wtd')
                    ->join('workflow_tasks as wt', 'wtd.task_id', '=', 'wt.id')
                    ->join('workflow_instances as wi', 'wt.instance_id', '=', 'wi.id')
                    ->join('document_versions as dv', 'wi.document_version_id', '=', 'dv.id')
                    ->where('dv.document_id', $corr->document_id)
                    ->where('dv.id', '!=', $targetVersionId)
                    ->where('wtd.decision', 'approve')
                    ->exists();

                if ($earlierApproved) {
                    return response()->json([
                        'success' => false,
                        'message' => "Approval mismatch: Material modifications detected. Version {$targetVersionId} has not been approved. Prior approval on an earlier version cannot be reused for modified content; renewed Department Head approval is required.",
                    ], 422);
                }

                return response()->json([
                    'success' => false,
                    'message' => "Dispatch rejected: Confirmed procedure requires Department Head workflow approval before dispatch. This letter version has not been approved.",
                ], 422);
            }
        }

        $now = now()->format('Y-m-d H:i:s.u');

        $dispatch = DB::transaction(function () use ($corr, $validated, $actor, $now) {
            $partyId = $validated['recipient_party_id'] ?? null;
            if (! $partyId) {
                $party = CommunicationParty::firstOrCreate([
                    'unit_id' => $corr->document->owner_unit_id,
                    'user_id' => null,
                    'external_party_id' => null,
                ]);
                $partyId = $party->id;
            }

            $participant = CorrespondenceParticipant::firstOrCreate([
                'correspondence_id' => $corr->id,
                'party_id' => $partyId,
                'participation_type' => 'to',
            ]);

            $disp = CorrespondenceDispatch::create([
                'participant_id' => $participant->id,
                'document_version_id' => $validated['document_version_id'],
                'method_id' => $validated['method_id'],
                'dispatched_by' => $actor->id,
                'dispatched_at' => $now,
                'tracking_reference' => $validated['tracking_reference'] ?? null,
                'idempotency_key' => (string) Str::uuid(),
            ]);

            $corr->document->update(['lifecycle' => 'active']);

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'correspondence.dispatched',
                'target_type' => 'correspondence_dispatch',
                'target_id' => (string) $disp->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => ['correspondence_id' => $corr->id, 'version_id' => $validated['document_version_id']],
            ]);

            return $disp;
        });

        return response()->json([
            'success' => true,
            'message' => 'Official correspondence dispatched successfully under confirmed procedure.',
            'data' => $dispatch->load(['method', 'documentVersion']),
        ], 201);
    }

    /**
     * Prepare a linked outgoing response referencing an incoming correspondence.
     */
    public function prepareLinkedResponse(Request $request, int $id): JsonResponse
    {
        $incomingCorr = Correspondence::with('document.ownerUnit')->findOrFail($id);
        $actor = $request->user();

        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'summary' => 'nullable|string',
            'category_id' => 'required|exists:document_categories,id',
            'confidentiality_id' => 'required|exists:confidentiality_levels,id',
            'owner_unit_id' => 'required|exists:organization_units,id',
            'priority_id' => 'required|exists:priorities,id',
            'purpose_code' => 'required|string|max:64',
            'correspondence_date' => 'required|date',
            'external_reference' => 'nullable|string|max:160',
        ]);

        $ownerUnitId = (int) $validated['owner_unit_id'];
        if (! $actor->hasPermission('correspondence.register', $ownerUnitId)) {
            return response()->json([
                'success' => false,
                'message' => "Forbidden: You do not have permission [correspondence.register] for originating office ID {$ownerUnitId}.",
            ], 403);
        }

        $now = now()->format('Y-m-d H:i:s.u');
        $currentYear = date('Y');

        $outgoingType = CorrespondenceType::where('code', 'outgoing')->firstOrFail();
        $respondsToType = DocumentRelationType::where('code', 'responds_to')->firstOrFail();

        $outgoingCorrespondence = DB::transaction(function () use ($validated, $actor, $now, $currentYear, $incomingCorr, $outgoingType, $respondsToType) {
            // 1. Generate sequence
            $seq = DB::table('reference_sequences')
                ->where('sequence_key', 'CORR')
                ->where('period_key', $currentYear)
                ->lockForUpdate()
                ->first();

            if (! $seq) {
                DB::table('reference_sequences')->insert([
                    'sequence_key' => 'CORR',
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

            $referenceNumber = sprintf('WU/CORR/%s/%06d', $currentYear, $seqVal);

            // 2. Create Document
            $doc = Document::create([
                'reference_number' => $referenceNumber,
                'title' => $validated['title'],
                'summary' => $validated['summary'] ?? null,
                'category_id' => $validated['category_id'],
                'confidentiality_id' => $validated['confidentiality_id'],
                'owner_unit_id' => $validated['owner_unit_id'],
                'custodian_id' => $actor->id,
                'created_by' => $actor->id,
                'document_date' => $validated['correspondence_date'],
                'registered_at' => $now,
                'lifecycle' => 'active',
                'lock_version' => 0,
            ]);

            DocumentVersion::create([
                'document_id' => $doc->id,
                'version_number' => 1,
                'created_by' => $actor->id,
                'change_note' => 'Initial linked outgoing response draft',
                'created_at' => $now,
            ]);

            // 3. Create Correspondence
            $corr = Correspondence::create([
                'document_id' => $doc->id,
                'type_id' => $outgoingType->id,
                'priority_id' => $validated['priority_id'],
                'purpose_code' => $validated['purpose_code'],
                'external_reference' => $validated['external_reference'] ?? null,
                'correspondence_date' => $validated['correspondence_date'],
                'first_receiving_unit_id' => null,
                'received_at' => null,
                'registered_by' => $actor->id,
                'registered_at' => $now,
            ]);

            // 4. Create 3NF Document Relation (source = outgoing, target = incoming, type = responds_to)
            DocumentRelation::create([
                'source_id' => $doc->id,
                'target_id' => $incomingCorr->document_id,
                'relation_type_id' => $respondsToType->id,
            ]);

            // 5. Audit event
            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'correspondence.linked_response_created',
                'target_type' => 'correspondence',
                'target_id' => (string) $corr->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => [
                    'incoming_correspondence_id' => $incomingCorr->id,
                    'outgoing_correspondence_id' => $corr->id,
                    'relation' => 'reply_to',
                ],
            ]);

            return $corr;
        });

        return response()->json([
            'success' => true,
            'message' => 'Linked outgoing response correspondence prepared successfully.',
            'data' => new CorrespondenceResource($outgoingCorrespondence->load(['document.versions', 'type', 'priority'])),
        ], 201);
    }
}
