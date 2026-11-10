<?php

declare(strict_types=1);

namespace App\Console\Commands;

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
                            {--now : Send synchronously instead of queueing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test diagnostic email to verify SMTP configuration and delivery pipeline';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $recipient = $this->argument('recipient') ?: 'admin@wu.edu.et';
        $sendNow = (bool) $this->option('now');

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
                ['Delivery Mode', $sendNow ? 'Synchronous (now)' : 'Queued'],
            ]
        );

        $this->line("Dispatching diagnostic email to {$recipient}...");

        try {
            $subject = "[WU-DMS] Test Diagnostic Email - " . now()->toDateTimeString();
            $body = "This is a diagnostic verification email sent by Wollo University DMS.\n\n"
                  . "Mailer: {$defaultMailer}\n"
                  . "Timestamp: " . now()->toISOString() . "\n"
                  . "Environment: " . app()->environment() . "\n\n"
                  . "If you received this email, the DMS email subsystem is operating correctly.";

            if ($sendNow) {
                Mail::raw($body, function ($message) use ($recipient, $subject) {
                    $message->to($recipient)->subject($subject);
                });
            } else {
                Mail::raw($body, function ($message) use ($recipient, $subject) {
                    $message->to($recipient)->subject($subject);
                });
            }

            $this->info("Successfully dispatched test email to <{$recipient}>.");
            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Failed to send test email: " . $e->getMessage());
            $this->line($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
