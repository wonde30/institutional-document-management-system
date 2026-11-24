<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ConfidentialityLevel;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\OrganizationUnit;
use App\Models\ResearchRecord;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchWorkflowSingleSourceOfTruthTest extends TestCase
{
    use RefreshDatabase;

    protected User $author;
    protected User $submitter;
    protected User $reviewer;
    protected User $approver;
    protected OrganizationUnit $unit;
    protected DocumentCategory $cat;
    protected ConfidentialityLevel $conf;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\BaselineReferenceVocabularySeeder::class);
        $this->seed(\Database\Seeders\InstitutionalCredentialSeeder::class);
        $this->seed(\Database\Seeders\ResearchProjectWorkflowSeeder::class);

        $unitType = \App\Models\UnitType::first();

        $this->unit = OrganizationUnit::first() ?? OrganizationUnit::create([
            'code' => 'SSOT_RES_UNIT',
            'name' => 'Department of Computer Science',
            'unit_type_id' => $unitType->id,
            'active' => true,
        ]);

        $this->cat = DocumentCategory::firstOrCreate(
            ['code' => 'research'],
            ['name' => 'Research', 'active' => true]
        );

        $this->conf = ConfidentialityLevel::firstOrCreate(
            ['code' => 'internal'],
            ['name' => 'Internal', 'rank' => 20]
        );

        // 1. Author: Creates draft
        $this->author = User::factory()->create(['status' => 'active', 'authz_version' => 1]);
        $this->grantPerms($this->author, ['research.view', 'research.create']);

        // 2. Submitter: Distinct from author
        $this->submitter = User::factory()->create(['status' => 'active', 'authz_version' => 1]);
        $this->grantPerms($this->submitter, ['research.view', 'research.submit', 'workflow.view', 'workflow.review']);

        // 3. Reviewer: Reviews
        $this->reviewer = User::factory()->create(['status' => 'active', 'authz_version' => 1]);
        $this->grantPerms($this->reviewer, ['research.view', 'research.review', 'workflow.view', 'workflow.review']);

        // 4. Approver: Approves
        $this->approver = User::factory()->create(['status' => 'active', 'authz_version' => 1]);
        $this->grantPerms($this->approver, ['research.view', 'research.approve', 'research.reject', 'workflow.view', 'workflow.review']);
    }

    protected function grantPerms(User $user, array $permCodes): void
    {
        foreach ($permCodes as $code) {
            $this->grantPermissionToUser($user, $code, 'unit', $this->unit->id);
        }
    }

    protected function createResearchItem(): array
    {
        $doc = Document::create([
            'reference_number' => 'WU/RES/SSOT/' . uniqid(),
            'title' => 'Single Source of Truth AI Research',
            'category_id' => $this->cat->id,
            'confidentiality_id' => $this->conf->id,
            'owner_unit_id' => $this->unit->id,
            'created_by' => $this->author->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $research = ResearchRecord::create([
            'document_id' => $doc->id,
            'research_type' => 'academic_monograph',
            'workflow_status' => 'draft',
            'recorded_by' => $this->author->id,
            'recorded_at' => now(),
        ]);

        return [$doc, $research];
    }

    /**
     * (a) The reviewer decides via /workflows/tasks/{id}/decide.
     * Record workflow_status, tracking columns, and task state must end consistent, with no pending task left on terminal approval.
     */
    public function test_direction_a_deciding_via_workflow_task_endpoint_updates_research_record_consistently(): void
    {
        [$doc, $research] = $this->createResearchItem();

        // Submitter submits
        $this->actingAs($this->submitter)
            ->postJson("/api/v1/research/outputs/{$research->id}/submit", ['remarks' => 'Submitting research'])
            ->assertStatus(200);

        $task = WorkflowTask::whereHas('instance.documentVersion', fn ($q) => $q->where('document_id', $doc->id))
            ->whereNull('closed_at')
            ->firstOrFail();

        // Reviewer decides task to start review
        $reviewRes = $this->actingAs($this->reviewer)
            ->postJson("/api/v1/workflows/tasks/{$task->id}/decide", [
                'decision' => 'start_review',
                'reason' => 'Starting peer review via task inbox',
            ]);
        $reviewRes->assertStatus(200);

        $research->refresh();
        $this->assertEquals('under_review', $research->workflow_status);
        $this->assertEquals($this->reviewer->id, $research->reviewed_by);

        // Next task for under_review state
        $reviewTask = WorkflowTask::where('instance_id', $task->instance_id)
            ->whereNull('closed_at')
            ->firstOrFail();

        // Approver decides task to approve
        $approveRes = $this->actingAs($this->approver)
            ->postJson("/api/v1/workflows/tasks/{$reviewTask->id}/decide", [
                'decision' => 'approve',
                'reason' => 'Approved publication through task inbox',
            ]);
        $approveRes->assertStatus(200);

        $research->refresh();
        $this->assertEquals('approved', $research->workflow_status);
        $this->assertEquals($this->approver->id, $research->approved_by);
        $this->assertEquals('Approved publication through task inbox', $research->decision_remarks);

        // After approval, no pending task remains
        $pendingTasksCount = WorkflowTask::where('instance_id', $task->instance_id)
            ->whereNull('closed_at')
            ->count();
        $this->assertEquals(0, $pendingTasksCount, 'No pending workflow task must remain once item is terminal.');
    }

    /**
     * (b) The reviewer decides via /research/outputs/{id}/review|approve.
     * Engine task must be decided and closed consistently, with no pending task left on terminal approval.
     */
    public function test_direction_b_deciding_via_research_endpoints_decides_workflow_task_consistently(): void
    {
        [$doc, $research] = $this->createResearchItem();

        // Submitter submits
        $this->actingAs($this->submitter)
            ->postJson("/api/v1/research/outputs/{$research->id}/submit", ['remarks' => 'Submitting research'])
            ->assertStatus(200);

        $task = WorkflowTask::whereHas('instance.documentVersion', fn ($q) => $q->where('document_id', $doc->id))
            ->whereNull('closed_at')
            ->firstOrFail();

        // Reviewer moves to under_review via research endpoint
        $this->actingAs($this->reviewer)
            ->postJson("/api/v1/research/outputs/{$research->id}/review", ['remarks' => 'Peer review begun'])
            ->assertStatus(200);

        $task->refresh();
        $this->assertNotNull($task->closed_at, 'Original task must be closed upon review transition.');

        // Approver approves via research endpoint
        $this->actingAs($this->approver)
            ->postJson("/api/v1/research/outputs/{$research->id}/approve", ['remarks' => 'Institutional approval granted'])
            ->assertStatus(200);

        $research->refresh();
        $this->assertEquals('approved', $research->workflow_status);
        $this->assertEquals($this->approver->id, $research->approved_by);

        // After approval, no pending task remains
        $version = $doc->versions()->latest('version_number')->firstOrFail();
        $instance = WorkflowInstance::where('document_version_id', $version->id)->firstOrFail();
        $pendingTasksCount = WorkflowTask::where('instance_id', $instance->id)
            ->whereNull('closed_at')
            ->count();
        $this->assertEquals(0, $pendingTasksCount, 'No pending workflow task must remain once research output is approved.');
    }

    /**
     * Separation of duty holds through the inbox path too (submitter cannot decide their own task).
     */
    public function test_separation_of_duty_holds_through_inbox_path(): void
    {
        [$doc, $research] = $this->createResearchItem();

        // Submitter submits
        $this->actingAs($this->submitter)
            ->postJson("/api/v1/research/outputs/{$research->id}/submit", ['remarks' => 'Submitting research'])
            ->assertStatus(200);

        $task = WorkflowTask::whereHas('instance.documentVersion', fn ($q) => $q->where('document_id', $doc->id))
            ->whereNull('closed_at')
            ->firstOrFail();

        // Submitter attempts to decide their own task to approve it via /workflows/tasks/{id}/decide
        $res = $this->actingAs($this->submitter)
            ->postJson("/api/v1/workflows/tasks/{$task->id}/decide", [
                'decision' => 'approve',
                'reason' => 'Attempting self approval through inbox',
            ]);

        $res->assertStatus(422);
        $this->assertNotEquals('approved', $research->fresh()->workflow_status);
    }

    /**
     * The task inbox shows the item for the assigned reviewer.
     */
    public function test_task_inbox_shows_item_for_assigned_reviewer(): void
    {
        [$doc, $research] = $this->createResearchItem();

        $this->actingAs($this->submitter)
            ->postJson("/api/v1/research/outputs/{$research->id}/submit", ['remarks' => 'Submitting research'])
            ->assertStatus(200);

        // Office scope shows task to unit members
        $inboxRes = $this->actingAs($this->reviewer)
            ->getJson('/api/v1/workflows/tasks?scope=office');
        $inboxRes->assertStatus(200);
        $items = $inboxRes->json('data');
        $this->assertTrue(collect($items)->contains(fn ($t) => ($t['instance']['document_version']['document_id'] ?? null) === $doc->id));
    }
}
