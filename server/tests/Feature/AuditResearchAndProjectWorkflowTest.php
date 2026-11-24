<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\AuthorizationScope;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\ConfidentialityLevel;
use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\ProjectProposal;
use App\Models\ProjectType;
use App\Models\ResearchRecord;
use App\Models\User;
use App\Models\UserPermission;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditResearchAndProjectWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $departmentHead;
    protected User $dean;
    protected User $staff;
    protected OrganizationUnit $unit;
    protected DocumentCategory $category;
    protected ConfidentialityLevel $confidentiality;
    protected ProjectType $projectType;
    protected AuthorizationScope $scope;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $this->unit = OrganizationUnit::first();

        $this->category = DocumentCategory::firstOrCreate(
            ['code' => 'research'],
            ['name' => 'Research', 'is_active' => true]
        );

        $this->confidentiality = ConfidentialityLevel::firstOrCreate(
            ['code' => 'internal'],
            ['name' => 'Internal', 'rank' => 20, 'is_active' => true]
        );

        $this->projectType = ProjectType::firstOrCreate(
            ['code' => 'innovation'],
            ['name' => 'Innovation Initiative', 'active' => true]
        );

        $this->scope = AuthorizationScope::firstOrCreate([
            'kind' => 'unit',
            'unit_id' => $this->unit->id,
        ]);

        // Staff user
        $this->staff = User::factory()->create([
            'email' => 'staff.research@wu.edu.et',
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPerms($this->staff, ['research.view', 'research.submit', 'projects.view', 'projects.submit']);

        // Department head (Reviewer)
        $this->departmentHead = User::factory()->create([
            'email' => 'head.research@wu.edu.et',
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPerms($this->departmentHead, [
            'research.view', 'research.review', 'research.reject',
            'projects.view', 'projects.review', 'projects.reject',
        ]);

        // Dean (Distinct Approver)
        $this->dean = User::factory()->create([
            'email' => 'dean.research@wu.edu.et',
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPerms($this->dean, [
            'research.view', 'research.approve', 'research.reject',
            'projects.view', 'projects.approve', 'projects.reject',
        ]);
    }

    protected function grantPerms(User $user, array $permCodes): void
    {
        $perms = Permission::whereIn('code', $permCodes)->get();
        foreach ($perms as $p) {
            UserPermission::create([
                'user_id' => $user->id,
                'permission_id' => $p->id,
                'scope_id' => $this->scope->id,
                'granted_by' => 1,
                'granted_at' => now(),
            ]);
        }
    }

    public function test_research_workflow_lifecycle_from_draft_to_approved(): void
    {
        // 1. Create document & draft research record
        $doc = Document::create([
            'reference_number' => 'WU/RES/TEST/001',
            'title' => 'Quantum Cryptography at Wollo',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->unit->id,
            'custodian_id' => $this->staff->id,
            'created_by' => $this->staff->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $research = ResearchRecord::create([
            'document_id' => $doc->id,
            'research_type' => 'academic_monograph',
            'workflow_status' => 'draft',
            'recorded_by' => $this->staff->id,
            'recorded_at' => now(),
        ]);

        // 2. Staff submits research (draft -> submitted)
        $submitRes = $this->actingAs($this->staff)
            ->postJson("/api/v1/research/outputs/{$research->id}/submit", [
                'remarks' => 'Completed monograph ready for evaluation.',
            ]);
        $submitRes->assertStatus(200)
            ->assertJsonPath('data.workflow_status', 'submitted');

        $this->assertEquals('submitted', $research->fresh()->workflow_status);

        // 3. Department Head reviews research (submitted -> under_review)
        $reviewRes = $this->actingAs($this->departmentHead)
            ->postJson("/api/v1/research/outputs/{$research->id}/review", [
                'remarks' => 'Commencing departmental peer review.',
            ]);
        $reviewRes->assertStatus(200)
            ->assertJsonPath('data.workflow_status', 'under_review');

        // 4. Dean approves research (under_review -> approved)
        $approveRes = $this->actingAs($this->dean)
            ->postJson("/api/v1/research/outputs/{$research->id}/approve", [
                'remarks' => 'Peer review passed with distinction.',
            ]);
        $approveRes->assertStatus(200)
            ->assertJsonPath('data.workflow_status', 'approved');

        $this->assertEquals('approved', $research->fresh()->workflow_status);

        // 5. Invalid transition: cannot re-submit an already approved research record
        $invalidRes = $this->actingAs($this->staff)
            ->postJson("/api/v1/research/outputs/{$research->id}/submit");
        $invalidRes->assertStatus(422);

        // 6. Audit Trail verification
        $this->assertDatabaseHas('audit_events', [
            'target_type' => 'research_record',
            'target_id' => (string) $research->id,
            'action' => 'research.approved',
        ]);
    }

    public function test_project_workflow_lifecycle_from_draft_to_approved(): void
    {
        // 1. Create document & draft project proposal
        $doc = Document::create([
            'reference_number' => 'WU/PROJ/TEST/001',
            'title' => 'AI Agritech Drone Survey Initiative',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->unit->id,
            'custodian_id' => $this->staff->id,
            'created_by' => $this->staff->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $project = ProjectProposal::create([
            'document_id' => $doc->id,
            'project_type_id' => $this->projectType->id,
            'workflow_status' => 'draft',
            'recorded_by' => $this->staff->id,
            'recorded_at' => now(),
        ]);

        // 2. Staff submits project (draft -> submitted)
        $submitRes = $this->actingAs($this->staff)
            ->postJson("/api/v1/projects/{$project->id}/submit", [
                'remarks' => 'Concept proposal submitted for university review.',
            ]);
        $submitRes->assertStatus(200)
            ->assertJsonPath('data.workflow_status', 'submitted');

        // 3. Department Head reviews project (submitted -> under_review)
        $reviewRes = $this->actingAs($this->departmentHead)
            ->postJson("/api/v1/projects/{$project->id}/review", [
                'remarks' => 'Commencing project committee review.',
            ]);
        $reviewRes->assertStatus(200)
            ->assertJsonPath('data.workflow_status', 'under_review');

        // 4. Dean approves project (under_review -> approved)
        $approveRes = $this->actingAs($this->dean)
            ->postJson("/api/v1/projects/{$project->id}/approve", [
                'remarks' => 'Proposal approved for institution funding.',
            ]);
        $approveRes->assertStatus(200)
            ->assertJsonPath('data.workflow_status', 'approved');

        $this->assertEquals('approved', $project->fresh()->workflow_status);

        // 4. Audit Trail verification
        $this->assertDatabaseHas('audit_events', [
            'target_type' => 'project_proposal',
            'target_id' => (string) $project->id,
            'action' => 'projects.approved',
        ]);
    }

    public function test_research_return_and_rejection(): void
    {
        $doc = Document::create([
            'reference_number' => 'WU/RES/TEST/002',
            'title' => 'Water Resource Management Study',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->unit->id,
            'custodian_id' => $this->staff->id,
            'created_by' => $this->staff->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $research = ResearchRecord::create([
            'document_id' => $doc->id,
            'research_type' => 'academic_monograph',
            'workflow_status' => 'submitted',
            'recorded_by' => $this->staff->id,
            'recorded_at' => now(),
        ]);

        // Return for revision
        $returnRes = $this->actingAs($this->departmentHead)
            ->postJson("/api/v1/research/outputs/{$research->id}/return", [
                'remarks' => 'Please provide detailed field data analysis.',
            ]);
        $returnRes->assertStatus(200)
            ->assertJsonPath('data.workflow_status', 'returned');

        $this->assertEquals('returned', $research->fresh()->workflow_status);

        // Re-submit
        $resubmitRes = $this->actingAs($this->staff)
            ->postJson("/api/v1/research/outputs/{$research->id}/submit", [
                'remarks' => 'Field data included.',
            ]);
        $resubmitRes->assertStatus(200)
            ->assertJsonPath('data.workflow_status', 'submitted');

        // Reject
        $rejectRes = $this->actingAs($this->departmentHead)
            ->postJson("/api/v1/research/outputs/{$research->id}/reject", [
                'remarks' => 'Duplicate research scope.',
            ]);
        $rejectRes->assertStatus(200)
            ->assertJsonPath('data.workflow_status', 'rejected');

        $this->assertEquals('rejected', $research->fresh()->workflow_status);
    }
}
