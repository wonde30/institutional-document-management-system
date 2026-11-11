<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentAssignment;
use App\Services\AssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function __construct(
        protected AssignmentService $assignmentService
    ) {}

    /**
     * List assignments (optionally scoped to a document, unit, or user).
     */
    public function index(Request $request, ?int $documentId = null): JsonResponse
    {
        $docId = $documentId ?? ($request->route('id') ? (int) $request->route('id') : null) ?? ($request->query('document_id') ? (int) $request->query('document_id') : null);

        $query = DocumentAssignment::with([
            'document',
            'assignedUser',
            'assignedUnit',
            'assignedBy',
            'participants.user',
            'events.actor',
            'events.targetUser',
            'responses.responseDocument',
        ]);

        if ($docId) {
            $query->where('document_id', $docId);
        }

        if ($request->filled('assigned_unit_id')) {
            $query->where('assigned_unit_id', $request->query('assigned_unit_id'));
        }

        if ($request->filled('assigned_user_id')) {
            $query->where('assigned_user_id', $request->query('assigned_user_id'));
        }

        $assignments = $query->orderByDesc('assigned_at')->get();

        return response()->json([
            'success' => true,
            'data' => $assignments,
        ]);
    }

    /**
     * Store new assignment for a document.
     */
    public function store(Request $request, int $documentId): JsonResponse
    {
        $document = Document::findOrFail($documentId);

        $validated = $request->validate([
            'assigned_user_id' => 'nullable|required_without:assigned_unit_id|exists:users,id',
            'assigned_unit_id' => 'nullable|required_without:assigned_user_id|exists:organization_units,id',
            'instruction' => 'required|string|max:1000',
            'due_at' => 'nullable|date|after:now',
            'participants' => 'nullable|array',
            'participants.*.user_id' => 'required|exists:users,id',
            'participants.*.role_code' => 'nullable|in:lead,writer,contributor,secretary',
        ]);

        $assignment = $this->assignmentService->createAssignment($document, $validated, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Operational directive assigned successfully.',
            'data' => $assignment->load(['assignedUser', 'assignedUnit', 'assignedBy', 'participants.user']),
        ], 201);
    }

    /**
     * Add a participant to an assignment team.
     */
    public function addParticipant(Request $request, int $id): JsonResponse
    {
        $assignment = DocumentAssignment::findOrFail($id);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_code' => 'required|in:lead,writer,contributor,secretary',
        ]);

        $participant = $this->assignmentService->addParticipant(
            $assignment,
            (int) $validated['user_id'],
            $validated['role_code'],
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => "Participant added with role '{$validated['role_code']}'.",
            'data' => $participant->load('user'),
        ], 201);
    }

    /**
     * Remove a participant from an assignment team.
     */
    public function removeParticipant(Request $request, int $id, int $userId): JsonResponse
    {
        $assignment = DocumentAssignment::findOrFail($id);

        $participant = $this->assignmentService->removeParticipant(
            $assignment,
            $userId,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Participant removed from assignment team.',
            'data' => [
                'assignment_id' => $assignment->id,
                'user_id' => $userId,
                'previous_role_code' => $participant->role_code,
            ],
        ]);
    }

    /**
     * Record an assignment lifecycle event (e.g. accepted, in_progress, delegated, completed).
     */
    public function recordEvent(Request $request, int $id): JsonResponse
    {
        $assignment = DocumentAssignment::findOrFail($id);

        $validated = $request->validate([
            'action' => 'required|in:assigned,claimed,delegated,delegation_accepted,delegation_revoked,reassigned,accepted,completed,returned,cancelled',
            'target_user_id' => 'nullable|exists:users,id',
            'comment' => 'nullable|string|max:1000',
        ]);

        $actor = $request->user();
        if ($validated['action'] === 'reassigned') {
            $isAuthorized = $actor->hasRole('super_admin')
                || $assignment->assigned_by === $actor->id
                || $assignment->assigned_user_id === $actor->id
                || $actor->hasPermission('correspondence.assign', $assignment->assigned_unit_id)
                || $actor->hasPermission('documents.manage', $assignment->assigned_unit_id);

            if (! $isAuthorized) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: You are not authorized to reassign this document assignment.',
                ], 403);
            }
        }

        $event = $this->assignmentService->recordEvent($assignment, $validated, $actor);

        return response()->json([
            'success' => true,
            'message' => "Assignment event '{$validated['action']}' recorded.",
            'data' => $event->load(['actor', 'targetUser']),
        ], 201);
    }

    /**
     * Link a fulfilling response document to this assignment.
     */
    public function linkResponse(Request $request, int $id): JsonResponse
    {
        $assignment = DocumentAssignment::findOrFail($id);

        $validated = $request->validate([
            'response_document_id' => 'required|exists:documents,id|different:'.$assignment->document_id,
            'relationship_role' => 'nullable|in:full_reply,interim_reply,supporting_report,partial_reply',
            'is_fulfilling' => 'nullable|boolean',
        ]);

        $responseDoc = Document::findOrFail($validated['response_document_id']);

        $response = $this->assignmentService->linkResponse(
            $assignment,
            $responseDoc,
            $validated,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Response document linked to operational directive.',
            'data' => $response->load(['responseDocument', 'linkedBy']),
        ], 201);
    }
}
