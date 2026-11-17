<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OverdueEscalationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $recipient,
        public string $itemType, // 'assignment' or 'workflow_task'
        public string $referenceNumber,
        public string $title,
        public string $dueDate,
        public string $escalationLevel = 'warning' // 'warning', 'escalated'
    ) {}

    public function envelope(): Envelope
    {
        $prefix = $this->escalationLevel === 'escalated' ? '[ESCALATED] ' : '[OVERDUE] ';
        return new Envelope(
            subject: "[WU-DMS] {$prefix}{$this->referenceNumber} Deadline Exceeded",
        );
    }

    public function content(): Content
    {
        $itemTypeName = $this->itemType === 'workflow_task' ? 'Workflow Task' : 'Administrative Assignment';

        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url', 'http://localhost:5173')), '/');
        $actionUrl = $this->itemType === 'workflow_task' ? "{$frontendUrl}/tasks" : "{$frontendUrl}/dashboard";

        return new Content(
            view: 'emails.overdue-escalation',
            text: 'emails.overdue-escalation-text',
            with: [
                'recipient' => $this->recipient,
                'itemType' => $this->itemType,
                'itemTypeName' => $itemTypeName,
                'referenceNumber' => $this->referenceNumber,
                'title' => $this->title,
                'dueDate' => $this->dueDate,
                'escalationLevel' => $this->escalationLevel,
                'actionUrl' => $actionUrl,
                'subject' => "[WU-DMS] " . ($this->escalationLevel === 'escalated' ? '[ESCALATED] ' : '[OVERDUE] ') . "{$this->referenceNumber} Deadline Exceeded",
            ]
        );
    }
}
