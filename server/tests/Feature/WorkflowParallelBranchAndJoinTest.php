<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\ConfidentialityLevel;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowState;
use App\Models\WorkflowTask;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Services\WorkflowEngineService;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Tests\TestCase;

class WorkflowParallelBranchAndJoinTest extends TestCase
{
    private User $admin;
    private User $reviewer1;
    private User $reviewer2;
    private Document $document;
    private DocumentVersion $version;
    private WorkflowDefinition $definition;
    private WorkflowVersion $wfVersion;
    private WorkflowState $initialState;
    private WorkflowState $legalBranchState;
    private WorkflowState $financeBranchState;
    private WorkflowState $finalApprovalState;
    private int $permissionId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BaselineReferenceVocabularySeeder::class);

        $unit = OrganizationUnit::first();
        if (! $unit) {
            $unitTypeId = \Illuminate\Support\Facades\DB::table('unit_types')->value('id') ?? 1;
            $unit = OrganizationUnit::create([
                'code' => 'TEST_UNIT',
                'name_en' => 'Test Academic Unit',
                'unit_type_id' => $unitTypeId,
            ]);
        }

        $this->admin = User::firstOrCreate(
            ['email' => 'admin_wf@wu.edu.et'],
            ['name' => 'Admin User', 'password' => bcrypt('password'), 'primary_unit_id' => $unit->id]
        );
        $this->assignRoleToUser($this->admin, 'admin');

        $this->reviewer1 = User::firstOrCreate(
            ['email' => 'rev1@wu.edu.et'],
            ['name' => 'Legal Officer', 'password' => bcrypt('password'), 'primary_unit_id' => $unit->id]
        );
        $this->assignRoleToUser($this->reviewer1, 'staff');
        $this->grantPermissionToUser($this->reviewer1, 'workflow.review');

        $this->reviewer2 = User::firstOrCreate(
            ['email' => 'rev2@wu.edu.et'],
            ['name' => 'Finance Officer', 'password' => bcrypt('password'), 'primary_unit_id' => $unit->id]
        );
        $this->assignRoleToUser($this->reviewer2, 'staff');
        $this->grantPermissionToUser($this->reviewer2, 'workflow.review');

        $this->permissionId = Permission::first()->id;

        $category = DocumentCategory::first();
        $confidentiality = ConfidentialityLevel::first();

        $this->document = Document::create([
            'reference_number' => 'DOC-WF-' . uniqid(),
            'title' => 'Test Parallel Workflow Document',
            'category_id' => $category?->id ?? 1,
            'confidentiality_id' => $confidentiality?->id ?? 1,
            'owner_unit_id' => $unit->id,
            'created_by' => $this->admin->id,
            'lifecycle' => 'active',
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
        ]);

        $this->version = DocumentVersion::create([
            'document_id' => $this->document->id,
            'version_number' => 1,
            'created_by' => $this->admin->id,
            'comment' => 'Initial version',
        ]);

        $this->definition = WorkflowDefinition::create([
            'code' => 'PARALLEL_TEST_WF_' . rand(100, 999),
            'name' => 'Parallel Approval Workflow',
            'category_id' => $category?->id ?? 1,
            'is_active' => true,
        ]);

        $this->wfVersion = WorkflowVersion::create([
            'definition_id' => $this->definition->id,
            'version_number' => 1,
            'created_by' => $this->admin->id,
            'created_at' => now(),
        ]);

        $this->initialState = WorkflowState::create([
            'workflow_version_id' => $this->wfVersion->id,
            'code' => 'st_initial_' . rand(100, 999),
            'name' => 'Initial Review',
            'is_initial' => 1,
            'is_terminal' => 0,
        ]);

        $this->legalBranchState = WorkflowState::create([
            'workflow_version_id' => $this->wfVersion->id,
            'code' => 'st_legal_' . rand(100, 999),
            'name' => 'Legal Compliance Review',
            'is_initial' => 0,
            'is_terminal' => 0,
        ]);

        $this->financeBranchState = WorkflowState::create([
            'workflow_version_id' => $this->wfVersion->id,
            'code' => 'st_finance_' . rand(100, 999),
            'name' => 'Finance Compliance Review',
            'is_initial' => 0,
            'is_terminal' => 0,
        ]);

        $this->finalApprovalState = WorkflowState::create([
            'workflow_version_id' => $this->wfVersion->id,
            'code' => 'st_final_' . rand(100, 999),
            'name' => 'Final Executive Approval',
            'is_initial' => 0,
            'is_terminal' => 0,
        ]);
    }

    public function test_parallel_fork_spawns_concurrent_branch_tasks(): void
    {
        $engine = app(WorkflowEngineService::class);

        // Transition that forks into legal and finance branches
        $forkTransition = WorkflowTransition::create([
            'from_state_id' => $this->initialState->id,
            'to_state_id' => $this->legalBranchState->id,
            'action_code' => 'approve',
            'permission_id' => $this->permissionId,
            'approval_policy' => 'single',
            'fork_state_ids' => [$this->legalBranchState->id, $this->financeBranchState->id],
        ]);

        $instance = $engine->startInstance($this->definition->id, $this->version->id, null, $this->admin);
        $initialTask = WorkflowTask::where('instance_id', $instance->id)->where('state_id', $this->initialState->id)->first();
        $this->assertNotNull($initialTask);

        // Assign to reviewer1 so non-author can approve
        $initialTask->update(['assigned_user_id' => $this->reviewer1->id]);

        // Approve initial task to trigger fork
        $result = $engine->processDecision($initialTask, ['decision' => 'approve'], $this->reviewer1);
        $this->assertEquals('completed', $result['status']);

        // Check that two parallel tasks exist with branch keys
        $branchTasks = WorkflowTask::where('instance_id', $instance->id)
            ->whereNotNull('branch_key')
            ->whereNull('closed_at')
            ->get();

        $this->assertCount(2, $branchTasks);
        $states = $branchTasks->pluck('state_id')->all();
        $this->assertContains($this->legalBranchState->id, $states);
        $this->assertContains($this->financeBranchState->id, $states);
    }

    public function test_parallel_join_with_all_branches_policy_waits_for_completion(): void
    {
        $engine = app(WorkflowEngineService::class);

        $instance = WorkflowInstance::create([
            'workflow_version_id' => $this->wfVersion->id,
            'document_version_id' => $this->version->id,
            'status' => 'running',
            'started_by' => $this->admin->id,
            'started_at' => now(),
        ]);

        // Branch 1: Legal
        $task1 = WorkflowTask::create([
            'instance_id' => $instance->id,
            'state_id' => $this->legalBranchState->id,
            'branch_key' => 'branch_legal',
            'assigned_user_id' => $this->reviewer1->id,
        ]);

        // Branch 2: Finance
        $task2 = WorkflowTask::create([
            'instance_id' => $instance->id,
            'state_id' => $this->financeBranchState->id,
            'branch_key' => 'branch_finance',
            'assigned_user_id' => $this->reviewer2->id,
        ]);

        // Transitions for each branch joining into final approval
        WorkflowTransition::create([
            'from_state_id' => $this->legalBranchState->id,
            'to_state_id' => $this->finalApprovalState->id,
            'action_code' => 'approve',
            'permission_id' => $this->permissionId,
            'approval_policy' => 'single',
            'join_policy' => 'all_branches',
        ]);

        WorkflowTransition::create([
            'from_state_id' => $this->financeBranchState->id,
            'to_state_id' => $this->finalApprovalState->id,
            'action_code' => 'approve',
            'permission_id' => $this->permissionId,
            'approval_policy' => 'single',
            'join_policy' => 'all_branches',
        ]);

        // Step 1: Legal officer approves branch 1
        $res1 = $engine->processDecision($task1, ['decision' => 'approve'], $this->reviewer1);
        $this->assertEquals('branch_completed', $res1['status']);
        $this->assertTrue($res1['task_closed']);
        $this->assertContains('branch_finance', $res1['pending_sibling_branches']);

        // Final approval task should NOT exist yet
        $finalTask = WorkflowTask::where('instance_id', $instance->id)
            ->where('state_id', $this->finalApprovalState->id)
            ->first();
        $this->assertNull($finalTask);

        // Step 2: Finance officer approves branch 2
        $res2 = $engine->processDecision($task2, ['decision' => 'approve'], $this->reviewer2);
        $this->assertEquals('completed', $res2['status']);
        $this->assertTrue($res2['task_closed']);

        // Final approval task SHOULD now exist
        $finalTaskAfter = WorkflowTask::where('instance_id', $instance->id)
            ->where('state_id', $this->finalApprovalState->id)
            ->whereNull('closed_at')
            ->first();
        $this->assertNotNull($finalTaskAfter);

        // Verify join satisfied audit event
        $auditSatisfied = AuditEvent::where('action', 'workflow.parallel_join_satisfied')
            ->where('target_id', (string) $instance->id)
            ->exists();
        $this->assertTrue($auditSatisfied);
    }

    public function test_parallel_join_with_any_branch_policy_advances_on_first_decision(): void
    {
        $engine = app(WorkflowEngineService::class);

        $instance = WorkflowInstance::create([
            'workflow_version_id' => $this->wfVersion->id,
            'document_version_id' => $this->version->id,
            'status' => 'running',
            'started_by' => $this->admin->id,
            'started_at' => now(),
        ]);

        $task1 = WorkflowTask::create([
            'instance_id' => $instance->id,
            'state_id' => $this->legalBranchState->id,
            'branch_key' => 'branch_legal',
            'assigned_user_id' => $this->reviewer1->id,
        ]);

        $task2 = WorkflowTask::create([
            'instance_id' => $instance->id,
            'state_id' => $this->financeBranchState->id,
            'branch_key' => 'branch_finance',
            'assigned_user_id' => $this->reviewer2->id,
        ]);

        WorkflowTransition::create([
            'from_state_id' => $this->legalBranchState->id,
            'to_state_id' => $this->finalApprovalState->id,
            'action_code' => 'approve',
            'permission_id' => $this->permissionId,
            'approval_policy' => 'single',
            'join_policy' => 'any_branch',
        ]);

        // Legal officer approves first
        $res = $engine->processDecision($task1, ['decision' => 'approve'], $this->reviewer1);
        $this->assertEquals('completed', $res['status']);

        // Check that task2 was automatically superseded and closed
        $task2Fresh = $task2->fresh();
        $this->assertNotNull($task2Fresh->closed_at);

        // Check that final approval task was immediately generated
        $finalTask = WorkflowTask::where('instance_id', $instance->id)
            ->where('state_id', $this->finalApprovalState->id)
            ->whereNull('closed_at')
            ->first();
        $this->assertNotNull($finalTask);
    }

    public function test_task_reassignment_updates_user_and_records_audit(): void
    {
        $engine = app(WorkflowEngineService::class);

        $instance = WorkflowInstance::create([
            'workflow_version_id' => $this->wfVersion->id,
            'document_version_id' => $this->version->id,
            'status' => 'running',
            'started_by' => $this->admin->id,
            'started_at' => now(),
        ]);

        $task = WorkflowTask::create([
            'instance_id' => $instance->id,
            'state_id' => $this->legalBranchState->id,
            'assigned_user_id' => $this->reviewer1->id,
        ]);

        $reassigned = $engine->reassignTask($task, $this->reviewer2->id, 'Reassigning due to duty rotation', $this->admin);

        $this->assertEquals($this->reviewer2->id, $reassigned->assigned_user_id);

        $audit = AuditEvent::where('action', 'workflow.task_reassigned')
            ->where('target_id', (string) $task->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals($this->reviewer2->id, $audit->details_json['target_user_id']);
        $this->assertEquals($this->reviewer1->id, $audit->details_json['previous_user_id']);
    }
}
