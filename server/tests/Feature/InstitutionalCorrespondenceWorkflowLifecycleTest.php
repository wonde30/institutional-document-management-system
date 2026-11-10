<?php

namespace Tests\Feature;

use App\Models\Correspondence;
use App\Models\CorrespondenceType;
use App\Models\DispatchMethod;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentMovement;
use App\Models\DocumentRelation;
use App\Models\DocumentRelationType;
use App\Models\DocumentVersion;
use App\Models\MovementReceipt;
use App\Models\OfficeProcedureVersion;
use App\Models\OrganizationUnit;
use App\Models\Priority;
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

class InstitutionalCorrespondenceWorkflowLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $registryOfficer;
    protected User $medOfficer;
    protected User $medDean;
    protected User $unrelatedOfficer;
    protected OrganizationUnit $registryUnit;
    protected OrganizationUnit $medCollege;
    protected OrganizationUnit $eeDept;
    protected WorkflowVersion $workflowVersion;
    protected WorkflowState $reviewState;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $this->admin = User::where('email', 'admin@wu.edu.et')->first();
        $rootUnit = OrganizationUnit::first();

        $deptType = UnitType::where('code', 'department')->first()
            ?? UnitType::create(['code' => 'department', 'name' => 'Department', 'active' => 1]);
        $collegeType = UnitType::where('code', 'college')->first()
            ?? UnitType::create(['code' => 'college', 'name' => 'College', 'active' => 1]);

        // 1. Central Registry Unit
        $this->registryUnit = OrganizationUnit::create([
            'parent_id' => $rootUnit->id,
            'unit_type_id' => $deptType->id,
            'code' => 'CENTRAL_REGISTRY',
            'name' => 'Institutional Central Registry',
            'active' => true,
        ]);

        // 2. Medical College
        $this->medCollege = OrganizationUnit::create([
            'parent_id' => $rootUnit->id,
            'unit_type_id' => $collegeType->id,
            'code' => 'COLLEGE_MED_HEALTH',
            'name' => 'College of Medicine and Health Sciences',
            'active' => true,
        ]);

        // 3. Unrelated Department (EE)
        $this->eeDept = OrganizationUnit::create([
            'parent_id' => $rootUnit->id,
            'unit_type_id' => $deptType->id,
            'code' => 'EE_DEPT',
            'name' => 'Department of Electrical Engineering',
            'active' => true,
        ]);

        // User 1: Central Registry Officer
        $this->registryOfficer = User::create([
            'name' => 'Registry Officer Abebe',
            'email' => 'registry.abebe@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->registryOfficer, 'records_officer', 'unit', $this->registryUnit->id);
        $this->grantPermissionToUser($this->registryOfficer, 'correspondence.register', 'unit', $this->registryUnit->id);
        $this->grantPermissionToUser($this->registryOfficer, 'correspondence.forward', 'unit', $this->registryUnit->id);
        $this->grantPermissionToUser($this->registryOfficer, 'correspondence.view', 'unit', $this->registryUnit->id);

        // User 2: Medical College Records Officer
        $this->medOfficer = User::create([
            'name' => 'Medical Records Officer Tigist',
            'email' => 'med.tigist@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->medOfficer, 'records_officer', 'unit', $this->medCollege->id);
        $this->grantPermissionToUser($this->medOfficer, 'correspondence.acknowledge', 'unit', $this->medCollege->id);
        $this->grantPermissionToUser($this->medOfficer, 'correspondence.assign', 'unit', $this->medCollege->id);
        $this->grantPermissionToUser($this->medOfficer, 'correspondence.register', 'unit', $this->medCollege->id);
        $this->grantPermissionToUser($this->medOfficer, 'correspondence.dispatch', 'unit', $this->medCollege->id);
        $this->grantPermissionToUser($this->medOfficer, 'correspondence.view', 'unit', $this->medCollege->id);

        // User 3: Medical College Dean
        $this->medDean = User::create([
            'name' => 'Prof. Medical Dean Worku',
            'email' => 'med.worku@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->medDean, 'department_head', 'unit', $this->medCollege->id);
        $this->grantPermissionToUser($this->medDean, 'workflow.review', 'unit', $this->medCollege->id);
        $this->grantPermissionToUser($this->medDean, 'workflow.view', 'unit', $this->medCollege->id);
        $this->grantPermissionToUser($this->medDean, 'correspondence.view', 'unit', $this->medCollege->id);

        // User 4: Unrelated EE Officer
        $this->unrelatedOfficer = User::create([
            'name' => 'EE Records Officer Kebede',
            'email' => 'ee.kebede@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->unrelatedOfficer, 'records_officer', 'unit', $this->eeDept->id);
        $this->grantPermissionToUser($this->unrelatedOfficer, 'correspondence.view', 'unit', $this->eeDept->id);
        $this->grantPermissionToUser($this->unrelatedOfficer, 'correspondence.acknowledge', 'unit', $this->eeDept->id);

        // Setup Outgoing Workflow & Office Procedure for Medical College
        $wfDef = WorkflowDefinition::firstOrCreate(
            ['code' => 'OUTGOING_LETTER_PROC'],
            ['name' => 'Outgoing Letter Approval Protocol', 'active' => true]
        );

        $this->workflowVersion = WorkflowVersion::create([
            'definition_id' => $wfDef->id,
            'version_number' => 1,
            'created_by' => $this->admin->id,
            'published_at' => now(),
        ]);

        $this->reviewState = WorkflowState::create([
            'workflow_version_id' => $this->workflowVersion->id,
            'code' => 'dean_review',
            'name' => 'Dean Official Review',
            'is_initial' => true,
            'is_terminal' => false,
        ]);

        $outType = CorrespondenceType::where('code', 'outgoing')->first();

        OfficeProcedureVersion::create([
            'unit_id' => $this->medCollege->id,
            'correspondence_type_id' => $outType->id,
            'purpose_code' => 'official_response',
            'version_number' => 1,
            'receipt_mode' => 'direct',
            'issue_mode' => 'workflow',
            'workflow_version_id' => $this->workflowVersion->id,
            'confirmed_at' => now(),
            'confirmed_by' => $this->admin->id,
            'confirmation_source' => 'Senate Executive Protocol #12/2026',
            'effective_from' => now(),
        ]);
    }

    public function test_complete_institutional_correspondence_workflow_lifecycle(): void
    {
        $incomingType = CorrespondenceType::where('code', 'incoming')->first();
        $outgoingType = CorrespondenceType::where('code', 'outgoing')->first();
        $academicCat = DocumentCategory::where('code', 'academic')->first();
        $confLevel = \App\Models\ConfidentialityLevel::first();
        $priorityHigh = Priority::first();
        $dispatchMethod = DispatchMethod::first();

        // =====================================================================
        // STEP 1: Register an Incoming Letter
        // Central Registry logs incoming letter from Ministry of Education
        // =====================================================================
        $this->authenticateAs($this->registryOfficer);

        $incomingResponse = $this->postJson('/api/v1/correspondences', [
            'title' => 'Ministry of Education Directive on Health Sciences Accreditation',
            'summary' => 'National directive mandating curriculum review and clinical training benchmarks.',
            'category_id' => $academicCat->id,
            'confidentiality_id' => $confLevel->id,
            'owner_unit_id' => $this->registryUnit->id,
            'type_id' => $incomingType->id,
            'priority_id' => $priorityHigh->id,
            'purpose_code' => 'directive_notice',
            'external_reference' => 'MOE/HERQA/2026/0992',
            'correspondence_date' => '2026-10-30',
            'first_receiving_unit_id' => $this->registryUnit->id,
        ]);

        $incomingResponse->assertStatus(201);
        $incomingCorrId = $incomingResponse->json('data.id');
        $this->assertNotNull($incomingCorrId);

        $incomingCorr = Correspondence::with('document.versions')->findOrFail($incomingCorrId);
        $this->assertEquals('MOE/HERQA/2026/0992', $incomingCorr->external_reference);

        // =====================================================================
        // STEP 1B: Duplicate Submission Defense
        // Attempting to re-register the same external reference must be rejected!
        // =====================================================================
        $duplicateAttempt = $this->postJson('/api/v1/correspondences', [
            'title' => 'Duplicate Attempt of MOE Directive',
            'category_id' => $academicCat->id,
            'confidentiality_id' => $confLevel->id,
            'owner_unit_id' => $this->registryUnit->id,
            'type_id' => $incomingType->id,
            'priority_id' => $priorityHigh->id,
            'purpose_code' => 'directive_notice',
            'external_reference' => 'MOE/HERQA/2026/0992', // Duplicate!
            'correspondence_date' => '2026-10-30',
        ]);

        $duplicateAttempt->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonFragment([
                'message' => "Duplicate correspondence detected: external reference 'MOE/HERQA/2026/0992' is already registered as Correspondence #{$incomingCorrId}. Maintain one correspondence identity instead of creating disconnected records.",
            ]);

        // =====================================================================
        // STEP 2: Forward / Move Document from Central Registry to Medical College
        // =====================================================================
        $movementResponse = $this->postJson("/api/v1/correspondences/{$incomingCorrId}/movements", [
            'from_unit_id' => $this->registryUnit->id,
            'to_unit_id' => $this->medCollege->id,
            'instruction' => 'Forwarded for Dean review and preparation of institutional compliance response.',
            'due_at' => now()->addDays(5)->format('Y-m-d H:i:s.u'),
        ]);

        $movementResponse->assertStatus(201);
        $movementId = $movementResponse->json('data.id');
        $this->assertNotNull($movementId);

        // =====================================================================
        // STEP 2B: Cross-Office Access Denial
        // Unrelated EE officer attempts to acknowledge movement intended for Medical College!
        // =====================================================================
        $this->authenticateAs($this->unrelatedOfficer);

        $unauthorizedAcknowledge = $this->postJson("/api/v1/correspondences/{$incomingCorrId}/movements/{$movementId}/acknowledge");
        $unauthorizedAcknowledge->assertStatus(403);

        // =====================================================================
        // STEP 3: Acknowledge Movement by Medical College Records Officer
        // =====================================================================
        $this->authenticateAs($this->medOfficer);

        $acknowledgeResponse = $this->postJson("/api/v1/correspondences/{$incomingCorrId}/movements/{$movementId}/acknowledge");
        $acknowledgeResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // Assert movement receipt was recorded in database
        $this->assertDatabaseHas('movement_receipts', [
            'movement_id' => $movementId,
            'acknowledged_by' => $this->medOfficer->id,
        ]);

        // Attempting to acknowledge a second time fails idempotently (422)
        $duplicateAcknowledge = $this->postJson("/api/v1/correspondences/{$incomingCorrId}/movements/{$movementId}/acknowledge");
        $duplicateAcknowledge->assertStatus(422)
            ->assertJsonPath('message', 'Movement has already been acknowledged.');

        // =====================================================================
        // STEP 4: Assign Internally & Review / Approve
        // Med Officer assigns task directive to Dean
        // =====================================================================
        $assignmentResponse = $this->postJson("/api/v1/correspondences/{$incomingCorrId}/assignments", [
            'assigned_user_id' => $this->medDean->id,
            'assigned_unit_id' => $this->medCollege->id,
            'instruction' => 'Please formulate formal response to Ministry regarding medical accreditation benchmarks.',
            'due_at' => now()->addDays(3)->format('Y-m-d H:i:s.u'),
        ]);
        $assignmentResponse->assertStatus(201);

        // =====================================================================
        // STEP 5: Prepare Linked Outgoing Response
        // Medical College prepares linked outgoing response referencing incoming letter
        // =====================================================================
        $linkedResponse = $this->postJson("/api/v1/correspondences/{$incomingCorrId}/linked-response", [
            'title' => 'Wollo University Medical College Compliance Report to MOE',
            'summary' => 'Formal institutional compliance submission addressing Ministry Directive MOE/HERQA/2026/0992.',
            'category_id' => $academicCat->id,
            'confidentiality_id' => $confLevel->id,
            'owner_unit_id' => $this->medCollege->id,
            'priority_id' => $priorityHigh->id,
            'purpose_code' => 'official_response',
            'correspondence_date' => '2026-10-30',
            'external_reference' => 'WU/SOM/MOE-RESP/2026/01',
        ]);

        $linkedResponse->assertStatus(201);
        $outgoingCorrId = $linkedResponse->json('data.id');
        $this->assertNotNull($outgoingCorrId);

        $outgoingCorr = Correspondence::with('document.versions')->findOrFail($outgoingCorrId);

        // Assert 3NF Document Relation was created linking outgoing to incoming
        $relationType = DocumentRelationType::where('code', 'responds_to')->first();
        $this->assertNotNull($relationType, "Canonical relation type 'responds_to' must exist");
        $this->assertDatabaseHas('document_relations', [
            'source_id' => $outgoingCorr->document_id,
            'target_id' => $incomingCorr->document_id,
            'relation_type_id' => $relationType->id,
        ]);

        // =====================================================================
        // STEP 6: Workflow Review & Approval
        // Dean reviews Version 1 of linked response and records approval
        // =====================================================================
        $ver1 = $outgoingCorr->document->versions->first();

        $instance = WorkflowInstance::create([
            'workflow_version_id' => $this->workflowVersion->id,
            'document_version_id' => $ver1->id,
            'started_by' => $this->medOfficer->id,
            'started_at' => now(),
        ]);

        $task = WorkflowTask::create([
            'instance_id' => $instance->id,
            'state_id' => $this->reviewState->id,
            'assigned_user_id' => $this->medDean->id,
            'created_at' => now(),
        ]);

        $this->authenticateAs($this->medDean);

        $approvalDecision = $this->postJson("/api/v1/workflows/tasks/{$task->id}/decide", [
            'decision' => 'approve',
            'reason' => 'Compliance narrative, faculty statistics, and clinical hours verified and approved.',
        ]);
        $approvalDecision->assertStatus(200);

        // =====================================================================
        // STEP 6B: Material Modification & Version-Bound Dispatch Check
        // Med Officer creates modified Version 2 without renewed approval
        // Attempting to dispatch Version 2 fails closed (HTTP 422)!
        // =====================================================================
        $ver2 = DocumentVersion::create([
            'document_id' => $outgoingCorr->document_id,
            'version_number' => 2,
            'created_by' => $this->medOfficer->id,
            'change_note' => 'Adjusted clinical partner hospital quotas in Table 4.',
            'created_at' => now(),
        ]);

        $this->authenticateAs($this->medOfficer);

        $prematureDispatch = $this->postJson("/api/v1/correspondences/{$outgoingCorrId}/dispatch", [
            'document_version_id' => $ver2->id,
            'method_id' => $dispatchMethod->id,
            'tracking_reference' => 'PREMATURE-V2-DISPATCH',
        ]);

        $prematureDispatch->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonFragment([
                'message' => "Approval mismatch: Material modifications detected. Version {$ver2->id} has not been approved. Prior approval on an earlier version cannot be reused for modified content; renewed Department Head approval is required.",
            ]);

        // =====================================================================
        // STEP 7: Dean Reviews & Approves Version 2; Dispatch Officer Transmits
        // =====================================================================
        $instanceV2 = WorkflowInstance::create([
            'workflow_version_id' => $this->workflowVersion->id,
            'document_version_id' => $ver2->id,
            'started_by' => $this->medOfficer->id,
            'started_at' => now(),
        ]);

        $taskV2 = WorkflowTask::create([
            'instance_id' => $instanceV2->id,
            'state_id' => $this->reviewState->id,
            'assigned_user_id' => $this->medDean->id,
            'created_at' => now(),
        ]);

        $this->authenticateAs($this->medDean);
        $approveV2 = $this->postJson("/api/v1/workflows/tasks/{$taskV2->id}/decide", [
            'decision' => 'approve',
            'reason' => 'Revised hospital quotas approved.',
        ]);
        $approveV2->assertStatus(200);

        // Med Officer now dispatches approved Version 2
        $this->authenticateAs($this->medOfficer);

        $finalDispatch = $this->postJson("/api/v1/correspondences/{$outgoingCorrId}/dispatch", [
            'document_version_id' => $ver2->id,
            'method_id' => $dispatchMethod->id,
            'tracking_reference' => 'WU-POST-MOE-2026-4411',
        ]);

        $finalDispatch->assertStatus(201)
            ->assertJsonPath('success', true);

        // =====================================================================
        // STEP 8: Retain History & Audit Trail Integrity
        // Complete movement, assignment, relation, and dispatch history is preserved
        // =====================================================================
        $historyResponse = $this->getJson("/api/v1/correspondences/{$incomingCorrId}");
        $historyResponse->assertStatus(200);

        // Verify movement history exists on incoming correspondence
        $this->assertDatabaseHas('document_movements', [
            'document_id' => $incomingCorr->document_id,
            'from_unit_id' => $this->registryUnit->id,
            'to_unit_id' => $this->medCollege->id,
        ]);

        // Verify movement receipt exists
        $this->assertDatabaseHas('movement_receipts', [
            'movement_id' => $movementId,
            'acknowledged_by' => $this->medOfficer->id,
        ]);

        // Verify assignment exists
        $this->assertDatabaseHas('document_assignments', [
            'document_id' => $incomingCorr->document_id,
            'assigned_user_id' => $this->medDean->id,
        ]);

        // Verify dispatch record exists on outgoing correspondence
        $this->assertDatabaseHas('correspondence_dispatches', [
            'document_version_id' => $ver2->id,
            'tracking_reference' => 'WU-POST-MOE-2026-4411',
            'dispatched_by' => $this->medOfficer->id,
        ]);

        // Verify complete immutable audit events
        $this->assertDatabaseHas('audit_events', [
            'action' => 'correspondence.movement_acknowledged',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'correspondence.linked_response_created',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'correspondence.dispatched',
        ]);
    }
}
