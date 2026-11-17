<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkflowDecisionNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public WorkflowInstance $instance,
        public WorkflowTask $task,
        public User $recipient,
        public string $decision
    ) {}

    public function envelope(): Envelope
    {
        $doc = $this->instance->document;
        $docRef = $doc ? ($doc->reference_number ?: "DOC-{$doc->id}") : 'Workflow';
        $dec = ucfirst($this->decision);

        return new Envelope(
            subject: "[WU-DMS] Workflow Decision ({$dec}): {$docRef}",
        );
    }

    public function content(): Content
    {
        $doc = $this->instance->document;
        $title = $doc?->title ?: 'Administrative Document';
        $ref = $doc?->reference_number ?: 'N/A';
        $dec = ucfirst($this->decision);
        $comments = $this->task->decision_comments ?: 'No additional remarks provided.';
        $decidedBy = $this->task->assignedUser?->name ?: 'Workflow Participant';

        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url', 'http://localhost:5173')), '/');
        $actionUrl = $doc ? "{$frontendUrl}/documents/{$doc->id}" : "{$frontendUrl}/tasks";

        return new Content(
            view: 'emails.workflow-decision',
            text: 'emails.workflow-decision-text',
            with: [
                'recipient' => $this->recipient,
                'instance' => $this->instance,
                'task' => $this->task,
                'decision' => $this->decision,
                'decidedBy' => $decidedBy,
                'ref' => $ref,
                'title' => $title,
                'comments' => $comments,
                'actionUrl' => $actionUrl,
                'subject' => "[WU-DMS] Workflow Decision ({$dec}): {$ref}",
            ]
        );
    }
}
