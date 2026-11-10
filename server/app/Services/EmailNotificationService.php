<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\AssignmentNotificationMail;
use App\Mail\OverdueEscalationMail;
use App\Mail\WorkflowDecisionNotificationMail;
use App\Mail\WorkflowTaskNotificationMail;
use App\Models\DocumentAssignment;
use App\Models\User;
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
        try {
            $recipient = $instance->initiator;
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
}
