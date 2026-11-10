<?php

namespace Tests\Feature;

use App\Models\Correspondence;
use App\Models\CorrespondenceType;
use App\Models\DispatchMethod;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\OfficeProcedureVersion;
use App\Models\OrganizationUnit;
use App\Models\PhysicalOriginal;
use App\Models\Priority;
use App\Models\ResearchRecord;
use App\Models\UnitType;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowState;
use App\Models\WorkflowTask;
use App\Models\WorkflowTaskDecision;
use App\Models\WorkflowVersion;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DepartmentalClarificationsRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $csHead;
    protected User $eeHead;
    protected OrganizationUnit $csDept;
    protected OrganizationUnit $eeDept;
    protected OrganizationUnit $rootUnit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $this->rootUnit = OrganizationUnit::first();
        $deptType = UnitType::where('code', 'department')->first()
            ?? UnitType::create(['code' => 'department', 'name' => 'Department', 'active' => 1]);

        $this->csDept = OrganizationUnit::create([
            'parent_id' => $this->rootUnit->id,
            'unit_type_id' => $deptType->id,
            'code' => 'CS_DEPT',
            'name' => 'Department of Computer Science',
            'active' => true,
        ]);

        $this->eeDept = OrganizationUnit::create([
            'parent_id' => $this->rootUnit->id,
            'unit_type_id' => $deptType->id,
            'code' => 'EE_DEPT',
            'name' => 'Department of Electrical Engineering',
            'active' => true,
        ]);

        $this->admin = User::where('email', 'admin@wu.edu.et')->first();

        // CS Department Head
        $this->csHead = User::create([
            'name' => 'Dr. CS Department Head',
            'email' => 'head.cs@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->csHead, 'department_head', 'unit', $this->csDept->id);

        // EE Department Head
        $this->eeHead = User::create([
            'name' => 'Dr. EE Department Head',
            'email' => 'head.ee@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->eeHead, 'department_head', 'unit', $this->eeDept->id);
    }

    /**
     * 1. Regression Test: Research publication requires the correct departmental authority.
     * Unrelated departments cannot publish another department's research.
     */
    public function test_research_publication_requires_correct_departmental_authority(): void
    {
        $this->authenticateAs($this->csHead);

        // CS Head stores research in CS Department
        $res = $this->postJson('/api/v1/research/outputs', [
            'title' => 'Algorithmic Optimization in Ethiopian Grid Networks',
            'office_id' => $this->csDept->id,
            'completion_date' => '2026-10-30',
            'research_type' => 'applied_computing',
            'external_reference' => 'WU-EXT-EVAL-2026-CS01',
            'notes' => 'Externally approved and selected by university research senate',
        ]);

        $res->assertStatus(201);
        $recordId = $res->json('data.id');

        // EE Head attempts to publish CS research -> MUST BE 403 FORBIDDEN
        $this->authenticateAs($this->eeHead);
        $forbiddenRes = $this->postJson("/api/v1/research/outputs/{$recordId}/toggle-publication", [
            'action' => 'publish',
        ]);

        $forbiddenRes->assertStatus(403)
            ->assertJsonPath('success', false);

        // CS Head publishes their own department's research -> 200 OK
        $this->authenticateAs($this->csHead);
        $successRes = $this->postJson("/api/v1/research/outputs/{$recordId}/toggle-publication", [
            'action' => 'publish',
        ]);

        $successRes->assertStatus(200)
            ->assertJsonPath('data.publication_status', 'published');
    }

    /**
     * 2. Regression Test: Unrelated departments cannot alter restricted research.
     */
    public function test_unrelated_departments_cannot_alter_restricted_research(): void
    {
        $this->authenticateAs($this->csHead);

        $res = $this->postJson('/api/v1/research/outputs', [
            'title' => 'Machine Learning Diagnostics for Endemic Crop Pathologies',
            'office_id' => $this->csDept->id,
            'completion_date' => '2026-10-30',
            'research_type' => 'computational_biology',
            'external_reference' => 'WU-EXT-AGRI-2026-09',
        ]);
        $recordId = $res->json('data.id');

        // EE Head attempts to update CS research metadata -> 403 Forbidden
        $this->authenticateAs($this->eeHead);
        $tamperRes = $this->putJson("/api/v1/research/outputs/{$recordId}", [
            'title' => 'Tampered Title by EE Department',
        ]);
        $tamperRes->assertStatus(403);

        // EE Head attempts to upload revision version -> 403 Forbidden
        $versionRes = $this->postJson("/api/v1/research/outputs/{$recordId}/versions", [
            'change_note' => 'Unauthorized revision by EE Department',
        ]);
        $versionRes->assertStatus(403);

        // CS Head updates their own research -> 200 OK
        $this->authenticateAs($this->csHead);
        $validUpdate = $this->putJson("/api/v1/research/outputs/{$recordId}", [
            'title' => 'Machine Learning Diagnostics for Endemic Crop Pathologies - Revised Edition',
        ]);
        $validUpdate->assertStatus(200)
            ->assertJsonPath('data.document.title', 'Machine Learning Diagnostics for Endemic Crop Pathologies - Revised Edition');

        // CS Head uploads revision version -> 201 Created
        $validVersion = $this->postJson("/api/v1/research/outputs/{$recordId}/versions", [
            'change_note' => 'Approved revision following external faculty peer review',
        ]);
        $validVersion->assertStatus(201)
            ->assertJsonPath('data.version_number', 2);
    }

    /**
     * 3. Regression Test: Direct external receipt preserves its actual entry point.
     * Subsequent movements between offices do not overwrite first receiving office.
     */
    public function test_direct_external_receipt_preserves_actual_entry_point(): void
    {
        $this->authenticateAs($this->csHead);

        $incomingType = CorrespondenceType::where('code', 'incoming')->first();
        $priority = Priority::first();
        $cat = DocumentCategory::first();
        $conf = \App\Models\ConfidentialityLevel::first();

        // 1. Direct receipt at CS Department (first_receiving_unit_id = CS Dept)
        $res = $this->postJson('/api/v1/correspondences', [
            'title' => 'External MoE High Performance Computing Grant Announcement',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $this->csDept->id,
            'type_id' => $incomingType->id,
            'priority_id' => $priority->id,
            'purpose_code' => 'grant_notice',
            'external_reference' => 'MOE-HPC-2026-9921',
            'correspondence_date' => '2026-10-30',
            'first_receiving_unit_id' => $this->csDept->id,
        ]);

        $res->assertStatus(201);
        $corrId = $res->json('data.id');

        // 2. Subsequent forwarding movement to EE Department
        $moveRes = $this->postJson("/api/v1/correspondences/{$corrId}/movements", [
            'from_unit_id' => $this->csDept->id,
            'to_unit_id' => $this->eeDept->id,
            'instruction' => 'Forwarded for joint EE/CS computing laboratory collaboration.',
        ]);
        $moveRes->assertStatus(201);

        // 3. Inspect correspondence: first_receiving_unit_id must remain CS Department
        $corr = Correspondence::findOrFail($corrId);
        $this->assertEquals($this->csDept->id, $corr->first_receiving_unit_id);
    }

    /**
     * 4. Regression Test: Letters requiring approval cannot be dispatched beforehand.
     */
    public function test_letters_requiring_approval_cannot_be_dispatched_beforehand(): void
    {
        $this->authenticateAs($this->csHead);

        $outgoingType = CorrespondenceType::where('code', 'outgoing')->first();
        $priority = Priority::first();
        $cat = DocumentCategory::first();
        $conf = \App\Models\ConfidentialityLevel::first();
        $method = DispatchMethod::first();

        $wfDef = WorkflowDefinition::first();
        $wfVer = WorkflowVersion::create([
            'definition_id' => $wfDef->id,
            'version_number' => 1,
            'created_by' => $this->admin->id,
            'published_at' => now(),
        ]);

        // Set confirmed office procedure for CS Department: issue_mode = 'workflow'
        OfficeProcedureVersion::create([
            'unit_id' => $this->csDept->id,
            'correspondence_type_id' => $outgoingType->id,
            'purpose_code' => 'official_letter',
            'version_number' => 1,
            'receipt_mode' => 'direct',
            'registry_unit_id' => null,
            'issue_mode' => 'workflow',
            'workflow_version_id' => $wfVer->id,
            'confirmed_by' => $this->admin->id,
            'confirmation_source' => 'Senate Secretariat Directive #09/2026',
            'confirmed_at' => now(),
            'effective_from' => now()->subMonth(),
        ]);

        // Register draft outgoing correspondence
        $res = $this->postJson('/api/v1/correspondences', [
            'title' => 'Formal Collaboration Request to Regional Tech Agency',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $this->csDept->id,
            'type_id' => $outgoingType->id,
            'priority_id' => $priority->id,
            'purpose_code' => 'official_letter',
            'correspondence_date' => '2026-10-30',
        ]);
        $res->assertStatus(201);
        $corrId = $res->json('data.id');

        $corr = Correspondence::with('document.versions')->findOrFail($corrId);
        $ver1 = $corr->document->versions->first();

        // Attempt dispatch without workflow approval -> MUST FAIL (422)
        $dispatchRes = $this->postJson("/api/v1/correspondences/{$corrId}/dispatch", [
            'document_version_id' => $ver1->id,
            'method_id' => $method->id,
            'tracking_reference' => 'DISP-UNAPPROVED-TEST',
        ]);

        $dispatchRes->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertStringContainsString('requires Department Head workflow approval before dispatch', $dispatchRes->json('message'));
    }

    /**
     * 5. Regression Test: Editing approved content cannot reuse approval improperly.
     * Material modifications (Version 2) require renewed approval.
     */
    public function test_editing_approved_content_cannot_reuse_approval_improperly(): void
    {
        $this->authenticateAs($this->csHead);

        $outgoingType = CorrespondenceType::where('code', 'outgoing')->first();
        $priority = Priority::first();
        $cat = DocumentCategory::first();
        $conf = \App\Models\ConfidentialityLevel::first();
        $method = DispatchMethod::first();

        $wfDef = WorkflowDefinition::first();
        $wfVer = WorkflowVersion::create([
            'definition_id' => $wfDef->id,
            'version_number' => 1,
            'created_by' => $this->admin->id,
            'published_at' => now(),
        ]);

        // 1. Confirmed procedure with issue_mode = 'workflow'
        OfficeProcedureVersion::create([
            'unit_id' => $this->csDept->id,
            'correspondence_type_id' => $outgoingType->id,
            'purpose_code' => 'official_directive',
            'version_number' => 1,
            'receipt_mode' => 'direct',
            'registry_unit_id' => null,
            'issue_mode' => 'workflow',
            'workflow_version_id' => $wfVer->id,
            'confirmed_by' => $this->admin->id,
            'confirmation_source' => 'Dean Council Policy #01',
            'confirmed_at' => now(),
            'effective_from' => now()->subMonth(),
        ]);

        // 2. Register outgoing letter
        $res = $this->postJson('/api/v1/correspondences', [
            'title' => 'Official Department Notification to Academic Commission',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $this->csDept->id,
            'type_id' => $outgoingType->id,
            'priority_id' => $priority->id,
            'purpose_code' => 'official_directive',
            'correspondence_date' => '2026-10-30',
        ]);
        $corrId = $res->json('data.id');
        $corr = Correspondence::with('document.versions')->findOrFail($corrId);
        $ver1 = $corr->document->versions->first();

        // 3. Workflow setup and approval for Version 1
        $state = WorkflowState::create([
            'workflow_version_id' => $wfVer->id,
            'code' => 'approved_state',
            'name' => 'Approved',
            'is_initial' => 0,
            'is_terminal' => 1,
        ]);
        $instance = WorkflowInstance::create([
            'document_version_id' => $ver1->id,
            'workflow_version_id' => $wfVer->id,
            'started_by' => $this->csHead->id,
            'started_at' => now(),
        ]);
        $task = WorkflowTask::create([
            'instance_id' => $instance->id,
            'state_id' => $state->id,
            'assigned_user_id' => $this->csHead->id,
            'created_at' => now(),
        ]);
        WorkflowTaskDecision::create([
            'task_id' => $task->id,
            'decided_by' => $this->csHead->id,
            'decision' => 'approve',
            'reason' => 'Official content reviewed and approved by CS department head.',
            'decided_at' => now(),
        ]);

        // 4. Material modification: Version 2 is authored
        $ver2 = DocumentVersion::create([
            'document_id' => $corr->document_id,
            'version_number' => 2,
            'created_by' => $this->csHead->id,
            'created_at' => now(),
            'change_note' => 'Material text modification altering commitment terms',
        ]);

        // 5. Attempt to dispatch Version 2 using approval from Version 1 -> MUST FAIL (422)
        $dispatchVer2Res = $this->postJson("/api/v1/correspondences/{$corrId}/dispatch", [
            'document_version_id' => $ver2->id,
            'method_id' => $method->id,
        ]);

        $dispatchVer2Res->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertStringContainsString('Approval mismatch: Material modifications detected', $dispatchVer2Res->json('message'));

        // 6. Dispatch of approved Version 1 succeeds -> 201 Created
        $dispatchVer1Res = $this->postJson("/api/v1/correspondences/{$corrId}/dispatch", [
            'document_version_id' => $ver1->id,
            'method_id' => $method->id,
            'tracking_reference' => 'DISP-VER1-APPROVED',
        ]);

        $dispatchVer1Res->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    /**
     * 6. Regression Test: Uploading an HR scan does not authorize disposal of its original.
     */
    public function test_uploading_hr_scan_does_not_authorize_disposal_of_original(): void
    {
        $this->authenticateAs($this->admin);

        // 1. Create physical original record with physical_retention = 'required' and electronic scan
        $res = $this->postJson('/api/v1/staff/records', [
            'title' => 'Permanent Faculty Service Dossier - Original Appointment & Credentials',
            'office_id' => $this->csDept->id,
            'location_description' => 'University Central Archive Vault Bay B, Shelf 14',
            'physical_retention' => 'required',
            'physical_reference' => 'HR-VAULT-PERM-2026-001',
            'has_scan' => true,
        ]);

        $res->assertStatus(201);
        $originalId = $res->json('data.id');

        // 2. Records officer verifies the electronic scan
        $verifyRes = $this->postJson("/api/v1/staff/records/{$originalId}/verify-scan", [
            'verification_status' => 'accepted',
            'verification_note' => '600 DPI archival scan verified against physical parchment seal.',
        ]);

        $verifyRes->assertStatus(200);
        $this->assertStringContainsString('A verified scan does NOT authorize destruction of the physical original', $verifyRes->json('message'));

        // Verify in database: physical retention remains REQUIRED
        $original = PhysicalOriginal::findOrFail($originalId);
        $this->assertEquals('required', $original->retention_requirement);

        // 3. Attempt physical destruction based on the premise that a verified scan exists -> MUST BE REJECTED (422)
        $destroyRes = $this->postJson("/api/v1/staff/records/{$originalId}/dispose-physical", [
            'authorization_reference' => 'UNAUTHORIZED_PREMISE_SCAN_EXISTS',
            'reason' => 'Scan already uploaded and verified.',
        ]);

        $destroyRes->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertStringContainsString('Physical disposal prohibited: Physical original retention is designated as REQUIRED', $destroyRes->json('message'));
        $this->assertStringContainsString('Uploading or verifying a digital scan does NOT authorize destruction of the physical original', $destroyRes->json('message'));

        // Confirm no 'destroy' custody event was created
        $this->assertDatabaseMissing('physical_custody_events', [
            'physical_original_id' => $originalId,
            'action' => 'destroy',
        ]);
    }
}
