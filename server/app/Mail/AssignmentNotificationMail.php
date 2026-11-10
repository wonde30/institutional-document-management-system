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

        return new Content(
            htmlString: <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #1e3a8a; color: white; padding: 15px 20px; border-radius: 6px 6px 0 0;">
        <h2 style="margin: 0; font-size: 18px;">Wollo University DMS - Assignment Notice</h2>
    </div>
    <div style="border: 1px solid #e5e7eb; border-top: none; padding: 20px; border-radius: 0 0 6px 6px;">
        <p>Dear <strong>{$this->recipient->name}</strong>,</p>
        <p>You have received an administrative document assignment in WU-DMS.</p>
        
        <table style="width: 100%; border-collapse: collapse; margin: 15px 0;">
            <tr style="border-bottom: 1px solid #f3f4f6;">
                <td style="padding: 8px 0; font-weight: bold; width: 140px;">Reference No:</td>
                <td style="padding: 8px 0;">{$ref}</td>
            </tr>
            <tr style="border-bottom: 1px solid #f3f4f6;">
                <td style="padding: 8px 0; font-weight: bold;">Document Title:</td>
                <td style="padding: 8px 0;">{$title}</td>
            </tr>
            <tr style="border-bottom: 1px solid #f3f4f6;">
                <td style="padding: 8px 0; font-weight: bold;">Priority:</td>
                <td style="padding: 8px 0;"><span style="text-transform: capitalize; font-weight: 600;">{$priority}</span></td>
            </tr>
            <tr style="border-bottom: 1px solid #f3f4f6;">
                <td style="padding: 8px 0; font-weight: bold;">Due Date:</td>
                <td style="padding: 8px 0;">{$dueDate}</td>
            </tr>
        </table>

        <div style="background-color: #f9fafb; border-left: 4px solid #3b82f6; padding: 12px; margin: 15px 0;">
            <p style="margin: 0; font-size: 13px; color: #4b5563;"><strong>Instructions:</strong></p>
            <p style="margin: 5px 0 0 0; font-size: 14px;">{$instructions}</p>
        </div>

        <p style="margin-top: 25px; font-size: 13px; color: #6b7280;">
            Please log in to the WU-DMS portal to view the document and submit your response.
        </p>
    </div>
</body>
</html>
HTML
        );
    }
}
