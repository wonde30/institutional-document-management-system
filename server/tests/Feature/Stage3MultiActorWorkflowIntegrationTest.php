<?php

namespace Tests\Feature;

use App\Models\AccreditationCycle;
use App\Models\AccreditationEvidence;
use App\Models\AccreditationStandard;
use App\Models\ArchiveCollection;
use App\Models\ArchiveEntry;
use App\Models\AuditEvent;
use App\Models\ConfidentialityLevel;
use App\Models\Correspondence;
use App\Models\CorrespondenceType;
use App\Models\DispatchMethod;
use App\Models\Document;
use App\Models\DocumentAssignment;
use App\Models\DocumentCategory;
use App\Models\DocumentMovement;
use App\Models\DocumentRelation;
use App\Models\DocumentVersion;
use App\Models\FileObject;
use App\Models\LegalHold;
use App\Models\MovementReceipt;
use App\Models\OfficeProcedureVersion;
use App\Models\OrganizationUnit;
use App\Models\PhysicalCustodyEvent;
use App\Models\PhysicalOriginal;
use App\Models\PhysicalStorageLocation;
use App\Models\Priority;
use App\Models\Publication;
use App\Models\ResearchRecord;
use App\Models\RetentionPolicy;
use App\Models\RetentionPolicyVersion;
use App\Models\SignatureRequest;
use App\Models\UnitType;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\VersionFile;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowState;
use App\Models\WorkflowTask;
use App\Models\WorkflowVersion;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class Stage3MultiActorWorkflowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $registryOfficer;
    protected User $deptHead;
    protected User $staffMember;
    protected User $dispatchOfficer;
    protected User $unauthorizedLecturer;
    protected User $archivist;
    protected User $academicOfficer;
    protected OrganizationUnit $informaticsDept;
    protected OrganizationUnit $agricultureDept;
    protected OrganizationUnit $centralRegistry;
    protected OrganizationUnit $academicAffairs;
    protected DocumentCategory $generalCategory;
    protected ConfidentialityLevel $confidentiality;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);
        Storage::fake('local');

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

        $this->centralRegistry = OrganizationUnit::create([
            'parent_id' => $rootUnit->id,
            'unit_type_id' => $deptType->id,
            'code' => 'CENTRAL_REGISTRY',
            'name' => 'Central Institutional Registry',
            'active' => true,
        ]);

        $this->academicAffairs = OrganizationUnit::create([
            'parent_id' => $rootUnit->id,
            'unit_type_id' => $deptType->id,
            'code' => 'ACADEMIC_AFFAIRS',
            'name' => 'Office of Academic Affairs',
            'active' => true,
        ]);

        // Actor 1: Central Registry Officer
        $this->registryOfficer = User::create([
            'name' => 'Registry Officer Tigist',
            'email' => 'registry.tigist@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->registryOfficer, 'records_officer', 'unit', $this->centralRegistry->id);
        $this->grantPermissionToUser($this->registryOfficer, 'correspondence.register', 'unit', $this->centralRegistry->id);
        $this->grantPermissionToUser($this->registryOfficer, 'correspondence.forward', 'unit', $this->centralRegistry->id);
        $this->grantPermissionToUser($this->registryOfficer, 'correspondence.view', 'unit', $this->centralRegistry->id);

        // Actor 2: Department Head (Informatics)
        $this->deptHead = User::create([
            'name' => 'Prof. Department Head Almaz',
            'email' => 'head.almaz@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->deptHead, 'department_head', 'unit', $this->informaticsDept->id);
        $this->grantPermissionToUser($this->deptHead, 'workflow.view', 'unit', $this->informaticsDept->id);
        $this->grantPermissionToUser($this->deptHead, 'workflow.review', 'unit', $this->informaticsDept->id);
        $this->grantPermissionToUser($this->deptHead, 'correspondence.view', 'unit', $this->informaticsDept->id);
        $this->grantPermissionToUser($this->deptHead, 'correspondence.assign', 'unit', $this->informaticsDept->id);
        $this->grantPermissionToUser($this->deptHead, 'correspondence.acknowledge', 'unit', $this->informaticsDept->id);
        $this->grantPermissionToUser($this->deptHead, 'research.publish', 'unit', $this->informaticsDept->id);

        // Actor 3: Staff Member / Lecturer (Informatics)
        $this->staffMember = User::create([
            'name' => 'Dr. Lecturer Bekele',
            'email' => 'lecturer.bekele@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->staffMember, 'staff', 'unit', $this->informaticsDept->id);
        $this->grantPermissionToUser($this->staffMember, 'documents.create', 'unit', $this->informaticsDept->id);
        $this->grantPermissionToUser($this->staffMember, 'documents.view', 'unit', $this->informaticsDept->id);
        $this->grantPermissionToUser($this->staffMember, 'documents.update', 'unit', $this->informaticsDept->id);
        $this->grantPermissionToUser($this->staffMember, 'documents.version', 'unit', $this->informaticsDept->id);
        $this->grantPermissionToUser($this->staffMember, 'correspondence.register', 'unit', $this->informaticsDept->id);
        $this->grantPermissionToUser($this->staffMember, 'correspondence.view', 'unit', $this->informaticsDept->id);
        $this->grantPermissionToUser($this->staffMember, 'research.create', 'unit', $this->informaticsDept->id);
        $this->grantPermissionToUser($this->staffMember, 'research.view', 'unit', $this->informaticsDept->id);
        $this->grantPermissionToUser($this->staffMember, 'accreditation.view', 'unit', $this->informaticsDept->id);
        $this->grantPermissionToUser($this->staffMember, 'accreditation.submit_evidence', 'unit', $this->informaticsDept->id);

        // Actor 4: Dispatch Officer
        $this->dispatchOfficer = User::create([
            'name' => 'Dispatch Officer Chala',
            'email' => 'dispatch.chala@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->dispatchOfficer, 'records_officer', 'unit', $this->informaticsDept->id);
        $this->grantPermissionToUser($this->dispatchOfficer, 'correspondence.dispatch', 'unit', $this->informaticsDept->id);
        $this->grantPermissionToUser($this->dispatchOfficer, 'correspondence.view', 'unit', $this->informaticsDept->id);

        // Actor 5: Unauthorized Lecturer in Agriculture
        $this->unauthorizedLecturer = User::create([
            'name' => 'Dr. External Desta',
            'email' => 'desta.agri@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->unauthorizedLecturer, 'staff', 'unit', $this->agricultureDept->id);
        $this->grantPermissionToUser($this->unauthorizedLecturer, 'documents.view', 'unit', $this->agricultureDept->id);
        $this->grantPermissionToUser($this->unauthorizedLecturer, 'correspondence.view', 'unit', $this->agricultureDept->id);

        // Actor 6: University Archivist (Institution-wide)
        $this->archivist = User::create([
            'name' => 'Archivist Abebe',
            'email' => 'archivist.abebe@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->archivist, 'archivist', 'global');
        $this->grantPermissionToUser($this->archivist, 'archive.view', 'global');
        $this->grantPermissionToUser($this->archivist, 'archive.transfer', 'global');
        $this->grantPermissionToUser($this->archivist, 'archive.manage_retention', 'global');
        $this->grantPermissionToUser($this->archivist, 'archive.approve_disposal', 'global');

        // Actor 7: Academic Affairs Officer / Dean
        $this->academicOfficer = User::create([
            'name' => 'Dean Kebede',
            'email' => 'dean.kebede@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->academicOfficer, 'department_head', 'unit', $this->academicAffairs->id);
        $this->grantPermissionToUser($this->academicOfficer, 'correspondence.view', 'unit', $this->academicAffairs->id);
        $this->grantPermissionToUser($this->academicOfficer, 'correspondence.forward', 'unit', $this->academicAffairs->id);
        $this->grantPermissionToUser($this->academicOfficer, 'correspondence.acknowledge', 'unit', $this->academicAffairs->id);

        $this->generalCategory = DocumentCategory::first();
        $this->confidentiality = ConfidentialityLevel::first();
    }

    /**
     * Scenario 1: Complete Document Lifecycle from Registration to Authorized & Denied Downloads.
     */
    public function test_scenario_1_complete_document_lifecycle(): void
    {
        $this->authenticateAs($this->staffMember);

        // 1. Register document with physical uploaded file
        $file = UploadedFile::fake()->create('curriculum_syllabus.pdf', 500, 'application/pdf');

        $regResponse = $this->post('/api/v1/documents', [
            'title' => 'Advanced Database Systems Curriculum 2026',
            'summary' => 'Comprehensive syllabus for graduate informatics computing curriculum',
            'category_id' => $this->generalCategory->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->informaticsDept->id,
            'document_date' => '2026-10-30',
            'change_note' => 'Initial curriculum baseline draft',
            'file' => $file,
        ]);

        $regResponse->assertStatus(201);
        $docId = $regResponse->json('data.id');
        $refNumber = $regResponse->json('data.reference_number');

        $this->assertNotNull($docId);
        $this->assertStringStartsWith('WU/DOC/', $refNumber);

        // 2. Retrieve document details
        $getResponse = $this->getJson("/api/v1/documents/{$docId}");
        $getResponse->assertStatus(200);
        $this->assertEquals($refNumber, $getResponse->json('data.reference_number'));

        // 3. Update document metadata with optimistic locking
        $updateResponse = $this->putJson("/api/v1/documents/{$docId}", [
            'title' => 'Advanced Database Systems Curriculum (Revised 2026)',
            'lock_version' => 0,
        ]);
        $updateResponse->assertStatus(200);
        $this->assertEquals(1, Document::find($docId)->lock_version);

        // 4. Create Version 2
        $versionFile = UploadedFile::fake()->create('curriculum_syllabus_v2.pdf', 600, 'application/pdf');
        $versionResponse = $this->post("/api/v1/documents/{$docId}/versions", [
            'change_note' => 'Incorporated Senate accreditation committee feedback',
            'file' => $versionFile,
        ]);
        $versionResponse->assertStatus(201);

        $doc = Document::with('versions.versionFiles.fileObject')->find($docId);
        $this->assertCount(2, $doc->versions);
        $fileId = $doc->versions->first()->versionFiles->first()->file_object_id;

        // 5. Authorized download by staff member
        $downloadResponse = $this->get("/api/v1/files/{$fileId}/download");
        $downloadResponse->assertStatus(200);

        // 6. Denied download by unauthorized lecturer in Agriculture department
        $this->authenticateAs($this->unauthorizedLecturer);
        $unauthorizedDownload = $this->get("/api/v1/files/{$fileId}/download");
        $unauthorizedDownload->assertStatus(403);
    }

    /**
     * Scenario 2: Incoming Correspondence Multi-Actor Routing & Acknowledgment.
     */
    public function test_scenario_2_incoming_correspondence_multi_actor_lifecycle(): void
    {
        // Step 1: Central Registry Officer registers incoming ministerial letter
        $this->authenticateAs($this->registryOfficer);
        $incomingType = CorrespondenceType::where('code', 'incoming')->first();
        $extRef = 'MOE-FDRE-WU-' . uniqid();

        $createResponse = $this->postJson('/api/v1/correspondences', [
            'title' => 'National Higher Education Accreditation Directive',
            'summary' => 'Mandatory self-evaluation guidelines for graduate programs',
            'category_id' => $this->generalCategory->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->centralRegistry->id,
            'type_id' => $incomingType->id,
            'priority_id' => Priority::first()->id,
            'purpose_code' => 'directive',
            'external_reference' => $extRef,
            'correspondence_date' => '2026-10-30',
        ]);

        $createResponse->assertStatus(201);
        $corrId = $createResponse->json('data.id');

        // Step 2: Duplicate external reference rejection
        $duplicateResponse = $this->postJson('/api/v1/correspondences', [
            'title' => 'Duplicate Ministerial Submission',
            'category_id' => $this->generalCategory->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->centralRegistry->id,
            'type_id' => $incomingType->id,
            'priority_id' => Priority::first()->id,
            'purpose_code' => 'directive',
            'external_reference' => $extRef, // DUPLICATE
            'correspondence_date' => '2026-10-30',
        ]);
        $duplicateResponse->assertStatus(422);

        // Step 3: Forward movement to Department of Informatics
        $moveResponse = $this->postJson("/api/v1/correspondences/{$corrId}/movements", [
            'from_unit_id' => $this->centralRegistry->id,
            'to_unit_id' => $this->informaticsDept->id,
            'instruction' => 'Forwarded to Informatics for departmental self-evaluation report',
        ]);
        $moveResponse->assertStatus(201);
        $movementId = $moveResponse->json('data.id');

        // Step 4: Unauthorized acknowledgment attempt by Agriculture Department
        $this->authenticateAs($this->unauthorizedLecturer);
        $unauthAck = $this->postJson("/api/v1/correspondences/{$corrId}/movements/{$movementId}/acknowledge");
        $unauthAck->assertStatus(403);

        // Step 5: Legitimate acknowledgment by Department Head of Informatics
        $this->authenticateAs($this->deptHead);
        $authAck = $this->postJson("/api/v1/correspondences/{$corrId}/movements/{$movementId}/acknowledge");
        $authAck->assertStatus(200);

        $this->assertDatabaseHas('movement_receipts', [
            'movement_id' => $movementId,
            'acknowledged_by' => $this->deptHead->id,
        ]);

        // Step 6: Department Head assigns letter to Dr. Bekele
        $assignResponse = $this->postJson("/api/v1/correspondences/{$corrId}/assignments", [
            'assigned_user_id' => $this->staffMember->id,
            'assigned_unit_id' => $this->informaticsDept->id,
            'instruction' => 'Prepare departmental response and evidence dossier',
            'due_at' => now()->addDays(5)->toDateTimeString(),
        ]);
        $assignResponse->assertStatus(201);

        // Step 7: Dr. Bekele accesses assigned letter
        $this->authenticateAs($this->staffMember);
        $staffView = $this->getJson("/api/v1/correspondences/{$corrId}");
        $staffView->assertStatus(200);
        $this->assertCount(1, $staffView->json('data.movements'));
    }

    /**
     * Scenario 3: Linked Outgoing Response, Workflow Review, Modification Detection & Exact Version Dispatch.
     */
    public function test_scenario_3_linked_outgoing_response_approval_and_exact_version_dispatch(): void
    {
        // Pre-create incoming correspondence
        $incType = CorrespondenceType::where('code', 'incoming')->first();
        $outType = CorrespondenceType::where('code', 'outgoing')->first();

        $incDoc = Document::create([
            'title' => 'Incoming Partnership Inquiry from AAU',
            'category_id' => $this->generalCategory->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->informaticsDept->id,
            'created_by' => $this->staffMember->id,
            'reference_number' => 'WU/INC/' . uniqid(),
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $incCorr = Correspondence::create([
            'document_id' => $incDoc->id,
            'type_id' => $incType->id,
            'priority_id' => Priority::first()->id,
            'purpose_code' => 'partnership',
            'external_reference' => 'AAU-INQ-2026-001',
            'correspondence_date' => '2026-10-30',
            'registered_by' => $this->staffMember->id,
            'registered_at' => now(),
        ]);

        // Setup Office Procedure mandating workflow review before dispatch
        $wfDef = WorkflowDefinition::firstOrCreate(
            ['code' => 'OUTGOING_PROC'],
            ['name' => 'Outgoing Approval Protocol', 'active' => true]
        );
        $wfVer = WorkflowVersion::create([
            'definition_id' => $wfDef->id,
            'version_number' => 1,
            'created_by' => $this->admin->id,
            'published_at' => now(),
        ]);
        $state = WorkflowState::create([
            'workflow_version_id' => $wfVer->id,
            'code' => 'head_review',
            'name' => 'Department Head Review',
            'is_initial' => true,
        ]);
        OfficeProcedureVersion::create([
            'unit_id' => $this->informaticsDept->id,
            'correspondence_type_id' => $outType->id,
            'purpose_code' => 'partnership',
            'version_number' => 1,
            'receipt_mode' => 'direct',
            'issue_mode' => 'workflow',
            'workflow_version_id' => $wfVer->id,
            'confirmed_at' => now(),
            'confirmed_by' => $this->admin->id,
            'confirmation_source' => 'executive_charter',
            'effective_from' => now(),
        ]);

        // Step 1: Staff prepares linked outgoing response
        $this->authenticateAs($this->staffMember);
        $responsePrep = $this->postJson("/api/v1/correspondences/{$incCorr->id}/linked-response", [
            'title' => 'Formal Collaboration Agreement Response to AAU',
            'category_id' => $this->generalCategory->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->informaticsDept->id,
            'priority_id' => Priority::first()->id,
            'purpose_code' => 'partnership',
            'correspondence_date' => '2026-10-30',
        ]);

        $responsePrep->assertStatus(201);
        $outCorrId = $responsePrep->json('data.id');
        $outDocId = $responsePrep->json('data.document_id');

        // Verify 3NF relation exists
        $this->assertDatabaseHas('document_relations', [
            'source_id' => $outDocId,
            'target_id' => $incDoc->id,
        ]);

        // Step 2: Submit Version 1 to workflow task
        $ver1 = DocumentVersion::where('document_id', $outDocId)->first();
        $instance = WorkflowInstance::create([
            'workflow_version_id' => $wfVer->id,
            'document_version_id' => $ver1->id,
            'status' => 'active',
            'started_by' => $this->staffMember->id,
            'started_at' => now(),
        ]);
        $task = WorkflowTask::create([
            'instance_id' => $instance->id,
            'state_id' => $state->id,
            'assigned_user_id' => $this->deptHead->id,
            'assigned_unit_id' => $this->informaticsDept->id,
            'due_at' => now()->addDays(2),
        ]);

        // Step 3: Department Head approves Version 1
        $this->authenticateAs($this->deptHead);
        $decideResp = $this->postJson("/api/v1/workflows/tasks/{$task->id}/decide", [
            'decision' => 'approve',
            'reason' => 'Partnership terms reviewed and endorsed.',
        ]);
        $decideResp->assertStatus(200);

        // Step 4: Staff modifies document, creating Version 2
        $this->authenticateAs($this->staffMember);
        $ver2 = DocumentVersion::create([
            'document_id' => $outDocId,
            'version_number' => 2,
            'created_by' => $this->staffMember->id,
            'change_note' => 'Material alteration to intellectual property clause',
            'created_at' => now(),
        ]);

        // Step 5: Dispatch Officer attempts to dispatch Version 2 without renewed approval
        $this->authenticateAs($this->dispatchOfficer);
        $method = DispatchMethod::first();

        $unapprovedDispatch = $this->postJson("/api/v1/correspondences/{$outCorrId}/dispatch", [
            'document_version_id' => $ver2->id,
            'method_id' => $method->id,
            'tracking_reference' => 'WU-POST-TEST-001',
        ]);

        // Must reject with 422: prior approval on Version 1 cannot be reused for Version 2!
        $unapprovedDispatch->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonFragment([
                'message' => "Approval mismatch: Material modifications detected. Version {$ver2->id} has not been approved. Prior approval on an earlier version cannot be reused for modified content; renewed Department Head approval is required.",
            ]);

        // Step 6: Department Head reviews and approves Version 2
        $this->authenticateAs($this->deptHead);
        $instance2 = WorkflowInstance::create([
            'workflow_version_id' => $wfVer->id,
            'document_version_id' => $ver2->id,
            'status' => 'active',
            'started_by' => $this->staffMember->id,
            'started_at' => now(),
        ]);
        $task2 = WorkflowTask::create([
            'instance_id' => $instance2->id,
            'state_id' => $state->id,
            'assigned_user_id' => $this->deptHead->id,
            'assigned_unit_id' => $this->informaticsDept->id,
            'due_at' => now()->addDays(2),
        ]);
        $this->postJson("/api/v1/workflows/tasks/{$task2->id}/decide", [
            'decision' => 'approve',
            'reason' => 'IP revisions vetted and authorized.',
        ]);

        // Step 7: Dispatch Officer now dispatches approved Version 2
        $this->authenticateAs($this->dispatchOfficer);
        $approvedDispatch = $this->postJson("/api/v1/correspondences/{$outCorrId}/dispatch", [
            'document_version_id' => $ver2->id,
            'method_id' => $method->id,
            'tracking_reference' => 'WU-POST-ETH-2026-9988',
        ]);

        $approvedDispatch->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('correspondence_dispatches', [
            'document_version_id' => $ver2->id,
            'tracking_reference' => 'WU-POST-ETH-2026-9988',
        ]);
    }

    /**
     * Scenario 4: Archival Transfer, Appraisal, Accessioning & Retention Execution.
     */
    public function test_scenario_4_archival_transfer_appraisal_and_retention_lifecycle(): void
    {
        // Step 1: Staff creates active historical record
        $this->authenticateAs($this->staffMember);
        $doc = Document::create([
            'title' => 'Informatics Department Foundation Charter 2010',
            'category_id' => $this->generalCategory->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->informaticsDept->id,
            'created_by' => $this->staffMember->id,
            'reference_number' => 'WU/ARCH/TEST/' . uniqid(),
            'document_date' => '2010-09-15',
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $collection = ArchiveCollection::first();
        $policyVersion = RetentionPolicyVersion::first();

        // Step 2: Dr. Bekele (unauthorized for archive transfer) attempts transfer -> 403 Forbidden
        $unauthTransfer = $this->postJson('/api/v1/archive/transfer', [
            'document_id' => $doc->id,
            'collection_id' => $collection->id,
            'retention_policy_version_id' => $policyVersion->id,
        ]);
        $unauthTransfer->assertStatus(403);

        // Step 3: University Archivist initiates archival transfer -> 201 Created
        $this->authenticateAs($this->archivist);
        $authTransfer = $this->postJson('/api/v1/archive/transfer', [
            'document_id' => $doc->id,
            'collection_id' => $collection->id,
            'retention_policy_version_id' => $policyVersion->id,
            'next_review_at' => now()->addYears(5)->format('Y-m-d'),
        ]);

        $authTransfer->assertStatus(201)
            ->assertJsonPath('success', true);

        // Step 4: Verify database state
        $this->assertDatabaseHas('archive_entries', [
            'document_id' => $doc->id,
            'collection_id' => $collection->id,
            'retention_policy_version_id' => $policyVersion->id,
            'archived_by' => $this->archivist->id,
        ]);

        // Document lifecycle must now be 'retired'
        $this->assertEquals('retired', $doc->fresh()->lifecycle);

        // Step 5: Duplicate transfer attempt must fail closed (422 Unprocessable)
        $dupTransfer = $this->postJson('/api/v1/archive/transfer', [
            'document_id' => $doc->id,
            'collection_id' => $collection->id,
            'retention_policy_version_id' => $policyVersion->id,
        ]);
        $dupTransfer->assertStatus(422);

        // Step 6: Archivist lists entries and verifies accessioned document is present
        $listResp = $this->getJson('/api/v1/archive/entries');
        $listResp->assertStatus(200);
        $entries = collect($listResp->json('data.data') ?? $listResp->json('data'));
        $this->assertTrue($entries->contains('document_id', $doc->id));
    }

    /**
     * Scenario 5: Physical Archive Custody & Active Legal Hold Destruction Protection.
     */
    public function test_scenario_5_physical_archive_custody_and_legal_hold_disposal_protection(): void
    {
        $this->authenticateAs($this->staffMember);

        $doc = Document::create([
            'title' => 'Statutory Historical Personnel File',
            'category_id' => $this->generalCategory->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->informaticsDept->id,
            'created_by' => $this->staffMember->id,
            'reference_number' => 'WU/PHYS/DOC/' . uniqid(),
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $vault = PhysicalStorageLocation::firstOrCreate(
            ['code' => 'VAULT-ALPHA'],
            ['name' => 'Preservation Vault Alpha', 'unit_id' => $this->informaticsDept->id, 'active' => true]
        );

        $physical = PhysicalOriginal::create([
            'document_id' => $doc->id,
            'physical_reference' => 'WU-BOX-101-' . uniqid(),
            'retention_requirement' => 'required',
            'created_by' => $this->staffMember->id,
            'created_at' => now(),
        ]);

        // Active legal hold placed by legal counsel
        $hold = LegalHold::firstOrCreate(
            ['code' => 'HOLD-SENATE-2026-001'],
            [
                'reason' => 'Federal Compliance Audit Preservation Mandate',
                'placed_by' => $this->admin->id,
                'placed_at' => now(),
                'released_at' => null,
            ]
        );
        DB::table('legal_hold_documents')->insert([
            'hold_id' => $hold->id,
            'document_id' => $doc->id,
        ]);

        // Attempt physical disposal while under active legal hold (admin holds archive.approve_disposal)
        $this->authenticateAs($this->admin);
        $disposeAttempt = $this->postJson("/api/v1/staff/records/{$physical->id}/dispose-physical", [
            'authorization_reference' => 'DISP-DIR-001',
            'reason' => 'Routine digital substitute cleanup',
        ]);

        // Disposal must be blocked with 422!
        $disposeAttempt->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonFragment([
                'message' => 'Physical disposal prohibited: Document is subject to an active statutory Legal Hold. Destruction or disposition of the physical original is strictly prohibited under federal preservation mandate.',
            ]);
    }

    /**
     * Scenario 6: Finalized Departmental Research Repository Lifecycle.
     */
    public function test_scenario_6_finalized_research_repository_lifecycle(): void
    {
        // Step 1: Authorized Informatics staff registers finalized research
        $this->authenticateAs($this->staffMember);

        $researchResp = $this->postJson('/api/v1/research/outputs', [
            'title' => 'Decentralized Fault-Tolerant File Integrity Verification',
            'office_id' => $this->informaticsDept->id,
            'completion_date' => '2026-10-30',
            'research_type' => 'academic_monograph',
            'notes' => 'Faculty research published under Wollo University repository',
        ]);

        $researchResp->assertStatus(201);
        $researchId = $researchResp->json('data.id');

        // Step 2: Department Head publishes research
        $this->authenticateAs($this->deptHead);
        $publishResp = $this->postJson("/api/v1/research/outputs/{$researchId}/toggle-publication", [
            'action' => 'publish',
        ]);
        $publishResp->assertStatus(200);

        $this->assertDatabaseHas('publications', [
            'document_id' => $researchResp->json('data.document_id'),
        ]);

        // Step 3: University members can view published research
        $this->authenticateAs($this->unauthorizedLecturer);
        $allResearch = $this->getJson('/api/v1/research/outputs');
        $allResearch->assertStatus(200);
        $this->assertContains($researchId, array_column($allResearch->json('data'), 'id'));

        // Step 4: Unauthorized user in Agriculture cannot withdraw Informatics publication
        $unauthWithdraw = $this->postJson("/api/v1/research/outputs/{$researchId}/toggle-publication", [
            'action' => 'withdraw',
        ]);
        $unauthWithdraw->assertStatus(403);
    }

    /**
     * Scenario 7: HERQA Accreditation Evidence Integration.
     */
    public function test_scenario_7_herqa_accreditation_evidence_integration(): void
    {
        $this->authenticateAs($this->staffMember);

        $doc = Document::create([
            'title' => 'Informatics Department Institutional Self-Evaluation Report',
            'category_id' => $this->generalCategory->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->informaticsDept->id,
            'created_by' => $this->staffMember->id,
            'reference_number' => 'WU/HERQA/' . uniqid(),
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $ver = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $this->staffMember->id,
            'created_at' => now(),
        ]);

        $framework = \App\Models\AccreditationFramework::firstOrCreate(
            ['code' => 'HERQA'],
            ['name' => 'Higher Education Relevance and Quality Agency', 'description' => 'Ethiopian National Accreditation Framework', 'active' => 1]
        );

        $frameworkVersion = \App\Models\AccreditationFrameworkVersion::firstOrCreate(
            ['framework_id' => $framework->id, 'version_label' => 'HERQA-2026-V1'],
            ['effective_from' => '2026-01-01']
        );

        $cycle = AccreditationCycle::firstOrCreate(
            [
                'framework_version_id' => $frameworkVersion->id,
                'unit_id' => $this->informaticsDept->id,
                'name' => 'National Higher Education Institutional Audit 2026',
            ],
            [
                'starts_on' => '2026-01-01',
                'ends_on' => '2026-12-31',
            ]
        );

        $standard = AccreditationStandard::firstOrCreate(
            [
                'framework_version_id' => $frameworkVersion->id,
                'code' => 'HERQA-STD-04',
            ],
            [
                'title' => 'Curriculum Design, Learning Infrastructure and Assessment',
                'description' => 'Evaluation of curriculum, labs, and student assessment',
            ]
        );

        // Submit evidence
        $evidenceResp = $this->postJson('/api/v1/accreditation/evidence', [
            'cycle_id' => $cycle->id,
            'standard_id' => $standard->id,
            'document_version_id' => $ver->id,
            'note' => 'Official curriculum dossier and laboratory compliance report',
        ]);

        $evidenceResp->assertStatus(201);
        $this->assertDatabaseHas('accreditation_evidence', [
            'cycle_id' => $cycle->id,
            'standard_id' => $standard->id,
            'document_version_id' => $ver->id,
        ]);
    }

    /**
     * Scenario 8: Inter-Office Movement Chains & Physical Custody Transfer.
     */
    public function test_scenario_8_inter_office_movement_chains_and_custody_transfer(): void
    {
        // Step 1: Central Registry registers incoming policy directive from Ministry
        $this->authenticateAs($this->registryOfficer);
        $doc = Document::create([
            'title' => 'National Higher Education Governance Directive 2026',
            'category_id' => $this->generalCategory->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->centralRegistry->id,
            'created_by' => $this->registryOfficer->id,
            'reference_number' => 'MOE/DIR/2026/' . uniqid(),
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $corr = Correspondence::create([
            'document_id' => $doc->id,
            'type_id' => CorrespondenceType::where('code', 'incoming')->value('id') ?? 1,
            'priority_id' => Priority::where('code', 'urgent')->value('id') ?? 1,
            'purpose_code' => 'directive',
            'external_reference' => 'MOE-GOV-99',
            'correspondence_date' => '2026-10-30',
            'registered_by' => $this->registryOfficer->id,
            'registered_at' => now(),
        ]);

        // Hop 1: Registry Officer forwards directive to Academic Affairs
        $hop1Resp = $this->postJson("/api/v1/correspondences/{$corr->id}/movements", [
            'from_unit_id' => $this->centralRegistry->id,
            'to_unit_id' => $this->academicAffairs->id,
            'purpose' => 'For review of academic curriculum implications',
            'physical_status' => 'original_with_courier',
        ]);
        $hop1Resp->assertStatus(201);
        $hop1MovementId = $hop1Resp->json('data.id');

        // Step 2: Academic Affairs Dean acknowledges receipt of Hop 1
        $this->authenticateAs($this->academicOfficer);
        $ack1Resp = $this->postJson("/api/v1/correspondences/{$corr->id}/movements/{$hop1MovementId}/acknowledge");
        $ack1Resp->assertStatus(200);

        $this->assertDatabaseHas('movement_receipts', [
            'movement_id' => $hop1MovementId,
            'acknowledged_by' => $this->academicOfficer->id,
        ]);

        // Hop 2: Academic Affairs forwards to Informatics Department for department-level action
        $hop2Resp = $this->postJson("/api/v1/correspondences/{$corr->id}/movements", [
            'from_unit_id' => $this->academicAffairs->id,
            'to_unit_id' => $this->informaticsDept->id,
            'purpose' => 'Align IT curriculum with national standard',
            'physical_status' => 'delivered_by_dean',
        ]);
        $hop2Resp->assertStatus(201);
        $hop2MovementId = $hop2Resp->json('data.id');

        // Step 3: Informatics Department Head acknowledges receipt of Hop 2
        $this->authenticateAs($this->deptHead);
        $ack2Resp = $this->postJson("/api/v1/correspondences/{$corr->id}/movements/{$hop2MovementId}/acknowledge");
        $ack2Resp->assertStatus(200);

        $this->assertDatabaseHas('movement_receipts', [
            'movement_id' => $hop2MovementId,
            'acknowledged_by' => $this->deptHead->id,
        ]);

        // Step 4: Verify complete movement trail via correspondence show endpoint
        $showResp = $this->getJson("/api/v1/correspondences/{$corr->id}");
        $showResp->assertStatus(200);
        $movements = collect($showResp->json('data.movements'));
        $this->assertCount(2, $movements);

        $hop1 = $movements->firstWhere('id', $hop1MovementId);
        $hop2 = $movements->firstWhere('id', $hop2MovementId);

        $this->assertNotNull($hop1);
        $this->assertNotNull($hop2);
        $this->assertEquals($this->centralRegistry->id, $hop1['from_unit_id']);
        $this->assertEquals($this->academicAffairs->id, $hop1['to_unit_id']);
        $this->assertEquals($this->academicAffairs->id, $hop2['from_unit_id']);
        $this->assertEquals($this->informaticsDept->id, $hop2['to_unit_id']);
    }

    /**
     * Scenario 9: User Notifications and Strict Boundary Privacy Isolation.
     */
    public function test_scenario_9_notifications_and_audit_privacy_isolation(): void
    {
        $event = \App\Models\NotificationEvent::create([
            'type_code' => 'workflow.task_assigned',
            'actor_id' => $this->admin->id,
            'occurred_at' => now(),
        ]);

        $notifA = UserNotification::create([
            'event_id' => $event->id,
            'user_id' => $this->staffMember->id,
            'created_at' => now(),
            'read_at' => null,
        ]);

        // Dr. Bekele fetches his notifications
        $this->authenticateAs($this->staffMember);
        $notifsResp = $this->getJson('/api/v1/notifications');
        $notifsResp->assertStatus(200);
        $this->assertEquals(1, $notifsResp->json('data.unread_count'));

        // Dr. Desta (unauthorized user) attempts to mark Dr. Bekele's notification as read
        $this->authenticateAs($this->unauthorizedLecturer);
        $unauthMark = $this->postJson("/api/v1/notifications/{$notifA->id}/read");
        // Must fail with 404 (ModelNotFound due to user_id scope)
        $unauthMark->assertStatus(404);

        // Dr. Bekele legitimately marks as read
        $this->authenticateAs($this->staffMember);
        $authMark = $this->postJson("/api/v1/notifications/{$notifA->id}/read");
        $authMark->assertStatus(200);

        $this->assertNotNull($notifA->fresh()->read_at);
    }

    /**
     * Scenario 10: Executive Dashboard Aggregation Verification against Real Database State.
     */
    public function test_scenario_10_dashboard_metrics_real_database_aggregation(): void
    {
        $this->authenticateAs($this->admin);

        $dashResp = $this->getJson('/api/v1/dashboard/metrics');
        $dashResp->assertStatus(200);

        $metrics = $dashResp->json('data.overview');

        // Assert figures match exact database counts
        $this->assertEquals(Document::count(), $metrics['total_documents']);
        $this->assertEquals(Correspondence::count(), $metrics['total_correspondences']);
        $this->assertEquals(User::where('status', 'active')->count(), $metrics['active_users']);
        $this->assertEquals(OrganizationUnit::where('active', 1)->count(), $metrics['organization_units']);
        $this->assertEquals(DocumentMovement::count(), $metrics['total_movements']);
        $this->assertEquals(ArchiveEntry::count(), $metrics['archived_records']);
    }
}
