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

        return new Content(
            htmlString: <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #047857; color: white; padding: 15px 20px; border-radius: 6px 6px 0 0;">
        <h2 style="margin: 0; font-size: 18px;">Wollo University DMS - Workflow Action Required</h2>
    </div>
    <div style="border: 1px solid #e5e7eb; border-top: none; padding: 20px; border-radius: 0 0 6px 6px;">
        <p>Dear <strong>{$this->recipient->name}</strong>,</p>
        <p>You have a pending workflow task awaiting your evaluation in WU-DMS.</p>
        
        <table style="width: 100%; border-collapse: collapse; margin: 15px 0;">
            <tr style="border-bottom: 1px solid #f3f4f6;">
                <td style="padding: 8px 0; font-weight: bold; width: 140px;">Task Type:</td>
                <td style="padding: 8px 0;">{$taskType}{$branchInfo}</td>
            </tr>
            <tr style="border-bottom: 1px solid #f3f4f6;">
                <td style="padding: 8px 0; font-weight: bold;">Document Ref:</td>
                <td style="padding: 8px 0;">{$ref}</td>
            </tr>
            <tr style="border-bottom: 1px solid #f3f4f6;">
                <td style="padding: 8px 0; font-weight: bold;">Document Title:</td>
                <td style="padding: 8px 0;">{$title}</td>
            </tr>
            <tr style="border-bottom: 1px solid #f3f4f6;">
                <td style="padding: 8px 0; font-weight: bold;">Due At:</td>
                <td style="padding: 8px 0;">{$dueDate}</td>
            </tr>
        </table>

        <p style="margin-top: 25px; font-size: 13px; color: #6b7280;">
            Please log in to the WU-DMS portal to review the submission and submit your decision.
        </p>
    </div>
</body>
</html>
HTML
        );
    }
}
