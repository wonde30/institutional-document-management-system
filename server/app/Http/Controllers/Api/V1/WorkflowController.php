<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTask;
use App\Services\WorkflowEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    public function __construct(
        protected WorkflowEngineService $workflowEngine
    ) {}

    /**
     * List workflow definitions.
     */
    public function definitions(): JsonResponse
    {
        $definitions = WorkflowDefinition::where('active', 1)->get();

        return response()->json([
            'success' => true,
            'data' => $definitions,
        ]);
    }

    /**
     * List workflow tasks assigned to current user or unit.
     */
    public function tasks(Request $request): JsonResponse
    {
        $actor = $request->user();
        $status = $request->query('status', 'open');

        $query = WorkflowTask::with([
            'instance.documentVersion.document',
            'state',
            'assignedUser',
            'assignedUnit',
            'requiredRole',
            'delegatedTo',
            'participants.user',
            'decision.decidedBy',
        ]);

        if ($status === 'closed') {
            $query->whereNotNull('closed_at');
        } elseif ($status !== 'all') {
            $query->whereNull('closed_at');
        }

        $permittedUnitIds = $actor ? $actor->getPermittedUnitIds('workflow.view') : [];
        $scope = $request->query('scope', 'mine');

        if ($scope === 'mine') {
            $query->where(function ($q) use ($actor) {
                $q->where('assigned_user_id', $actor->id)
                  ->orWhere('delegated_to_user_id', $actor->id)
                  ->orWhereHas('participants', fn ($pq) => $pq->where('user_id', $actor->id)->where('status', 'pending'));
            });
        } else {
            $primaryUnitId = $actor->getPrimaryUnitId();
            $filterUnits = in_array($scope, ['office', 'all'], true)
                ? ($permittedUnitIds ?? ($primaryUnitId ? [$primaryUnitId] : []))
                : $permittedUnitIds;

            if ($filterUnits !== null && ! empty($filterUnits)) {
                $query->where(function ($q) use ($filterUnits, $actor) {
                    $q->whereIn('assigned_unit_id', $filterUnits)
                        ->orWhere('assigned_user_id', $actor->id)
                        ->orWhere('delegated_to_user_id', $actor->id)
                        ->orWhereHas('instance.documentVersion.document', fn ($dq) => $dq->whereIn('owner_unit_id', $filterUnits));
                });
            } elseif ($filterUnits !== null) {
                $query->where(function ($q) use ($actor) {
                    $q->where('assigned_user_id', $actor->id)
                        ->orWhere('delegated_to_user_id', $actor->id)
                        ->orWhereHas('participants', fn ($pq) => $pq->where('user_id', $actor->id));
                });
            }
        }

        $orderColumn = $status === 'closed' ? 'closed_at' : 'due_at';
        $orderDirection = $status === 'closed' ? 'desc' : 'asc';
        $tasks = $query->orderBy($orderColumn, $orderDirection)->paginate($request->query('per_page', 25));

        return response()->json([
            'success' => true,
            // The list is returned in the same envelope as every other
            // paginated endpoint: the records in "data" and the paging
            // counters in "meta".
            'data' => $tasks->items(),
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'total' => $tasks->total(),
                'per_page' => $tasks->perPage(),
            ],
        ]);
    }

    /**
     * Start a new workflow instance on a document version.
     */
    public function startInstance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workflow_definition_id' => 'required|exists:workflow_definitions,id',
            'document_version_id' => 'nullable|exists:document_versions,id',
            'parent_instance_id' => 'nullable|exists:workflow_instances,id',
        ]);

        $actor = $request->user();
        $definition = WorkflowDefinition::where('id', $validated['workflow_definition_id'])->where('active', 1)->first();
        if (! $definition) {
            return response()->json([
                'success' => false,
                'message' => 'The selected workflow definition is inactive or does not exist.',
            ], 422);
        }

        if (! empty($validated['document_version_id'])) {
            $docVersion = \App\Models\DocumentVersion::with('document')->findOrFail((int) $validated['document_version_id']);
            $doc = $docVersion->document;
            if ($doc && $actor && ! $doc->allowsUser($actor, 'workflow.review') && ! $doc->allowsUser($actor, 'documents.update') && ! $actor->hasPermission('workflow.submit', (int) $doc->owner_unit_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: You are not authorized to initiate a workflow on this document.',
                ], 403);
            }
        }

        $instance = $this->workflowEngine->startInstance(
            (int) $validated['workflow_definition_id'],
            isset($validated['document_version_id']) && $validated['document_version_id'] !== null ? (int) $validated['document_version_id'] : null,
            isset($validated['parent_instance_id']) ? (int) $validated['parent_instance_id'] : null,
            $actor
        );

        return response()->json([
            'success' => true,
            'message' => 'Workflow instance started successfully.',
            'data' => $instance->load(['documentVersion', 'tasks.state']),
        ], 201);
    }

    /**
     * Assign participants/committee reviewers to a workflow task.
     */
    public function addParticipants(Request $request, int $id): JsonResponse
    {
        $task = WorkflowTask::with('instance.documentVersion.document')->whereNull('closed_at')->findOrFail($id);
        $actor = $request->user();
        $unitId = $task->assigned_unit_id ?? $task->instance?->documentVersion?->document?->owner_unit_id;
        $canManage = $actor->hasPermission('system.configure')
            || $actor->hasPermission('workflow.reassign', $unitId)
            || $actor->hasPermission('workflow.configure', $unitId)
            || $actor->hasPermission('workflow.review', $unitId)
            || $task->assigned_user_id === $actor->id;

        if (! $canManage) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: You are not authorized to assign participants to this workflow task.',
            ], 403);
        }

        $validated = $request->validate([
            'participants' => 'required|array|min:1',
            'participants.*.user_id' => 'required|exists:users,id',
            'participants.*.role' => 'nullable|in:lead_reviewer,reviewer,contributor,observer',
        ]);

        $this->workflowEngine->assignParticipants($task, $validated['participants']);

        return response()->json([
            'success' => true,
            'message' => 'Workflow task participants updated.',
            'data' => $task->fresh(['participants.user']),
        ]);
    }

    /**
     * Delegate a workflow task.
     */
    public function delegate(Request $request, int $id): JsonResponse
    {
        $task = WorkflowTask::with('instance.documentVersion.document')->whereNull('closed_at')->findOrFail($id);

        $actor = $request->user();
        $unitId = $task->assigned_unit_id ?? $task->instance?->documentVersion?->document?->owner_unit_id;
        $isAssigned = $task->assigned_user_id === $actor->id;
        $canDelegate = $isAssigned
            || $actor->hasPermission('system.configure')
            || $actor->hasPermission('workflow.reassign', $unitId)
            || $actor->hasPermission('workflow.configure', $unitId);

        if (! $canDelegate) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: You are not authorized to delegate this workflow task.',
            ], 403);
        }

        $validated = $request->validate([
            'target_user_id' => 'required|exists:users,id',
            'reason' => 'nullable|string|max:500',
        ]);

        $delegated = $this->workflowEngine->delegateTask(
            $task,
            (int) $validated['target_user_id'],
            $validated['reason'] ?? null,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Workflow task delegated successfully.',
            'data' => $delegated->load(['delegatedTo', 'delegatedBy']),
        ]);
    }

    /**
     * Reassign a workflow task to another user.
     */
    public function reassign(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        $task = WorkflowTask::with(['instance.documentVersion.document'])->whereNull('closed_at')->findOrFail($id);
        $unitId = $task->assigned_unit_id ?? $task->instance?->documentVersion?->document?->owner_unit_id;

        if (! $actor->hasPermission('system.configure') && ! $actor->hasPermission('workflow.configure', $unitId) && ! $actor->hasPermission('workflow.reassign', $unitId)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: You are not authorized to reassign this workflow task.',
            ], 403);
        }

        $validated = $request->validate([
            'target_user_id' => 'required|exists:users,id',
            'reason' => 'nullable|string|max:500',
        ]);

        $updatedTask = $this->workflowEngine->reassignTask(
            $task,
            (int) $validated['target_user_id'],
            $validated['reason'] ?? null,
            $actor
        );

        return response()->json([
            'success' => true,
            'message' => 'Workflow task successfully reassigned.',
            'data' => $updatedTask,
        ]);
    }

    /**
     * Record a decision on a workflow task with multi-reviewer consensus evaluation.
     */
    public function decide(Request $request, int $id): JsonResponse
    {
        $task = WorkflowTask::findOrFail($id);

        $validated = $request->validate([
            'decision' => 'required|in:approve,reject,return,cancel,submit,resubmit,review,start_review',
            'reason' => 'nullable|string|max:1000',
            'idempotency_key' => 'nullable|string|max:100',
            'spawn_revision' => 'nullable|boolean',
        ]);

        $result = $this->workflowEngine->processDecision($task, $validated, $request->user());

        $statusCode = match ($result['status']) {
            'idempotent_duplicate' => 200,
            'pending_consensus' => 202,
            default => 200,
        };

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => $result,
        ], $statusCode);
    }
}
