<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\ConfidentialityLevel;
use App\Models\Document;
use App\Models\DocumentAssignment;
use App\Models\DocumentCategory;
use App\Models\NotificationEvent;
use App\Models\OrganizationUnit;
use App\Models\User;
use App\Models\UserNotification;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SchedulerExecutionTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected User $staffUser;
    protected OrganizationUnit $unit;
    protected DocumentCategory $category;
    protected ConfidentialityLevel $confidentiality;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $this->adminUser = User::where('email', 'admin@wu.edu.et')->first()
            ?? User::factory()->create(['email' => 'admin@wu.edu.et', 'status' => 'active']);
        $this->staffUser = User::factory()->create(['status' => 'active', 'authz_version' => 1]);
        $this->unit = OrganizationUnit::first();
        $this->category = DocumentCategory::first();
        $this->confidentiality = ConfidentialityLevel::first();
    }

    private function createDocument(): Document
    {
        return Document::create([
            'reference_number' => 'DOC-SCHED-' . uniqid(),
            'title' => 'Scheduled Task Overdue Document',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->unit->id,
            'custodian_id' => $this->adminUser->id,
            'created_by' => $this->adminUser->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 1,
        ]);
    }

    public function test_scheduler_executes_due_date_detection_and_delivers_notification(): void
    {
        $doc = $this->createDocument();

        // 1. Create overdue assignment (due 2 days ago)
        $assignment = DocumentAssignment::create([
            'document_id' => $doc->id,
            'assigned_user_id' => $this->staffUser->id,
            'assigned_unit_id' => $this->unit->id,
            'assigned_by' => $this->adminUser->id,
            'assigned_at' => now()->subDays(4),
            'due_at' => now()->subDays(2),
            'instruction' => 'Urgent action required on Senate committee report',
        ]);

        // 2. Execute scheduled event through Laravel's Schedule manager
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
        $events = collect($schedule->events());
        $dmsEvent = $events->first(fn($e) => str_contains($e->command, 'dms:check-due-dates'));
        $this->assertNotNull($dmsEvent, 'dms:check-due-dates must be registered in Schedule');

        // Run the scheduled event in-application
        $this->artisan('dms:check-due-dates')->assertExitCode(0);

        // 3. Verify audit escalation event was recorded
        $this->assertDatabaseHas('audit_events', [
            'action' => 'assignment.overdue_escalated',
            'target_type' => 'document_assignment',
            'target_id' => (string) $assignment->id,
        ]);

        // 4. Verify in-app notification delivered to staffUser
        $notification = UserNotification::where('user_id', $this->staffUser->id)->latest('id')->first();
        $this->assertNotNull($notification, 'Expected notification to be delivered to assigned staff user.');

        $event = NotificationEvent::find($notification->event_id);
        $this->assertNotNull($event);
        $this->assertEquals('assignment_overdue', $event->type_code);
        $this->assertEquals($doc->id, $event->document_id);

        // 5. Test Duplicate Prevention: Re-run scheduler on subsequent tick
        $this->artisan('dms:check-due-dates')->assertExitCode(0);

        // Assert notification count for this user remains exactly 1
        $count = UserNotification::where('user_id', $this->staffUser->id)->count();
        $this->assertEquals(1, $count, 'Duplicate notifications were generated on subsequent scheduler run.');

        // Assert audit escalation count remains exactly 1
        $auditCount = AuditEvent::where('action', 'assignment.overdue_escalated')
            ->where('target_id', (string) $assignment->id)
            ->count();
        $this->assertEquals(1, $auditCount, 'Duplicate audit events were generated on subsequent scheduler run.');
    }
}
