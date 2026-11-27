<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowState;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkflowAdminController extends Controller
{
    /**
     * List all workflow definitions with versions and active state.
     */
    public function index(Request $request): JsonResponse
    {
        $definitions = WorkflowDefinition::with([
            'documentCategory',
            'activeVersion.states.assignedRole',
            'activeVersion.states.assignedUnit',
            'activeVersion.states.outgoingTransitions.permission',
        ])
            ->withCount('versions')
            ->orderBy('name')
            ->get();

        $data = $definitions->map(function ($def) {
            $latestDraft = WorkflowVersion::where('definition_id', $def->id)
                ->whereNull('published_at')
                ->orderByDesc('version_number')
                ->first();

            return [
                'id' => $def->id,
                'code' => $def->code,
                'name' => $def->name,
                'description' => $def->description,
                'document_category_id' => $def->document_category_id,
                'document_category' => $def->documentCategory ? [
                    'id' => $def->documentCategory->id,
                    'code' => $def->documentCategory->code,
                    'name' => $def->documentCategory->name,
                ] : null,
                'active' => (bool) $def->active,
                'versions_count' => $def->versions_count,
                'active_version' => $def->activeVersion ? [
                    'id' => $def->activeVersion->id,
                    'version_number' => $def->activeVersion->version_number,
                    'published_at' => $def->activeVersion->published_at,
                    'states_count' => $def->activeVersion->states->count(),
                ] : null,
                'draft_version' => $latestDraft ? [
                    'id' => $latestDraft->id,
                    'version_number' => $latestDraft->version_number,
                    'created_at' => $latestDraft->created_at,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Show a workflow definition in full detail with all versions, states, and transitions.
     */
    public function show(int $id): JsonResponse
    {
        $def = WorkflowDefinition::with(['documentCategory'])->findOrFail($id);

        $versions = WorkflowVersion::where('definition_id', $def->id)
            ->with([
                'createdBy:id,name,email',
                'states.assignedRole:id,code,name',
                'states.assignedUnit:id,code,name',
                'states.outgoingTransitions.permission:id,code,name',
                'states.outgoingTransitions.toState:id,code,name',
            ])
            ->orderByDesc('version_number')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $def->id,
                'code' => $def->code,
                'name' => $def->name,
                'description' => $def->description,
                'document_category_id' => $def->document_category_id,
                'document_category' => $def->documentCategory,
                'active' => (bool) $def->active,
                'versions' => $versions,
            ],
        ]);
    }

    /**
     * Create a new workflow definition with an initial draft version.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:64|unique:workflow_definitions,code',
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:1000',
            'document_category_id' => 'nullable|exists:document_categories,id',
            'active' => 'nullable|boolean',
        ]);

        $actor = $request->user();
        $now = now()->format('Y-m-d H:i:s.u');

        $result = DB::transaction(function () use ($validated, $actor, $now) {
            $def = WorkflowDefinition::create([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'document_category_id' => $validated['document_category_id'] ?? null,
                'active' => $validated['active'] ?? true,
            ]);

            $version = WorkflowVersion::create([
                'definition_id' => $def->id,
                'version_number' => 1,
                'created_by' => $actor->id,
                'published_at' => null,
                'created_at' => $now,
            ]);

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'workflow.definition_created',
                'target_type' => 'workflow_definition',
                'target_id' => (string) $def->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => [
                    'code' => $def->code,
                    'name' => $def->name,
                    'version_id' => $version->id,
                ],
            ]);

            return ['definition' => $def, 'version' => $version];
        });

        return response()->json([
            'success' => true,
            'message' => 'Workflow definition created with initial draft version 1.',
            'data' => $result,
        ], 201);
    }

    /**
     * Update workflow definition details.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $def = WorkflowDefinition::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:1000',
            'document_category_id' => 'nullable|exists:document_categories,id',
            'active' => 'required|boolean',
        ]);

        DB::transaction(function () use ($def, $validated, $request) {
            $def->update($validated);

            AuditEvent::create([
                'actor_id' => $request->user()->id,
                'action' => 'workflow.definition_updated',
                'target_type' => 'workflow_definition',
                'target_id' => (string) $def->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => now()->format('Y-m-d H:i:s.u'),
                'details_json' => $validated,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Workflow definition updated.',
            'data' => $def,
        ]);
    }

    /**
     * Create a new draft version for a definition (optionally cloning an existing version).
     */
    public function storeVersion(Request $request, int $id): JsonResponse
    {
        $def = WorkflowDefinition::findOrFail($id);

        $validated = $request->validate([
            'clone_from_version_id' => 'nullable|exists:workflow_versions,id',
        ]);

        $actor = $request->user();
        $now = now()->format('Y-m-d H:i:s.u');

        $newVersion = DB::transaction(function () use ($def, $validated, $actor, $now) {
            $maxVersion = (int) WorkflowVersion::where('definition_id', $def->id)->max('version_number');
            $newVerNum = $maxVersion + 1;

            $version = WorkflowVersion::create([
                'definition_id' => $def->id,
                'version_number' => $newVerNum,
                'created_by' => $actor->id,
                'published_at' => null,
                'created_at' => $now,
            ]);

            // Clone states and transitions if requested
            if (! empty($validated['clone_from_version_id'])) {
                $sourceVersion = WorkflowVersion::where('definition_id', $def->id)
                    ->findOrFail($validated['clone_from_version_id']);

                $sourceStates = WorkflowState::where('workflow_version_id', $sourceVersion->id)->get();
                $idMap = [];

                foreach ($sourceStates as $sState) {
                    $cloned = WorkflowState::create([
                        'workflow_version_id' => $version->id,
                        'code' => $sState->code,
                        'name' => $sState->name,
                        'is_initial' => $sState->is_initial,
                        'is_terminal' => $sState->is_terminal,
                        'assigned_role_id' => $sState->assigned_role_id,
                        'assigned_unit_id' => $sState->assigned_unit_id,
                        'sla_hours' => $sState->sla_hours,
                    ]);
                    $idMap[$sState->id] = $cloned->id;
                }

                $sourceTransitions = WorkflowTransition::whereIn('from_state_id', $sourceStates->pluck('id'))->get();
                foreach ($sourceTransitions as $sTrans) {
                    if (isset($idMap[$sTrans->from_state_id], $idMap[$sTrans->to_state_id])) {
                        $forkIds = [];
                        if (is_array($sTrans->fork_state_ids)) {
                            foreach ($sTrans->fork_state_ids as $fid) {
                                if (isset($idMap[$fid])) {
                                    $forkIds[] = $idMap[$fid];
                                }
                            }
                        }

                        WorkflowTransition::create([
                            'from_state_id' => $idMap[$sTrans->from_state_id],
                            'to_state_id' => $idMap[$sTrans->to_state_id],
                            'action_code' => $sTrans->action_code,
                            'permission_id' => $sTrans->permission_id,
                            'requires_reason' => $sTrans->requires_reason,
                            'requires_signature' => $sTrans->requires_signature,
                            'prohibit_self_approval' => $sTrans->prohibit_self_approval,
                            'approval_policy' => $sTrans->approval_policy,
                            'quorum_count' => $sTrans->quorum_count,
                            'quorum_percentage' => $sTrans->quorum_percentage,
                            'join_policy' => $sTrans->join_policy,
                            'fork_state_ids' => ! empty($forkIds) ? $forkIds : null,
                        ]);
                    }
                }
            }

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'workflow.version_created',
                'target_type' => 'workflow_version',
                'target_id' => (string) $version->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => [
                    'definition_id' => $def->id,
                    'version_number' => $newVerNum,
                    'cloned_from' => $validated['clone_from_version_id'] ?? null,
                ],
            ]);

            return $version;
        });

        return response()->json([
            'success' => true,
            'message' => "Draft workflow version {$newVersion->version_number} created.",
            'data' => $newVersion->load(['states.outgoingTransitions']),
        ], 201);
    }

    /**
     * Add a stage (state) to a draft workflow version.
     */
    public function storeState(Request $request, int $id, int $versionId): JsonResponse
    {
        $version = WorkflowVersion::where('definition_id', $id)->findOrFail($versionId);

        if ($version->published_at !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Published workflow versions are immutable. Create a new draft version to modify states.',
            ], 422);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:64',
            'name' => 'required|string|max:120',
            'is_initial' => 'nullable|boolean',
            'is_terminal' => 'nullable|boolean',
            'assigned_role_id' => 'nullable|exists:roles,id',
            'assigned_unit_id' => 'nullable|exists:organization_units,id',
            'sla_hours' => 'nullable|integer|min:1',
        ]);

        $state = DB::transaction(function () use ($version, $validated) {
            if (! empty($validated['is_initial'])) {
                WorkflowState::where('workflow_version_id', $version->id)
                    ->update(['is_initial' => false]);
            }

            return WorkflowState::create([
                'workflow_version_id' => $version->id,
                'code' => $validated['code'],
                'name' => $validated['name'],
                'is_initial' => (bool) ($validated['is_initial'] ?? false),
                'is_terminal' => (bool) ($validated['is_terminal'] ?? false),
                'assigned_role_id' => $validated['assigned_role_id'] ?? null,
                'assigned_unit_id' => $validated['assigned_unit_id'] ?? null,
                'sla_hours' => $validated['sla_hours'] ?? null,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Workflow stage created.',
            'data' => $state->load(['assignedRole', 'assignedUnit']),
        ], 201);
    }

    /**
     * Update a stage in a draft workflow version.
     */
    public function updateState(Request $request, int $id, int $versionId, int $stateId): JsonResponse
    {
        $version = WorkflowVersion::where('definition_id', $id)->findOrFail($versionId);

        if ($version->published_at !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Published workflow versions are immutable. Create a new draft version to modify states.',
            ], 422);
        }

        $state = WorkflowState::where('workflow_version_id', $version->id)->findOrFail($stateId);

        $validated = $request->validate([
            'code' => 'required|string|max:64',
            'name' => 'required|string|max:120',
            'is_initial' => 'nullable|boolean',
            'is_terminal' => 'nullable|boolean',
            'assigned_role_id' => 'nullable|exists:roles,id',
            'assigned_unit_id' => 'nullable|exists:organization_units,id',
            'sla_hours' => 'nullable|integer|min:1',
        ]);

        DB::transaction(function () use ($version, $state, $validated) {
            if (! empty($validated['is_initial'])) {
                WorkflowState::where('workflow_version_id', $version->id)
                    ->where('id', '!=', $state->id)
                    ->update(['is_initial' => false]);
            }

            $state->update([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'is_initial' => (bool) ($validated['is_initial'] ?? false),
                'is_terminal' => (bool) ($validated['is_terminal'] ?? false),
                'assigned_role_id' => $validated['assigned_role_id'] ?? null,
                'assigned_unit_id' => $validated['assigned_unit_id'] ?? null,
                'sla_hours' => $validated['sla_hours'] ?? null,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Workflow stage updated.',
            'data' => $state->load(['assignedRole', 'assignedUnit']),
        ]);
    }

    /**
     * Delete a stage and its associated transitions from a draft workflow version.
     */
    public function destroyState(int $id, int $versionId, int $stateId): JsonResponse
    {
        $version = WorkflowVersion::where('definition_id', $id)->findOrFail($versionId);

        if ($version->published_at !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Published workflow versions are immutable.',
            ], 422);
        }

        $state = WorkflowState::where('workflow_version_id', $version->id)->findOrFail($stateId);

        DB::transaction(function () use ($state) {
            WorkflowTransition::where('from_state_id', $state->id)
                ->orWhere('to_state_id', $state->id)
                ->delete();

            $state->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Workflow stage deleted.',
        ]);
    }

    /**
     * Add a transition between stages in a draft workflow version.
     */
    public function storeTransition(Request $request, int $id, int $versionId): JsonResponse
    {
        $version = WorkflowVersion::where('definition_id', $id)->findOrFail($versionId);

        if ($version->published_at !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Published workflow versions are immutable.',
            ], 422);
        }

        $validated = $request->validate([
            'from_state_id' => 'required|exists:workflow_states,id',
            'to_state_id' => 'required|exists:workflow_states,id',
            'action_code' => 'required|string|max:64',
            'permission_id' => 'nullable|exists:permissions,id',
            'requires_reason' => 'nullable|boolean',
            'requires_signature' => 'nullable|boolean',
            'prohibit_self_approval' => 'nullable|boolean',
            'approval_policy' => 'nullable|in:single,all_required,any_required,majority,quorum',
            'quorum_count' => 'nullable|integer|min:1',
            'quorum_percentage' => 'nullable|numeric|min:0|max:100',
            'join_policy' => 'nullable|in:all_branches,any_branch',
            'fork_state_ids' => 'nullable|array',
        ]);

        $fromState = WorkflowState::where('workflow_version_id', $version->id)->findOrFail($validated['from_state_id']);
        $toState = WorkflowState::where('workflow_version_id', $version->id)->findOrFail($validated['to_state_id']);

        if ($fromState->id === $toState->id) {
            return response()->json([
                'success' => false,
                'message' => 'Self-loop transition detected: a stage cannot transition directly to itself.',
            ], 422);
        }

        if ($fromState->is_terminal) {
            return response()->json([
                'success' => false,
                'message' => "Terminal stage '{$fromState->name}' cannot have outgoing transitions.",
            ], 422);
        }

        $permissionId = $validated['permission_id']
            ?? \App\Models\Permission::where('code', 'workflow.review')->value('id')
            ?? \App\Models\Permission::first()?->id
            ?? 1;

        $transition = WorkflowTransition::create([
            'from_state_id' => $fromState->id,
            'to_state_id' => $toState->id,
            'action_code' => $validated['action_code'],
            'permission_id' => $permissionId,
            'requires_reason' => (bool) ($validated['requires_reason'] ?? false),
            'requires_signature' => (bool) ($validated['requires_signature'] ?? false),
            'prohibit_self_approval' => (bool) ($validated['prohibit_self_approval'] ?? false),
            'approval_policy' => $validated['approval_policy'] ?? 'single',
            'quorum_count' => $validated['quorum_count'] ?? null,
            'quorum_percentage' => $validated['quorum_percentage'] ?? null,
            'join_policy' => $validated['join_policy'] ?? null,
            'fork_state_ids' => $validated['fork_state_ids'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Workflow transition created.',
            'data' => $transition->load(['permission', 'fromState', 'toState']),
        ], 201);
    }

    /**
     * Delete a transition from a draft workflow version.
     */
    public function destroyTransition(int $id, int $versionId, int $transitionId): JsonResponse
    {
        $version = WorkflowVersion::where('definition_id', $id)->findOrFail($versionId);

        if ($version->published_at !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Published workflow versions are immutable.',
            ], 422);
        }

        $transition = WorkflowTransition::whereHas('fromState', function ($q) use ($version) {
            $q->where('workflow_version_id', $version->id);
        })->findOrFail($transitionId);

        $transition->delete();

        return response()->json([
            'success' => true,
            'message' => 'Workflow transition deleted.',
        ]);
    }

    /**
     * Validate workflow graph completeness and structural integrity.
     */
    public function validateGraph(int $id, int $versionId): JsonResponse
    {
        $version = WorkflowVersion::where('definition_id', $id)->findOrFail($versionId);
        $result = $this->evaluateGraphIntegrity($version);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Publish and activate a workflow version.
     */
    public function publishVersion(Request $request, int $id, int $versionId): JsonResponse
    {
        $version = WorkflowVersion::where('definition_id', $id)->findOrFail($versionId);

        if ($version->published_at !== null) {
            return response()->json([
                'success' => false,
                'message' => 'This workflow version is already published.',
            ], 422);
        }

        $validation = $this->evaluateGraphIntegrity($version);
        if (! $validation['valid']) {
            return response()->json([
                'success' => false,
                'message' => 'Workflow graph validation failed. Correct the structural errors before publishing.',
                'errors' => $validation['errors'],
            ], 422);
        }

        $actor = $request->user();
        $now = now()->format('Y-m-d H:i:s.u');

        DB::transaction(function () use ($version, $actor, $now) {
            $version->update([
                'published_at' => $now,
            ]);

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'workflow.version_published',
                'target_type' => 'workflow_version',
                'target_id' => (string) $version->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => [
                    'definition_id' => $version->definition_id,
                    'version_number' => $version->version_number,
                ],
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => "Workflow version {$version->version_number} successfully published and activated for future document workflows.",
            'data' => $version->fresh(),
        ]);
    }

    /**
     * Algorithm to validate graph integrity:
     * 1. Minimum 2 states (1 initial, 1 terminal)
     * 2. Exactly 1 initial state
     * 3. At least 1 terminal state
     * 4. No self-loops (from == to)
     * 5. Terminal states must not have outgoing transitions
     * 6. Every state reachable from initial state (BFS)
     * 7. Every non-terminal state must be able to reach at least one terminal state
     */
    protected function evaluateGraphIntegrity(WorkflowVersion $version): array
    {
        $states = WorkflowState::where('workflow_version_id', $version->id)->get();
        $errors = [];

        if ($states->count() < 2) {
            $errors[] = 'Workflow must have at least 2 stages (at least one initial and one terminal stage).';

            return ['valid' => false, 'errors' => $errors];
        }

        $initialStates = $states->where('is_initial', true);
        if ($initialStates->count() === 0) {
            $errors[] = 'Workflow is missing an initial stage.';
        } elseif ($initialStates->count() > 1) {
            $errors[] = 'Workflow has multiple initial stages (' . $initialStates->pluck('name')->implode(', ') . '). Exactly one initial stage is required.';
        }

        $terminalStates = $states->where('is_terminal', true);
        if ($terminalStates->count() === 0) {
            $errors[] = 'Workflow is missing a terminal stage.';
        }

        $stateIds = $states->pluck('id')->all();
        $stateMap = $states->keyBy('id');
        $transitions = WorkflowTransition::whereIn('from_state_id', $stateIds)->get();

        foreach ($transitions as $t) {
            if (! in_array($t->to_state_id, $stateIds, true)) {
                $errors[] = "Transition '{$t->action_code}' points to a non-existent stage (ID {$t->to_state_id}).";
            }
            if ($t->from_state_id === $t->to_state_id) {
                $name = $stateMap[$t->from_state_id]->name ?? "ID {$t->from_state_id}";
                $errors[] = "Circular self-loop detected: stage '{$name}' cannot transition directly to itself.";
            }
            $fromState = $stateMap[$t->from_state_id] ?? null;
            if ($fromState && $fromState->is_terminal) {
                $errors[] = "Terminal stage '{$fromState->name}' cannot have outgoing transitions.";
            }
        }

        if ($initialStates->count() === 1) {
            $initialState = $initialStates->first();

            // Build adjacency list
            $adj = [];
            foreach ($stateIds as $sid) {
                $adj[$sid] = [];
            }
            foreach ($transitions as $t) {
                if (in_array($t->to_state_id, $stateIds, true)) {
                    $adj[$t->from_state_id][] = $t->to_state_id;
                }
                if (is_array($t->fork_state_ids)) {
                    foreach ($t->fork_state_ids as $fid) {
                        if (in_array($fid, $stateIds, true)) {
                            $adj[$t->from_state_id][] = $fid;
                        }
                    }
                }
            }

            // Reachability from initial state via BFS
            $reachable = [];
            $queue = [$initialState->id];
            $reachable[$initialState->id] = true;

            while (! empty($queue)) {
                $curr = array_shift($queue);
                foreach ($adj[$curr] as $nxt) {
                    if (! isset($reachable[$nxt])) {
                        $reachable[$nxt] = true;
                        $queue[] = $nxt;
                    }
                }
            }

            foreach ($states as $s) {
                if (! isset($reachable[$s->id])) {
                    $errors[] = "Stage '{$s->name}' is unreachable from the initial stage.";
                }
            }

            // Terminal path reachability for all reachable non-terminal states
            $terminalIds = $terminalStates->pluck('id')->all();
            foreach ($states as $s) {
                if ($s->is_terminal) {
                    continue;
                }

                if (isset($reachable[$s->id])) {
                    $visited = [];
                    $q = [$s->id];
                    $visited[$s->id] = true;
                    $canReachTerminal = false;

                    while (! empty($q)) {
                        $c = array_shift($q);
                        if (in_array($c, $terminalIds, true)) {
                            $canReachTerminal = true;
                            break;
                        }
                        foreach ($adj[$c] as $nxt) {
                            if (! isset($visited[$nxt])) {
                                $visited[$nxt] = true;
                                $q[] = $nxt;
                            }
                        }
                    }

                    if (! $canReachTerminal) {
                        $errors[] = "Stage '{$s->name}' cannot reach any terminal stage (dead-end or circular trap).";
                    }
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
