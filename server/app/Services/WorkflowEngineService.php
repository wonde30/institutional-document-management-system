<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\NotificationEvent;
use App\Models\ProjectProposal;
use App\Models\ResearchRecord;
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
use Carbon\Carbon;
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
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send workflow task notification', ['error' => $e->getMessage()]);
            }

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
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Failed to create workflow task delegated notification', ['error' => $e->getMessage()]);
            }

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
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Failed to create workflow task reassigned notification', ['error' => $e->getMessage()]);
            }

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
                'submit', 'resubmit' => 'submit',
                'review', 'start_review' => 'start_review',
                default => 'cancel',
            };

            $transition = WorkflowTransition::where('from_state_id', $task->state_id)
                ->where(function ($tq) use ($actionCode, $decision) {
                    $tq->where('action_code', $actionCode)->orWhere('action_code', $decision);
                })
                ->first();

            $transitionsExist = WorkflowTransition::where('from_state_id', $task->state_id)->exists();

            if (! $transition && $transitionsExist) {
                throw new HttpException(422, "Invalid workflow transition: action '{$decision}' is not permitted from current state.");
            }

            // Anti-Self-Approval Enforcement (INV-04)
            // Default-deny: approve decisions block self-approval unless explicitly configured otherwise
            $doc = $task->instance->documentVersion?->document;
            $ver = $task->instance->documentVersion;

            if ($decision === 'approve' && $doc && $ver) {
                // Prohibit if flag is missing (null) or explicitly true; allow only if explicitly false
                $prohibit = $transition?->prohibit_self_approval ?? true;
                if ($prohibit) {
                    if ($actor->id === $doc->created_by || $actor->id === $ver->created_by || $actor->id === $task->instance->started_by) {
                        throw new HttpException(422, 'Anti-self-approval violation: Authors and submitting actors cannot approve their own documents or versions.');
                    }
                }
            }

            // Research and Project Separation of Duty Enforcement across task decisions
            if ($doc) {
                $researchRecord = ResearchRecord::where('document_id', $doc->id)->first();
                if ($researchRecord) {
                    $submitterId = (int) ($researchRecord->submitted_by ?? $researchRecord->recorded_by ?? $doc->created_by ?? 0);
                    $staffId = $actor->staffProfile?->id;
                    $isAuthor = ((int) $doc->created_by === (int) $actor->id)
                        || ((int) $researchRecord->recorded_by === (int) $actor->id)
                        || ($staffId && $researchRecord->authors()->where('staff_id', $staffId)->exists());

                    if (in_array($decision, ['review', 'start_review', 'approve', 'reject', 'return'], true)) {
                        if ((int) $actor->id === $submitterId || $isAuthor) {
                            throw new HttpException(422, 'Prohibited: Submitter and authors cannot review, approve, reject, or return their own research output.');
                        }
                    }

                    if ($decision === 'approve' && is_null($researchRecord->submitted_by)) {
                        throw new HttpException(422, 'Prohibited: Cannot approve research output with unrecorded or null submitted_by.');
                    }

                    $reviewerId = (int) ($researchRecord->reviewed_by ?? 0);
                    if ($decision === 'approve' && $reviewerId && (int) $actor->id === $reviewerId) {
                        throw new HttpException(422, 'Prohibited: Reviewer cannot approve the same research output.');
                    }

                    if (in_array($decision, ['reject', 'return'], true) && empty($reason)) {
                        throw new HttpException(422, 'A valid reason or remarks must be provided when rejecting or returning research.');
                    }
                }

                $projectProposal = ProjectProposal::where('document_id', $doc->id)->first();
                if ($projectProposal) {
                    $submitterId = (int) ($projectProposal->submitted_by ?? $projectProposal->recorded_by ?? $doc->created_by ?? 0);
                    $staffId = $actor->staffProfile?->id;
                    $isAuthor = ((int) $doc->created_by === (int) $actor->id)
                        || ((int) $projectProposal->recorded_by === (int) $actor->id)
                        || ($staffId && DB::table('project_proposal_members')->where('proposal_id', $projectProposal->id)->where('staff_id', $staffId)->exists());

                    if (in_array($decision, ['review', 'start_review', 'approve', 'reject', 'return'], true)) {
                        if ((int) $actor->id === $submitterId || $isAuthor) {
                            throw new HttpException(422, 'Prohibited: Submitter and proposal members cannot review, approve, reject, or return their own proposal.');
                        }
                    }

                    if ($decision === 'approve' && is_null($projectProposal->submitted_by)) {
                        throw new HttpException(422, 'Prohibited: Cannot approve project proposal with unrecorded or null submitted_by.');
                    }

                    $reviewerId = (int) ($projectProposal->reviewed_by ?? 0);
                    if ($decision === 'approve' && $reviewerId && (int) $actor->id === $reviewerId) {
                        throw new HttpException(422, 'Prohibited: Reviewer cannot approve the same project proposal.');
                    }

                    if (in_array($decision, ['reject', 'return'], true) && empty($reason)) {
                        throw new HttpException(422, 'A valid reason or remarks must be provided when rejecting or returning a project proposal.');
                    }
                }
            }

            // 4. Authorization check
            $isAssigned = $task->assigned_user_id === $actor->id || $task->delegated_to_user_id === $actor->id;
            $directParticipant = WorkflowTaskParticipant::where('task_id', $task->id)
                ->where('user_id', $actor->id)
                ->first();

            // Resolve represented participant seat if actor is acting as an authorized delegate
            $representedParticipant = null;
            if (! $directParticipant && $task->delegated_to_user_id === $actor->id) {
                $delegatorId = $task->delegated_by ?? $task->assigned_user_id;
                if ($delegatorId) {
                    $representedParticipant = WorkflowTaskParticipant::where('task_id', $task->id)
                        ->where('user_id', $delegatorId)
                        ->first();
                }
            }

            $effectiveParticipant = $directParticipant ?? $representedParticipant;

            $allParticipants = WorkflowTaskParticipant::where('task_id', $task->id)
                ->whereIn('participation_role', ['lead_reviewer', 'reviewer'])
                ->get();
            $totalParticipantCount = $allParticipants->count();
            $participantUserIds = $allParticipants->pluck('user_id')->all();

            $unitId = $task->assigned_unit_id;
            $hasPerm = $actor->hasPermission('workflow.review', $unitId)
                || $actor->hasPermission('workflow.review')
                || $actor->can('workflow.review')
                || ($decision === 'submit' && ($actor->hasPermission('research.submit', $unitId) || $actor->hasPermission('research.submit') || $actor->hasPermission('projects.submit', $unitId) || $actor->hasPermission('projects.submit') || $actor->hasPermission('documents.create', $unitId)))
                || ($decision === 'approve' && ($actor->hasPermission('research.approve', $unitId) || $actor->hasPermission('research.approve') || $actor->hasPermission('projects.approve', $unitId) || $actor->hasPermission('projects.approve')))
                || (in_array($decision, ['review', 'start_review'], true) && ($actor->hasPermission('research.review', $unitId) || $actor->hasPermission('research.review') || $actor->hasPermission('projects.review', $unitId) || $actor->hasPermission('projects.review')))
                || (in_array($decision, ['reject', 'return'], true) && ($actor->hasPermission('research.reject', $unitId) || $actor->hasPermission('research.reject') || $actor->hasPermission('projects.reject', $unitId) || $actor->hasPermission('projects.reject') || $actor->hasPermission('research.review', $unitId) || $actor->hasPermission('projects.review', $unitId)));

            if (! $isAssigned && ! $effectiveParticipant && ! $hasPerm) {
                throw new HttpException(403, 'Forbidden: You are not authorized to decide this workflow task.');
            }

            // Committee restriction: if multiple participants are assigned, only registered participants or delegates can vote
            if ($totalParticipantCount > 1 && ! $effectiveParticipant) {
                throw new HttpException(403, 'Forbidden: You are not a designated participant or delegate on this committee task.');
            }

            // Prevent duplicate voting by the seat/participant
            if ($effectiveParticipant && $effectiveParticipant->status === 'completed') {
                throw new HttpException(422, 'Participant has already submitted a decision for this task.');
            }

            // 5. Update participant status if registered
            if ($effectiveParticipant) {
                $effectiveParticipant->update([
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
                    'participant_id' => $effectiveParticipant?->id,
                    'represented_user_id' => $effectiveParticipant?->user_id ?? $actor->id,
                ],
            ]);

            $policy = $transition?->approval_policy ?? 'single';

            // 8. Consensus Evaluation
            $allParticipants = WorkflowTaskParticipant::where('task_id', $task->id)
                ->whereIn('participation_role', ['lead_reviewer', 'reviewer'])
                ->get();
            $totalParticipantCount = $allParticipants->count();

            // NOTE: Deduplicating votes by seat (participant_id or represented_user_id) ensures
            // committee quorum cannot be forged by replay or duplicate delegation.
            $pastVotes = AuditEvent::where('action', 'workflow.task_participant_vote')
                ->where('target_type', 'workflow_task')
                ->where('target_id', (string) $task->id)
                ->orderBy('occurred_at')
                ->get();

            $latestVotesBySeat = [];
            foreach ($pastVotes as $v) {
                $details = $v->details_json;
                if (! is_array($details)) {
                    continue;
                }

                // Strict task & instance scoping
                if (($details['task_id'] ?? null) != $task->id || ($details['instance_id'] ?? null) != $task->instance_id) {
                    continue;
                }

                $vt = $details['vote'] ?? null;
                $representedUserId = $details['represented_user_id'] ?? $v->actor_id;
                $seatKey = $details['participant_id'] ?? $representedUserId;

                if ($vt && ($totalParticipantCount <= 1 || in_array($representedUserId, $participantUserIds, true))) {
                    $latestVotesBySeat[$seatKey] = $vt;
                }
            }

            $approvals = 0;
            $rejections = 0;
            $returns = 0;

            foreach ($latestVotesBySeat as $seatKey => $vt) {
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
            $dbDecision = in_array($finalOutcome, ['approve', 'reject', 'return', 'cancel'], true)
                ? $finalOutcome
                : 'approve';

            $taskDecision = WorkflowTaskDecision::create([
                'task_id' => $task->id,
                'decided_by' => $actor->id,
                'decision' => $dbDecision,
                'reason' => $reason,
                'decided_at' => $now,
            ]);

            $closedAt = Carbon::parse($now)->lt(Carbon::parse($task->created_at)) ? $task->created_at : $now;
            $task->update(['closed_at' => $closedAt]);

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

            WorkflowDecisionEvent::firstOrCreate([
                'decision_id' => $taskDecision->id,
            ], [
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
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send workflow decision notification', ['error' => $e->getMessage()]);
            }

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
                            } catch (\Throwable $e) {
                                \Illuminate\Support\Facades\Log::error('Failed to send workflow task notification', ['error' => $e->getMessage()]);
                            }
                        }
                    }
                } elseif ($transition->to_state_id) {
                    $toState = WorkflowState::find($transition->to_state_id);
                    if ($toState && ! $toState->is_terminal) {
                        $targetUnitId = $toState->assigned_unit_id ?? ($task->assigned_unit_id ?? $task->instance->documentVersion->document->owner_unit_id);
                        $targetDueAt = $toState->sla_hours
                            ? now()->addHours((int) $toState->sla_hours)->format('Y-m-d H:i:s.u')
                            : now()->addDays(7)->format('Y-m-d H:i:s.u');

                        $assignedUser = ($toState->code === 'under_review') ? $actor->id : null;

                        $newTask = WorkflowTask::create([
                            'instance_id' => $task->instance_id,
                            'state_id' => $toState->id,
                            'assigned_user_id' => $assignedUser,
                            'assigned_unit_id' => $targetUnitId,
                            'required_role_id' => $toState->assigned_role_id,
                            'created_at' => $now,
                            'due_at' => $targetDueAt,
                        ]);

                        try {
                            app(\App\Services\EmailNotificationService::class)->notifyWorkflowTask($newTask);
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::error('Failed to send workflow task notification', ['error' => $e->getMessage()]);
                        }
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

                        // Ensure no open tasks remain on this workflow instance
                        WorkflowTask::where('instance_id', $task->instance_id)
                            ->whereNull('closed_at')
                            ->update(['closed_at' => $closedAt]);

                        // Synchronize terminal workflow state with parent document lifecycle and audit trail
                        $parentDoc = $task->instance->documentVersion?->document;
                        if ($parentDoc) {
                            $isRejection = $finalOutcome === 'reject' || str_contains(strtolower($toState->code), 'reject');
                            $isRetirement = str_contains(strtolower($toState->code), 'retire') || str_contains(strtolower($toState->code), 'archive');

                            if ($isRetirement) {
                                $parentDoc->update(['lifecycle' => 'retired']);
                            }

                            AuditEvent::create([
                                'actor_id' => $actor->id,
                                'action' => $isRejection ? 'document.workflow_rejected' : 'document.workflow_approved',
                                'target_type' => 'document',
                                'target_id' => (string) $parentDoc->id,
                                'request_id' => (string) Str::uuid(),
                                'occurred_at' => $now,
                                'details_json' => [
                                    'workflow_instance_id' => $task->instance_id,
                                    'state_code' => $toState->code,
                                    'state_name' => $toState->name,
                                    'final_outcome' => $finalOutcome,
                                    'lifecycle' => $parentDoc->fresh()->lifecycle,
                                ],
                            ]);
                        }
                    }

                    // Synchronize state and decision columns on associated ResearchRecord or ProjectProposal
                    $parentDoc = $task->instance->documentVersion?->document;
                    if ($parentDoc) {
                        $targetStatusCode = $toState?->code ?? $finalOutcome;
                        $rRecord = ResearchRecord::where('document_id', $parentDoc->id)->first();
                        if ($rRecord) {
                            $rUpdate = ['workflow_status' => $targetStatusCode];
                            if ($targetStatusCode === 'submitted') {
                                $rUpdate['submitted_by'] = $actor->id;
                                $rUpdate['submitted_at'] = $now;
                            } elseif ($targetStatusCode === 'under_review') {
                                $rUpdate['reviewed_by'] = $actor->id;
                            } elseif ($targetStatusCode === 'approved') {
                                $rUpdate['approved_by'] = $actor->id;
                                if ($reason) { $rUpdate['decision_remarks'] = $reason; }
                            } elseif ($targetStatusCode === 'rejected') {
                                $rUpdate['rejected_by'] = $actor->id;
                                $rUpdate['decision_remarks'] = $reason;
                            } elseif ($targetStatusCode === 'returned') {
                                $rUpdate['decision_remarks'] = $reason;
                            }
                            $rRecord->update($rUpdate);
                        }

                        $pProposal = ProjectProposal::where('document_id', $parentDoc->id)->first();
                        if ($pProposal) {
                            $pUpdate = ['workflow_status' => $targetStatusCode];
                            if ($targetStatusCode === 'submitted') {
                                $pUpdate['submitted_by'] = $actor->id;
                                $pUpdate['submitted_at'] = $now;
                            } elseif ($targetStatusCode === 'under_review') {
                                $pUpdate['reviewed_by'] = $actor->id;
                            } elseif ($targetStatusCode === 'approved') {
                                $pUpdate['approved_by'] = $actor->id;
                                if ($reason) { $pUpdate['decision_remarks'] = $reason; }
                            } elseif ($targetStatusCode === 'rejected') {
                                $pUpdate['rejected_by'] = $actor->id;
                                $pUpdate['decision_remarks'] = $reason;
                            } elseif ($targetStatusCode === 'returned') {
                                $pUpdate['decision_remarks'] = $reason;
                            }
                            $pProposal->update($pUpdate);
                        }
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
