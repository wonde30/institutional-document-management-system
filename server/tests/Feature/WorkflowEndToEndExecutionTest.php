<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ConfidentialityLevel;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\OrganizationUnit;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowState;
use App\Models\WorkflowTask;
use App\Models\WorkflowTransition;
use Database\Seeders\WorkflowBaselineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowEndToEndExecutionTest extends TestCase
{
    use RefreshDatabase;

    protected User $staff;
    protected User $deptHead;
    protected User $delegateUser;
    protected User $unauthorized;
    protected OrganizationUnit $seUnit;
    protected DocumentCategory $category;
    protected ConfidentialityLevel $confidentiality;
    protected WorkflowDefinition $definition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorkflowBaselineSeeder::class);

        WorkflowTransition::where('action_code', 'approve')->update(['prohibit_self_approval' => true]);

        $this->seUnit = OrganizationUnit::first();
        $this->category = DocumentCategory::first();
        $this->confidentiality = ConfidentialityLevel::first();
        $this->definition = WorkflowDefinition::where('code', 'standard_doc_approval')->firstOrFail();

        $this->staff = User::create([
            'name' => 'Staff Member',
            'email' => 'staff.e2e.' . uniqid() . '@wu.edu.et',
            'password' => bcrypt('StrongPass123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($this->staff, 'documents.create', 'unit', $this->seUnit->id);
        $this->grantPermissionToUser($this->staff, 'documents.view', 'unit', $this->seUnit->id);
        $this->grantPermissionToUser($this->staff, 'workflow.view', 'unit', $this->seUnit->id);
        $this->grantPermissionToUser($this->staff, 'workflow.submit', 'unit', $this->seUnit->id);
        $this->grantPermissionToUser($this->staff, 'workflow.review', 'unit', $this->seUnit->id);

        $this->deptHead = User::create([
            'name' => 'Department Head',
            'email' => 'head.e2e.' . uniqid() . '@wu.edu.et',
            'password' => bcrypt('StrongPass123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($this->deptHead, 'documents.view', 'unit', $this->seUnit->id);
        $this->grantPermissionToUser($this->deptHead, 'workflow.view', 'unit', $this->seUnit->id);
        $this->grantPermissionToUser($this->deptHead, 'workflow.review', 'unit', $this->seUnit->id);
        $this->grantPermissionToUser($this->deptHead, 'workflow.approve', 'unit', $this->seUnit->id);

        $this->delegateUser = User::create([
            'name' => 'Delegate Reviewer',
            'email' => 'delegate.e2e.' . uniqid() . '@wu.edu.et',
            'password' => bcrypt('StrongPass123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($this->delegateUser, 'workflow.view', 'unit', $this->seUnit->id);
        $this->grantPermissionToUser($this->delegateUser, 'workflow.review', 'unit', $this->seUnit->id);

        $this->unauthorized = User::create([
            'name' => 'Unauthorized User',
            'email' => 'unauth.e2e.' . uniqid() . '@wu.edu.et',
            'password' => bcrypt('StrongPass123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
    }

    private function createDocumentWithVersion(User $author): array
    {
        $doc = Document::create([
            'reference_number' => 'DOC-E2E-' . uniqid(),
            'title' => 'Software Engineering Policy Manual',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->seUnit->id,
            'created_by' => $author->id,
            'lifecycle' => 'active',
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
        ]);

        $ver = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $author->id,
            'comment' => 'Initial upload for committee review',
        ]);

        return [$doc, $ver];
    }

    public function test_complete_workflow_execution_through_api(): void
    {
        // 1. Verify definitions endpoint
        $this->authenticateAs($this->staff);
        $defResp = $this->getJson('/api/v1/workflows/definitions');
        $defResp->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertNotEmpty($defResp->json('data'));

        // 2. Create document and start workflow instance
        [$doc, $ver] = $this->createDocumentWithVersion($this->staff);

        $startResp = $this->postJson('/api/v1/workflows/instances', [
            'workflow_definition_id' => $this->definition->id,
            'document_version_id' => $ver->id,
        ]);
        $startResp->assertStatus(201)
            ->assertJsonPath('success', true);

        $instanceId = $startResp->json('data.id');
        $this->assertNotNull($instanceId);

        // 3. Draft task exists and is submitted
        $draftTask = WorkflowTask::where('instance_id', $instanceId)->whereNull('closed_at')->firstOrFail();
        $submitResp = $this->postJson("/api/v1/workflows/tasks/{$draftTask->id}/decide", [
            'decision' => 'submit',
            'reason' => 'Submitting policy document for review',
        ]);
        $submitResp->assertStatus(200);

        // 4. Verify task list endpoint
        $this->authenticateAs($this->deptHead);
        $tasksResp = $this->getJson('/api/v1/workflows/tasks?status=open&scope=office');
        $tasksResp->assertStatus(200)
            ->assertJsonPath('success', true);

        // 5. Department head approves the under-review task
        $reviewTask = WorkflowTask::where('instance_id', $instanceId)->whereNull('closed_at')->firstOrFail();
        $approveResp = $this->postJson("/api/v1/workflows/tasks/{$reviewTask->id}/decide", [
            'decision' => 'approve',
            'reason' => 'Policy endorsed and approved',
        ]);
        $approveResp->assertStatus(200);

        // 6. Workflow is complete: all tasks closed
        $this->assertNotNull($reviewTask->fresh()->closed_at);
        $remainingOpen = WorkflowTask::where('instance_id', $instanceId)->whereNull('closed_at')->count();
        $this->assertSame(0, $remainingOpen);
    }

    public function test_workflow_rejects_unauthorized_user(): void
    {
        [$doc, $ver] = $this->createDocumentWithVersion($this->staff);

        $this->authenticateAs($this->staff);
        $startResp = $this->postJson('/api/v1/workflows/instances', [
            'workflow_definition_id' => $this->definition->id,
            'document_version_id' => $ver->id,
        ]);
        $startResp->assertStatus(201);
        $instanceId = $startResp->json('data.id');

        $task = WorkflowTask::where('instance_id', $instanceId)->whereNull('closed_at')->firstOrFail();

        $this->authenticateAs($this->unauthorized);
        $decideResp = $this->postJson("/api/v1/workflows/tasks/{$task->id}/decide", [
            'decision' => 'submit',
        ]);

        $decideResp->assertStatus(403);
    }

    public function test_workflow_prevents_self_approval(): void
    {
        [$doc, $ver] = $this->createDocumentWithVersion($this->staff);

        $this->authenticateAs($this->staff);
        $startResp = $this->postJson('/api/v1/workflows/instances', [
            'workflow_definition_id' => $this->definition->id,
            'document_version_id' => $ver->id,
        ]);
        $startResp->assertStatus(201);
        $instanceId = $startResp->json('data.id');

        $draftTask = WorkflowTask::where('instance_id', $instanceId)->whereNull('closed_at')->firstOrFail();
        $this->postJson("/api/v1/workflows/tasks/{$draftTask->id}/decide", ['decision' => 'submit'])->assertStatus(200);

        $reviewTask = WorkflowTask::where('instance_id', $instanceId)->whereNull('closed_at')->firstOrFail();

        // Staff (author) attempts to approve own document
        $selfApproveResp = $this->postJson("/api/v1/workflows/tasks/{$reviewTask->id}/decide", [
            'decision' => 'approve',
            'reason' => 'Approving my own document',
        ]);

        $selfApproveResp->assertStatus(422);
        $this->assertNull($reviewTask->fresh()->closed_at);
    }

    public function test_approve_transition_with_flag_false_allows_self_approval(): void
    {
        [$doc, $ver] = $this->createDocumentWithVersion($this->staff);

        $this->authenticateAs($this->staff);
        $startResp = $this->postJson('/api/v1/workflows/instances', [
            'workflow_definition_id' => $this->definition->id,
            'document_version_id' => $ver->id,
        ]);
        $startResp->assertStatus(201);
        $instanceId = $startResp->json('data.id');

        // Submit to move to under_review
        $draftTask = WorkflowTask::where('instance_id', $instanceId)->whereNull('closed_at')->firstOrFail();
        $this->postJson("/api/v1/workflows/tasks/{$draftTask->id}/decide", ['decision' => 'submit'])->assertStatus(200);

        $reviewTask = WorkflowTask::where('instance_id', $instanceId)->whereNull('closed_at')->firstOrFail();
        
        // Temporarily set the transition's prohibit_self_approval to false
        $transition = WorkflowTransition::where('action_code', 'approve')
            ->whereHas('fromState.workflowVersion', function ($q) {
                $q->where('definition_id', $this->definition->id);
            })
            ->first();
        $originalValue = $transition->prohibit_self_approval;
        $transition->update(['prohibit_self_approval' => false]);

        try {
            // Staff (author) attempts to approve - should be ALLOWED when flag is explicitly false
            $decideResp = $this->postJson("/api/v1/workflows/tasks/{$reviewTask->id}/decide", [
                'decision' => 'approve',
                'reason' => 'Self-approval allowed when flag is explicitly false',
            ]);

            $decideResp->assertStatus(200);
            $this->assertNotNull($reviewTask->fresh()->closed_at);
        } finally {
            // Restore original value
            $transition->update(['prohibit_self_approval' => $originalValue]);
        }
    }

    public function test_workflow_task_can_be_delegated(): void
    {
        [$doc, $ver] = $this->createDocumentWithVersion($this->staff);

        $this->authenticateAs($this->staff);
        $startResp = $this->postJson('/api/v1/workflows/instances', [
            'workflow_definition_id' => $this->definition->id,
            'document_version_id' => $ver->id,
        ]);
        $startResp->assertStatus(201);
        $instanceId = $startResp->json('data.id');

        $this->postJson("/api/v1/workflows/tasks/" . WorkflowTask::where('instance_id', $instanceId)->whereNull('closed_at')->firstOrFail()->id . "/decide", [
            'decision' => 'submit',
        ])->assertStatus(200);

        $reviewTask = WorkflowTask::where('instance_id', $instanceId)->whereNull('closed_at')->firstOrFail();
        $reviewTask->update(['assigned_user_id' => $this->deptHead->id]);

        // Department head delegates task to delegateUser
        $this->authenticateAs($this->deptHead);
        $delegateResp = $this->postJson("/api/v1/workflows/tasks/{$reviewTask->id}/delegate", [
            'target_user_id' => $this->delegateUser->id,
            'reason' => 'Delegating review during academic travel',
        ]);

        $delegateResp->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSame($this->delegateUser->id, $reviewTask->fresh()->delegated_to_user_id);
    }
}
