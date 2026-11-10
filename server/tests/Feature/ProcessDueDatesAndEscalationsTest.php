<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\ConfidentialityLevel;
use App\Models\Document;
use App\Models\DocumentAssignment;
use App\Models\DocumentCategory;
use App\Models\DocumentMovement;
use App\Models\DocumentVersion;
use App\Models\MovementReceipt;
use App\Models\OrganizationUnit;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowState;
use App\Models\WorkflowTask;
use App\Models\WorkflowVersion;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProcessDueDatesAndEscalationsTest extends TestCase
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

    private function createTestDocument(): Document
    {
        return Document::create([
            'reference_number' => 'DOC-ESC-' . uniqid(),
            'title' => 'Escalation Test Document',
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

    public function test_scans_and_escalates_overdue_assignment(): void
    {
        $doc = $this->createTestDocument();

        // 1. Create an overdue assignment (due 3 days ago)
        $assignment = DocumentAssignment::create([
            'document_id' => $doc->id,
            'assigned_user_id' => $this->staffUser->id,
            'assigned_unit_id' => $this->unit->id,
            'assigned_by' => $this->adminUser->id,
            'assigned_at' => now()->subDays(5),
            'due_at' => now()->subDays(3),
            'instruction' => 'Urgent action required on ministerial circular',
        ]);

        // Run artisan command
        $this->artisan('dms:check-due-dates')
            ->expectsOutputToContain('Found 1 overdue assignment(s)')
            ->assertExitCode(0);

        // Verify audit event recorded
        $this->assertDatabaseHas('audit_events', [
            'action' => 'assignment.overdue_escalated',
            'target_type' => 'document_assignment',
            'target_id' => (string) $assignment->id,
        ]);

        // Verify notification delivered to staff user
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $this->staffUser->id,
        ]);
    }

    public function test_notifications_and_escalations_are_not_duplicated_on_multiple_runs(): void
    {
        $doc = $this->createTestDocument();

        $assignment = DocumentAssignment::create([
            'document_id' => $doc->id,
            'assigned_user_id' => $this->staffUser->id,
            'assigned_unit_id' => $this->unit->id,
            'assigned_by' => $this->adminUser->id,
            'assigned_at' => now()->subDays(5),
            'due_at' => now()->subDays(3),
            'instruction' => 'Urgent action required on ministerial circular',
        ]);

        // First execution
        $this->artisan('dms:check-due-dates')->assertExitCode(0);

        $initialAuditCount = AuditEvent::where('action', 'assignment.overdue_escalated')
            ->where('target_id', (string) $assignment->id)
            ->count();
        $initialNotificationCount = UserNotification::where('user_id', $this->staffUser->id)->count();

        $this->assertEquals(1, $initialAuditCount);
        $this->assertEquals(1, $initialNotificationCount);

        // Second execution (e.g. next hourly schedule tick)
        $this->artisan('dms:check-due-dates')->assertExitCode(0);

        // Counts must remain exactly 1 (no duplicate spamming)
        $this->assertEquals(1, AuditEvent::where('action', 'assignment.overdue_escalated')
            ->where('target_id', (string) $assignment->id)
            ->count());
        $this->assertEquals(1, UserNotification::where('user_id', $this->staffUser->id)->count());
    }

    public function test_dry_run_does_not_persist_escalations(): void
    {
        $doc = $this->createTestDocument();

        $assignment = DocumentAssignment::create([
            'document_id' => $doc->id,
            'assigned_user_id' => $this->staffUser->id,
            'assigned_unit_id' => $this->unit->id,
            'assigned_by' => $this->adminUser->id,
            'assigned_at' => now()->subDays(5),
            'due_at' => now()->subDays(2),
            'instruction' => 'Dry run test assignment',
        ]);

        $this->artisan('dms:check-due-dates', ['--dry-run' => true])
            ->expectsOutputToContain('[DRY RUN]')
            ->expectsOutputToContain('Found 1 overdue assignment(s)')
            ->assertExitCode(0);

        // No audit event should be created for this assignment
        $this->assertDatabaseMissing('audit_events', [
            'action' => 'assignment.overdue_escalated',
            'target_id' => (string) $assignment->id,
        ]);
    }

    public function test_scans_and_escalates_overdue_workflow_task(): void
    {
        $doc = $this->createTestDocument();

        $version = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'change_note' => 'Initial Version',
            'created_by' => $this->adminUser->id,
            'created_at' => now(),
        ]);

        $wfDef = WorkflowDefinition::firstOrCreate(
            ['name' => 'Overdue Escalation Workflow'],
            ['code' => 'OVERDUE_ESC_WF', 'active' => 1]
        );

        $wfVersion = WorkflowVersion::create([
            'definition_id' => $wfDef->id,
            'version_number' => 1,
            'created_by' => $this->adminUser->id,
            'published_at' => now(),
            'created_at' => now(),
        ]);

        $state = WorkflowState::create([
            'workflow_version_id' => $wfVersion->id,
            'code' => 'stg_pending_review',
            'name' => 'Pending Review',
            'is_initial' => true,
            'is_terminal' => false,
        ]);

        $instance = WorkflowInstance::create([
            'document_version_id' => $version->id,
            'workflow_version_id' => $wfVersion->id,
            'started_by' => $this->adminUser->id,
            'started_at' => now()->subDays(10),
        ]);

        $task = WorkflowTask::create([
            'instance_id' => $instance->id,
            'state_id' => $state->id,
            'assigned_user_id' => $this->staffUser->id,
            'assigned_unit_id' => $this->unit->id,
            'created_at' => now()->subDays(10),
            'due_at' => now()->subDays(2),
        ]);

        $this->artisan('dms:check-due-dates')
            ->expectsOutputToContain('Found 1 overdue workflow task(s)')
            ->assertExitCode(0);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'workflow.task_overdue_escalated',
            'target_type' => 'workflow_task',
            'target_id' => (string) $task->id,
        ]);
    }

    public function test_scans_and_escalates_overdue_document_movement(): void
    {
        $doc = $this->createTestDocument();
        $targetUnit = OrganizationUnit::where('id', '!=', $this->unit->id)->first()
            ?? OrganizationUnit::create([
                'code' => 'TEST_UNIT',
                'name' => 'Secondary Test Unit',
                'unit_type_id' => $this->unit->unit_type_id,
                'active' => 1,
            ]);

        $mov = DocumentMovement::create([
            'document_id' => $doc->id,
            'from_unit_id' => $this->unit->id,
            'to_unit_id' => $targetUnit->id,
            'sent_by' => $this->adminUser->id,
            'sent_at' => now()->subDays(10),
            'instruction' => 'Urgent transfer of physical case file',
            'due_at' => now()->subDays(4),
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $this->artisan('dms:check-due-dates')
            ->expectsOutputToContain('Found 1 overdue document movement(s)')
            ->assertExitCode(0);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'movement.overdue_escalated',
            'target_type' => 'document_movement',
            'target_id' => (string) $mov->id,
        ]);
    }
}
