<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditEvent;
use App\Models\DocumentAssignment;
use App\Models\DocumentMovement;
use App\Models\NotificationEvent;
use App\Models\UserNotification;
use App\Models\WorkflowTask;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcessDueDatesAndEscalationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dms:check-due-dates 
                            {--dry-run : Inspect and report overdue items without generating notifications or audit records}';

    protected $aliases = ['dms:process-due-dates-and-escalations'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan overdue assignments, workflow tasks, and document movements to dispatch automated reminders and escalation audit events';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $now = now();
        $nowFormatted = $now->format('Y-m-d H:i:s.u');
        $systemActorId = \App\Models\User::where('email', 'admin@wu.edu.et')->value('id')
            ?? \App\Models\User::orderBy('id')->value('id');

        $this->info("Scanning WU-DMS for overdue records as of {$now->toDateTimeString()} " . ($dryRun ? '[DRY RUN]' : ''));

        // 1. Process Overdue Document Assignments
        $overdueAssignments = DocumentAssignment::with(['document', 'assignedUser', 'assignedUnit'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->whereDoesntHave('events', function ($q) {
                $q->whereIn('action', ['completed', 'cancelled']);
            })
            ->get();

        $this->line("Found {$overdueAssignments->count()} overdue assignment(s).");

        $assignmentRows = [];
        foreach ($overdueAssignments as $assignment) {
            $assignmentRows[] = [
                $assignment->id,
                $assignment->document?->reference_number ?? ('Doc #' . $assignment->document_id),
                $assignment->assignedUser?->name ?? ($assignment->assignedUnit?->name ?? 'Unassigned'),
                $assignment->due_at?->toDateTimeString(),
                $assignment->due_at ? $now->diffForHumans($assignment->due_at, true) . ' overdue' : 'N/A',
            ];

            if (! $dryRun && $systemActorId) {
                DB::transaction(function () use ($assignment, $nowFormatted, $systemActorId) {
                    $alreadyEscalated = AuditEvent::where('action', 'assignment.overdue_escalated')
                        ->where('target_type', 'document_assignment')
                        ->where('target_id', (string) $assignment->id)
                        ->where('occurred_at', '>=', now()->subHours(24))
                        ->exists();

                    if (! $alreadyEscalated) {
                        // Record system escalation in audit log
                        AuditEvent::create([
                            'actor_id' => $systemActorId,
                            'action' => 'assignment.overdue_escalated',
                            'target_type' => 'document_assignment',
                            'target_id' => (string) $assignment->id,
                            'request_id' => (string) Str::uuid(),
                            'occurred_at' => $nowFormatted,
                            'details_json' => [
                                'assignment_id' => $assignment->id,
                                'document_id' => $assignment->document_id,
                                'due_at' => $assignment->due_at?->toDateTimeString(),
                                'assigned_user_id' => $assignment->assigned_user_id,
                                'assigned_unit_id' => $assignment->assigned_unit_id,
                            ],
                        ]);
                    }

                    // Generate in-app notification if target user is assigned and not notified within 24h
                    if ($assignment->assigned_user_id) {
                        $alreadyNotified = NotificationEvent::where('type_code', 'assignment_overdue')
                            ->where('document_id', $assignment->document_id)
                            ->where('occurred_at', '>=', now()->subHours(24))
                            ->exists();

                        if (! $alreadyNotified) {
                            $event = NotificationEvent::create([
                                'type_code' => 'assignment_overdue',
                                'document_id' => $assignment->document_id,
                                'actor_id' => $systemActorId,
                                'occurred_at' => $nowFormatted,
                            ]);

                            UserNotification::create([
                                'event_id' => $event->id,
                                'user_id' => $assignment->assigned_user_id,
                                'created_at' => $nowFormatted,
                            ]);

                            if ($assignment->assignedUser) {
                                app(\App\Services\EmailNotificationService::class)->notifyOverdue(
                                    $assignment->assignedUser,
                                    'assignment',
                                    $assignment->document?->reference_number ?? ('Doc #' . $assignment->document_id),
                                    $assignment->document?->title ?? 'Administrative Document',
                                    $assignment->due_at?->toDateTimeString() ?? 'Immediate',
                                    'warning'
                                );
                            }
                        }
                    }
                });
            }
        }

        if (! empty($assignmentRows)) {
            $this->table(['Assignment ID', 'Document', 'Assigned To', 'Due At', 'Overdue By'], $assignmentRows);
        }

        // 2. Process Overdue Workflow Tasks
        $overdueTasks = WorkflowTask::with(['instance.documentVersion.document', 'state', 'assignedUser', 'assignedUnit'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->whereNull('closed_at')
            ->get();

        $this->line("Found {$overdueTasks->count()} overdue workflow task(s).");

        $taskRows = [];
        foreach ($overdueTasks as $task) {
            $taskRows[] = [
                $task->id,
                $task->state?->name ?? ('State #' . $task->state_id),
                $task->assignedUser?->name ?? ($task->assignedUnit?->name ?? 'Unassigned'),
                $task->due_at?->toDateTimeString(),
                $task->due_at ? $now->diffForHumans($task->due_at, true) . ' overdue' : 'N/A',
            ];

            if (! $dryRun && $systemActorId) {
                DB::transaction(function () use ($task, $nowFormatted, $systemActorId) {
                    $alreadyEscalated = AuditEvent::where('action', 'workflow.task_overdue_escalated')
                        ->where('target_type', 'workflow_task')
                        ->where('target_id', (string) $task->id)
                        ->where('occurred_at', '>=', now()->subHours(24))
                        ->exists();

                    if (! $alreadyEscalated) {
                        AuditEvent::create([
                            'actor_id' => $systemActorId,
                            'action' => 'workflow.task_overdue_escalated',
                            'target_type' => 'workflow_task',
                            'target_id' => (string) $task->id,
                            'request_id' => (string) Str::uuid(),
                            'occurred_at' => $nowFormatted,
                            'details_json' => [
                                'task_id' => $task->id,
                                'instance_id' => $task->instance_id,
                                'due_at' => $task->due_at?->toDateTimeString(),
                                'assigned_user_id' => $task->assigned_user_id,
                                'assigned_unit_id' => $task->assigned_unit_id,
                            ],
                        ]);
                    }

                    if ($task->assigned_user_id) {
                        $docId = $task->instance?->documentVersion?->document_id;
                        $alreadyNotified = NotificationEvent::where('type_code', 'workflow_task_overdue')
                            ->where('document_id', $docId)
                            ->where('occurred_at', '>=', now()->subHours(24))
                            ->exists();

                        if (! $alreadyNotified) {
                            $event = NotificationEvent::create([
                                'type_code' => 'workflow_task_overdue',
                                'document_id' => $docId,
                                'actor_id' => $systemActorId,
                                'occurred_at' => $nowFormatted,
                            ]);

                            UserNotification::create([
                                'event_id' => $event->id,
                                'user_id' => $task->assigned_user_id,
                                'created_at' => $nowFormatted,
                            ]);

                            if ($task->assignedUser) {
                                app(\App\Services\EmailNotificationService::class)->notifyOverdue(
                                    $task->assignedUser,
                                    'workflow_task',
                                    $task->instance?->documentVersion?->document?->reference_number ?? ('Task #' . $task->id),
                                    $task->state?->name ?? 'Workflow Task',
                                    $task->due_at?->toDateTimeString() ?? 'Immediate',
                                    'warning'
                                );
                            }
                        }
                    }
                });
            }
        }

        if (! empty($taskRows)) {
            $this->table(['Task ID', 'State', 'Assigned To', 'Due At', 'Overdue By'], $taskRows);
        }

        // 3. Process Overdue Document Movements
        $overdueMovements = DocumentMovement::with(['document', 'fromUnit', 'toUnit'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->whereDoesntHave('receipt')
            ->get();

        $this->line("Found {$overdueMovements->count()} overdue document movement(s).");

        $movementRows = [];
        foreach ($overdueMovements as $mov) {
            $movementRows[] = [
                $mov->id,
                $mov->document?->reference_number ?? ('Doc #' . $mov->document_id),
                $mov->fromUnit?->name ?? 'External/Central Registry',
                $mov->toUnit?->name ?? 'N/A',
                $mov->due_at?->toDateTimeString(),
            ];

            if (! $dryRun && $systemActorId) {
                DB::transaction(function () use ($mov, $nowFormatted, $systemActorId) {
                    $alreadyEscalated = AuditEvent::where('action', 'movement.overdue_escalated')
                        ->where('target_type', 'document_movement')
                        ->where('target_id', (string) $mov->id)
                        ->where('occurred_at', '>=', now()->subHours(24))
                        ->exists();

                    if (! $alreadyEscalated) {
                        AuditEvent::create([
                            'actor_id' => $systemActorId,
                            'action' => 'movement.overdue_escalated',
                            'target_type' => 'document_movement',
                            'target_id' => (string) $mov->id,
                            'request_id' => (string) Str::uuid(),
                            'occurred_at' => $nowFormatted,
                            'details_json' => [
                                'movement_id' => $mov->id,
                                'document_id' => $mov->document_id,
                                'from_unit_id' => $mov->from_unit_id,
                                'to_unit_id' => $mov->to_unit_id,
                                'due_at' => $mov->due_at?->toDateTimeString(),
                            ],
                        ]);
                    }
                });
            }
        }

        if (! empty($movementRows)) {
            $this->table(['Movement ID', 'Document', 'From Unit', 'To Unit', 'Due At'], $movementRows);
        }

        $this->info('Due date and escalation processing completed successfully.');

        return Command::SUCCESS;
    }
}
