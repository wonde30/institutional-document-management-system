<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\ConfidentialityLevel;
use App\Models\OrganizationUnit;
use App\Models\ResearchRecord;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowState;
use App\Models\WorkflowTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResearchWorkflowEngineHookupTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $head;
    protected User $researcher;
    protected OrganizationUnit $dept;
    protected WorkflowDefinition $researchDef;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\BaselineReferenceVocabularySeeder::class);
        $this->seed(\Database\Seeders\InstitutionalCredentialSeeder::class);
        $this->seed(\Database\Seeders\ResearchProjectWorkflowSeeder::class);

        $this->dept = OrganizationUnit::firstOrCreate(
            ['code' => 'SE_DEPT'],
            ['name' => 'Software Engineering', 'unit_type_id' => 1, 'active' => true]
        );

        $this->researchDef = WorkflowDefinition::where('code', 'research_departmental_review')->firstOrFail();

        // 1. Admin
        $this->admin = User::factory()->create(['status' => 'active', 'authz_version' => 1]);
        $this->grantPermissionToUser($this->admin, 'system.configure', 'global', null);
        $this->grantPermissionToUser($this->admin, 'workflow.configure', 'global', null);
        $this->grantPermissionToUser($this->admin, 'workflow.review', 'global', null);
        $this->grantPermissionToUser($this->admin, 'workflow.approve', 'global', null);
        $this->grantPermissionToUser($this->admin, 'research.approve', 'global', null);

        // 2. Department Head
        $this->head = User::factory()->create(['status' => 'active', 'authz_version' => 1]);
        $this->grantPermissionToUser($this->head, 'research.submit', 'unit', $this->dept->id);
        $this->grantPermissionToUser($this->head, 'research.review', 'unit', $this->dept->id);
        $this->grantPermissionToUser($this->head, 'research.approve', 'unit', $this->dept->id);
        $this->grantPermissionToUser($this->head, 'research.reject', 'unit', $this->dept->id);
        $this->grantPermissionToUser($this->head, 'workflow.review', 'unit', $this->dept->id);
        $this->grantPermissionToUser($this->head, 'workflow.approve', 'unit', $this->dept->id);

        // 3. Submitting Researcher
        $this->researcher = User::factory()->create(['status' => 'active', 'authz_version' => 1]);
        $this->grantPermissionToUser($this->researcher, 'research.create', 'unit', $this->dept->id);
        $this->grantPermissionToUser($this->researcher, 'research.submit', 'unit', $this->dept->id);
        $this->grantPermissionToUser($this->researcher, 'research.review', 'unit', $this->dept->id);
        $this->grantPermissionToUser($this->researcher, 'research.approve', 'unit', $this->dept->id);
        $this->grantPermissionToUser($this->researcher, 'workflow.submit', 'unit', $this->dept->id);
        $this->grantPermissionToUser($this->researcher, 'workflow.review', 'unit', $this->dept->id);
        $this->grantPermissionToUser($this->researcher, 'workflow.approve', 'unit', $this->dept->id);
    }

    public function test_item_submitted_without_linked_document_still_gets_a_task(): void
    {
        // Start workflow instance directly without a linked document version
        $response = $this->actingAs($this->researcher)
            ->postJson('/api/v1/workflows/instances', [
                'workflow_definition_id' => $this->researchDef->id,
                'document_version_id' => null,
            ]);

        $response->assertStatus(201);
        $instanceId = $response->json('data.id');
        $this->assertNotNull($instanceId);

        $instance = WorkflowInstance::findOrFail($instanceId);
        $this->assertNull($instance->document_version_id);

        // Assert initial task was spawned
        $openTask = WorkflowTask::where('instance_id', $instance->id)->whereNull('closed_at')->first();
        $this->assertNotNull($openTask);
        $this->assertEquals('draft', $openTask->state->code);
    }

    public function test_submitter_cannot_decide_their_own_task_via_institutional_path(): void
    {
        $cat = DocumentCategory::first();
        $conf = ConfidentialityLevel::first();

        $doc = Document::create([
            'reference_number' => 'WU/RES/DOC/' . uniqid(),
            'title' => 'AI in Medicine Research',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $this->dept->id,
            'custodian_id' => $this->researcher->id,
            'created_by' => $this->researcher->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $record = ResearchRecord::create([
            'document_id' => $doc->id,
            'research_type' => 'applied_research',
            'recorded_by' => $this->researcher->id,
            'recorded_at' => now(),
            'workflow_status' => 'draft',
        ]);

        // 1. Submit by researcher -> 200
        $subRes = $this->actingAs($this->researcher)
            ->postJson("/api/v1/research/outputs/{$record->id}/submit");
        $subRes->assertStatus(200);

        // 2. Submitter attempts to review their own output -> 422
        $revRes = $this->actingAs($this->researcher)
            ->postJson("/api/v1/research/outputs/{$record->id}/review");
        $revRes->assertStatus(422);

        // 3. Move to review by department head -> 200
        $headRev = $this->actingAs($this->head)
            ->postJson("/api/v1/research/outputs/{$record->id}/review");
        $headRev->assertStatus(200);

        // 4. Submitter attempts to approve their own output -> 422
        $appRes = $this->actingAs($this->researcher)
            ->postJson("/api/v1/research/outputs/{$record->id}/approve");
        $appRes->assertStatus(422);
    }

    public function test_submitter_cannot_decide_their_own_task_via_workflow_controller_path(): void
    {
        // Start workflow instance where researcher is started_by
        $instance = app(\App\Services\WorkflowEngineService::class)->startInstance(
            $this->researchDef->id,
            null,
            null,
            $this->researcher,
            $this->dept->id
        );

        $draftTask = WorkflowTask::where('instance_id', $instance->id)->whereNull('closed_at')->firstOrFail();

        // Submitter submits draft task
        app(\App\Services\WorkflowEngineService::class)->processDecision($draftTask, [
            'decision' => 'submit',
        ], $this->researcher);

        // Advance to under_review by head
        $subTask = WorkflowTask::where('instance_id', $instance->id)->whereNull('closed_at')->firstOrFail();
        app(\App\Services\WorkflowEngineService::class)->processDecision($subTask, [
            'decision' => 'start_review',
        ], $this->head);

        // Now active task is under_review
        $reviewTask = WorkflowTask::where('instance_id', $instance->id)->whereNull('closed_at')->firstOrFail();
        $this->assertEquals('under_review', $reviewTask->state->code);

        // Researcher (submitter) attempts to approve via /api/v1/workflows/tasks/{id}/decide -> 422
        $decideRes = $this->actingAs($this->researcher)
            ->postJson("/api/v1/workflows/tasks/{$reviewTask->id}/decide", [
                'decision' => 'approve',
            ]);

        $decideRes->assertStatus(422);
    }

    public function test_both_paths_end_with_record_status_equals_task_state_and_no_open_task_once_terminal(): void
    {
        $cat = DocumentCategory::first();
        $conf = ConfidentialityLevel::first();

        // --- PATH 1: Institutional endpoints ---
        $doc1 = Document::create([
            'reference_number' => 'WU/RES/P1/' . uniqid(),
            'title' => 'Renewable Energy Study',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $this->dept->id,
            'custodian_id' => $this->researcher->id,
            'created_by' => $this->researcher->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $rec1 = ResearchRecord::create([
            'document_id' => $doc1->id,
            'research_type' => 'experimental',
            'recorded_by' => $this->researcher->id,
            'recorded_at' => now(),
            'workflow_status' => 'draft',
        ]);

        $this->actingAs($this->researcher)->postJson("/api/v1/research/outputs/{$rec1->id}/submit")->assertStatus(200);
        $this->actingAs($this->head)->postJson("/api/v1/research/outputs/{$rec1->id}/review")->assertStatus(200);
        $this->actingAs($this->admin)->postJson("/api/v1/research/outputs/{$rec1->id}/approve")->assertStatus(200);

        $rec1->refresh();
        $this->assertEquals('approved', $rec1->workflow_status);

        // Find associated workflow instance
        $instance1 = WorkflowInstance::whereHas('workflowVersion', fn ($q) => $q->where('definition_id', $this->researchDef->id))
            ->whereHas('events', fn ($eq) => $eq->where('reason', 'like', "%research_record:{$rec1->id}%"))
            ->orWhere('document_version_id', $doc1->latestVersion?->id)
            ->latest('id')
            ->first();

        if ($instance1) {
            $openTasks1 = WorkflowTask::where('instance_id', $instance1->id)->whereNull('closed_at')->count();
            $this->assertEquals(0, $openTasks1, 'Path 1 should have no open tasks once terminal');
            $latestDecision1 = \App\Models\WorkflowTaskDecision::whereHas('task', fn ($tq) => $tq->where('instance_id', $instance1->id))->latest('id')->value('decision');
            $this->assertEquals('approve', $latestDecision1);
        }

        // --- PATH 2: Workflow controller endpoints ---
        $doc2 = Document::create([
            'reference_number' => 'WU/RES/P2/' . uniqid(),
            'title' => 'Smart Grid Integration',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $this->dept->id,
            'custodian_id' => $this->researcher->id,
            'created_by' => $this->researcher->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $rec2 = ResearchRecord::create([
            'document_id' => $doc2->id,
            'research_type' => 'experimental',
            'recorded_by' => $this->researcher->id,
            'recorded_at' => now(),
            'workflow_status' => 'draft',
        ]);

        // Start instance via engine linking to doc2
        $version2 = \App\Models\DocumentVersion::create([
            'document_id' => $doc2->id,
            'version_number' => 1,
            'created_by' => $this->researcher->id,
            'change_note' => 'Original research paper',
        ]);

        $instance2 = app(\App\Services\WorkflowEngineService::class)->startInstance(
            $this->researchDef->id,
            $version2->id,
            null,
            $this->researcher
        );

        // Draft -> Submit
        $t1 = WorkflowTask::where('instance_id', $instance2->id)->whereNull('closed_at')->firstOrFail();
        $this->actingAs($this->researcher)->postJson("/api/v1/workflows/tasks/{$t1->id}/decide", ['decision' => 'submit'])->assertStatus(200);

        // Submit -> Under Review
        $t2 = WorkflowTask::where('instance_id', $instance2->id)->whereNull('closed_at')->firstOrFail();
        $this->actingAs($this->head)->postJson("/api/v1/workflows/tasks/{$t2->id}/decide", ['decision' => 'start_review'])->assertStatus(200);

        // Under Review -> Approve
        $t3 = WorkflowTask::where('instance_id', $instance2->id)->whereNull('closed_at')->firstOrFail();
        $this->actingAs($this->admin)->postJson("/api/v1/workflows/tasks/{$t3->id}/decide", ['decision' => 'approve'])->assertStatus(200);

        $rec2->refresh();
        $this->assertEquals('approved', $rec2->workflow_status);

        $openTasks2 = WorkflowTask::where('instance_id', $instance2->id)->whereNull('closed_at')->count();
        $this->assertEquals(0, $openTasks2, 'Path 2 should have no open tasks once terminal');
        $latestDecision2 = \App\Models\WorkflowTaskDecision::where('task_id', $t3->id)->value('decision');
        $this->assertEquals('approve', $latestDecision2);
    }
}
