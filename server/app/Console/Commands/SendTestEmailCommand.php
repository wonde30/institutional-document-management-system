<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\AssignmentNotificationMail;
use App\Mail\OverdueEscalationMail;
use App\Mail\WorkflowDecisionNotificationMail;
use App\Mail\WorkflowTaskNotificationMail;
use App\Models\Document;
use App\Models\DocumentAssignment;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendTestEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dms:send-test-email 
                            {recipient? : The destination email address (defaults to admin@wu.edu.et)}
                            {--template=raw : The email template to test (raw, assignment, workflow-task, workflow-decision, overdue)}
                            {--now : Send synchronously instead of queueing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test diagnostic email or render an institutional notification template to verify delivery';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $recipient = $this->argument('recipient') ?: 'admin@wu.edu.et';
        $sendNow = (bool) $this->option('now');
        $template = (string) ($this->option('template') ?: 'raw');

        $defaultMailer = config('mail.default');
        $host = config("mail.mailers.{$defaultMailer}.host", 'N/A');
        $port = config("mail.mailers.{$defaultMailer}.port", 'N/A');
        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name');

        $this->info("=== WU-DMS Email Diagnostic Test ===");
        $this->table(
            ['Configuration Key', 'Value'],
            [
                ['Default Mailer', $defaultMailer],
                ['Host', (string) $host],
                ['Port', (string) $port],
                ['From Address', (string) $fromAddress],
                ['From Name', (string) $fromName],
                ['Recipient', $recipient],
                ['Template', $template],
                ['Delivery Mode', $sendNow ? 'Synchronous (now)' : 'Queued'],
            ]
        );

        $this->line("Dispatching diagnostic email [{$template}] to {$recipient}...");

        try {
            $user = User::where('email', $recipient)->first() ?? new User([
                'name' => 'Wollo University Administrator',
                'email' => $recipient,
            ]);

            if ($template === 'assignment') {
                $doc = Document::first() ?? new Document([
                    'reference_number' => 'WU/DOC/TEST/001',
                    'title' => 'Diagnostic Document Assignment Test',
                ]);
                $assignment = new DocumentAssignment();
                $assignment->priority = 'routine';
                $assignment->due_date = now()->addDays(5);
                $assignment->instructions = 'Please review this test document assignment in the WU-DMS test environment.';
                $assignment->setRelation('document', $doc);

                $mailable = new AssignmentNotificationMail($assignment, $user, 'assigned');
                $sendNow ? Mail::to($recipient)->send($mailable) : Mail::to($recipient)->queue($mailable);
            } elseif ($template === 'workflow-task') {
                $doc = Document::first() ?? new Document([
                    'reference_number' => 'WU/WF/TEST/002',
                    'title' => 'Diagnostic Workflow Task Test',
                ]);
                $instance = new WorkflowInstance();
                $instance->setRelation('document', $doc);
                $task = new WorkflowTask([
                    'task_type' => 'approval',
                    'due_at' => now()->addDays(2),
                ]);
                $task->id = 1001;
                $task->setRelation('instance', $instance);

                $mailable = new WorkflowTaskNotificationMail($task, $user);
                $sendNow ? Mail::to($recipient)->send($mailable) : Mail::to($recipient)->queue($mailable);
            } elseif ($template === 'workflow-decision') {
                $doc = Document::first() ?? new Document([
                    'reference_number' => 'WU/DEC/TEST/003',
                    'title' => 'Diagnostic Workflow Decision Test',
                ]);
                $instance = new WorkflowInstance();
                $instance->setRelation('document', $doc);
                $decider = new User(['name' => 'Dean of Informatics']);
                $task = new WorkflowTask();
                $task->decision_comments = 'Test workflow evaluation completed successfully in diagnostic mode.';
                $task->setRelation('instance', $instance);
                $task->setRelation('assignedUser', $decider);

                $mailable = new WorkflowDecisionNotificationMail($instance, $task, $user, 'approved');
                $sendNow ? Mail::to($recipient)->send($mailable) : Mail::to($recipient)->queue($mailable);
            } elseif ($template === 'overdue') {
                $mailable = new OverdueEscalationMail(
                    $user,
                    'assignment',
                    'WU/OVERDUE/TEST/004',
                    'Diagnostic Overdue Escalation Alert',
                    now()->subDays(2)->format('Y-m-d'),
                    'warning'
                );
                $sendNow ? Mail::to($recipient)->send($mailable) : Mail::to($recipient)->queue($mailable);
            } else {
                $subject = "[WU-DMS] Test Diagnostic Email - " . now()->toDateTimeString();
                $body = "This is a diagnostic verification email sent by Wollo University DMS.\n\n"
                      . "Mailer: {$defaultMailer}\n"
                      . "Timestamp: " . now()->toISOString() . "\n"
                      . "Environment: " . app()->environment() . "\n\n"
                      . "If you received this email, the DMS email subsystem is operating correctly.";

                Mail::raw($body, function ($message) use ($recipient, $subject) {
                    $message->to($recipient)->subject($subject);
                });
            }

            $this->info("Successfully dispatched test email [{$template}] to <{$recipient}>.");
            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Failed to send test email: " . $e->getMessage());
            $this->line($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
