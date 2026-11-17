<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\NotificationEvent;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\WorkflowDecisionEvent;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowEvent;
use App\Models\WorkflowInstance;
use App\Models\WorkflowState;
use App\Models\WorkflowTask;
use App\Models\WorkflowTaskDecision;
use App\Models\WorkflowTaskParticipant;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WorkflowEngineService
{
    /**
     * Start a new workflow instance on a document version.
     */
    public function startInstance(
        int $definitionId,
        int $documentVersionId,
        ?int $parentInstanceId,
        User $actor
    ): WorkflowInstance {
        $now = now()->format('Y-m-d H:i:s.u');

        return DB::transaction(function () use ($definitionId, $documentVersionId, $parentInstanceId, $actor, $now) {
            $version = WorkflowVersion::where('definition_id', $definitionId)
                ->whereNotNull('published_at')
                ->orderByDesc('version_number')
                ->first();

            if (! $version) {
                $version = WorkflowVersion::where('definition_id', $definitionId)
                    ->orderByDesc('version_number')
                    ->firstOrFail();
            }

            $initialState = WorkflowState::where('workflow_version_id', $version->id)
                ->where('is_initial', 1)
                ->firstOrFail();

            $instance = WorkflowInstance::create([
                'document_version_id' => $documentVersionId,
                'workflow_version_id' => $version->id,
                'parent_instance_id' => $parentInstanceId,
                'started_by' => $actor->id,
                'started_at' => $now,
            ]);

            // Create initial start event
            $startEvent = WorkflowEvent::create([
                'instance_id' => $instance->id,
                'sequence_number' => 1,
                'transition_id' => null,
                'actor_id' => $actor->id,
                'event_kind' => 'start',
                'reason' => 'Workflow instance initiated',
                'occurred_at' => $now,
                'idempotency_key' => (string) Str::uuid(),
            ]);

            // Spawn initial task for initial state
            $docVersion = DocumentVersion::with('document')->find($documentVersionId);
            $ownerUnitId = $docVersion?->document?->owner_unit_id;
            $assignedUnitId = $initialState->assigned_unit_id ?? $ownerUnitId;
            $dueAt = $initialState->sla_hours
                ? now()->addHours((int) $initialState->sla_hours)->format('Y-m-d H:i:s.u')
                : now()->addDays(7)->format('Y-m-d H:i:s.u');

            $task = WorkflowTask::create([
                'instance_id' => $instance->id,
                'state_id' => $initialState->id,
                'assigned_user_id' => $assignedUnitId ? null : $actor->id,
                'assigned_unit_id' => $assignedUnitId,
                'required_role_id' => $initialState->assigned_role_id,
                'created_at' => $now,
                'due_at' => $dueAt,
            ]);

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'workflow.started',
                'target_type' => 'workflow_instance',
                'target_id' => (string) $instance->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => [
                    'definition_id' => $definitionId,
                    'document_version_id' => $documentVersionId,
                    'initial_task_id' => $task->id,
                ],
            ]);

            try {
                app(\App\Services\EmailNotificationService::class)->notifyWorkflowTask($task);
            } catch (\Throwable $e) {}

            return $instance;
        });
    }

    /**
     * Add participants (e.g. committee members) to a workflow task.
     *
     * @param array<int, array{user_id: int, role?: string}> $participants
     */
    public function assignParticipants(WorkflowTask $task, array $participants): void
    {
        $now = now()->format('Y-m-d H:i:s.u');

        foreach ($participants as $p) {
            $userId = (int) $p['user_id'];
            $role = $p['role'] ?? 'reviewer';

            WorkflowTaskParticipant::updateOrCreate(
                ['task_id' => $task->id, 'user_id' => $userId],
                [
                    'participation_role' => in_array($role, ['lead_reviewer', 'reviewer', 'contributor', 'observer'], true) ? $role : 'reviewer',
                    'status' => 'pending',
                    'assigned_at' => $now,
                ]
            );
        }
    }

    /**
     * Delegate a workflow task to another user.
     */
    public function delegateTask(WorkflowTask $task, int $targetUserId, ?string $reason, User $actor): WorkflowTask
    {
        $now = now()->format('Y-m-d H:i:s.u');

        return DB::transaction(function () use ($task, $targetUserId, $reason, $actor, $now) {
            $task->update([
                'delegated_to_user_id' => $targetUserId,
                'delegated_by' => $actor->id,
                'delegation_reason' => $reason,
            ]);

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'workflow.task_delegated',
                'target_type' => 'workflow_task',
                'target_id' => (string) $task->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => [
                    'task_id' => $task->id,
                    'delegated_to_user_id' => $targetUserId,
                    'reason' => $reason,
                ],
            ]);

            try {
                $docId = $task->instance?->documentVersion?->document_id;
                $notifEvent = NotificationEvent::create([
                    'type_code' => 'workflow.task_delegated',
                    'document_id' => $docId,
                    'actor_id' => $actor->id,
                    'occurred_at' => $now,
                ]);
                UserNotification::create([
                    'event_id' => $notifEvent->id,
                    'user_id' => $targetUserId,
                    'created_at' => $now,
                ]);
            } catch (\Throwable $e) {}

            return $task;
        });
    }

    /**
     * Reassign a workflow task to a new user.
     */
    public function reassignTask(WorkflowTask $task, int $targetUserId, ?string $reason, User $actor): WorkflowTask
    {
        $now = now()->format('Y-m-d H:i:s.u');

        return DB::transaction(function () use ($task, $targetUserId, $reason, $actor, $now) {
            $previousUserId = $task->assigned_user_id;
            $task->update([
                'assigned_user_id' => $targetUserId,
            ]);

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'workflow.task_reassigned',
                'target_type' => 'workflow_task',
                'target_id' => (string) $task->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => [
                    'task_id' => $task->id,
                    'previous_user_id' => $previousUserId,
                    'target_user_id' => $targetUserId,
                    'reason' => $reason,
                ],
            ]);

            try {
                $docId = $task->instance?->documentVersion?->document_id;
                $notifEvent = NotificationEvent::create([
                    'type_code' => 'workflow.task_reassigned',
                    'document_id' => $docId,
                    'actor_id' => $actor->id,
                    'occurred_at' => $now,
                ]);
                UserNotification::create([
                    'event_id' => $notifEvent->id,
                    'user_id' => $targetUserId,
                    'created_at' => $now,
                ]);
            } catch (\Throwable $e) {}

            return $task;
        });
    }

    /**
     * Process a decision or vote on a workflow task with consensus evaluation.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function processDecision(WorkflowTask $taskInput, array $payload, User $actor): array
    {
        $decision = $payload['decision']; // approve, reject, return, cancel
        $reason = $payload['reason'] ?? null;
        $idempotencyKey = $payload['idempotency_key'] ?? (string) Str::uuid();
        $now = now()->format('Y-m-d H:i:s.u');

        return DB::transaction(function () use ($taskInput, $decision, $reason, $idempotencyKey, $actor, $now, $payload) {
            // 1. Lock task row for concurrency safety
            $task = WorkflowTask::with(['instance.documentVersion.document', 'state', 'participants'])
                ->where('id', $taskInput->id)
                ->lockForUpdate()
                ->firstOrFail();

            // 2. Check if already decided with this idempotency key
            $existingEvent = WorkflowEvent::where('instance_id', $task->instance_id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingEvent) {
                return [
                    'status' => 'idempotent_duplicate',
                    'message' => 'Decision already processed with this idempotency key.',
                    'task_closed' => $task->closed_at !== null,
                    'event' => $existingEvent,
                ];
            }

            if ($task->closed_at !== null) {
                throw new HttpException(422, 'Workflow task has already been completed and closed.');
            }

            // 3. Find candidate transition for this decision
            $actionCode = match ($decision) {
                'approve' => 'approve',
                'reject' => 'reject',
                'return' => 'return',
                default => 'cancel',
            };

            $transition = WorkflowTransition::where('from_state_id', $task->state_id)
                ->where('action_code', $actionCode)
                ->first();

            if (! $transition) {
                // Fallback: any transition originating from this state
                $transition = WorkflowTransition::where('from_state_id', $task->state_id)->first();
            }

            // Anti-Self-Approval Enforcement (INV-04)
            $doc = $task->instance->documentVersion?->document;
            $ver = $task->instance->documentVersion;

            if ($decision === 'approve' && $transition && $transition->prohibit_self_approval && $doc && $ver) {
                if ($actor->id === $doc->created_by || $actor->id === $ver->created_by || $actor->id === $task->instance->started_by) {
                    throw new HttpException(422, 'Anti-self-approval violation: Authors and submitting actors cannot approve their own documents or versions.');
                }
            }

            // 4. Authorization check
            $isAssigned = $task->assigned_user_id === $actor->id || $task->delegated_to_user_id === $actor->id;
            $participant = WorkflowTaskParticipant::where('task_id', $task->id)
                ->where('user_id', $actor->id)
                ->first();

            $hasPerm = $actor->hasPermission('workflow.review', $task->assigned_unit_id)
                || $actor->hasPermission('workflow.review')
                || $actor->can('workflow.review');

            if (! $isAssigned && ! $participant && ! $hasPerm) {
                throw new HttpException(403, 'Forbidden: You are not authorized to decide this workflow task.');
            }

            // 5. Update participant status if registered
            if ($participant) {
                $participant->update([
                    'status' => 'completed',
                    'responded_at' => $now,
                ]);
            }

            // 6. Record vote in audit ledger
            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'workflow.task_participant_vote',
                'target_type' => 'workflow_task',
                'target_id' => (string) $task->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => [
                    'task_id' => $task->id,
                    'instance_id' => $task->instance_id,
                    'vote' => $decision,
                    'reason' => $reason,
                    'user_id' => $actor->id,
                ],
            ]);

            $policy = $transition?->approval_policy ?? 'single';

            // 8. Consensus Evaluation
            $allParticipants = WorkflowTaskParticipant::where('task_id', $task->id)
                ->whereIn('participation_role', ['lead_reviewer', 'reviewer'])
                ->get();
            $totalParticipantCount = $allParticipants->count();

            // Fetch all cast votes from audit ledger for this task
            $pastVotes = AuditEvent::where('action', 'workflow.task_participant_vote')
                ->where('target_type', 'workflow_task')
                ->where('target_id', (string) $task->id)
                ->get();

            $approvals = 0;
            $rejections = 0;
            $returns = 0;

            foreach ($pastVotes as $v) {
                $details = $v->details_json;
                $vt = $details['vote'] ?? null;
                if ($vt === 'approve') {
                    $approvals++;
                } elseif ($vt === 'reject') {
                    $rejections++;
                } elseif ($vt === 'return') {
                    $returns++;
                }
            }

            $consensusReached = false;
            $finalOutcome = null;

            if ($totalParticipantCount <= 1 || $policy === 'single') {
                $consensusReached = true;
                $finalOutcome = $decision;
            } elseif ($policy === 'all_required') {
                if ($rejections > 0) {
                    $consensusReached = true;
                    $finalOutcome = 'reject';
                } elseif ($returns > 0) {
                    $consensusReached = true;
                    $finalOutcome = 'return';
                } elseif ($approvals >= $totalParticipantCount) {
                    $consensusReached = true;
                    $finalOutcome = 'approve';
                }
            } elseif ($policy === 'any_required') {
                if ($approvals > 0) {
                    $consensusReached = true;
                    $finalOutcome = 'approve';
                } elseif ($rejections >= $totalParticipantCount) {
                    $consensusReached = true;
                    $finalOutcome = 'reject';
                }
            } elseif ($policy === 'majority') {
                $half = $totalParticipantCount / 2;
                if ($approvals > $half) {
                    $consensusReached = true;
                    $finalOutcome = 'approve';
                } elseif ($rejections >= $half) {
                    $consensusReached = true;
                    $finalOutcome = 'reject';
                }
            } elseif ($policy === 'quorum') {
                $totalVotes = $approvals + $rejections + $returns;
                $quorumCount = $transition?->quorum_count ?? 1;
                $quorumPct = (float) ($transition?->quorum_percentage ?? 0);

                $countMet = $totalVotes >= $quorumCount;
                $pctMet = $totalParticipantCount > 0 ? (($totalVotes / $totalParticipantCount) * 100) >= $quorumPct : true;

                if ($countMet && $pctMet) {
                    if ($approvals > $rejections) {
                        $consensusReached = true;
                        $finalOutcome = 'approve';
                    } elseif ($rejections >= $approvals) {
                        $consensusReached = true;
                        $finalOutcome = 'reject';
                    }
                }
            }

            // If consensus not reached, return intermediate state
            if (! $consensusReached) {
                return [
                    'status' => 'pending_consensus',
                    'message' => "Vote recorded. Awaiting remaining committee members ({$approvals} approved, {$rejections} rejected).",
                    'approvals' => $approvals,
                    'rejections' => $rejections,
                    'total_participants' => $totalParticipantCount,
                    'task_closed' => false,
                ];
            }

            // 9. Finalize Task Decision and Advance Workflow
            $finalOutcome = $finalOutcome ?? $decision;

            $taskDecision = WorkflowTaskDecision::create([
                'task_id' => $task->id,
                'decided_by' => $actor->id,
                'decision' => $finalOutcome,
                'reason' => $reason,
                'decided_at' => $now,
            ]);

            $task->update(['closed_at' => $now]);

            $latestSeq = WorkflowEvent::where('instance_id', $task->instance_id)->max('sequence_number') ?? 0;
            $nextSeq = $latestSeq + 1;

            $workflowEvent = WorkflowEvent::create([
                'instance_id' => $task->instance_id,
                'sequence_number' => $nextSeq,
                'transition_id' => $transition?->id,
                'actor_id' => $actor->id,
                'event_kind' => $transition ? 'transition' : ($finalOutcome === 'cancel' ? 'cancel' : 'start'),
                'reason' => $reason,
                'occurred_at' => $now,
                'idempotency_key' => $idempotencyKey,
            ]);

            WorkflowDecisionEvent::create([
                'decision_id' => $taskDecision->id,
                'event_id' => $workflowEvent->id,
            ]);

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'workflow.task_decided',
                'target_type' => 'workflow_task',
                'target_id' => (string) $task->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => [
                    'decision' => $finalOutcome,
                    'reason' => $reason,
                    'instance_id' => $task->instance_id,
                    'state_id' => $task->state_id,
                ],
            ]);

            // Notify initiator or stakeholders of task decision
            try {
                app(\App\Services\EmailNotificationService::class)->notifyWorkflowDecision($task->instance, $task, $finalOutcome);
            } catch (\Throwable $e) {}

            // Check if task is part of a parallel branch set
            $shouldAdvance = true;
            if ($task->branch_key !== null) {
                $siblingTasks = WorkflowTask::where('instance_id', $task->instance_id)
                    ->whereNotNull('branch_key')
                    ->where('id', '!=', $task->id)
                    ->get();

                $joinPolicy = $transition?->join_policy ?? 'all_branches';

                if ($joinPolicy === 'all_branches') {
                    $pendingSiblings = $siblingTasks->whereNull('closed_at');
                    if ($pendingSiblings->isNotEmpty()) {
                        // Sibling branches still active: wait for all branches to complete
                        $shouldAdvance = false;

                        AuditEvent::create([
                            'actor_id' => $actor->id,
                            'action' => 'workflow.branch_completed',
                            'target_type' => 'workflow_task',
                            'target_id' => (string) $task->id,
                            'request_id' => (string) Str::uuid(),
                            'occurred_at' => $now,
                            'details_json' => [
                                'branch_key' => $task->branch_key,
                                'pending_branches' => $pendingSiblings->pluck('branch_key')->values()->all(),
                                'join_policy' => 'all_branches',
                            ],
                        ]);

                        return [
                            'status' => 'branch_completed',
                            'message' => "Parallel branch '{$task->branch_key}' decided. Awaiting remaining parallel branches.",
                            'task_closed' => true,
                            'branch_key' => $task->branch_key,
                            'pending_sibling_branches' => $pendingSiblings->pluck('branch_key')->values()->all(),
                            'final_decision' => $taskDecision,
                            'event' => $workflowEvent,
                        ];
                    }

                    // All branches are closed: parallel join satisfied!
                    AuditEvent::create([
                        'actor_id' => $actor->id,
                        'action' => 'workflow.parallel_join_satisfied',
                        'target_type' => 'workflow_instance',
                        'target_id' => (string) $task->instance_id,
                        'request_id' => (string) Str::uuid(),
                        'occurred_at' => $now,
                        'details_json' => [
                            'join_policy' => 'all_branches',
                            'completed_branch' => $task->branch_key,
                        ],
                    ]);
                } elseif ($joinPolicy === 'any_branch') {
                    // First branch satisfied the join: cancel/supersede remaining active siblings
                    $openSiblings = $siblingTasks->whereNull('closed_at');
                    foreach ($openSiblings as $sibling) {
                        $sibling->update(['closed_at' => $now]);
                        WorkflowTaskDecision::create([
                            'task_id' => $sibling->id,
                            'decided_by' => $actor->id,
                            'decision' => 'cancel',
                            'reason' => "Parallel branch cancelled: branch '{$task->branch_key}' satisfied join policy 'any_branch'",
                            'decided_at' => $now,
                        ]);
                    }

                    AuditEvent::create([
                        'actor_id' => $actor->id,
                        'action' => 'workflow.parallel_join_satisfied',
                        'target_type' => 'workflow_instance',
                        'target_id' => (string) $task->instance_id,
                        'request_id' => (string) Str::uuid(),
                        'occurred_at' => $now,
                        'details_json' => [
                            'join_policy' => 'any_branch',
                            'triggering_branch' => $task->branch_key,
                            'superseded_tasks' => $openSiblings->pluck('id')->all(),
                        ],
                    ]);
                }
            }

            // Advance state if shouldAdvance is true
            if ($shouldAdvance && $transition) {
                // Check if transition forks into parallel states
                $forkStateIds = is_array($transition->fork_state_ids) ? $transition->fork_state_ids : [];

                if (! empty($forkStateIds)) {
                    foreach ($forkStateIds as $fIndex => $forkStateId) {
                        $fState = WorkflowState::find($forkStateId);
                        if ($fState && ! $fState->is_terminal) {
                            $branchKey = 'branch_' . ($fState->code ?? "b{$fIndex}_{$forkStateId}");
                            $fUnitId = $fState->assigned_unit_id ?? ($task->assigned_unit_id ?? $task->instance->documentVersion->document->owner_unit_id);
                            $fDueAt = $fState->sla_hours
                                ? now()->addHours((int) $fState->sla_hours)->format('Y-m-d H:i:s.u')
                                : now()->addDays(7)->format('Y-m-d H:i:s.u');

                            $newTask = WorkflowTask::create([
                                'instance_id' => $task->instance_id,
                                'state_id' => $fState->id,
                                'branch_key' => $branchKey,
                                'assigned_user_id' => null,
                                'assigned_unit_id' => $fUnitId,
                                'required_role_id' => $fState->assigned_role_id,
                                'created_at' => $now,
                                'due_at' => $fDueAt,
                            ]);

                            try {
                                app(\App\Services\EmailNotificationService::class)->notifyWorkflowTask($newTask);
                            } catch (\Throwable $e) {}
                        }
                    }
                } elseif ($transition->to_state_id) {
                    $toState = WorkflowState::find($transition->to_state_id);
                    if ($toState && ! $toState->is_terminal) {
                        $targetUnitId = $toState->assigned_unit_id ?? ($task->assigned_unit_id ?? $task->instance->documentVersion->document->owner_unit_id);
                        $targetDueAt = $toState->sla_hours
                            ? now()->addHours((int) $toState->sla_hours)->format('Y-m-d H:i:s.u')
                            : now()->addDays(7)->format('Y-m-d H:i:s.u');

                        $newTask = WorkflowTask::create([
                            'instance_id' => $task->instance_id,
                            'state_id' => $toState->id,
                            'assigned_user_id' => null,
                            'assigned_unit_id' => $targetUnitId,
                            'required_role_id' => $toState->assigned_role_id,
                            'created_at' => $now,
                            'due_at' => $targetDueAt,
                        ]);

                        try {
                            app(\App\Services\EmailNotificationService::class)->notifyWorkflowTask($newTask);
                        } catch (\Throwable $e) {}
                    } elseif ($toState && $toState->is_terminal) {
                        AuditEvent::create([
                            'actor_id' => $actor->id,
                            'action' => 'workflow.terminal_state_reached',
                            'target_type' => 'workflow_instance',
                            'target_id' => (string) $task->instance_id,
                            'request_id' => (string) Str::uuid(),
                            'occurred_at' => $now,
                            'details_json' => [
                                'state_id' => $toState->id,
                                'state_code' => $toState->code,
                                'state_name' => $toState->name,
                                'final_outcome' => $finalOutcome,
                                'document_version_id' => $task->instance->document_version_id,
                            ],
                        ]);
                    }
                }
            }

            // If decision is return, check if parent revision instance needs to be linked
            if ($finalOutcome === 'return' && ($payload['spawn_revision'] ?? false)) {
                $revisionInstance = $this->startInstance(
                    $task->instance->workflowVersion->definition_id,
                    $task->instance->document_version_id,
                    $task->instance->id,
                    $actor
                );
            }

            return [
                'status' => 'completed',
                'message' => "Workflow task finalized with decision '{$finalOutcome}'.",
                'task_closed' => true,
                'final_decision' => $taskDecision,
                'event' => $workflowEvent,
            ];
        });
    }
}
