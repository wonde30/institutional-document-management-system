<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use App\Models\WorkflowTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkflowTaskNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public WorkflowTask $task,
        public User $recipient
    ) {}

    public function envelope(): Envelope
    {
        $instance = $this->task->instance;
        $doc = $instance?->document;
        $docRef = $doc ? ($doc->reference_number ?: "DOC-{$doc->id}") : 'Workflow Task';

        return new Envelope(
            subject: "[WU-DMS] Action Required: Task #{$this->task->id} on {$docRef}",
        );
    }

    public function content(): Content
    {
        $instance = $this->task->instance;
        $doc = $instance?->document;
        $title = $doc?->title ?: 'Administrative Workflow Document';
        $ref = $doc?->reference_number ?: 'N/A';
        $taskType = ucfirst(str_replace('_', ' ', $this->task->task_type ?? 'Review'));
        $dueDate = $this->task->due_at ? $this->task->due_at->format('Y-m-d H:i') : 'No deadline';
        $branchInfo = $this->task->branch_key ? " (Branch: {$this->task->branch_key})" : '';

        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url', 'http://localhost:5173')), '/');
        $actionUrl = "{$frontendUrl}/tasks";

        return new Content(
            view: 'emails.workflow-task',
            text: 'emails.workflow-task-text',
            with: [
                'recipient' => $this->recipient,
                'task' => $this->task,
                'taskType' => $taskType,
                'branchInfo' => $branchInfo,
                'ref' => $ref,
                'title' => $title,
                'dueDate' => $dueDate,
                'actionUrl' => $actionUrl,
                'subject' => "[WU-DMS] Action Required: Task #{$this->task->id} on {$ref}",
            ]
        );
    }
}
