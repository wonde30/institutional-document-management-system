<?php

namespace Tests\Feature;

use App\Models\Correspondence;
use App\Models\CorrespondenceType;
use App\Models\DispatchMethod;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\FileObject;
use App\Models\OfficeProcedureVersion;
use App\Models\OrganizationUnit;
use App\Models\PhysicalOriginal;
use App\Models\Priority;
use App\Models\ResearchRecord;
use App\Models\UnitType;
use App\Models\User;
use App\Models\VersionFile;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowState;
use App\Models\WorkflowTask;
use App\Models\WorkflowTaskDecision;
use App\Models\WorkflowVersion;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityPenetrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $csOfficer;
    protected User $eeOfficer;
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

        // CS Scoped Records Officer
        $this->csOfficer = User::create([
            'name' => 'CS Department Officer',
            'email' => 'officer.cs@wu.edu.et',
            'password' => bcrypt('SecurePassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->csOfficer, 'records_officer', 'unit', $this->csDept->id);
        $this->grantPermissionToUser($this->csOfficer, 'research.view', 'unit', $this->csDept->id);
        $this->grantPermissionToUser($this->csOfficer, 'research.publish', 'unit', $this->csDept->id);
        $this->grantPermissionToUser($this->csOfficer, 'research.update', 'unit', $this->csDept->id);
        $this->grantPermissionToUser($this->csOfficer, 'correspondence.dispatch', 'unit', $this->csDept->id);

        // EE Scoped Records Officer
        $this->eeOfficer = User::create([
            'name' => 'EE Department Officer',
            'email' => 'officer.ee@wu.edu.et',
            'password' => bcrypt('SecurePassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->eeOfficer, 'records_officer', 'unit', $this->eeDept->id);
        $this->grantPermissionToUser($this->eeOfficer, 'research.view', 'unit', $this->eeDept->id);
        $this->grantPermissionToUser($this->eeOfficer, 'research.publish', 'unit', $this->eeDept->id);
        $this->grantPermissionToUser($this->eeOfficer, 'research.update', 'unit', $this->eeDept->id);
        $this->grantPermissionToUser($this->eeOfficer, 'correspondence.dispatch', 'unit', $this->eeDept->id);
    }

    public function test_cross_unit_scope_escalation_on_research_publication_attempt(): void
    {
        // Create EE department research paper
        $doc = Document::create([
            'title' => 'Advanced EE Signal Processing Systems',
            'category_id' => DocumentCategory::first()->id,
            'confidentiality_id' => \App\Models\ConfidentialityLevel::first()->id,
            'owner_unit_id' => $this->eeDept->id,
            'created_by' => $this->eeOfficer->id,
            'reference_number' => 'WU/EE/RES/2026/001',
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $eeResearch = ResearchRecord::create([
            'document_id' => $doc->id,
            'recorded_by' => $this->eeOfficer->id,
            'recorded_at' => now(),
            'research_type' => 'institutional_funded',
            'completed_on' => '2026-06-15',
            'external_selection_reference' => 'WU-EXT-EE-001',
        ]);

        // CS Officer attempts to publish EE research
        $this->authenticateAs($this->csOfficer);

        $response = $this->postJson("/api/v1/research/outputs/{$eeResearch->id}/toggle-publication", [
            'action' => 'publish',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('research_publications', [
            'research_id' => $eeResearch->id,
        ]);
    }

    public function test_cross_unit_scope_escalation_on_research_metadata_update(): void
    {
        $doc = Document::create([
            'title' => 'EE Power Distribution Study',
            'category_id' => DocumentCategory::first()->id,
            'confidentiality_id' => \App\Models\ConfidentialityLevel::first()->id,
            'owner_unit_id' => $this->eeDept->id,
            'created_by' => $this->eeOfficer->id,
            'reference_number' => 'WU/EE/RES/2026/002',
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $eeResearch = ResearchRecord::create([
            'document_id' => $doc->id,
            'recorded_by' => $this->eeOfficer->id,
            'recorded_at' => now(),
            'research_type' => 'institutional_funded',
            'completed_on' => '2026-06-15',
            'external_selection_reference' => 'WU-EXT-EE-002',
        ]);

        // CS Officer attempts to tamper with EE research title
        $this->authenticateAs($this->csOfficer);

        $response = $this->putJson("/api/v1/research/outputs/{$eeResearch->id}", [
            'title' => 'Tampered Title By CS Officer',
            'notes' => 'Maliciously injected notes',
        ]);

        $response->assertStatus(403);
        $this->assertEquals('EE Power Distribution Study', $doc->fresh()->title);
    }

    public function test_parameter_tampering_owner_unit_spoofing(): void
    {
        $this->authenticateAs($this->csOfficer);

        // CS Officer tries to create a correspondence owned by EE department
        $response = $this->postJson('/api/v1/correspondences', [
            'title' => 'Spoofed EE Correspondence',
            'category_id' => DocumentCategory::first()->id,
            'confidentiality_id' => \App\Models\ConfidentialityLevel::first()->id,
            'owner_unit_id' => $this->eeDept->id,
            'type_id' => CorrespondenceType::first()->id,
            'priority_id' => Priority::first()->id,
            'purpose_code' => 'general_memo',
            'correspondence_date' => '2026-10-30',
        ]);

        // Denied because CS officer does not have correspondence.register in EE dept
        $response->assertStatus(403);
    }

    public function test_parameter_tampering_idor_on_user_privilege_escalation(): void
    {
        $this->authenticateAs($this->csOfficer);

        // CS Officer attempts to grant themselves superadmin role or modify authz_version
        $response = $this->putJson("/api/v1/users/{$this->csOfficer->id}", [
            'name' => 'CS Officer Escalated',
            'status' => 'inactive',
            'authz_version' => 99,
        ]);

        // Missing users.update permission
        $response->assertStatus(403);
    }

    public function test_dispatch_bypass_rejected_for_unapproved_outgoing_correspondence(): void
    {
        $wfDef = WorkflowDefinition::first();
        $workflowVer = WorkflowVersion::create([
            'definition_id' => $wfDef->id,
            'version_number' => 1,
            'created_by' => $this->admin->id,
            'published_at' => now(),
        ]);

        $outType = CorrespondenceType::where('code', 'outgoing')->first()
            ?? CorrespondenceType::create(['code' => 'outgoing', 'name' => 'Outgoing', 'active' => true]);

        OfficeProcedureVersion::create([
            'unit_id' => $this->csDept->id,
            'correspondence_type_id' => $outType->id,
            'purpose_code' => 'official_directive',
            'version_number' => 1,
            'receipt_mode' => 'direct',
            'issue_mode' => 'workflow',
            'workflow_version_id' => $workflowVer->id,
            'confirmed_at' => now(),
            'confirmed_by' => $this->admin->id,
            'confirmation_source' => 'executive_order',
            'effective_from' => now(),
        ]);

        // 2. Create outgoing correspondence without approval
        $doc = Document::create([
            'title' => 'Unapproved Letter to Ministry of Education',
            'category_id' => DocumentCategory::first()->id,
            'confidentiality_id' => \App\Models\ConfidentialityLevel::first()->id,
            'owner_unit_id' => $this->csDept->id,
            'created_by' => $this->csOfficer->id,
            'reference_number' => 'WU/CS/OUT/2026/001',
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $ver = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $this->csOfficer->id,
            'change_note' => 'Initial letter draft',
        ]);

        $corr = Correspondence::create([
            'document_id' => $doc->id,
            'type_id' => $outType->id,
            'priority_id' => Priority::first()->id,
            'purpose_code' => 'official_directive',
            'correspondence_date' => '2026-10-30',
            'registered_by' => $this->csOfficer->id,
            'registered_at' => now(),
        ]);

        $this->authenticateAs($this->csOfficer);

        // 3. Attempt direct dispatch without workflow approval
        $dispatchResponse = $this->postJson("/api/v1/correspondences/{$corr->id}/dispatch", [
            'document_version_id' => $ver->id,
            'method_id' => DispatchMethod::first()->id,
            'tracking_reference' => 'UNAPPROVED-DISPATCH-999',
        ]);

        $dispatchResponse->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Dispatch rejected: Confirmed procedure requires Department Head workflow approval before dispatch. This letter version has not been approved.');
    }

    public function test_dispatch_bypass_rejected_when_document_version_changed_after_prior_approval(): void
    {
        $wfDef = WorkflowDefinition::first();
        $workflowVer = WorkflowVersion::create([
            'definition_id' => $wfDef->id,
            'version_number' => 1,
            'created_by' => $this->admin->id,
            'published_at' => now(),
        ]);

        $outType = CorrespondenceType::where('code', 'outgoing')->first()
            ?? CorrespondenceType::create(['code' => 'outgoing', 'name' => 'Outgoing', 'active' => true]);

        OfficeProcedureVersion::create([
            'unit_id' => $this->csDept->id,
            'correspondence_type_id' => $outType->id,
            'purpose_code' => 'official_directive',
            'version_number' => 1,
            'receipt_mode' => 'direct',
            'issue_mode' => 'workflow',
            'workflow_version_id' => $workflowVer->id,
            'confirmed_at' => now(),
            'confirmed_by' => $this->admin->id,
            'confirmation_source' => 'executive_order',
            'effective_from' => now(),
        ]);

        $doc = Document::create([
            'title' => 'Official Letter with Subsequent Edits',
            'category_id' => DocumentCategory::first()->id,
            'confidentiality_id' => \App\Models\ConfidentialityLevel::first()->id,
            'owner_unit_id' => $this->csDept->id,
            'created_by' => $this->csOfficer->id,
            'reference_number' => 'WU/CS/OUT/2026/002',
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        // Version 1 created & approved
        $ver1 = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $this->csOfficer->id,
            'change_note' => 'Original signed draft',
        ]);

        $corr = Correspondence::create([
            'document_id' => $doc->id,
            'type_id' => $outType->id,
            'priority_id' => Priority::first()->id,
            'purpose_code' => 'official_directive',
            'correspondence_date' => '2026-10-30',
            'registered_by' => $this->csOfficer->id,
            'registered_at' => now(),
        ]);

        $state = WorkflowState::firstOrCreate(
            ['workflow_version_id' => $workflowVer->id, 'code' => 'review'],
            ['name' => 'Under Review', 'is_initial' => true, 'is_terminal' => false]
        );

        $instance = WorkflowInstance::create([
            'workflow_version_id' => $workflowVer->id,
            'document_version_id' => $ver1->id,
            'started_by' => $this->csOfficer->id,
            'started_at' => now(),
        ]);

        $task = WorkflowTask::create([
            'instance_id' => $instance->id,
            'state_id' => $state->id,
            'assigned_user_id' => $this->admin->id,
            'created_at' => now(),
            'closed_at' => now(),
        ]);

        WorkflowTaskDecision::create([
            'task_id' => $task->id,
            'decided_by' => $this->admin->id,
            'decision' => 'approve',
            'decided_at' => now(),
            'comment' => 'Approved version 1',
        ]);

        // An unauthorized / altered Version 2 is now created
        $ver2 = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 2,
            'created_by' => $this->csOfficer->id,
            'change_note' => 'Modified clauses after initial approval',
        ]);

        $this->authenticateAs($this->csOfficer);

        // Attempt to dispatch altered Version 2 using prior approval from Version 1
        $dispatchResponse = $this->postJson("/api/v1/correspondences/{$corr->id}/dispatch", [
            'document_version_id' => $ver2->id,
            'method_id' => DispatchMethod::first()->id,
            'tracking_reference' => 'ALTERED-VER2-DISPATCH',
        ]);

        $dispatchResponse->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonFragment([
                'message' => "Approval mismatch: Material modifications detected. Version {$ver2->id} has not been approved. Prior approval on an earlier version cannot be reused for modified content; renewed Department Head approval is required.",
            ]);
    }

    public function test_physical_original_shredding_defense_without_senate_disposal_order(): void
    {
        $doc = Document::create([
            'title' => 'Original Official Contract Document',
            'category_id' => DocumentCategory::first()->id,
            'confidentiality_id' => \App\Models\ConfidentialityLevel::first()->id,
            'owner_unit_id' => $this->csDept->id,
            'created_by' => $this->csOfficer->id,
            'reference_number' => 'WU/HR/PHYS/2026/001',
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $original = PhysicalOriginal::create([
            'document_id' => $doc->id,
            'physical_reference' => 'WU-PHYS-REF-2026-001',
            'retention_requirement' => 'required',
            'created_by' => $this->csOfficer->id,
            'created_at' => now(),
        ]);

        // User with disposal role attempts disposal without Senate order
        $disposalOfficer = User::create([
            'name' => 'Archive Officer',
            'email' => 'archive.officer@wu.edu.et',
            'password' => bcrypt('SecurePassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($disposalOfficer, 'archive.approve_disposal', 'global');

        $this->authenticateAs($disposalOfficer);

        $response = $this->postJson("/api/v1/staff/records/{$original->id}/dispose-physical", [
            'authorization_reference' => 'REGULAR-ROUTINE-CLEANUP',
            'reason' => 'Routine digitization complete, clearing physical paper.',
            // Missing executive_senate_disposal_order
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonFragment([
                'message' => 'Physical disposal prohibited: Physical original retention is designated as REQUIRED. Uploading or verifying a digital scan does NOT authorize destruction of the physical original without an explicit executive Senate and Legal Counsel disposal order.',
            ]);

        // Verify custody event was not created
        $this->assertDatabaseMissing('physical_custody_events', [
            'physical_original_id' => $original->id,
            'action' => 'destroy',
        ]);
    }

    public function test_digital_scan_verification_preserves_physical_custody(): void
    {
        $doc = Document::create([
            'title' => 'Faculty Employment Dossier Original',
            'category_id' => DocumentCategory::first()->id,
            'confidentiality_id' => \App\Models\ConfidentialityLevel::first()->id,
            'owner_unit_id' => $this->csDept->id,
            'created_by' => $this->csOfficer->id,
            'reference_number' => 'WU/HR/PHYS/2026/002',
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $original = PhysicalOriginal::create([
            'document_id' => $doc->id,
            'physical_reference' => 'WU-PHYS-REF-2026-001',
            'retention_requirement' => 'required',
            'created_by' => $this->csOfficer->id,
            'created_at' => now(),
        ]);

        $ver = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $this->csOfficer->id,
            'change_note' => 'High-resolution scan upload',
        ]);

        $digitization = \App\Models\DigitizationRecord::create([
            'physical_original_id' => $original->id,
            'document_version_id' => $ver->id,
            'digitized_by' => $this->csOfficer->id,
            'digitized_at' => now(),
            'verification_status' => 'pending',
        ]);

        $this->grantPermissionToUser($this->csOfficer, 'staff.update', 'global');
        $this->authenticateAs($this->csOfficer);

        $response = $this->postJson("/api/v1/staff/records/{$original->id}/verify-scan", [
            'verification_status' => 'accepted',
            'verification_note' => 'Scan verified complete against 4-page paper dossier.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'notice' => 'Physical original custody remains active and legally distinct.',
            ]);

        $this->assertEquals('accepted', $digitization->fresh()->verification_status);
        $this->assertEquals('required', $original->fresh()->retention_requirement);
    }

    public function test_session_invalidation_and_token_revocation_on_logout(): void
    {
        $token = $this->authenticateAs($this->csOfficer);

        // Authenticated request works
        $this->getJson('/api/v1/auth/me')->assertStatus(200);

        // Logout
        $logoutResponse = $this->postJson('/api/v1/auth/logout');
        $logoutResponse->assertStatus(200);

        // Forget cached auth guards
        $this->app['auth']->forgetGuards();

        // Replay attempt with logged out bearer token
        $replayResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me');

        $replayResponse->assertStatus(401);
    }

    public function test_deactivated_account_immediate_lockout(): void
    {
        $token = $this->authenticateAs($this->csOfficer);

        $this->getJson('/api/v1/auth/me')->assertStatus(200);

        // Security administrator deactivates account immediately to 'locked'
        $this->csOfficer->update(['status' => 'locked']);
        $this->app['auth']->forgetGuards();

        // Active account middleware blocks further requests
        $lockedResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me');

        $lockedResponse->assertStatus(403)
            ->assertJsonFragment([
                'success' => false,
            ]);
    }

    public function test_idor_cross_unit_document_update_via_header_spoofing_denied(): void
    {
        $this->grantPermissionToUser($this->csOfficer, 'documents.update', 'unit', $this->csDept->id);

        $eeDoc = Document::create([
            'title' => 'Electrical Grid Distribution Architecture',
            'category_id' => DocumentCategory::first()->id,
            'confidentiality_id' => \App\Models\ConfidentialityLevel::first()->id,
            'owner_unit_id' => $this->eeDept->id,
            'created_by' => $this->eeOfficer->id,
            'reference_number' => 'WU/EE/DOC/2026/010',
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $this->authenticateAs($this->csOfficer);

        // CS Officer attempts to update EE Document using header spoofing
        $response = $this->withHeader('X-Organization-Unit-Id', (string) $this->csDept->id)
            ->putJson("/api/v1/documents/{$eeDoc->id}", [
                'title' => 'Tampered Title Injected By CS Officer',
                'lock_version' => 0,
            ]);

        $response->assertStatus(403);
        $this->assertEquals('Electrical Grid Distribution Architecture', $eeDoc->fresh()->title);

        // Now authorized EE Officer updates the document
        $this->grantPermissionToUser($this->eeOfficer, 'documents.update', 'unit', $this->eeDept->id);
        $this->authenticateAs($this->eeOfficer);

        $authResponse = $this->putJson("/api/v1/documents/{$eeDoc->id}", [
            'title' => 'Authorized EE Update Title',
            'lock_version' => 0,
        ]);

        $authResponse->assertStatus(200);
        $this->assertEquals('Authorized EE Update Title', $eeDoc->fresh()->title);
    }

    public function test_cross_unit_collection_isolation_on_document_index(): void
    {
        $this->grantPermissionToUser($this->csOfficer, 'documents.view', 'unit', $this->csDept->id);

        $csDoc = Document::create([
            'title' => 'CS Department Curriculum Guide 2026',
            'category_id' => DocumentCategory::first()->id,
            'confidentiality_id' => \App\Models\ConfidentialityLevel::first()->id,
            'owner_unit_id' => $this->csDept->id,
            'created_by' => $this->csOfficer->id,
            'reference_number' => 'WU/CS/DOC/2026/020',
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $eeDoc = Document::create([
            'title' => 'Confidential EE Radar System Specs',
            'category_id' => DocumentCategory::first()->id,
            'confidentiality_id' => \App\Models\ConfidentialityLevel::first()->id,
            'owner_unit_id' => $this->eeDept->id,
            'created_by' => $this->eeOfficer->id,
            'reference_number' => 'WU/EE/DOC/2026/021',
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $this->authenticateAs($this->csOfficer);

        // General index should only return CS documents for CS Officer
        $response = $this->getJson('/api/v1/documents');
        $response->assertStatus(200);

        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertContains('CS Department Curriculum Guide 2026', $titles);
        $this->assertNotContains('Confidential EE Radar System Specs', $titles);

        // Explicit filter for EE Department without permission must be blocked
        $blockedResponse = $this->getJson("/api/v1/documents?owner_unit_id={$this->eeDept->id}");
        $blockedResponse->assertStatus(403);
    }

    public function test_parameter_tampering_header_payload_mismatch_on_creation_denied(): void
    {
        $this->grantPermissionToUser($this->csOfficer, 'correspondence.register', 'unit', $this->csDept->id);
        $this->authenticateAs($this->csOfficer);

        // Header claims CS Dept (where user is authorized), but JSON body targets EE Dept
        $response = $this->withHeader('X-Organization-Unit-Id', (string) $this->csDept->id)
            ->postJson('/api/v1/correspondences', [
                'title' => 'Spoofed Cross-Unit Inbound Letter',
                'category_id' => DocumentCategory::first()->id,
                'confidentiality_id' => \App\Models\ConfidentialityLevel::first()->id,
                'owner_unit_id' => $this->eeDept->id,
                'type_id' => CorrespondenceType::first()->id,
                'priority_id' => Priority::first()->id,
                'purpose_code' => 'official_directive',
                'correspondence_date' => '2026-10-30',
            ]);

        $response->assertStatus(403);
    }

    public function test_unauthorized_file_download_idor_defense(): void
    {
        Storage::fake('local');
        $filePath = 'documents/2026/confidential_ee_report.pdf';
        Storage::disk('local')->put($filePath, 'CONFIDENTIAL EE RESEARCH DATA');

        $confidentialLevel = \App\Models\ConfidentialityLevel::where('code', 'confidential')->first()
            ?? \App\Models\ConfidentialityLevel::first();

        $eeDoc = Document::create([
            'title' => 'EE Confidential Departmental Strategy',
            'category_id' => DocumentCategory::first()->id,
            'confidentiality_id' => $confidentialLevel->id,
            'owner_unit_id' => $this->eeDept->id,
            'created_by' => $this->eeOfficer->id,
            'reference_number' => 'WU/EE/FILE/2026/001',
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $ver = DocumentVersion::create([
            'document_id' => $eeDoc->id,
            'version_number' => 1,
            'created_by' => $this->eeOfficer->id,
            'change_note' => 'Initial upload',
        ]);

        $fileObject = FileObject::create([
            'storage_disk' => 'local',
            'storage_key' => $filePath,
            'original_filename' => 'confidential_ee_report.pdf',
            'detected_mime' => 'application/pdf',
            'byte_size' => 28,
            'sha256' => hash('sha256', 'CONFIDENTIAL EE RESEARCH DATA'),
            'uploaded_by' => $this->eeOfficer->id,
            'uploaded_at' => now(),
            'scan_status' => 'clean',
            'scanned_at' => now(),
            'storage_status' => 'ready',
        ]);

        VersionFile::create([
            'document_version_id' => $ver->id,
            'file_object_id' => $fileObject->id,
            'purpose' => 'primary',
            'display_order' => 1,
        ]);

        // CS Officer holds download permission only in CS Dept
        $this->grantPermissionToUser($this->csOfficer, 'documents.download', 'unit', $this->csDept->id);
        $this->authenticateAs($this->csOfficer);

        $deniedResponse = $this->getJson("/api/v1/files/{$fileObject->id}/download");
        $deniedResponse->assertStatus(403);

        // EE Officer holds download permission in EE Dept
        $this->grantPermissionToUser($this->eeOfficer, 'documents.download', 'unit', $this->eeDept->id);
        $this->authenticateAs($this->eeOfficer);

        $allowedResponse = $this->getJson("/api/v1/files/{$fileObject->id}/download");
        $allowedResponse->assertStatus(200);
        $this->assertEquals('application/pdf', $allowedResponse->headers->get('Content-Type'));
    }

    public function test_session_replay_denied_after_password_change_or_authz_bump(): void
    {
        $token = $this->authenticateAs($this->csOfficer);

        $this->getJson('/api/v1/auth/me')->assertStatus(200);

        // Increment authz_version representing password change or forced logout
        $this->csOfficer->increment('authz_version');
        $this->app['auth']->forgetGuards();

        $replayResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me');

        $replayResponse->assertStatus(401);
    }

    public function test_workflow_decision_bypass_denied_for_unassigned_user(): void
    {
        $wfDef = WorkflowDefinition::first();
        $workflowVer = WorkflowVersion::create([
            'definition_id' => $wfDef->id,
            'version_number' => 1,
            'created_by' => $this->admin->id,
            'published_at' => now(),
        ]);

        $doc = Document::create([
            'title' => 'Workflow Review Target Document',
            'category_id' => DocumentCategory::first()->id,
            'confidentiality_id' => \App\Models\ConfidentialityLevel::first()->id,
            'owner_unit_id' => $this->eeDept->id,
            'created_by' => $this->eeOfficer->id,
            'reference_number' => 'WU/EE/WF/2026/001',
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $ver = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $this->eeOfficer->id,
            'change_note' => 'Draft',
        ]);

        $state = WorkflowState::firstOrCreate(
            ['workflow_version_id' => $workflowVer->id, 'code' => 'review'],
            ['name' => 'Review', 'is_initial' => true, 'is_terminal' => false]
        );

        $instance = WorkflowInstance::create([
            'workflow_version_id' => $workflowVer->id,
            'document_version_id' => $ver->id,
            'started_by' => $this->eeOfficer->id,
            'started_at' => now(),
        ]);

        $task = WorkflowTask::create([
            'instance_id' => $instance->id,
            'state_id' => $state->id,
            'assigned_user_id' => $this->eeOfficer->id,
            'assigned_unit_id' => $this->eeDept->id,
            'created_at' => now(),
        ]);

        // CS Officer (with workflow.review only in CS Dept) attempts to decide task in EE Dept
        $this->grantPermissionToUser($this->csOfficer, 'workflow.review', 'unit', $this->csDept->id);
        $this->authenticateAs($this->csOfficer);

        $response = $this->postJson("/api/v1/workflows/tasks/{$task->id}/decide", [
            'decision' => 'approve',
            'comment' => 'Unauthorized approval attempt',
        ]);

        $response->assertStatus(403);
    }

    public function test_super_admin_bypass_cannot_be_obtained_unauthorized(): void
    {
        $superRole = \App\Models\Role::where('code', 'super_admin')->first();
        $this->assertNotNull($superRole);

        // CS Officer holds roles.assign but LACKS system.configure (is not Super Admin)
        $this->grantPermissionToUser($this->csOfficer, 'roles.assign', 'global');
        $this->authenticateAs($this->csOfficer);

        // Attempt privilege escalation: grant super_admin role to self
        $escalationResponse = $this->postJson('/api/v1/user-roles', [
            'user_id' => $this->csOfficer->id,
            'role_id' => $superRole->id,
            'scope_kind' => 'global',
        ]);

        $escalationResponse->assertStatus(403)
            ->assertJsonFragment([
                'message' => 'Forbidden: Only an existing Super Administrator can assign the super_admin role.',
            ]);

        // Verify role was not granted in database
        $this->assertDatabaseMissing('user_roles', [
            'user_id' => $this->csOfficer->id,
            'role_id' => $superRole->id,
        ]);

        // Attempt mass-assignment tampering on user update
        $tamperResponse = $this->putJson("/api/v1/users/{$this->csOfficer->id}", [
            'name' => 'CS Officer Tampered',
            'is_super_admin' => true,
            'role' => 'super_admin',
            'permissions' => ['system.configure'],
        ]);

        // The user controller does not accept role/permission mass assignment
        $this->assertFalse($this->csOfficer->fresh()->hasPermission('system.configure'));

        // Legitimate Super Admin (Admin has system.configure) assigns role successfully
        $this->grantPermissionToUser($this->admin, 'system.configure', 'global');
        $this->grantPermissionToUser($this->admin, 'roles.assign', 'global');
        $this->authenticateAs($this->admin);

        $legitResponse = $this->postJson('/api/v1/user-roles', [
            'user_id' => $this->csOfficer->id,
            'role_id' => $superRole->id,
            'scope_kind' => 'global',
        ]);

        $legitResponse->assertStatus(200);
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $this->csOfficer->id,
            'role_id' => $superRole->id,
        ]);
    }

    public function test_cross_unit_isolation_across_staff_records_and_workflow_tasks(): void
    {
        // 1. Create EE Staff Record (Physical Original)
        $eeDoc = Document::create([
            'title' => 'EE Faculty Physical Dossier',
            'category_id' => DocumentCategory::first()->id,
            'confidentiality_id' => \App\Models\ConfidentialityLevel::first()->id,
            'owner_unit_id' => $this->eeDept->id,
            'created_by' => $this->eeOfficer->id,
            'reference_number' => 'WU/EE/PHYS/2026/099',
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $eeOriginal = PhysicalOriginal::create([
            'document_id' => $eeDoc->id,
            'physical_reference' => 'WU-PHYS-EE-099',
            'retention_requirement' => 'required',
            'created_by' => $this->eeOfficer->id,
            'created_at' => now(),
        ]);

        // 2. Create EE Workflow Task
        $wfDef = WorkflowDefinition::first();
        $workflowVer = WorkflowVersion::create([
            'definition_id' => $wfDef->id,
            'version_number' => 2,
            'created_by' => $this->admin->id,
            'published_at' => now(),
        ]);
        $ver = DocumentVersion::create([
            'document_id' => $eeDoc->id,
            'version_number' => 1,
            'created_by' => $this->eeOfficer->id,
            'change_note' => 'Original',
        ]);
        $state = WorkflowState::firstOrCreate(
            ['workflow_version_id' => $workflowVer->id, 'code' => 'review_2'],
            ['name' => 'Review 2', 'is_initial' => true, 'is_terminal' => false]
        );
        $instance = WorkflowInstance::create([
            'workflow_version_id' => $workflowVer->id,
            'document_version_id' => $ver->id,
            'started_by' => $this->eeOfficer->id,
            'started_at' => now(),
        ]);
        $task = WorkflowTask::create([
            'instance_id' => $instance->id,
            'state_id' => $state->id,
            'assigned_user_id' => $this->eeOfficer->id,
            'assigned_unit_id' => $this->eeDept->id,
            'created_at' => now(),
        ]);

        // CS Officer queries staff records and workflow tasks
        $this->grantPermissionToUser($this->csOfficer, 'staff.view', 'unit', $this->csDept->id);
        $this->grantPermissionToUser($this->csOfficer, 'workflow.view', 'unit', $this->csDept->id);
        $this->authenticateAs($this->csOfficer);

        // Staff records index must NOT leak EE records to CS officer
        $staffResponse = $this->getJson('/api/v1/staff/records');
        $staffResponse->assertStatus(200);
        $staffIds = collect($staffResponse->json('data'))->pluck('id')->all();
        $this->assertNotContains($eeOriginal->id, $staffIds);

        // Workflow tasks index must NOT leak EE tasks to CS officer
        $taskResponse = $this->getJson('/api/v1/workflows/tasks');
        $taskResponse->assertStatus(200);
        $taskIds = collect($taskResponse->json('data.data'))->pluck('id')->all();
        $this->assertNotContains($task->id, $taskIds);

        // EE Officer queries $\rightarrow$ records are visible
        $this->grantPermissionToUser($this->eeOfficer, 'staff.view', 'unit', $this->eeDept->id);
        $this->grantPermissionToUser($this->eeOfficer, 'workflow.view', 'unit', $this->eeDept->id);
        $this->authenticateAs($this->eeOfficer);

        $eeStaffResp = $this->getJson('/api/v1/staff/records');
        $eeStaffResp->assertStatus(200);
        $this->assertContains($eeOriginal->id, collect($eeStaffResp->json('data'))->pluck('id')->all());

        $eeTaskResp = $this->getJson('/api/v1/workflows/tasks');
        $eeTaskResp->assertStatus(200);
        $this->assertContains($task->id, collect($eeTaskResp->json('data.data'))->pluck('id')->all());
    }

    public function test_empty_permitted_unit_scope_strictly_fails_closed(): void
    {
        $unassignedUser = User::create([
            'name' => 'Zero Grant Staff',
            'email' => 'zero.grant@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        $this->authenticateAs($unassignedUser);

        // Endpoints strictly fail closed (403 Forbidden)
        $this->getJson('/api/v1/documents')->assertStatus(403);
        $this->getJson('/api/v1/correspondences')->assertStatus(403);
        $this->getJson('/api/v1/research/outputs')->assertStatus(403);
        $this->getJson('/api/v1/workflows/tasks')->assertStatus(403);
        $this->getJson('/api/v1/audit-events')->assertStatus(403);
        $this->postJson('/api/v1/documents', ['title' => 'Test'])->assertStatus(403);
    }

    public function test_permission_revocation_and_role_change_immediate_lockout(): void
    {
        $testUser = User::create([
            'name' => 'Dynamic Revocation User',
            'email' => 'dynamic.revocation@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        $this->grantPermissionToUser($testUser, 'documents.view', 'unit', $this->csDept->id);
        $token = $this->authenticateAs($testUser);

        // 1. Initial request with granted permission succeeds
        $this->getJson('/api/v1/documents')->assertStatus(200);

        // 2. Revoke permission from database directly
        DB::table('user_permissions')->where('user_id', $testUser->id)->delete();
        $this->app['auth']->forgetGuards();

        // 3. Immediate next request with same token fails closed with 403 Forbidden
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/documents')
            ->assertStatus(403);

        // 4. Update status to inactive
        $testUser->update(['status' => 'inactive']);
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(403);
    }
}
