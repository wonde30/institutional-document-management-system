<?php

namespace Tests\Feature;

use App\Models\AccreditationCycle;
use App\Models\AccreditationEvidence;
use App\Models\AccreditationFramework;
use App\Models\AccreditationFrameworkVersion;
use App\Models\AccreditationReview;
use App\Models\AccreditationStandard;
use App\Models\ConfidentialityLevel;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\OrganizationUnit;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccreditationLifecycleAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $qaAuditor;
    protected User $deptHead;
    protected User $lecturer;
    protected User $unauthorizedUser;
    protected OrganizationUnit $informaticsDept;
    protected OrganizationUnit $agricultureDept;
    protected AccreditationFrameworkVersion $herqaVersion;
    protected AccreditationStandard $standard1;
    protected AccreditationStandard $standard2;
    protected AccreditationStandard $standard3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $this->admin = User::where('email', 'admin@wu.edu.et')->first();
        $rootUnit = OrganizationUnit::first();

        $deptType = UnitType::where('code', 'department')->first()
            ?? UnitType::create(['code' => 'department', 'name' => 'Department', 'active' => 1]);

        $this->informaticsDept = OrganizationUnit::create([
            'parent_id' => $rootUnit->id,
            'unit_type_id' => $deptType->id,
            'code' => 'INFORMATICS_DEPT',
            'name' => 'Department of Informatics',
            'active' => true,
        ]);

        $this->agricultureDept = OrganizationUnit::create([
            'parent_id' => $rootUnit->id,
            'unit_type_id' => $deptType->id,
            'code' => 'AGRICULTURE_DEPT',
            'name' => 'Department of Agriculture',
            'active' => true,
        ]);

        // Actor 1: Quality Assurance Auditor (Global Scope)
        $this->qaAuditor = User::create([
            'name' => 'Dr. Institutional Auditor Tesfaye',
            'email' => 'auditor.tesfaye@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->qaAuditor, 'admin', 'global');
        $this->grantPermissionToUser($this->qaAuditor, 'accreditation.view', 'global');
        $this->grantPermissionToUser($this->qaAuditor, 'accreditation.manage', 'global');
        $this->grantPermissionToUser($this->qaAuditor, 'accreditation.review_evidence', 'global');
        $this->grantPermissionToUser($this->qaAuditor, 'accreditation.export', 'global');

        // Actor 2: Department Head (Informatics)
        $this->deptHead = User::create([
            'name' => 'Prof. Head Almaz',
            'email' => 'head.almaz@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->deptHead, 'department_head', 'unit', $this->informaticsDept->id);
        $this->grantPermissionToUser($this->deptHead, 'accreditation.view', 'unit', $this->informaticsDept->id);
        $this->grantPermissionToUser($this->deptHead, 'accreditation.submit_evidence', 'unit', $this->informaticsDept->id);

        // Actor 3: Academic Staff / Lecturer (Informatics)
        $this->lecturer = User::create([
            'name' => 'Dr. Lecturer Bekele',
            'email' => 'lecturer.bekele@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->lecturer, 'staff', 'unit', $this->informaticsDept->id);
        $this->grantPermissionToUser($this->lecturer, 'accreditation.view', 'unit', $this->informaticsDept->id);
        $this->grantPermissionToUser($this->lecturer, 'accreditation.submit_evidence', 'unit', $this->informaticsDept->id);
        $this->grantPermissionToUser($this->lecturer, 'documents.create', 'unit', $this->informaticsDept->id);
        $this->grantPermissionToUser($this->lecturer, 'documents.view', 'unit', $this->informaticsDept->id);

        // Actor 4: Unauthorized User (Agriculture) without accreditation permissions
        $this->unauthorizedUser = User::create([
            'name' => 'Dr. External Desta',
            'email' => 'desta.agri@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->unauthorizedUser, 'staff', 'unit', $this->agricultureDept->id);
        // Note: unauthorizedUser has zero accreditation permissions

        $this->herqaVersion = AccreditationFrameworkVersion::first();
        $this->standard1 = AccreditationStandard::where('code', 'HERQA_STD_01')->first();
        $this->standard2 = AccreditationStandard::where('code', 'HERQA_STD_02')->first();
        $this->standard3 = AccreditationStandard::where('code', 'HERQA_STD_03')->first();
    }

    /**
     * Track C: Create and Manage Accreditation Cycles with Validation Constraints.
     */
    public function test_cycle_lifecycle_and_date_boundary_validations(): void
    {
        $this->authenticateAs($this->qaAuditor);

        // 1. Invalid date range (ends_on before starts_on) must fail with 422
        $invalidDates = $this->postJson('/api/v1/accreditation/cycles', [
            'framework_version_id' => $this->herqaVersion->id,
            'unit_id' => $this->informaticsDept->id,
            'name' => 'Invalid Date Cycle',
            'starts_on' => '2026-12-31',
            'ends_on' => '2026-01-01',
        ]);
        $invalidDates->assertStatus(422)
            ->assertJsonValidationErrors(['ends_on']);

        // 2. Valid cycle creation
        $validCycle = $this->postJson('/api/v1/accreditation/cycles', [
            'framework_version_id' => $this->herqaVersion->id,
            'unit_id' => $this->informaticsDept->id,
            'name' => 'Informatics Department Institutional QA Cycle 2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);
        $validCycle->assertStatus(201)
            ->assertJsonPath('success', true);
        $cycleId = $validCycle->json('data.id');

        $this->assertDatabaseHas('accreditation_cycles', [
            'id' => $cycleId,
            'name' => 'Informatics Department Institutional QA Cycle 2026',
            'unit_id' => $this->informaticsDept->id,
        ]);

        // 3. Duplicate cycle name for same unit and framework must fail with 422
        $dupCycle = $this->postJson('/api/v1/accreditation/cycles', [
            'framework_version_id' => $this->herqaVersion->id,
            'unit_id' => $this->informaticsDept->id,
            'name' => 'Informatics Department Institutional QA Cycle 2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);
        $dupCycle->assertStatus(422)
            ->assertJsonPath('success', false);

        // 4. Update cycle details
        $updateResp = $this->putJson("/api/v1/accreditation/cycles/{$cycleId}", [
            'name' => 'Informatics Department Extended QA Cycle 2026',
            'ends_on' => '2027-01-31',
        ]);
        $updateResp->assertStatus(200);
        $this->assertEquals('Informatics Department Extended QA Cycle 2026', AccreditationCycle::find($cycleId)->name);
    }

    /**
     * Track D: Complete Evidence Lifecycle (Submission -> Review -> Needs Revision -> Resubmission).
     */
    public function test_evidence_submission_review_and_audit_lifecycle(): void
    {
        // Setup Cycle
        $cycle = AccreditationCycle::create([
            'framework_version_id' => $this->herqaVersion->id,
            'unit_id' => $this->informaticsDept->id,
            'name' => '2026 Curriculum Review Cycle',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);

        // Lecturer registers document with version 1
        $doc = Document::create([
            'title' => 'Software Engineering Undergraduate Modular Curriculum Dossier',
            'category_id' => DocumentCategory::first()->id,
            'confidentiality_id' => ConfidentialityLevel::first()->id,
            'owner_unit_id' => $this->informaticsDept->id,
            'created_by' => $this->lecturer->id,
            'reference_number' => 'WU/INF/CURR/' . uniqid(),
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $ver1 = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $this->lecturer->id,
            'change_note' => 'Initial modular curriculum dossier',
            'created_at' => now(),
        ]);

        // Step 1: Lecturer submits Version 1 as evidence for Standard 2 (Curricula Relevance)
        $this->authenticateAs($this->lecturer);
        $submitResp = $this->postJson('/api/v1/accreditation/evidence', [
            'cycle_id' => $cycle->id,
            'standard_id' => $this->standard2->id,
            'document_version_id' => $ver1->id,
            'note' => 'Submitted official modular curriculum dossier with industry feedback.',
        ]);
        $submitResp->assertStatus(201);
        $evidenceId = $submitResp->json('data.id');

        $this->assertDatabaseHas('accreditation_evidence', [
            'id' => $evidenceId,
            'cycle_id' => $cycle->id,
            'standard_id' => $this->standard2->id,
            'document_version_id' => $ver1->id,
            'submitted_by' => $this->lecturer->id,
        ]);

        // Step 2: Quality Auditor examines evidence and records decision 'needs_revision'
        $this->authenticateAs($this->qaAuditor);
        $rev1 = $this->postJson("/api/v1/accreditation/evidence/{$evidenceId}/reviews", [
            'decision' => 'needs_revision',
            'comment' => 'Missing external industry advisory board meeting minutes.',
        ]);
        $rev1->assertStatus(201)
            ->assertJsonPath('data.decision', 'needs_revision');

        $this->assertDatabaseHas('accreditation_reviews', [
            'evidence_id' => $evidenceId,
            'reviewer_id' => $this->qaAuditor->id,
            'decision' => 'needs_revision',
        ]);

        // Step 3: Lecturer adds industry minutes and creates Version 2
        $ver2 = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 2,
            'created_by' => $this->lecturer->id,
            'change_note' => 'Appended external industry advisory minutes and employer survey findings',
            'created_at' => now(),
        ]);

        // Step 4: Lecturer submits Version 2
        $this->authenticateAs($this->lecturer);
        $submitVer2 = $this->postJson('/api/v1/accreditation/evidence', [
            'cycle_id' => $cycle->id,
            'standard_id' => $this->standard2->id,
            'document_version_id' => $ver2->id,
            'note' => 'Resubmitted with industry advisory board endorsement minutes included.',
        ]);
        $submitVer2->assertStatus(201);
        $evidenceId2 = $submitVer2->json('data.id');

        // Step 5: Quality Auditor approves Version 2 as 'accepted'
        $this->authenticateAs($this->qaAuditor);
        $rev2 = $this->postJson("/api/v1/accreditation/evidence/{$evidenceId2}/reviews", [
            'decision' => 'accepted',
            'comment' => 'Industry advisory board minutes verified. Meets HERQA Standard 2 requirements.',
        ]);
        $rev2->assertStatus(201)
            ->assertJsonPath('data.decision', 'accepted');

        // Verify historical preservation: both evidence submissions and reviews remain intact
        $this->assertDatabaseHas('accreditation_evidence', ['id' => $evidenceId, 'document_version_id' => $ver1->id]);
        $this->assertDatabaseHas('accreditation_evidence', ['id' => $evidenceId2, 'document_version_id' => $ver2->id]);
        $this->assertEquals(2, AccreditationReview::count());
    }

    /**
     * Track E: Evidence Mapping Completeness & Live Analytics Verification.
     */
    public function test_evidence_completeness_analytics_real_record_calculation(): void
    {
        $cycle = AccreditationCycle::create([
            'framework_version_id' => $this->herqaVersion->id,
            'unit_id' => $this->informaticsDept->id,
            'name' => 'Completeness Benchmark Cycle',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);

        $doc = Document::create([
            'title' => 'QA Benchmark Dossier',
            'category_id' => DocumentCategory::first()->id,
            'confidentiality_id' => ConfidentialityLevel::first()->id,
            'owner_unit_id' => $this->informaticsDept->id,
            'created_by' => $this->lecturer->id,
            'reference_number' => 'WU/BENCH/' . uniqid(),
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $v1 = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $this->lecturer->id,
            'created_at' => now(),
        ]);

        // Submit evidence for Standard 1 (Accepted)
        $ev1 = AccreditationEvidence::create([
            'cycle_id' => $cycle->id,
            'standard_id' => $this->standard1->id,
            'document_version_id' => $v1->id,
            'submitted_by' => $this->lecturer->id,
            'submitted_at' => now(),
        ]);
        AccreditationReview::create([
            'evidence_id' => $ev1->id,
            'reviewer_id' => $this->qaAuditor->id,
            'decision' => 'accepted',
            'comment' => 'Standard 1 validated',
            'reviewed_at' => now(),
        ]);

        // Submit evidence for Standard 2 (Needs Revision)
        $ev2 = AccreditationEvidence::create([
            'cycle_id' => $cycle->id,
            'standard_id' => $this->standard2->id,
            'document_version_id' => $v1->id,
            'submitted_by' => $this->lecturer->id,
            'submitted_at' => now(),
        ]);
        AccreditationReview::create([
            'evidence_id' => $ev2->id,
            'reviewer_id' => $this->qaAuditor->id,
            'decision' => 'needs_revision',
            'comment' => 'Revision required',
            'reviewed_at' => now(),
        ]);

        // Submit evidence for Standard 3 (Pending Review)
        $ev3 = AccreditationEvidence::create([
            'cycle_id' => $cycle->id,
            'standard_id' => $this->standard3->id,
            'document_version_id' => $v1->id,
            'submitted_by' => $this->lecturer->id,
            'submitted_at' => now(),
        ]);

        // Fetch summary
        $this->authenticateAs($this->qaAuditor);
        $summaryResp = $this->getJson("/api/v1/accreditation/cycles/{$cycle->id}/summary");
        $summaryResp->assertStatus(200);

        $metrics = $summaryResp->json('data.metrics');
        $this->assertEquals(10, $metrics['total_standards']); // 10 HERQA standards
        $this->assertEquals(3, $metrics['covered_standards']); // Standards 1, 2, 3
        $this->assertEquals(7, $metrics['missing_standards']); // 7 missing
        $this->assertEquals(1, $metrics['accepted_standards']); // Standard 1
        $this->assertEquals(1, $metrics['needs_revision']); // Standard 2
        $this->assertEquals(1, $metrics['pending_review']); // Standard 3
        $this->assertEquals(30.0, $metrics['submission_rate_percent']); // 3 / 10 = 30%
        $this->assertEquals(10.0, $metrics['acceptance_rate_percent']); // 1 / 10 = 10%
    }

    /**
     * Track F: Dynamic RBAC Barriers & Closed Cycle Protections.
     */
    public function test_rbac_security_barriers_and_closed_cycle_rejections(): void
    {
        // 1. Unauthorized actor attempts to create accreditation cycle -> 403 Forbidden
        $this->authenticateAs($this->unauthorizedUser);
        $unauthCycle = $this->postJson('/api/v1/accreditation/cycles', [
            'framework_version_id' => $this->herqaVersion->id,
            'unit_id' => $this->agricultureDept->id,
            'name' => 'Unauthorized Agriculture Cycle',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);
        $unauthCycle->assertStatus(403);

        // 2. Setup closed cycle (ends_on in the past)
        $closedCycle = AccreditationCycle::create([
            'framework_version_id' => $this->herqaVersion->id,
            'unit_id' => $this->informaticsDept->id,
            'name' => 'Historical Closed QA Cycle 2024',
            'starts_on' => '2024-01-01',
            'ends_on' => '2024-12-31', // Past date
        ]);

        $doc = Document::create([
            'title' => 'Late Document Submission Attempt',
            'category_id' => DocumentCategory::first()->id,
            'confidentiality_id' => ConfidentialityLevel::first()->id,
            'owner_unit_id' => $this->informaticsDept->id,
            'created_by' => $this->lecturer->id,
            'reference_number' => 'WU/LATE/' . uniqid(),
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $v1 = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $this->lecturer->id,
            'created_at' => now(),
        ]);

        // 3. Lecturer attempts to submit evidence to closed cycle -> rejected with 422
        $this->authenticateAs($this->lecturer);
        $closedSubmit = $this->postJson('/api/v1/accreditation/evidence', [
            'cycle_id' => $closedCycle->id,
            'standard_id' => $this->standard1->id,
            'document_version_id' => $v1->id,
        ]);
        $closedSubmit->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Accreditation submission rejected: The selected accreditation cycle was closed on 2024-12-31. Closed cycles cannot receive new evidence.',
            ]);

        // 4. Lecturer without review permission attempts to record review decision -> 403 Forbidden
        $openCycle = AccreditationCycle::create([
            'framework_version_id' => $this->herqaVersion->id,
            'unit_id' => $this->informaticsDept->id,
            'name' => 'Open Active Cycle 2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);

        $ev = AccreditationEvidence::create([
            'cycle_id' => $openCycle->id,
            'standard_id' => $this->standard1->id,
            'document_version_id' => $v1->id,
            'submitted_by' => $this->lecturer->id,
            'submitted_at' => now(),
        ]);

        $unauthReview = $this->postJson("/api/v1/accreditation/evidence/{$ev->id}/reviews", [
            'decision' => 'accepted',
            'comment' => 'Self-approving my own submission',
        ]);
        $unauthReview->assertStatus(403);
    }
}
