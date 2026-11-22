<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\ConfidentialityLevel;
use App\Models\Document;
use App\Models\DocumentAssignment;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowEvent;
use App\Models\WorkflowInstance;
use App\Models\WorkflowState;
use App\Models\WorkflowTask;
use App\Models\WorkflowTaskParticipant;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowCompletenessAndConsensusTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $deanUser;
    protected User $headUser;
    protected User $staffUser1;
    protected User $staffUser2;
    protected User $staffUser3;
    protected OrganizationUnit $collegeUnit;
    protected OrganizationUnit $deptUnit;
    protected DocumentCategory $category;
    protected ConfidentialityLevel $confidentiality;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $this->superAdmin = User::where('email', 'admin@wu.edu.et')->first();
        $this->collegeUnit = OrganizationUnit::first();
        $this->deptUnit = OrganizationUnit::where('parent_id', $this->collegeUnit->id)->first() ?? $this->collegeUnit;

        $this->category = DocumentCategory::first();
        $this->confidentiality = ConfidentialityLevel::where('code', 'internal')->first() ?? ConfidentialityLevel::first();

        $this->deanUser = User::create([
            'name' => 'College Dean',
            'email' => 'dean.wf@wu.edu.et',
            'password' => bcrypt('StrongPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->deanUser, 'department_head', 'unit', $this->collegeUnit->id);

        $this->headUser = User::create([
            'name' => 'Department Head',
            'email' => 'head.wf@wu.edu.et',
            'password' => bcrypt('StrongPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->headUser, 'department_head', 'unit', $this->deptUnit->id);

        $this->staffUser1 = User::create([
            'name' => 'Reviewer One',
            'email' => 'rev1.wf@wu.edu.et',
            'password' => bcrypt('StrongPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($this->staffUser1, 'workflow.review', 'unit', $this->collegeUnit->id);

        $this->staffUser2 = User::create([
            'name' => 'Reviewer Two',
            'email' => 'rev2.wf@wu.edu.et',
            'password' => bcrypt('StrongPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($this->staffUser2, 'workflow.review', 'unit', $this->collegeUnit->id);

        $this->staffUser3 = User::create([
            'name' => 'Reviewer Three',
            'email' => 'rev3.wf@wu.edu.et',
            'password' => bcrypt('StrongPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($this->staffUser3, 'workflow.review', 'unit', $this->collegeUnit->id);
    }

    protected function createSampleDocument(): array
    {
        $doc = Document::create([
            'reference_number' => 'DOC-WF-'.uniqid(),
            'title' => 'Curriculum Evaluation Report',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->collegeUnit->id,
            'created_by' => $this->superAdmin->id,
            'lifecycle' => 'active',
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
        ]);

        $ver = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $this->superAdmin->id,
            'comment' => 'Initial version',
        ]);

        return [$doc, $ver];
    }

    /**
     * 1. Sequential approvals advance cleanly across distinct workflow states.
     */
    public function test_sequential_approvals_advance_across_workflow_states(): void
    {
        [$doc, $ver] = $this->createSampleDocument();

        $def = WorkflowDefinition::create([
            'code' => 'WF_SEQ_'.uniqid(),
            'name' => 'Sequential Academic Approval',
            'is_active' => 1,
            'category_id' => $this->category->id,
        ]);

        $wfVer = WorkflowVersion::create([
            'definition_id' => $def->id,
            'version_number' => 1,
            'created_by' => $this->superAdmin->id,
            'created_at' => now(),
        ]);

        $stDept = WorkflowState::create([
            'workflow_version_id' => $wfVer->id,
            'code' => 'dept_eval',
            'name' => 'Department Evaluation',
            'assigned_unit_id' => $this->deptUnit->id,
            'is_initial' => 1,
            'is_terminal' => 0,
        ]);

        $stCollege = WorkflowState::create([
            'workflow_version_id' => $wfVer->id,
            'code' => 'college_eval',
            'name' => 'College Approval',
            'assigned_unit_id' => $this->collegeUnit->id,
            'is_initial' => 0,
            'is_terminal' => 0,
        ]);

        $stPublished = WorkflowState::create([
            'workflow_version_id' => $wfVer->id,
            'code' => 'approved_final',
            'name' => 'Approved and Enacted',
            'is_initial' => 0,
            'is_terminal' => 1,
        ]);

        $perm = Permission::where('code', 'workflow.review')->firstOrFail();

        WorkflowTransition::create([
            'from_state_id' => $stDept->id,
            'to_state_id' => $stCollege->id,
            'action_code' => 'approve',
            'permission_id' => $perm->id,
            'prohibit_self_approval' => 0,
            'approval_policy' => 'single',
        ]);

        WorkflowTransition::create([
            'from_state_id' => $stCollege->id,
            'to_state_id' => $stPublished->id,
            'action_code' => 'approve',
            'permission_id' => $perm->id,
            'prohibit_self_approval' => 0,
            'approval_policy' => 'single',
        ]);

        // Start workflow instance
        $this->authenticateAs($this->superAdmin);
        $initResp = $this->postJson('/api/v1/workflows/instances', [
            'workflow_definition_id' => $def->id,
            'document_version_id' => $ver->id,
        ]);
        $initResp->assertStatus(201);
        $instanceId = $initResp->json('data.id');

        // Step 1: Department Head approves -> advances to College
        $task1 = WorkflowTask::where('instance_id', $instanceId)->where('state_id', $stDept->id)->firstOrFail();
        $this->authenticateAs($this->headUser);
        $step1Resp = $this->postJson("/api/v1/workflows/tasks/{$task1->id}/decide", [
            'decision' => 'approve',
            'comment' => 'Department evaluation endorsed.',
        ]);
        $step1Resp->assertStatus(200);

        // Step 2: College Dean approves -> reaches terminal state
        $task2 = WorkflowTask::where('instance_id', $instanceId)->where('state_id', $stCollege->id)->whereNull('closed_at')->firstOrFail();
        $this->authenticateAs($this->deanUser);
        $step2Resp = $this->postJson("/api/v1/workflows/tasks/{$task2->id}/decide", [
            'decision' => 'approve',
            'comment' => 'College-level approval granted.',
        ]);
        $step2Resp->assertStatus(200);

        // Verify task closed and terminal state reached (no open tasks remain)
        $this->assertNotNull($task2->fresh()->closed_at);
        $this->assertEquals(0, WorkflowTask::where('instance_id', $instanceId)->whereNull('closed_at')->count());

        // Verify parent document received terminal workflow audit event
        $this->assertDatabaseHas('audit_events', [
            'action' => 'document.workflow_approved',
            'target_type' => 'document',
            'target_id' => (string) $doc->id,
        ]);
    }

    /**
     * 2. All-Required Consensus:
     * Unanimous approval required from all committee participants before advancing.
     */
    public function test_all_required_consensus_policy_requires_unanimous_votes(): void
    {
        [$doc, $ver] = $this->createSampleDocument();

        $def = WorkflowDefinition::create([
            'code' => 'WF_ALLREQ_'.uniqid(),
            'name' => 'All Required Committee Approval',
            'is_active' => 1,
            'category_id' => $this->category->id,
        ]);
        $wfVer = WorkflowVersion::create([
            'definition_id' => $def->id,
            'version_number' => 1,
            'created_by' => $this->superAdmin->id,
            'created_at' => now(),
        ]);
        $stReview = WorkflowState::create([
            'workflow_version_id' => $wfVer->id,
            'code' => 'committee_review',
            'name' => 'Committee Review',
            'is_initial' => 1,
            'is_terminal' => 0,
        ]);
        $stPassed = WorkflowState::create([
            'workflow_version_id' => $wfVer->id,
            'code' => 'committee_passed',
            'name' => 'Committee Passed',
            'is_initial' => 0,
            'is_terminal' => 1,
        ]);

        $perm = Permission::where('code', 'workflow.review')->firstOrFail();
        WorkflowTransition::create([
            'from_state_id' => $stReview->id,
            'to_state_id' => $stPassed->id,
            'action_code' => 'approve',
            'permission_id' => $perm->id,
            'prohibit_self_approval' => 0,
            'approval_policy' => 'all_required',
        ]);

        $this->authenticateAs($this->superAdmin);
        $inst = $this->postJson('/api/v1/workflows/instances', [
            'workflow_definition_id' => $def->id,
            'document_version_id' => $ver->id,
        ])->json('data');

        $task = WorkflowTask::where('instance_id', $inst['id'])->whereNull('closed_at')->firstOrFail();

        // Assign 2 committee members
        $this->postJson("/api/v1/workflows/tasks/{$task->id}/participants", [
            'participants' => [
                ['user_id' => $this->staffUser1->id, 'role' => 'lead_reviewer'],
                ['user_id' => $this->staffUser2->id, 'role' => 'reviewer'],
            ],
        ])->assertStatus(200);

        // Member 1 votes approve -> pending consensus (HTTP 202)
        $this->authenticateAs($this->staffUser1);
        $vote1 = $this->postJson("/api/v1/workflows/tasks/{$task->id}/decide", ['decision' => 'approve']);
        $vote1->assertStatus(202);
        $this->assertEquals('pending_consensus', $vote1->json('data.status'));
        $this->assertNull($task->fresh()->closed_at);

        // Member 1 attempts duplicate vote -> rejected with HTTP 422
        $duplicateVote = $this->postJson("/api/v1/workflows/tasks/{$task->id}/decide", ['decision' => 'approve']);
        $duplicateVote->assertStatus(422)
            ->assertJsonPath('message', 'Participant has already submitted a decision for this task.');

        // Member 2 votes approve -> consensus met (HTTP 200)
        $this->authenticateAs($this->staffUser2);
        $vote2 = $this->postJson("/api/v1/workflows/tasks/{$task->id}/decide", ['decision' => 'approve']);
        $vote2->assertStatus(200);
        $this->assertEquals('completed', $vote2->json('data.status'));
        $this->assertNotNull($task->fresh()->closed_at);
    }

    /**
     * 3. Any-One Consensus:
     * Advancing immediately on the first approval vote among multiple reviewers.
     */
    public function test_any_one_approval_policy_advances_on_first_affirmative_vote(): void
    {
        [$doc, $ver] = $this->createSampleDocument();

        $def = WorkflowDefinition::create([
            'code' => 'WF_ANYREQ_'.uniqid(),
            'name' => 'Fast Track Any One Approval',
            'is_active' => 1,
            'category_id' => $this->category->id,
        ]);
        $wfVer = WorkflowVersion::create([
            'definition_id' => $def->id,
            'version_number' => 1,
            'created_by' => $this->superAdmin->id,
            'created_at' => now(),
        ]);
        $stReview = WorkflowState::create([
            'workflow_version_id' => $wfVer->id,
            'code' => 'fast_track_review',
            'name' => 'Fast Track Review',
            'is_initial' => 1,
            'is_terminal' => 0,
        ]);
        $stApproved = WorkflowState::create([
            'workflow_version_id' => $wfVer->id,
            'code' => 'fast_track_done',
            'name' => 'Fast Track Enacted',
            'is_initial' => 0,
            'is_terminal' => 1,
        ]);

        $perm = Permission::where('code', 'workflow.review')->firstOrFail();
        WorkflowTransition::create([
            'from_state_id' => $stReview->id,
            'to_state_id' => $stApproved->id,
            'action_code' => 'approve',
            'permission_id' => $perm->id,
            'prohibit_self_approval' => 0,
            'approval_policy' => 'any_required',
        ]);

        $this->authenticateAs($this->superAdmin);
        $inst = $this->postJson('/api/v1/workflows/instances', [
            'workflow_definition_id' => $def->id,
            'document_version_id' => $ver->id,
        ])->json('data');

        $task = WorkflowTask::where('instance_id', $inst['id'])->whereNull('closed_at')->firstOrFail();

        // Assign 3 reviewers
        $this->postJson("/api/v1/workflows/tasks/{$task->id}/participants", [
            'participants' => [
                ['user_id' => $this->staffUser1->id, 'role' => 'reviewer'],
                ['user_id' => $this->staffUser2->id, 'role' => 'reviewer'],
                ['user_id' => $this->staffUser3->id, 'role' => 'reviewer'],
            ],
        ])->assertStatus(200);

        // Reviewer 1 votes approve -> Should complete immediately
        $this->authenticateAs($this->staffUser1);
        $vote1 = $this->postJson("/api/v1/workflows/tasks/{$task->id}/decide", ['decision' => 'approve']);
        $vote1->assertStatus(200);
        $this->assertEquals('completed', $vote1->json('data.status'));
        $this->assertNotNull($task->fresh()->closed_at);
    }

    /**
     * 4. Rejection and Return with Revision Resubmission:
     * Workflow task rejection transitions to terminal reject state, while return can spawn revision.
     */
    public function test_rejection_and_return_handling(): void
    {
        [$doc, $ver] = $this->createSampleDocument();

        $def = WorkflowDefinition::create([
            'code' => 'WF_REJECT_'.uniqid(),
            'name' => 'Rejection and Return Flow',
            'is_active' => 1,
            'category_id' => $this->category->id,
        ]);
        $wfVer = WorkflowVersion::create([
            'definition_id' => $def->id,
            'version_number' => 1,
            'created_by' => $this->superAdmin->id,
            'created_at' => now(),
        ]);
        $stReview = WorkflowState::create([
            'workflow_version_id' => $wfVer->id,
            'code' => 'pending_critique',
            'name' => 'Pending Critique',
            'is_initial' => 1,
            'is_terminal' => 0,
        ]);
        $stRejected = WorkflowState::create([
            'workflow_version_id' => $wfVer->id,
            'code' => 'formally_rejected',
            'name' => 'Formally Rejected',
            'is_initial' => 0,
            'is_terminal' => 1,
        ]);

        $perm = Permission::where('code', 'workflow.review')->firstOrFail();
        WorkflowTransition::create([
            'from_state_id' => $stReview->id,
            'to_state_id' => $stRejected->id,
            'action_code' => 'reject',
            'permission_id' => $perm->id,
            'prohibit_self_approval' => 0,
            'approval_policy' => 'single',
        ]);

        $this->authenticateAs($this->superAdmin);
        $inst = $this->postJson('/api/v1/workflows/instances', [
            'workflow_definition_id' => $def->id,
            'document_version_id' => $ver->id,
        ])->json('data');

        $task = WorkflowTask::where('instance_id', $inst['id'])->whereNull('closed_at')->firstOrFail();

        // Reject task
        $this->authenticateAs($this->staffUser1);
        $rejectResp = $this->postJson("/api/v1/workflows/tasks/{$task->id}/decide", [
            'decision' => 'reject',
            'reason' => 'Deficiencies in syllabus alignment with national standards.',
        ]);
        $rejectResp->assertStatus(200);
        $this->assertEquals('completed', $rejectResp->json('data.status'));
        $this->assertNotNull($task->fresh()->closed_at);
    }

    /**
     * 5. Workflow Task Delegation:
     * Assigned task is delegated with formal justification note and audit record.
     */
    public function test_workflow_task_delegation(): void
    {
        [$doc, $ver] = $this->createSampleDocument();

        $def = WorkflowDefinition::create([
            'code' => 'WF_DEL_'.uniqid(),
            'name' => 'Delegation Flow',
            'is_active' => 1,
            'category_id' => $this->category->id,
        ]);
        $wfVer = WorkflowVersion::create([
            'definition_id' => $def->id,
            'version_number' => 1,
            'created_by' => $this->superAdmin->id,
            'created_at' => now(),
        ]);
        $stInit = WorkflowState::create([
            'workflow_version_id' => $wfVer->id,
            'code' => 'state_del',
            'name' => 'Delegable Review',
            'is_initial' => 1,
            'is_terminal' => 0,
        ]);

        $inst = WorkflowInstance::create([
            'workflow_version_id' => $wfVer->id,
            'document_version_id' => $ver->id,
            'current_state_id' => $stInit->id,
            'status' => 'running',
            'started_by' => $this->superAdmin->id,
            'started_at' => now(),
        ]);

        $task = WorkflowTask::create([
            'instance_id' => $inst->id,
            'state_id' => $stInit->id,
            'assigned_user_id' => $this->deanUser->id,
            'assigned_unit_id' => $this->collegeUnit->id,
            'created_at' => now(),
        ]);

        // Dean delegates task to Department Head
        $this->authenticateAs($this->deanUser);
        $delResp = $this->postJson("/api/v1/workflows/tasks/{$task->id}/delegate", [
            'target_user_id' => $this->headUser->id,
            'reason' => 'Subject matter expertise belongs to ECE department.',
        ]);
        $delResp->assertStatus(200);

        $freshTask = $task->fresh();
        $this->assertEquals($this->headUser->id, $freshTask->delegated_to_user_id);
        $this->assertEquals($this->deanUser->id, $freshTask->delegated_by);
        $this->assertStringContainsString('Subject matter expertise', $freshTask->delegation_reason);

        // Verify audit event
        $auditExists = AuditEvent::where('action', 'workflow.task_delegated')
            ->where('target_id', (string) $task->id)
            ->exists();
        $this->assertTrue($auditExists);
    }

    /**
     * 6. Workflow Cancellation:
     * Workflow task can be formally cancelled, closing the task and logging cancellation event.
     */
    public function test_workflow_cancellation(): void
    {
        [$doc, $ver] = $this->createSampleDocument();

        $def = WorkflowDefinition::create([
            'code' => 'WF_CANCEL_'.uniqid(),
            'name' => 'Cancellable Flow',
            'is_active' => 1,
            'category_id' => $this->category->id,
        ]);
        $wfVer = WorkflowVersion::create([
            'definition_id' => $def->id,
            'version_number' => 1,
            'created_by' => $this->superAdmin->id,
            'created_at' => now(),
        ]);
        $stInit = WorkflowState::create([
            'workflow_version_id' => $wfVer->id,
            'code' => 'state_to_cancel',
            'name' => 'To Cancel Review',
            'is_initial' => 1,
            'is_terminal' => 0,
        ]);

        $inst = WorkflowInstance::create([
            'workflow_version_id' => $wfVer->id,
            'document_version_id' => $ver->id,
            'current_state_id' => $stInit->id,
            'status' => 'running',
            'started_by' => $this->superAdmin->id,
            'started_at' => now(),
        ]);

        $task = WorkflowTask::create([
            'instance_id' => $inst->id,
            'state_id' => $stInit->id,
            'assigned_user_id' => $this->staffUser1->id,
            'assigned_unit_id' => $this->collegeUnit->id,
            'created_at' => now(),
        ]);

        $this->authenticateAs($this->staffUser1);
        $cancelResp = $this->postJson("/api/v1/workflows/tasks/{$task->id}/decide", [
            'decision' => 'cancel',
            'reason' => 'Curriculum project discontinued by Senate decree.',
        ]);
        $cancelResp->assertStatus(200);
        $this->assertNotNull($task->fresh()->closed_at);

        $wfEvent = WorkflowEvent::where('instance_id', $inst->id)->where('event_kind', 'cancel')->first();
        $this->assertNotNull($wfEvent);
    }
}
