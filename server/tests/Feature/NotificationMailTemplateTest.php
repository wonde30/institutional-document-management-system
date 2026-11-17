<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\AssignmentNotificationMail;
use App\Mail\OverdueEscalationMail;
use App\Mail\WorkflowDecisionNotificationMail;
use App\Mail\WorkflowTaskNotificationMail;
use App\Models\Document;
use App\Models\DocumentAssignment;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTask;
use Tests\TestCase;

class NotificationMailTemplateTest extends TestCase
{
    public function test_assignment_notification_mail_renders_html_and_text_properly(): void
    {
        $user = new User([
            'name' => 'Abebe Bikila',
            'email' => 'abebe@wu.edu.et',
        ]);
        $user->id = 42;

        $document = new Document([
            'reference_number' => 'WU/DOC/2026/00101',
            'title' => 'Senate Strategic Initiative 2026',
        ]);
        $document->id = 101;

        $assignment = new DocumentAssignment();
        $assignment->id = 501;
        $assignment->document_id = 101;
        $assignment->priority = 'urgent';
        $assignment->due_date = now()->addDays(3);
        $assignment->instructions = 'Review the strategic initiative and verify faculty budget allocations.';
        $assignment->setRelation('document', $document);

        $mail = new AssignmentNotificationMail($assignment, $user, 'assigned');

        // Test envelope
        $envelope = $mail->envelope();
        $this->assertStringContainsString('WU/DOC/2026/00101', $envelope->subject);

        // Test HTML rendering
        $html = $mail->render();
        $this->assertStringContainsString('Wollo University', $html);
        $this->assertStringContainsString('Abebe Bikila', $html);
        $this->assertStringContainsString('WU/DOC/2026/00101', $html);
        $this->assertStringContainsString('Senate Strategic Initiative 2026', $html);
        $this->assertStringContainsString('urgent', $html);
        $this->assertStringContainsString('Review the strategic initiative', $html);
        $this->assertStringContainsString('/documents/101', $html);

        // Verify XSS escaping
        $unsafeDoc = new Document([
            'reference_number' => 'WU/XSS/001',
            'title' => 'Test <script>alert("xss")</script> Document',
        ]);
        $unsafeDoc->id = 102;

        $unsafeAssignment = new DocumentAssignment();
        $unsafeAssignment->id = 502;
        $unsafeAssignment->document_id = 102;
        $unsafeAssignment->priority = 'routine';
        $unsafeAssignment->instructions = 'Malicious payload: <img src=x onerror=alert(1)>';
        $unsafeAssignment->setRelation('document', $unsafeDoc);

        $xssMail = new AssignmentNotificationMail($unsafeAssignment, $user, 'assigned');
        $xssHtml = $xssMail->render();
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $xssHtml);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $xssHtml);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $xssHtml);
    }

    public function test_workflow_task_notification_mail_renders_properly(): void
    {
        $user = new User([
            'name' => 'Tigist Assefa',
            'email' => 'tigist@wu.edu.et',
        ]);
        $user->id = 43;

        $document = new Document([
            'reference_number' => 'WU/WF/2026/00202',
            'title' => 'Curriculum Modernization Proposal',
        ]);
        $document->id = 202;

        $instance = new WorkflowInstance();
        $instance->id = 88;
        $instance->setRelation('document', $document);

        $task = new WorkflowTask([
            'task_type' => 'approval',
            'branch_key' => 'academic_senate',
            'due_at' => now()->addDays(2),
        ]);
        $task->id = 999;
        $task->setRelation('instance', $instance);

        $mail = new WorkflowTaskNotificationMail($task, $user);

        $envelope = $mail->envelope();
        $this->assertStringContainsString('WU/WF/2026/00202', $envelope->subject);

        $html = $mail->render();
        $this->assertStringContainsString('Tigist Assefa', $html);
        $this->assertStringContainsString('WU/WF/2026/00202', $html);
        $this->assertStringContainsString('Curriculum Modernization Proposal', $html);
        $this->assertStringContainsString('academic_senate', $html);
        $this->assertStringContainsString('/tasks', $html);
    }

    public function test_workflow_decision_notification_mail_renders_properly(): void
    {
        $user = new User([
            'name' => 'Derartu Tulu',
            'email' => 'derartu@wu.edu.et',
        ]);
        $user->id = 44;

        $decider = new User([
            'name' => 'Academic Vice President',
        ]);
        $decider->id = 10;

        $document = new Document([
            'reference_number' => 'WU/RES/2026/00303',
            'title' => 'Informatics Laboratory Equipment Procurement',
        ]);
        $document->id = 303;

        $instance = new WorkflowInstance();
        $instance->id = 77;
        $instance->setRelation('document', $document);

        $task = new WorkflowTask();
        $task->id = 888;
        $task->decision_comments = 'Endorsed in full compliance with the 2026 capital budget allocation.';
        $task->setRelation('instance', $instance);
        $task->setRelation('assignedUser', $decider);

        $mail = new WorkflowDecisionNotificationMail($instance, $task, $user, 'approved');

        $envelope = $mail->envelope();
        $this->assertStringContainsString('Approved', $envelope->subject);

        $html = $mail->render();
        $this->assertStringContainsString('Derartu Tulu', $html);
        $this->assertStringContainsString('Academic Vice President', $html);
        $this->assertStringContainsString('WU/RES/2026/00303', $html);
        $this->assertStringContainsString('Endorsed in full compliance', $html);
        $this->assertStringContainsString('/documents/303', $html);
    }

    public function test_overdue_escalation_mail_renders_properly(): void
    {
        $user = new User([
            'name' => 'Haile Gebrselassie',
            'email' => 'haile@wu.edu.et',
        ]);
        $user->id = 45;

        $mail = new OverdueEscalationMail(
            $user,
            'assignment',
            'WU/ESC/2026/00999',
            'Quarterly Financial Statement Reconciliation',
            '2026-11-10',
            'escalated'
        );

        $envelope = $mail->envelope();
        $this->assertStringContainsString('[ESCALATED]', $envelope->subject);
        $this->assertStringContainsString('WU/ESC/2026/00999', $envelope->subject);

        $html = $mail->render();
        $this->assertStringContainsString('Haile Gebrselassie', $html);
        $this->assertStringContainsString('WU/ESC/2026/00999', $html);
        $this->assertStringContainsString('Quarterly Financial Statement Reconciliation', $html);
        $this->assertStringContainsString('Original Deadline:', $html);
        $this->assertStringContainsString('In accordance with Wollo University Administrative Standards', $html);
    }
}
