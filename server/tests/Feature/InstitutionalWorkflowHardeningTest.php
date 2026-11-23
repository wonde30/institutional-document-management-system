<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ConfidentialityLevel;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\OrganizationUnit;
use App\Models\ProjectProposal;
use App\Models\ProjectType;
use App\Models\ResearchRecord;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InstitutionalWorkflowHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $authorUser;
    protected User $reviewerUser;
    protected User $otherUnitUser;
    protected OrganizationUnit $unitSE;
    protected OrganizationUnit $unitEE;
    protected DocumentCategory $cat;
    protected ConfidentialityLevel $conf;
    protected ProjectType $projectType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $units = OrganizationUnit::take(2)->get();
        $this->unitSE = $units[0];
        $this->unitEE = $units[1];

        $this->cat = DocumentCategory::first();
        $this->conf = ConfidentialityLevel::where('code', 'internal')->first() ?? ConfidentialityLevel::first();
        $this->projectType = ProjectType::first();

        // 1. Author user (has submit, review, approve in unitSE)
        $this->authorUser = User::create([
            'name' => 'Author Faculty',
            'email' => 'author.' . Str::random(6) . '@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($this->authorUser, 'research.create', 'unit', $this->unitSE->id);
        $this->grantPermissionToUser($this->authorUser, 'research.submit', 'unit', $this->unitSE->id);
        $this->grantPermissionToUser($this->authorUser, 'research.review', 'unit', $this->unitSE->id);
        $this->grantPermissionToUser($this->authorUser, 'research.approve', 'unit', $this->unitSE->id);
        $this->grantPermissionToUser($this->authorUser, 'projects.submit', 'unit', $this->unitSE->id);
        $this->grantPermissionToUser($this->authorUser, 'projects.review', 'unit', $this->unitSE->id);
        $this->grantPermissionToUser($this->authorUser, 'projects.approve', 'unit', $this->unitSE->id);

        // 2. Separate Reviewer / Approver user (in unitSE)
        $this->reviewerUser = User::create([
            'name' => 'Department Head Reviewer',
            'email' => 'reviewer.' . Str::random(6) . '@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($this->reviewerUser, 'research.review', 'unit', $this->unitSE->id);
        $this->grantPermissionToUser($this->reviewerUser, 'research.approve', 'unit', $this->unitSE->id);
        $this->grantPermissionToUser($this->reviewerUser, 'research.reject', 'unit', $this->unitSE->id);
        $this->grantPermissionToUser($this->reviewerUser, 'projects.review', 'unit', $this->unitSE->id);
        $this->grantPermissionToUser($this->reviewerUser, 'projects.approve', 'unit', $this->unitSE->id);
        $this->grantPermissionToUser($this->reviewerUser, 'projects.reject', 'unit', $this->unitSE->id);

        // 3. User in different unit (unitEE)
        $this->otherUnitUser = User::create([
            'name' => 'Other Department Staff',
            'email' => 'other.' . Str::random(6) . '@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($this->otherUnitUser, 'research.approve', 'unit', $this->unitEE->id);
        $this->grantPermissionToUser($this->otherUnitUser, 'projects.approve', 'unit', $this->unitEE->id);
    }

    protected function createResearchRecord(User $creator): ResearchRecord
    {
        $doc = Document::create([
            'reference_number' => 'WU/RES/TEST/' . Str::random(6),
            'title' => 'Monograph On Quantum Cryptography',
            'summary' => 'Research output draft',
            'category_id' => $this->cat->id,
            'confidentiality_id' => $this->conf->id,
            'owner_unit_id' => $this->unitSE->id,
            'created_by' => $creator->id,
            'custodian_id' => $creator->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        return ResearchRecord::create([
            'document_id' => $doc->id,
            'research_type' => 'faculty_monograph',
            'completed_on' => now()->toDateString(),
            'recorded_by' => $creator->id,
            'recorded_at' => now(),
            'workflow_status' => 'draft',
        ]);
    }

    protected function createProjectProposal(User $creator): ProjectProposal
    {
        $doc = Document::create([
            'reference_number' => 'WU/PRJ/TEST/' . Str::random(6),
            'title' => 'Campus Green Energy Initiative',
            'summary' => 'Project proposal draft',
            'category_id' => $this->cat->id,
            'confidentiality_id' => $this->conf->id,
            'owner_unit_id' => $this->unitSE->id,
            'created_by' => $creator->id,
            'custodian_id' => $creator->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        return ProjectProposal::create([
            'document_id' => $doc->id,
            'project_type_id' => $this->projectType->id,
            'recorded_by' => $creator->id,
            'recorded_at' => now(),
            'workflow_status' => 'draft',
        ]);
    }

    public function test_submitter_cannot_approve_or_review_own_research_output(): void
    {
        $research = $this->createResearchRecord($this->authorUser);

        // Author submits
        $this->authenticateAs($this->authorUser);
        $subRes = $this->postJson("/api/v1/research/outputs/{$research->id}/submit");
        $subRes->assertStatus(200);
        $this->assertEquals('submitted', $research->fresh()->workflow_status);

        // Author attempts self-review -> MUST FAIL (422)
        $selfReviewRes = $this->postJson("/api/v1/research/outputs/{$research->id}/review", [
            'remarks' => 'Author self reviewing',
        ]);
        $selfReviewRes->assertStatus(422);
        $this->assertEquals('submitted', $research->fresh()->workflow_status);

        // Author attempts self-approval -> MUST FAIL (422)
        $selfApproveRes = $this->postJson("/api/v1/research/outputs/{$research->id}/approve", [
            'remarks' => 'Author self approving',
        ]);
        $selfApproveRes->assertStatus(422);
        $this->assertEquals('submitted', $research->fresh()->workflow_status);
    }

    public function test_submitter_cannot_approve_own_project_proposal(): void
    {
        $project = $this->createProjectProposal($this->authorUser);

        // Author submits
        $this->authenticateAs($this->authorUser);
        $subRes = $this->postJson("/api/v1/projects/{$project->id}/submit");
        $subRes->assertStatus(200);
        $this->assertEquals('submitted', $project->fresh()->workflow_status);

        // Author attempts self-approval -> MUST FAIL (422)
        $selfApproveRes = $this->postJson("/api/v1/projects/{$project->id}/approve", [
            'remarks' => 'Author self approving project',
        ]);
        $selfApproveRes->assertStatus(422);
        $this->assertEquals('submitted', $project->fresh()->workflow_status);
    }

    public function test_separate_authorized_reviewer_can_review_and_approve_research(): void
    {
        $research = $this->createResearchRecord($this->authorUser);

        // Author submits
        $this->authenticateAs($this->authorUser);
        $this->postJson("/api/v1/research/outputs/{$research->id}/submit")->assertStatus(200);

        // Different user reviews
        $this->authenticateAs($this->reviewerUser);
        $revRes = $this->postJson("/api/v1/research/outputs/{$research->id}/review", [
            'remarks' => 'Peer review complete',
        ]);
        $revRes->assertStatus(200);
        $this->assertEquals('under_review', $research->fresh()->workflow_status);

        // Different user approves
        $appRes = $this->postJson("/api/v1/research/outputs/{$research->id}/approve", [
            'remarks' => 'Institutional approval granted',
        ]);
        $appRes->assertStatus(200);
        $this->assertEquals('approved', $research->fresh()->workflow_status);
    }

    public function test_reject_and_return_require_remarks_reason(): void
    {
        $research = $this->createResearchRecord($this->authorUser);

        $this->authenticateAs($this->authorUser);
        $this->postJson("/api/v1/research/outputs/{$research->id}/submit")->assertStatus(200);

        $this->authenticateAs($this->reviewerUser);

        // Reject without remarks -> MUST FAIL 422
        $rejectNoRemarks = $this->postJson("/api/v1/research/outputs/{$research->id}/reject");
        $rejectNoRemarks->assertStatus(422);
        $this->assertEquals('submitted', $research->fresh()->workflow_status);

        // Return without remarks -> MUST FAIL 422
        $returnNoRemarks = $this->postJson("/api/v1/research/outputs/{$research->id}/return");
        $returnNoRemarks->assertStatus(422);
        $this->assertEquals('submitted', $research->fresh()->workflow_status);

        // Return with remarks -> SUCCESS 200
        $returnWithRemarks = $this->postJson("/api/v1/research/outputs/{$research->id}/return", [
            'remarks' => 'Need additional data in chapter 3',
        ]);
        $returnWithRemarks->assertStatus(200);
        $this->assertEquals('returned', $research->fresh()->workflow_status);
    }

    public function test_user_from_different_unit_without_permission_gets_403(): void
    {
        $research = $this->createResearchRecord($this->authorUser);

        $this->authenticateAs($this->authorUser);
        $this->postJson("/api/v1/research/outputs/{$research->id}/submit")->assertStatus(200);

        // otherUnitUser has research.approve in unitEE, not unitSE
        $this->authenticateAs($this->otherUnitUser);
        $unauthApproveRes = $this->postJson("/api/v1/research/outputs/{$research->id}/approve", [
            'remarks' => 'Cross-unit unauthorized approval attempt',
        ]);
        $unauthApproveRes->assertStatus(403);
    }
}
