<?php

namespace Tests\Feature;

use App\Models\AuthorizationScope;
use App\Models\ConfidentialityLevel;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserPermission;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTask;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Database\Seeders\WorkflowBaselineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Complete Workflow Lifecycle Integration Test
 *
 * Verifies end-to-end workflow execution against real API endpoints:
 * - Start workflow instance on a document version
 * - Complete task decisions through real lifecycle transitions
 * - Enforces anti-self-approval boundary (INV-04)
 * - Rejects invalid state transitions
 * - Enforces authorization boundaries
 * - Protects against duplicate/concurrent decisions
 */
class CompleteWorkflowLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $submitter;
    protected User $reviewer;
    protected User $unauthorizedUser;
    protected WorkflowDefinition $definition;
    protected Document $document;
    protected DocumentVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BaselineReferenceVocabularySeeder::class);
        $this->seed(WorkflowBaselineSeeder::class);

        \App\Models\WorkflowTransition::where('action_code', 'approve')->update(['prohibit_self_approval' => true]);

        $unit = OrganizationUnit::first();
        $category = DocumentCategory::first();
        $conf = ConfidentialityLevel::first();

        // 1. Submitter (has workflow.review and workflow.submit to start and submit workflows)
        $this->submitter = User::create([
            'name' => 'Workflow Submitter',
            'email' => 'workflow.submitter@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantGlobalPermission($this->submitter, 'workflow.review');
        $this->grantGlobalPermission($this->submitter, 'workflow.submit');

        // 2. Reviewer (independent officer with workflow.review and workflow.approve)
        $this->reviewer = User::create([
            'name' => 'Independent Reviewer',
            'email' => 'workflow.reviewer@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantGlobalPermission($this->reviewer, 'workflow.review');
        $this->grantGlobalPermission($this->reviewer, 'workflow.approve');

        // 3. Unauthorized User (has no workflow permissions)
        $this->unauthorizedUser = User::create([
            'name' => 'Unauthorized User',
            'email' => 'unauthorized.user@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        // Definition from WorkflowBaselineSeeder
        $this->definition = WorkflowDefinition::where('code', 'standard_doc_approval')->firstOrFail();

        // Document and Version
        $this->document = Document::create([
            'reference_number' => 'DOC-WF-' . uniqid(),
            'title' => 'Policy Approval Document',
            'summary' => 'Document for workflow integration tests',
            'category_id' => $category->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $unit->id,
            'created_by' => $this->submitter->id,
            'lifecycle' => 'active',
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
        ]);

        $this->version = DocumentVersion::create([
            'document_id' => $this->document->id,
            'version_number' => 1,
            'created_by' => $this->submitter->id,
            'comment' => 'Initial version for workflow lifecycle',
        ]);
    }

    private function grantGlobalPermission(User $user, string $permCode): void
    {
        $perm = Permission::where('code', $permCode)->first();
        if (! $perm) {
            $perm = Permission::create(['code' => $permCode, 'name' => $permCode, 'active' => 1]);
        }

        $globalScope = AuthorizationScope::firstOrCreate([
            'kind' => 'global',
            'unit_id' => null,
        ]);

        UserPermission::updateOrInsert(
            ['user_id' => $user->id, 'permission_id' => $perm->id, 'scope_id' => $globalScope->id],
            ['granted_by' => $this->submitter->id, 'granted_at' => now()]
        );
    }

    /**
     * Test complete happy-path workflow lifecycle: start -> submit -> approve -> completion.
     */
    public function test_complete_workflow_lifecycle_from_start_to_completion(): void
    {
        $this->authenticateAs($this->submitter);

        // 1. Start workflow instance
        $startResp = $this->postJson('/api/v1/workflows/instances', [
            'workflow_definition_id' => $this->definition->id,
            'document_version_id' => $this->version->id,
        ]);

        $startResp->assertStatus(201)
            ->assertJsonPath('success', true);

        $instanceId = $startResp->json('data.id');
        $this->assertNotNull($instanceId);

        // Find initial draft task
        $task1 = WorkflowTask::where('instance_id', $instanceId)->whereNull('closed_at')->firstOrFail();
        $this->assertEquals('draft', $task1->state->code);

        // 2. Submitter submits the draft task
        $submitResp = $this->postJson("/api/v1/workflows/tasks/{$task1->id}/decide", [
            'decision' => 'submit',
            'reason' => 'Submitting policy document for review.',
        ]);

        $submitResp->assertStatus(200)
            ->assertJsonPath('success', true);

        // Task 1 is closed
        $this->assertNotNull($task1->fresh()->closed_at);

        // New task spawned for 'under_review' state
        $task2 = WorkflowTask::where('instance_id', $instanceId)->whereNull('closed_at')->firstOrFail();
        $this->assertEquals('under_review', $task2->state->code);

        // 3. Independent Reviewer approves
        $this->authenticateAs($this->reviewer);
        $approveResp = $this->postJson("/api/v1/workflows/tasks/{$task2->id}/decide", [
            'decision' => 'approve',
            'reason' => 'Approved by independent reviewer.',
        ]);

        $approveResp->assertStatus(200)
            ->assertJsonPath('success', true);

        // Task 2 is closed
        $this->assertNotNull($task2->fresh()->closed_at);

        // Workflow completed: no remaining open tasks
        $openTasksCount = WorkflowTask::where('instance_id', $instanceId)->whereNull('closed_at')->count();
        $this->assertEquals(0, $openTasksCount);

        // Terminal audit event recorded
        $this->assertDatabaseHas('audit_events', [
            'action' => 'workflow.terminal_state_reached',
            'target_id' => (string) $instanceId,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'document.workflow_approved',
            'target_id' => (string) $this->document->id,
        ]);
    }

    /**
     * Required Case 1: Anti-Self-Approval Enforcement (INV-04)
     * The submitter/author cannot decide their own approval task; expect 422; task and instance state unchanged.
     */
    public function test_workflow_strictly_prohibits_self_approval(): void
    {
        $this->authenticateAs($this->submitter);

        // Start instance and submit initial draft
        $startResp = $this->postJson('/api/v1/workflows/instances', [
            'workflow_definition_id' => $this->definition->id,
            'document_version_id' => $this->version->id,
        ]);
        $startResp->assertStatus(201);
        $instanceId = $startResp->json('data.id');

        $task1 = WorkflowTask::where('instance_id', $instanceId)->whereNull('closed_at')->firstOrFail();
        $this->postJson("/api/v1/workflows/tasks/{$task1->id}/decide", ['decision' => 'submit'])->assertStatus(200);

        // Second task is under_review
        $task2 = WorkflowTask::where('instance_id', $instanceId)->whereNull('closed_at')->firstOrFail();
        $initialStateId = $task2->state_id;

        // Submitter attempts to self-approve
        $selfApproveResp = $this->postJson("/api/v1/workflows/tasks/{$task2->id}/decide", [
            'decision' => 'approve',
            'reason' => 'Attempting self-approval.',
        ]);

        $selfApproveResp->assertStatus(422)
            ->assertJsonPath('message', 'Anti-self-approval violation: Authors and submitting actors cannot approve their own documents or versions.');

        // Verify task state unchanged
        $freshTask2 = $task2->fresh();
        $this->assertNull($freshTask2->closed_at, 'Task must remain open after rejected self-approval');
        $this->assertEquals($initialStateId, $freshTask2->state_id);
        $this->assertEquals('under_review', $freshTask2->state->code);
    }

    /**
     * Required Case 2: Invalid Transition Rejection
     * Expect 422; state unchanged.
     */
    public function test_workflow_rejects_invalid_transition(): void
    {
        $this->authenticateAs($this->submitter);

        $startResp = $this->postJson('/api/v1/workflows/instances', [
            'workflow_definition_id' => $this->definition->id,
            'document_version_id' => $this->version->id,
        ]);
        $startResp->assertStatus(201);
        $instanceId = $startResp->json('data.id');

        $task1 = WorkflowTask::where('instance_id', $instanceId)->whereNull('closed_at')->firstOrFail();
        $initialStateId = $task1->state_id;

        // In draft state, only 'submit' is valid. Attempting 'approve' directly must be rejected.
        $invalidResp = $this->postJson("/api/v1/workflows/tasks/{$task1->id}/decide", [
            'decision' => 'approve',
            'reason' => 'Invalid direct approval from draft state.',
        ]);

        $invalidResp->assertStatus(422)
            ->assertJsonPath('message', "Invalid workflow transition: action 'approve' is not permitted from current state.");

        // Task state unchanged
        $this->assertNull($task1->fresh()->closed_at);
        $this->assertEquals($initialStateId, $task1->fresh()->state_id);
        $this->assertEquals('draft', $task1->fresh()->state->code);
    }

    /**
     * Required Case 3: Unauthorized User Rejection
     * Expect 403.
     */
    public function test_workflow_rejects_unauthorized_user(): void
    {
        $this->authenticateAs($this->submitter);

        $startResp = $this->postJson('/api/v1/workflows/instances', [
            'workflow_definition_id' => $this->definition->id,
            'document_version_id' => $this->version->id,
        ]);
        $startResp->assertStatus(201);
        $instanceId = $startResp->json('data.id');

        $task1 = WorkflowTask::where('instance_id', $instanceId)->whereNull('closed_at')->firstOrFail();

        // Unauthorized user attempts decision
        $this->authenticateAs($this->unauthorizedUser);
        $unauthResp = $this->postJson("/api/v1/workflows/tasks/{$task1->id}/decide", [
            'decision' => 'submit',
        ]);

        $unauthResp->assertStatus(403);

        // Task remains open and unmutated
        $this->assertNull($task1->fresh()->closed_at);
        $this->assertEquals('draft', $task1->fresh()->state->code);
    }

    /**
     * Required Case 4: Concurrent/Duplicate Decision Protection
     * The second decision is rejected with 422.
     */
    public function test_workflow_rejects_duplicate_decision(): void
    {
        $this->authenticateAs($this->submitter);

        $startResp = $this->postJson('/api/v1/workflows/instances', [
            'workflow_definition_id' => $this->definition->id,
            'document_version_id' => $this->version->id,
        ]);
        $startResp->assertStatus(201);
        $instanceId = $startResp->json('data.id');

        $task1 = WorkflowTask::where('instance_id', $instanceId)->whereNull('closed_at')->firstOrFail();
        $this->postJson("/api/v1/workflows/tasks/{$task1->id}/decide", ['decision' => 'submit'])->assertStatus(200);

        $task2 = WorkflowTask::where('instance_id', $instanceId)->whereNull('closed_at')->firstOrFail();

        // Reviewer approves task 2
        $this->authenticateAs($this->reviewer);
        $firstResp = $this->postJson("/api/v1/workflows/tasks/{$task2->id}/decide", [
            'decision' => 'approve',
            'reason' => 'First approval.',
        ]);
        $firstResp->assertStatus(200);

        // Second decision on the now-closed task is rejected
        $secondResp = $this->postJson("/api/v1/workflows/tasks/{$task2->id}/decide", [
            'decision' => 'approve',
            'reason' => 'Duplicate approval attempt.',
        ]);

        $secondResp->assertStatus(422)
            ->assertJsonPath('message', 'Workflow task has already been completed and closed.');
    }
}
