<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ConfidentialityLevel;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\ProjectProposal;
use App\Models\ProjectType;
use App\Models\ResearchRecord;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchAndProjectApprovalWorkflowAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $author;
    protected User $submitter;
    protected User $reviewer;
    protected User $approver;
    protected OrganizationUnit $unit;
    protected DocumentCategory $cat;
    protected ConfidentialityLevel $conf;
    protected ProjectType $projectType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\BaselineReferenceVocabularySeeder::class);
        $this->seed(\Database\Seeders\InstitutionalCredentialSeeder::class);
        $this->seed(\Database\Seeders\ResearchProjectWorkflowSeeder::class);

        $unitType = \App\Models\UnitType::first();

        $this->unit = OrganizationUnit::first() ?? OrganizationUnit::create([
            'code' => 'TEST_RES_UNIT',
            'name' => 'Department of Computing',
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

        $this->projectType = ProjectType::firstOrCreate(
            ['code' => 'grant_funded'],
            ['name' => 'Grant Funded Project', 'active' => true]
        );

        // 1. Author: Creates the draft
        $this->author = User::factory()->create(['status' => 'active', 'authz_version' => 1]);
        $this->grantPerms($this->author, ['research.view', 'research.create', 'projects.view', 'projects.submit']);

        // 2. Submitter: Different from author, submits the item
        $this->submitter = User::factory()->create(['status' => 'active', 'authz_version' => 1]);
        $this->grantPerms($this->submitter, ['research.view', 'research.submit', 'projects.view', 'projects.submit']);

        // 3. Reviewer: Reviews the item
        $this->reviewer = User::factory()->create(['status' => 'active', 'authz_version' => 1]);
        $this->grantPerms($this->reviewer, ['research.view', 'research.review', 'projects.view', 'projects.review', 'workflow.view']);

        // 4. Approver: Distinct user who approves the item
        $this->approver = User::factory()->create(['status' => 'active', 'authz_version' => 1]);
        $this->grantPerms($this->approver, ['research.view', 'research.approve', 'research.reject', 'projects.view', 'projects.approve', 'projects.reject', 'workflow.view']);
    }

    protected function grantPerms(User $user, array $permCodes): void
    {
        foreach ($permCodes as $code) {
            $this->grantPermissionToUser($user, $code, 'unit', $this->unit->id);
        }
    }

    public function test_submitter_distinct_from_author_cannot_approve_own_submission(): void
    {
        // 1. Author creates draft research
        $doc = Document::create([
            'reference_number' => 'WU/RES/SUBMIT/001',
            'title' => 'Climate Adaptation Study',
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

        // 2. Submitter (User B != Author A) submits the research
        $submitRes = $this->actingAs($this->submitter)
            ->postJson("/api/v1/research/outputs/{$research->id}/submit", [
                'remarks' => 'Submitted on behalf of research office.',
            ]);
        $submitRes->assertStatus(200);
        $this->assertEquals('submitted', $research->fresh()->workflow_status);
        $this->assertEquals($this->submitter->id, $research->fresh()->submitted_by);

        // 3. Submitter attempts to approve their own submission -> MUST FAIL (422) with unchanged state
        // Give submitter temporary approve permission to test authorization rule
        $this->grantPerms($this->submitter, ['research.approve']);
        $selfApproveRes = $this->actingAs($this->submitter)
            ->postJson("/api/v1/research/outputs/{$research->id}/approve", [
                'remarks' => 'Attempting self-approval by submitter',
            ]);
        $selfApproveRes->assertStatus(422);
        $this->assertEquals('submitted', $research->fresh()->workflow_status);
    }

    public function test_same_user_who_reviewed_cannot_approve(): void
    {
        $doc = Document::create([
            'reference_number' => 'WU/RES/REV/001',
            'title' => 'Renewable Energy Grid Integration',
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
            'workflow_status' => 'submitted',
            'submitted_by' => $this->submitter->id,
            'recorded_by' => $this->author->id,
            'recorded_at' => now(),
        ]);

        // Reviewer C reviews the item
        $reviewRes = $this->actingAs($this->reviewer)
            ->postJson("/api/v1/research/outputs/{$research->id}/review", [
                'remarks' => 'Technical methodology reviewed and endorsed.',
            ]);
        $reviewRes->assertStatus(200);
        $this->assertEquals('under_review', $research->fresh()->workflow_status);
        $this->assertEquals($this->reviewer->id, $research->fresh()->reviewed_by);

        // Give reviewer approve permission as well
        $this->grantPerms($this->reviewer, ['research.approve']);

        // Same Reviewer C attempts to approve -> MUST FAIL 422
        $sameApproveRes = $this->actingAs($this->reviewer)
            ->postJson("/api/v1/research/outputs/{$research->id}/approve", [
                'remarks' => 'Attempting approval by same reviewer',
            ]);
        $sameApproveRes->assertStatus(422);
        $this->assertEquals('under_review', $research->fresh()->workflow_status);

        // Distinct Approver D approves -> 200 SUCCESS
        $approverRes = $this->actingAs($this->approver)
            ->postJson("/api/v1/research/outputs/{$research->id}/approve", [
                'remarks' => 'Institutional dean approval granted.',
            ]);
        $approverRes->assertStatus(200);
        $this->assertEquals('approved', $research->fresh()->workflow_status);
    }

    public function test_duplicate_or_invalid_transition_returns_422(): void
    {
        $doc = Document::create([
            'reference_number' => 'WU/RES/DUP/001',
            'title' => 'Soil Salinity Analysis',
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
            'workflow_status' => 'approved', // Already approved
            'submitted_by' => $this->submitter->id,
            'reviewed_by' => $this->reviewer->id,
            'recorded_by' => $this->author->id,
            'recorded_at' => now(),
        ]);

        // Attempting to reject an already approved research -> 422
        $rejectRes = $this->actingAs($this->approver)
            ->postJson("/api/v1/research/outputs/{$research->id}/reject", [
                'remarks' => 'Rejecting after approval',
            ]);
        $rejectRes->assertStatus(422);
        $this->assertEquals('approved', $research->fresh()->workflow_status);
    }

    public function test_pending_item_creates_workflow_task_for_reviewers(): void
    {
        $doc = Document::create([
            'reference_number' => 'WU/RES/TASK/001',
            'title' => 'Microgrid Reliability Evaluation',
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

        // Submitter submits the research
        $this->actingAs($this->submitter)
            ->postJson("/api/v1/research/outputs/{$research->id}/submit", [
                'remarks' => 'Initial submission for task generation test.',
            ])
            ->assertStatus(200);

        // Verify that a workflow task was created for this document / submission
        $task = WorkflowTask::whereHas('instance.documentVersion', function ($vq) use ($doc) {
            $vq->where('document_id', $doc->id);
        })->whereNull('closed_at')->first();

        $this->assertNotNull($task, 'A workflow task must be spawned when research is submitted.');

        // Verify task appears in reviewer tasks listing
        $tasksRes = $this->actingAs($this->reviewer)
            ->getJson('/api/v1/workflows/tasks?scope=office');
        $tasksRes->assertStatus(200);
        $tasksData = $tasksRes->json('data');
        $found = collect($tasksData)->contains(fn ($t) => $t['id'] === $task->id);
        $this->assertTrue($found, 'The pending workflow task must appear in the reviewer tasks inbox.');
    }

    public function test_null_submitted_by_blocks_approval_with_422(): void
    {
        $doc = Document::create([
            'reference_number' => 'WU/RES/NULLSUB/001',
            'title' => 'Null Submitter Test Document',
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
            'workflow_status' => 'under_review',
            'submitted_by' => null, // Explicit null submitted_by
            'reviewed_by' => $this->reviewer->id,
            'recorded_by' => $this->author->id,
            'recorded_at' => now(),
        ]);

        $res = $this->actingAs($this->approver)
            ->postJson("/api/v1/research/outputs/{$research->id}/approve", [
                'remarks' => 'Approving item with null submitted_by',
            ]);

        $res->assertStatus(422);
        $this->assertEquals('under_review', $research->fresh()->workflow_status);
    }

    public function test_return_by_the_submitter_is_blocked_with_422(): void
    {
        $doc = Document::create([
            'reference_number' => 'WU/RES/RETSUB/001',
            'title' => 'Submitter Return Test Document',
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
            'workflow_status' => 'under_review',
            'submitted_by' => $this->submitter->id,
            'reviewed_by' => $this->reviewer->id,
            'recorded_by' => $this->author->id,
            'recorded_at' => now(),
        ]);

        // Grant submitter review permission to attempt return
        $this->grantPerms($this->submitter, ['research.review']);

        $res = $this->actingAs($this->submitter)
            ->postJson("/api/v1/research/outputs/{$research->id}/return", [
                'remarks' => 'Submitter attempting to return own item',
            ]);

        $res->assertStatus(422);
        $this->assertEquals('under_review', $research->fresh()->workflow_status);
    }

    public function test_unit_b_record_with_unit_a_only_reviewer_is_denied(): void
    {
        $unitType = \App\Models\UnitType::first();
        $unitB = OrganizationUnit::create([
            'code' => 'TEST_RES_UNIT_B',
            'name' => 'Department of Physics',
            'unit_type_id' => $unitType->id,
            'active' => true,
        ]);

        $docB = Document::create([
            'reference_number' => 'WU/RES/UNITB/001',
            'title' => 'Quantum Optics in Unit B',
            'category_id' => $this->cat->id,
            'confidentiality_id' => $this->conf->id,
            'owner_unit_id' => $unitB->id,
            'created_by' => $this->author->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $researchB = ResearchRecord::create([
            'document_id' => $docB->id,
            'research_type' => 'academic_monograph',
            'workflow_status' => 'submitted',
            'submitted_by' => $this->submitter->id,
            'recorded_by' => $this->author->id,
            'recorded_at' => now(),
        ]);

        // Reviewer holds research.review ONLY on Unit A ($this->unit)
        $res = $this->actingAs($this->reviewer)
            ->postJson("/api/v1/research/outputs/{$researchB->id}/review", [
                'remarks' => 'Attempting review across units',
            ]);

        // Access must be denied (403/404) because reviewer lacks research.review in Unit B
        $this->assertContains($res->status(), [403, 404]);
        $this->assertEquals('submitted', $researchB->fresh()->workflow_status);
    }

    public function test_two_sequential_approve_calls_on_same_item_one_200_one_422(): void
    {
        $doc = Document::create([
            'reference_number' => 'WU/RES/SEQ/001',
            'title' => 'Sequential Approval Test Document',
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
            'workflow_status' => 'under_review',
            'submitted_by' => $this->submitter->id,
            'reviewed_by' => $this->reviewer->id,
            'recorded_by' => $this->author->id,
            'recorded_at' => now(),
        ]);

        // First approve call: 200 OK
        $firstApprove = $this->actingAs($this->approver)
            ->postJson("/api/v1/research/outputs/{$research->id}/approve", [
                'remarks' => 'First valid approval',
            ]);
        $firstApprove->assertStatus(200);
        $this->assertEquals('approved', $research->fresh()->workflow_status);

        // Second approve call: 422 Unprocessable Entity
        $secondApprove = $this->actingAs($this->approver)
            ->postJson("/api/v1/research/outputs/{$research->id}/approve", [
                'remarks' => 'Second duplicate approval',
            ]);
        $secondApprove->assertStatus(422);
        $this->assertEquals('approved', $research->fresh()->workflow_status);
    }

    public function test_cannot_approve_directly_from_submitted_state(): void
    {
        $doc = Document::create([
            'reference_number' => 'WU/RES/SUBAPPR/001',
            'title' => 'Direct Approval from Submitted Test',
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
            'workflow_status' => 'submitted',
            'submitted_by' => $this->submitter->id,
            'recorded_by' => $this->author->id,
            'recorded_at' => now(),
        ]);

        // Approver attempts to approve directly from submitted (review mandatory)
        $res = $this->actingAs($this->approver)
            ->postJson("/api/v1/research/outputs/{$research->id}/approve", [
                'remarks' => 'Attempting direct approval without review',
            ]);

        $res->assertStatus(422);
        $this->assertEquals('submitted', $research->fresh()->workflow_status);
    }
}
