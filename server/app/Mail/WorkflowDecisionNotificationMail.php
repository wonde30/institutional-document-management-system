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

        $bannerColor = match (strtolower($this->decision)) {
            'approve', 'approved' => '#047857',
            'reject', 'rejected' => '#b91c1c',
            default => '#d97706',
        };

        return new Content(
            htmlString: <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: {$bannerColor}; color: white; padding: 15px 20px; border-radius: 6px 6px 0 0;">
        <h2 style="margin: 0; font-size: 18px;">Wollo University DMS - Workflow Decision</h2>
    </div>
    <div style="border: 1px solid #e5e7eb; border-top: none; padding: 20px; border-radius: 0 0 6px 6px;">
        <p>Dear <strong>{$this->recipient->name}</strong>,</p>
        <p>A decision of <strong>{$dec}</strong> was recorded for your workflow item.</p>
        
        <table style="width: 100%; border-collapse: collapse; margin: 15px 0;">
            <tr style="border-bottom: 1px solid #f3f4f6;">
                <td style="padding: 8px 0; font-weight: bold; width: 140px;">Decision:</td>
                <td style="padding: 8px 0;"><span style="font-weight: bold; text-transform: uppercase;">{$dec}</span></td>
            </tr>
            <tr style="border-bottom: 1px solid #f3f4f6;">
                <td style="padding: 8px 0; font-weight: bold;">Decided By:</td>
                <td style="padding: 8px 0;">{$decidedBy}</td>
            </tr>
            <tr style="border-bottom: 1px solid #f3f4f6;">
                <td style="padding: 8px 0; font-weight: bold;">Document Ref:</td>
                <td style="padding: 8px 0;">{$ref}</td>
            </tr>
            <tr style="border-bottom: 1px solid #f3f4f6;">
                <td style="padding: 8px 0; font-weight: bold;">Document Title:</td>
                <td style="padding: 8px 0;">{$title}</td>
            </tr>
        </table>

        <div style="background-color: #f9fafb; border-left: 4px solid {$bannerColor}; padding: 12px; margin: 15px 0;">
            <p style="margin: 0; font-size: 13px; color: #4b5563;"><strong>Comments:</strong></p>
            <p style="margin: 5px 0 0 0; font-size: 14px;">{$comments}</p>
        </div>

        <p style="margin-top: 25px; font-size: 13px; color: #6b7280;">
            Please log in to the WU-DMS portal for full transition details and history.
        </p>
    </div>
</body>
</html>
HTML
        );
    }
}
