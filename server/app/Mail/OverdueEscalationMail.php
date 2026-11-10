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

        return new Content(
            htmlString: <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #dc2626; color: white; padding: 15px 20px; border-radius: 6px 6px 0 0;">
        <h2 style="margin: 0; font-size: 18px;">Wollo University DMS - Escalation Alert</h2>
    </div>
    <div style="border: 1px solid #e5e7eb; border-top: none; padding: 20px; border-radius: 0 0 6px 6px;">
        <p>Dear <strong>{$this->recipient->name}</strong>,</p>
        <p>An institutional action item has exceeded its established deadline and requires immediate attention.</p>
        
        <table style="width: 100%; border-collapse: collapse; margin: 15px 0;">
            <tr style="border-bottom: 1px solid #f3f4f6;">
                <td style="padding: 8px 0; font-weight: bold; width: 140px;">Item Type:</td>
                <td style="padding: 8px 0;">{$itemTypeName}</td>
            </tr>
            <tr style="border-bottom: 1px solid #f3f4f6;">
                <td style="padding: 8px 0; font-weight: bold;">Reference No:</td>
                <td style="padding: 8px 0;">{$this->referenceNumber}</td>
            </tr>
            <tr style="border-bottom: 1px solid #f3f4f6;">
                <td style="padding: 8px 0; font-weight: bold;">Item Title:</td>
                <td style="padding: 8px 0;">{$this->title}</td>
            </tr>
            <tr style="border-bottom: 1px solid #f3f4f6;">
                <td style="padding: 8px 0; font-weight: bold; color: #dc2626;">Original Deadline:</td>
                <td style="padding: 8px 0; color: #dc2626; font-weight: bold;">{$this->dueDate}</td>
            </tr>
        </table>

        <p style="margin-top: 25px; font-size: 13px; color: #6b7280;">
            Immediate action is required to maintain compliance with university SLA policies.
        </p>
    </div>
</body>
</html>
HTML
        );
    }
}
