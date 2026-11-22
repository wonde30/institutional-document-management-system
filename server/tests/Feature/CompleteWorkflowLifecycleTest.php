<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\OrganizationUnit;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowState;
use App\Models\WorkflowTask;
use Database\Seeders\WorkflowBaselineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteWorkflowLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $staff;
    protected User $reviewer;
    protected User $unauthorized;
    protected OrganizationUnit $unit;
    protected DocumentCategory $category;
    protected \App\Models\ConfidentialityLevel $confidentiality;
    protected WorkflowDefinition $definition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorkflowBaselineSeeder::class);

        $this->unit = OrganizationUnit::first();
        $this->category = DocumentCategory::first();
        $this->confidentiality = \App\Models\ConfidentialityLevel::first();
        $this->definition = WorkflowDefinition::where('code', 'standard_doc_approval')->firstOrFail();

        $this->staff = User::create([
            'name' => 'Staff Workflow Author',
            'email' => 'staff.wf.' . uniqid() . '@wu.edu.et',
            'password' => bcrypt('StrongPass123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($this->staff, 'documents.create', 'unit', $this->unit->id);
        $this->grantPermissionToUser($this->staff, 'documents.view', 'unit', $this->unit->id);
        $this->grantPermissionToUser($this->staff, 'workflow.view', 'unit', $this->unit->id);
        $this->grantPermissionToUser($this->staff, 'workflow.submit', 'unit', $this->unit->id);
        $this->grantPermissionToUser($this->staff, 'workflow.review', 'unit', $this->unit->id);

        $this->reviewer = User::create([
            'name' => 'Department Head Reviewer',
            'email' => 'head.wf.' . uniqid() . '@wu.edu.et',
            'password' => bcrypt('StrongPass123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($this->reviewer, 'documents.view', 'unit', $this->unit->id);
        $this->grantPermissionToUser($this->reviewer, 'workflow.view', 'unit', $this->unit->id);
        $this->grantPermissionToUser($this->reviewer, 'workflow.review', 'unit', $this->unit->id);
        $this->grantPermissionToUser($this->reviewer, 'workflow.approve', 'unit', $this->unit->id);

        $this->unauthorized = User::create([
            'name' => 'Unauthorized User',
            'email' => 'unauth.wf.' . uniqid() . '@wu.edu.et',
            'password' => bcrypt('StrongPass123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
    }

    private function createDocumentWithVersion(User $author): array
    {
        $doc = Document::create([
            'reference_number' => 'DOC-WF-' . uniqid(),
            'title' => 'Curriculum Revision Proposal',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->unit->id,
            'created_by' => $author->id,
            'lifecycle' => 'active',
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
        ]);

        $ver = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $author->id,
            'comment' => 'Initial version for review',
        ]);

        return [$doc, $ver];
    }

    public function test_complete_workflow_lifecycle_from_start_to_final_approval(): void
    {
        [$doc, $ver] = $this->createDocumentWithVersion($this->staff);

        // 1. Staff starts workflow instance via real API
        $this->authenticateAs($this->staff);
        $startResp = $this->postJson('/api/v1/workflows/instances', [
            'workflow_definition_id' => $this->definition->id,
            'document_version_id' => $ver->id,
        ]);

        $startResp->assertStatus(201)
            ->assertJsonPath('success', true);

        $instanceId = $startResp->json('data.id');
        $this->assertNotNull($instanceId);

        // 2. Initial task is in 'draft' state
        $draftTask = WorkflowTask::where('instance_id', $instanceId)
            ->whereNull('closed_at')
            ->firstOrFail();

        // 3. Staff submits draft task -> advances to under_review
        $submitResp = $this->postJson("/api/v1/workflows/tasks/{$draftTask->id}/decide", [
            'decision' => 'submit',
            'reason' => 'Submitted for review',
        ]);
        $submitResp->assertStatus(200);

        $this->assertNotNull($draftTask->fresh()->closed_at);

        // 4. Next task is in 'under_review' state
        $reviewTask = WorkflowTask::where('instance_id', $instanceId)
            ->whereNull('closed_at')
            ->firstOrFail();

        // 5. Reviewer approves task -> advances to terminal 'approved' state
        $this->authenticateAs($this->reviewer);
        $approveResp = $this->postJson("/api/v1/workflows/tasks/{$reviewTask->id}/decide", [
            'decision' => 'approve',
            'reason' => 'Proposal is sound and approved.',
        ]);
        $approveResp->assertStatus(200);

        $this->assertNotNull($reviewTask->fresh()->closed_at);

        // 6. No more open tasks; terminal state reached
        $openTasks = WorkflowTask::where('instance_id', $instanceId)->whereNull('closed_at')->count();
        $this->assertSame(0, $openTasks);
    }

    public function test_self_approval_is_rejected_with_state_unchanged(): void
    {
        [$doc, $ver] = $this->createDocumentWithVersion($this->staff);

        $this->authenticateAs($this->staff);
        $startResp = $this->postJson('/api/v1/workflows/instances', [
            'workflow_definition_id' => $this->definition->id,
            'document_version_id' => $ver->id,
        ]);
        $startResp->assertStatus(201);
        $instanceId = $startResp->json('data.id');

        // Submit draft task
        $draftTask = WorkflowTask::where('instance_id', $instanceId)->whereNull('closed_at')->firstOrFail();
        $this->postJson("/api/v1/workflows/tasks/{$draftTask->id}/decide", [
            'decision' => 'submit',
        ])->assertStatus(200);

        // Under review task
        $reviewTask = WorkflowTask::where('instance_id', $instanceId)->whereNull('closed_at')->firstOrFail();
        $originalStateId = $reviewTask->state_id;

        // Staff (author of document & version) attempts to approve own document
        $selfApprovalResp = $this->postJson("/api/v1/workflows/tasks/{$reviewTask->id}/decide", [
            'decision' => 'approve',
            'reason' => 'Self-approving my own proposal',
        ]);

        $this->assertContains($selfApprovalResp->status(), [403, 422]);

        // State remains unchanged and task remains open
        $freshTask = $reviewTask->fresh();
        $this->assertNull($freshTask->closed_at);
        $this->assertSame($originalStateId, $freshTask->state_id);
    }

    public function test_invalid_transition_is_rejected_with_state_unchanged(): void
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
        $originalStateId = $draftTask->state_id;

        // In draft state, only 'submit' is valid. 'reject' is invalid from draft.
        $this->authenticateAs($this->reviewer);
        $invalidResp = $this->postJson("/api/v1/workflows/tasks/{$draftTask->id}/decide", [
            'decision' => 'reject',
            'reason' => 'Attempting invalid reject from draft state',
        ]);

        $invalidResp->assertStatus(422);

        // State remains unchanged and task remains open
        $freshTask = $draftTask->fresh();
        $this->assertNull($freshTask->closed_at);
        $this->assertSame($originalStateId, $freshTask->state_id);
    }

    public function test_unauthorized_user_gets_403(): void
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

        // Unauthorized user attempts to decide task
        $this->authenticateAs($this->unauthorized);
        $unauthResp = $this->postJson("/api/v1/workflows/tasks/{$draftTask->id}/decide", [
            'decision' => 'submit',
        ]);

        $unauthResp->assertStatus(403);
    }

    public function test_duplicate_decision_is_rejected(): void
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

        // Submit task first time
        $firstResp = $this->postJson("/api/v1/workflows/tasks/{$draftTask->id}/decide", [
            'decision' => 'submit',
        ]);
        $firstResp->assertStatus(200);

        // Attempt second decision on the same already-closed task
        $duplicateResp = $this->postJson("/api/v1/workflows/tasks/{$draftTask->id}/decide", [
            'decision' => 'submit',
        ]);

        $this->assertContains($duplicateResp->status(), [404, 422]);
    }
}
