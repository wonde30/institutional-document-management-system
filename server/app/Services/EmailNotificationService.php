<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\AssignmentNotificationMail;
use App\Mail\OverdueEscalationMail;
use App\Mail\WorkflowDecisionNotificationMail;
use App\Mail\WorkflowTaskNotificationMail;
use App\Models\ArchiveEntry;
use App\Models\DocumentAssignment;
use App\Models\DocumentMovement;
use App\Models\NotificationEvent;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTask;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailNotificationService
{
    /**
     * Notify assigned user of an assignment.
     */
    public function notifyAssignment(DocumentAssignment $assignment, string $actionType = 'assigned'): void
    {
        // 1. In-app database notification
        try {
            if ($assignment->assigned_user_id) {
                $now = now()->format('Y-m-d H:i:s.u');
                $event = \App\Models\NotificationEvent::create([
                    'type_code' => 'document.assigned',
                    'document_id' => $assignment->document_id,
                    'actor_id' => $assignment->assigned_by,
                    'occurred_at' => $now,
                ]);
                \App\Models\UserNotification::create([
                    'event_id' => $event->id,
                    'user_id' => $assignment->assigned_user_id,
                    'created_at' => $now,
                ]);
            }
        } catch (Throwable $e) {
            Log::warning("Failed to record assignment in-app notification: " . $e->getMessage());
        }

        // 2. Email notification
        try {
            $user = $assignment->assignedToUser;
            if (! $user || empty($user->email)) {
                return;
            }

            Mail::to($user->email)->queue(new AssignmentNotificationMail($assignment, $user, $actionType));
        } catch (Throwable $e) {
            Log::warning("Failed to queue assignment notification email: " . $e->getMessage(), [
                'assignment_id' => $assignment->id,
                'action_type' => $actionType,
            ]);
        }
    }

    /**
     * Notify assigned user of a workflow task.
     */
    public function notifyWorkflowTask(WorkflowTask $task): void
    {
        // 1. In-app database notification
        try {
            $userId = $task->assigned_user_id ?? $task->delegated_to_user_id;
            $docId = $task->instance?->documentVersion?->document_id;
            $actorId = $task->instance?->started_by ?? $task->instance?->initiator_user_id;
            $now = now()->format('Y-m-d H:i:s.u');

            if ($userId) {
                $event = \App\Models\NotificationEvent::create([
                    'type_code' => 'workflow.task_assigned',
                    'document_id' => $docId,
                    'actor_id' => $actorId,
                    'occurred_at' => $now,
                ]);
                \App\Models\UserNotification::create([
                    'event_id' => $event->id,
                    'user_id' => $userId,
                    'created_at' => $now,
                ]);
            } elseif ($task->assigned_unit_id) {
                $unitUsers = \App\Models\User::whereHas('memberships', fn ($uq) => $uq->where('unit_id', $task->assigned_unit_id))
                    ->where('status', 'active')
                    ->limit(10)
                    ->get();
                if ($unitUsers->isNotEmpty()) {
                    $event = \App\Models\NotificationEvent::create([
                        'type_code' => 'workflow.task_assigned',
                        'document_id' => $docId,
                        'actor_id' => $actorId,
                        'occurred_at' => $now,
                    ]);
                    foreach ($unitUsers as $u) {
                        \App\Models\UserNotification::create([
                            'event_id' => $event->id,
                            'user_id' => $u->id,
                            'created_at' => $now,
                        ]);
                    }
                }
            }
        } catch (Throwable $e) {
            Log::warning("Failed to record workflow task in-app notification: " . $e->getMessage());
        }

        // 2. Email notification
        try {
            $user = $task->assignedUser;
            if (! $user || empty($user->email)) {
                return;
            }

            Mail::to($user->email)->queue(new WorkflowTaskNotificationMail($task, $user));
        } catch (Throwable $e) {
            Log::warning("Failed to queue workflow task notification email: " . $e->getMessage(), [
                'task_id' => $task->id,
            ]);
        }
    }

    /**
     * Notify initiator or stakeholders of a workflow decision.
     */
    public function notifyWorkflowDecision(WorkflowInstance $instance, WorkflowTask $task, string $decision): void
    {
        // 1. In-app database notification
        try {
            $initiatorId = $instance->started_by ?? $instance->initiator_user_id;
            if ($initiatorId) {
                $now = now()->format('Y-m-d H:i:s.u');
                $docId = $instance->documentVersion?->document_id;
                $deciderId = $task->decisions?->first()?->decided_by ?? $task->decision?->decided_by;
                $typeCode = match ($decision) {
                    'approve' => 'workflow.approved',
                    'reject' => 'workflow.rejected',
                    'return' => 'workflow.returned',
                    default => 'workflow.task_completed',
                };
                $event = \App\Models\NotificationEvent::create([
                    'type_code' => $typeCode,
                    'document_id' => $docId,
                    'actor_id' => $deciderId,
                    'occurred_at' => $now,
                ]);
                \App\Models\UserNotification::create([
                    'event_id' => $event->id,
                    'user_id' => $initiatorId,
                    'created_at' => $now,
                ]);
            }
        } catch (Throwable $e) {
            Log::warning("Failed to record workflow decision in-app notification: " . $e->getMessage());
        }

        // 2. Email notification
        try {
            $recipient = $instance->startedBy ?? $instance->initiator;
            if (! $recipient || empty($recipient->email)) {
                return;
            }

            Mail::to($recipient->email)->queue(new WorkflowDecisionNotificationMail($instance, $task, $recipient, $decision));
        } catch (Throwable $e) {
            Log::warning("Failed to queue workflow decision notification email: " . $e->getMessage(), [
                'instance_id' => $instance->id,
                'task_id' => $task->id,
                'decision' => $decision,
            ]);
        }
    }

    /**
     * Notify recipient of an overdue or escalated item with deduplication.
     */
    public function notifyOverdue(
        User $recipient,
        string $itemType,
        string $referenceNumber,
        string $title,
        string $dueDate,
        string $escalationLevel = 'warning'
    ): void {
        try {
            if (empty($recipient->email)) {
                return;
            }

            // Deduplicate: avoid re-sending identical overdue notification within 4 hours
            $cacheKey = "overdue_email_{$itemType}_{$referenceNumber}_{$recipient->id}_{$escalationLevel}";
            if (Cache::has($cacheKey)) {
                return;
            }

            Mail::to($recipient->email)->queue(
                new OverdueEscalationMail($recipient, $itemType, $referenceNumber, $title, $dueDate, $escalationLevel)
            );

            Cache::put($cacheKey, true, now()->addHours(4));
        } catch (Throwable $e) {
            Log::warning("Failed to queue overdue notification email: " . $e->getMessage(), [
                'recipient_id' => $recipient->id,
                'reference' => $referenceNumber,
            ]);
        }
    }

    /**
     * Notify destination unit members of a correspondence movement or forwarding.
     */
    public function notifyCorrespondenceMovement(DocumentMovement $movement, string $actionType = 'forwarded'): void
    {
        try {
            $dedupKey = "corr_movement_{$movement->id}_{$actionType}";
            if (Cache::has($dedupKey)) {
                return;
            }

            $now = now()->format('Y-m-d H:i:s.u');

            $event = NotificationEvent::create([
                'type_code' => $actionType === 'received' ? 'correspondence.received' : 'correspondence.forwarded',
                'document_id' => $movement->document_id,
                'actor_id' => $movement->sent_by,
                'occurred_at' => $now,
            ]);

            // Notify active users in target unit
            if ($movement->to_unit_id) {
                $targetUsers = User::whereHas('memberships', fn ($uq) => $uq->where('unit_id', $movement->to_unit_id))
                    ->where('status', 'active')
                    ->limit(20)
                    ->get();

                foreach ($targetUsers as $targetUser) {
                    UserNotification::create([
                        'event_id' => $event->id,
                        'user_id' => $targetUser->id,
                        'created_at' => $now,
                    ]);
                }
            }

            Cache::put($dedupKey, true, now()->addMinutes(15));
        } catch (Throwable $e) {
            Log::warning("Failed to record correspondence movement notification: " . $e->getMessage(), [
                'movement_id' => $movement->id,
            ]);
        }
    }

    /**
     * Notify archive managers of an upcoming or due retention review.
     */
    public function notifyRetentionReview(ArchiveEntry $entry, string $eventType = 'due'): void
    {
        try {
            $dedupKey = "retention_review_{$entry->id}_{$eventType}";
            if (Cache::has($dedupKey)) {
                return;
            }

            $now = now()->format('Y-m-d H:i:s.u');

            $event = NotificationEvent::create([
                'type_code' => 'archive.retention_review_due',
                'document_id' => $entry->document_id,
                'actor_id' => $entry->archived_by,
                'occurred_at' => $now,
            ]);

            $archivistUsers = User::whereHas('userPermissions.permission', fn ($pq) => $pq->where('code', 'archive.manage'))
                ->orWhereHas('roles.permissions', fn ($pq) => $pq->where('code', 'archive.manage'))
                ->limit(10)
                ->get();

            foreach ($archivistUsers as $archivist) {
                UserNotification::create([
                    'event_id' => $event->id,
                    'user_id' => $archivist->id,
                    'created_at' => $now,
                ]);
            }

            Cache::put($dedupKey, true, now()->addHours(24));
        } catch (Throwable $e) {
            Log::warning("Failed to record retention review notification: " . $e->getMessage(), [
                'entry_id' => $entry->id,
            ]);
        }
    }
}
