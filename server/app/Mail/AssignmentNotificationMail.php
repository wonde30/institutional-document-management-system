<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\DocumentAssignment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssignmentNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public DocumentAssignment $assignment,
        public User $recipient,
        public string $actionType = 'assigned' // 'assigned', 'forwarded'
    ) {}

    public function envelope(): Envelope
    {
        $doc = $this->assignment->document;
        $docRef = $doc ? ($doc->reference_number ?: "DOC-{$doc->id}") : 'Document';
        $verb = $this->actionType === 'forwarded' ? 'Forwarded' : 'New Assignment';

        return new Envelope(
            subject: "[WU-DMS] {$verb}: {$docRef}",
        );
    }

    public function content(): Content
    {
        $doc = $this->assignment->document;
        $title = $doc?->title ?: 'Administrative Document';
        $ref = $doc?->reference_number ?: 'N/A';
        $priority = $this->assignment->priority ?? 'routine';
        $dueDate = $this->assignment->due_date ? $this->assignment->due_date->format('Y-m-d') : 'No deadline';
        $instructions = $this->assignment->instructions ?: 'Please review and take necessary action.';
        $verb = $this->actionType === 'forwarded' ? 'Forwarded Document' : 'New Assignment';

        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url', 'http://localhost:5173')), '/');
        $actionUrl = $doc ? "{$frontendUrl}/documents/{$doc->id}" : "{$frontendUrl}/dashboard";

        return new Content(
            view: 'emails.assignment',
            text: 'emails.assignment-text',
            with: [
                'recipient' => $this->recipient,
                'assignment' => $this->assignment,
                'actionType' => $this->actionType,
                'verb' => $verb,
                'ref' => $ref,
                'title' => $title,
                'priority' => $priority,
                'dueDate' => $dueDate,
                'instructions' => $instructions,
                'actionUrl' => $actionUrl,
                'subject' => "[WU-DMS] {$verb}: {$ref}",
            ]
        );
    }
}
