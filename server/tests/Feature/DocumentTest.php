<?php

namespace Tests\Feature;

use App\Models\AssignmentEvent;
use App\Models\Document;
use App\Models\DocumentAssignment;
use App\Models\DocumentCategory;
use App\Models\DocumentMovement;
use App\Models\DocumentVersion;
use App\Models\FileObject;
use App\Models\OrganizationUnit;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $staffUser;
    protected OrganizationUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        Storage::fake('local');

        $this->unit = OrganizationUnit::first();
        $this->adminUser = User::where('email', 'admin@wu.edu.et')->first();

        $this->staffUser = User::create([
            'name' => 'Regular Staff Member',
            'email' => 'regular.staff@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->staffUser, 'staff');
    }

    public function test_user_can_register_document_with_file_upload_and_atomic_reference(): void
    {
        $this->authenticateAs($this->adminUser);

        $fileContent = 'Wollo University Official Academic Charter Content 2026';
        $file = UploadedFile::fake()->createWithContent('Charter-2026.pdf', $fileContent);
        $expectedHash = hash('sha256', $fileContent);

        $category = DocumentCategory::first();
        $confidentiality = \App\Models\ConfidentialityLevel::first();

        $response = $this->postJson('/api/v1/documents', [
            'title' => 'Undergraduate Medical Curriculum 2026',
            'summary' => 'Comprehensive modular syllabus for clinical medicine.',
            'category_id' => $category->id,
            'confidentiality_id' => $confidentiality->id,
            'owner_unit_id' => $this->unit->id,
            'document_date' => '2026-10-29',
            'file' => $file,
            'change_note' => 'Initial senate submission',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Undergraduate Medical Curriculum 2026')
            ->assertJsonPath('data.lock_version', 0);

        $refNumber = $response->json('data.reference_number');
        $this->assertMatchesRegularExpression('/^WU\/DOC\/\d{4}\/\d{6}$/', $refNumber);

        $docId = $response->json('data.id');
        $this->assertDatabaseHas('documents', [
            'id' => $docId,
            'reference_number' => $refNumber,
        ]);

        $this->assertDatabaseHas('document_versions', [
            'document_id' => $docId,
            'version_number' => 1,
        ]);

        $fileObject = FileObject::where('sha256', $expectedHash)->first();
        $this->assertNotNull($fileObject);
        $this->assertEquals('Charter-2026.pdf', $fileObject->original_filename);
    }

    public function test_user_can_upload_new_version_and_lock_version_increments(): void
    {
        $this->authenticateAs($this->adminUser);

        $file1 = UploadedFile::fake()->create('protocol_v1.pdf', 500, 'application/pdf');
        $category = DocumentCategory::first();
        $confidentiality = \App\Models\ConfidentialityLevel::first();

        $createResponse = $this->postJson('/api/v1/documents', [
            'title' => 'Research Ethics Protocol',
            'summary' => 'Institutional guidelines for research participants',
            'category_id' => $category->id,
            'confidentiality_id' => $confidentiality->id,
            'owner_unit_id' => $this->unit->id,
            'document_date' => '2026-10-29',
            'file' => $file1,
        ]);

        $docId = $createResponse->json('data.id');

        // Upload version 2
        $file2 = UploadedFile::fake()->create('protocol_v2.pdf', 600, 'application/pdf');
        $versionResponse = $this->postJson("/api/v1/documents/{$docId}/versions", [
            'file' => $file2,
            'change_note' => 'Added IRB board checklist to appendix.',
        ]);

        $versionResponse->assertStatus(201)
            ->assertJsonPath('data.version_number', 2);

        $this->assertDatabaseHas('document_versions', [
            'document_id' => $docId,
            'version_number' => 2,
        ]);

        // Prior version 1 remains untouched
        $this->assertDatabaseHas('document_versions', [
            'document_id' => $docId,
            'version_number' => 1,
        ]);

        // Document lock_version is incremented to 1
        $doc = Document::find($docId);
        $this->assertEquals(1, $doc->lock_version);
    }

    public function test_user_can_download_file_with_checksum_integrity(): void
    {
        $this->authenticateAs($this->adminUser);

        $fileContent = 'CONFIDENTIAL_SENATE_DECREE_BYTES_2026';
        $file = UploadedFile::fake()->createWithContent('decree.pdf', $fileContent);
        $expectedHash = hash('sha256', $fileContent);

        $category = DocumentCategory::first();
        $confidentiality = \App\Models\ConfidentialityLevel::first();

        $docRes = $this->postJson('/api/v1/documents', [
            'title' => 'Senate Decree on Grading Policy',
            'category_id' => $category->id,
            'confidentiality_id' => $confidentiality->id,
            'owner_unit_id' => $this->unit->id,
            'document_date' => '2026-10-29',
            'file' => $file,
        ]);

        $fileObj = FileObject::where('sha256', $expectedHash)->first();
        $this->assertNotNull($fileObj);

        $downloadRes = $this->getJson("/api/v1/files/{$fileObj->id}/download");

        $downloadRes->assertStatus(200);
        $downloadRes->assertHeader('X-Checksum-Sha256', $expectedHash);
    }

    public function test_documents_list_supports_pagination_and_search(): void
    {
        $this->authenticateAs($this->adminUser);

        $cat1 = DocumentCategory::first();
        $conf = \App\Models\ConfidentialityLevel::first();

        Document::create([
            'reference_number' => 'WU/DOC/2026/900001',
            'title' => 'Searchable Key Document Alpha',
            'summary' => 'Alpha document summary for search testing',
            'category_id' => $cat1->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $this->unit->id,
            'created_by' => $this->adminUser->id,
            'document_date' => '2026-10-29',
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $listRes = $this->getJson('/api/v1/documents?search=Key+Document+Alpha');

        $listRes->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(1, count($listRes->json('data')));

        $mismatchRes = $this->getJson('/api/v1/documents?search=NonExistentKeywordXYZ123');
        $mismatchRes->assertStatus(200);
        $this->assertCount(0, $mismatchRes->json('data'));
    }

    public function test_unauthorized_user_without_permission_cannot_create_document(): void
    {
        $restrictedUser = User::create([
            'name' => 'Restricted User',
            'email' => 'restricted@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        $this->authenticateAs($restrictedUser);

        $response = $this->postJson('/api/v1/documents', [
            'title' => 'Unauthorized Document Attempt',
            'category_id' => DocumentCategory::first()->id,
            'confidentiality_id' => \App\Models\ConfidentialityLevel::first()->id,
            'owner_unit_id' => $this->unit->id,
            'document_date' => '2026-10-29',
        ]);

        $response->assertStatus(403);
    }

    public function test_show_document_serializes_versions_and_version_files_without_private_storage_metadata(): void
    {
        $this->authenticateAs($this->adminUser);

        $file1 = UploadedFile::fake()->createWithContent('version1.pdf', 'WU Document Version 1 Content');
        $cat = DocumentCategory::first();
        $conf = \App\Models\ConfidentialityLevel::first();

        $createRes = $this->postJson('/api/v1/documents', [
            'title' => 'Institutional Governance Charter 2026',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $this->unit->id,
            'document_date' => '2026-10-29',
            'file' => $file1,
            'change_note' => 'Original charter submission',
        ]);
        $createRes->assertStatus(201);
        $docId = $createRes->json('data.id');

        // Add version 2
        $file2 = UploadedFile::fake()->createWithContent('version2.pdf', 'WU Document Version 2 Amended Content');
        $this->postJson("/api/v1/documents/{$docId}/versions", [
            'file' => $file2,
            'change_note' => 'Senate legal committee amendment',
        ])->assertStatus(201);

        // Fetch detail
        $showRes = $this->getJson("/api/v1/documents/{$docId}");
        $showRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $docId)
            ->assertJsonCount(2, 'data.versions');

        $versions = $showRes->json('data.versions');
        $this->assertEquals(2, $versions[0]['version_number']);
        $this->assertEquals('Senate legal committee amendment', $versions[0]['change_note']);
        $this->assertEquals(1, $versions[1]['version_number']);
        $this->assertEquals('Original charter submission', $versions[1]['change_note']);
        $this->assertNotEmpty($versions[1]['version_files']);

        $fileObj = $versions[1]['version_files'][0]['file_object'];
        $this->assertNotNull($fileObj);
        $this->assertEquals('version1.pdf', $fileObj['original_filename']);
        $this->assertEquals('application/pdf', $fileObj['detected_mime']);
        $this->assertNotEmpty($fileObj['sha256']);
        $this->assertEquals('ready', $fileObj['storage_status']);

        // Assert private storage paths and disks are strictly omitted
        $jsonString = $showRes->getContent();
        $this->assertStringNotContainsString('storage_disk', $jsonString);
        $this->assertStringNotContainsString('storage_key', $jsonString);
        $this->assertStringNotContainsString('documents/2026/', $jsonString);
    }

    public function test_receiving_unit_user_can_view_and_download_file_of_routed_document(): void
    {
        // 1. Create Unit B (Registrar Directorate)
        $unitB = OrganizationUnit::create([
            'unit_type_id' => $this->unit->unit_type_id,
            'code' => 'REG-OFFICE',
            'name' => 'Office of the Registrar',
            'active' => 1,
        ]);

        // 2. Create receiving officer in Unit B with scoped permissions
        $receiver = User::create([
            'name' => 'Registrar Receiving Officer',
            'email' => 'registrar.officer@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($receiver, 'documents.view', 'unit', $unitB->id);
        $this->grantPermissionToUser($receiver, 'documents.download', 'unit', $unitB->id);

        // 3. Register document in Unit A with file
        $this->authenticateAs($this->adminUser);
        $file = UploadedFile::fake()->createWithContent('curriculum_dossier.pdf', 'CURRICULUM_DATA_STREAM_2026');
        $cat = DocumentCategory::first();
        $conf = \App\Models\ConfidentialityLevel::first();

        $createRes = $this->postJson('/api/v1/documents', [
            'title' => 'Curriculum Accreditation Dossier',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $this->unit->id,
            'document_date' => '2026-10-29',
            'file' => $file,
        ]);
        $createRes->assertStatus(201);
        $docId = $createRes->json('data.id');
        $doc = Document::with('versions.versionFiles.fileObject')->find($docId);
        $fileObjId = $doc->versions->first()->versionFiles->first()->file_object_id;

        // 4. Before movement: Receiver in Unit B is denied access (403)
        $this->authenticateAs($receiver);
        $this->getJson("/api/v1/documents/{$docId}")->assertStatus(403);
        $this->getJson("/api/v1/files/{$fileObjId}/download")->assertStatus(403);

        // 5. Admin records institutional movement from Unit A to Unit B
        $this->authenticateAs($this->adminUser);
        DocumentMovement::create([
            'document_id' => $docId,
            'from_unit_id' => $this->unit->id,
            'to_unit_id' => $unitB->id,
            'sent_by' => $this->adminUser->id,
            'sent_at' => now(),
            'instruction' => 'For registrar curriculum verification and archiving.',
            'idempotency_key' => Str::uuid()->toString(),
        ]);

        // 6. After movement: Receiver in Unit B can view and download!
        $this->authenticateAs($receiver);
        $viewRes = $this->getJson("/api/v1/documents/{$docId}");
        $viewRes->assertStatus(200)
            ->assertJsonPath('data.id', $docId)
            ->assertJsonPath('data.title', 'Curriculum Accreditation Dossier');

        // Can download file with integrity checksum
        $downRes = $this->getJson("/api/v1/files/{$fileObjId}/download");
        $downRes->assertStatus(200);
        $this->assertNotEmpty($downRes->headers->get('X-Checksum-Sha256'));

        // Document appears in receiver's index
        $indexRes = $this->getJson('/api/v1/documents');
        $indexRes->assertStatus(200);
        $ids = collect($indexRes->json('data'))->pluck('id')->all();
        $this->assertContains($docId, $ids);
    }

    public function test_receiving_unit_user_loses_access_when_document_forwarded_to_different_unit(): void
    {
        $unitB = OrganizationUnit::create([
            'unit_type_id' => $this->unit->unit_type_id,
            'code' => 'UNIT-B',
            'name' => 'Intermediate Receiving Unit B',
            'active' => 1,
        ]);
        $unitC = OrganizationUnit::create([
            'unit_type_id' => $this->unit->unit_type_id,
            'code' => 'UNIT-C',
            'name' => 'Final Destination Unit C',
            'active' => 1,
        ]);

        $receiverB = User::create([
            'name' => 'Unit B Officer',
            'email' => 'officer.b@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($receiverB, 'documents.view', 'unit', $unitB->id);
        $this->grantPermissionToUser($receiverB, 'documents.download', 'unit', $unitB->id);

        $receiverC = User::create([
            'name' => 'Unit C Officer',
            'email' => 'officer.c@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($receiverC, 'documents.view', 'unit', $unitC->id);
        $this->grantPermissionToUser($receiverC, 'documents.download', 'unit', $unitC->id);

        $this->authenticateAs($this->adminUser);
        $file = UploadedFile::fake()->createWithContent('routing_test.pdf', 'ROUTING_TEST_CONTENT');
        $createRes = $this->postJson('/api/v1/documents', [
            'title' => 'Forwarding Access Invalidation Test',
            'category_id' => DocumentCategory::first()->id,
            'confidentiality_id' => \App\Models\ConfidentialityLevel::first()->id,
            'owner_unit_id' => $this->unit->id,
            'document_date' => '2026-10-29',
            'file' => $file,
        ]);
        $docId = $createRes->json('data.id');
        $doc = Document::with('versions.versionFiles.fileObject')->find($docId);
        $fileObjId = $doc->versions->first()->versionFiles->first()->file_object_id;

        // Route to Unit B first
        DocumentMovement::create([
            'document_id' => $docId,
            'from_unit_id' => $this->unit->id,
            'to_unit_id' => $unitB->id,
            'sent_by' => $this->adminUser->id,
            'sent_at' => now()->subHours(2),
            'idempotency_key' => Str::uuid()->toString(),
        ]);

        // Receiver B has access
        $this->authenticateAs($receiverB);
        $this->getJson("/api/v1/documents/{$docId}")->assertStatus(200);

        // Document is now forwarded to Unit C
        $this->authenticateAs($this->adminUser);
        DocumentMovement::create([
            'document_id' => $docId,
            'from_unit_id' => $unitB->id,
            'to_unit_id' => $unitC->id,
            'sent_by' => $this->adminUser->id,
            'sent_at' => now()->subMinute(),
            'idempotency_key' => Str::uuid()->toString(),
        ]);

        // Receiver B now receives 403 Forbidden (historical movement cannot hold access)
        $this->authenticateAs($receiverB);
        $this->getJson("/api/v1/documents/{$docId}")->assertStatus(403);
        $this->getJson("/api/v1/files/{$fileObjId}/download")->assertStatus(403);

        // Receiver C now holds legitimate access
        $this->authenticateAs($receiverC);
        $this->getJson("/api/v1/documents/{$docId}")->assertStatus(200);
        $this->getJson("/api/v1/files/{$fileObjId}/download")->assertStatus(200);
    }

    public function test_assigned_user_can_view_document_while_active_but_denied_when_cancelled(): void
    {
        $unitB = OrganizationUnit::create([
            'unit_type_id' => $this->unit->unit_type_id,
            'code' => 'AUDIT-UNIT',
            'name' => 'Internal Audit Directorate',
            'active' => 1,
        ]);

        $assignedUser = User::create([
            'name' => 'Case Investigator User',
            'email' => 'investigator@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($assignedUser, 'documents.view', 'unit', $unitB->id);

        \App\Models\OrganizationMembership::create([
            'user_id' => $assignedUser->id,
            'unit_id' => $unitB->id,
            'position_title' => 'Internal Auditor',
            'starts_at' => now(),
            'created_by' => $this->adminUser->id,
            'created_at' => now(),
        ]);

        $this->authenticateAs($this->adminUser);
        $createRes = $this->postJson('/api/v1/documents', [
            'title' => 'Assigned Inquiry Investigation Dossier',
            'category_id' => DocumentCategory::first()->id,
            'confidentiality_id' => \App\Models\ConfidentialityLevel::first()->id,
            'owner_unit_id' => $this->unit->id,
            'document_date' => '2026-10-29',
        ]);
        $docId = $createRes->json('data.id');

        // Before assignment: user in Unit B cannot view Unit A document
        $this->authenticateAs($assignedUser);
        $this->getJson("/api/v1/documents/{$docId}")->assertStatus(403);

        // Create assignment for user
        $this->authenticateAs($this->adminUser);
        $assignment = DocumentAssignment::create([
            'document_id' => $docId,
            'assigned_user_id' => $assignedUser->id,
            'assigned_by' => $this->adminUser->id,
            'assigned_at' => now(),
            'instruction' => 'Lead inquiry investigation.',
        ]);

        // Active assignee can now view
        $this->authenticateAs($assignedUser);
        $this->getJson("/api/v1/documents/{$docId}")->assertStatus(200);

        // Cancel assignment via event
        $this->authenticateAs($this->adminUser);
        AssignmentEvent::create([
            'assignment_id' => $assignment->id,
            'sequence_number' => 1,
            'action' => 'cancelled',
            'actor_id' => $this->adminUser->id,
            'occurred_at' => now(),
            'comment' => 'Inquiry reassigned to external audit committee.',
        ]);

        // Cancelled assignee is immediately denied
        $this->authenticateAs($assignedUser);
        $this->getJson("/api/v1/documents/{$docId}")->assertStatus(403);
    }

    public function test_unauthorized_cross_unit_user_is_strictly_denied_document_access(): void
    {
        $isolatedUnit = OrganizationUnit::create([
            'unit_type_id' => $this->unit->unit_type_id,
            'code' => 'UNREL-UNIT',
            'name' => 'Unrelated Unit Directorate',
            'active' => 1,
        ]);

        $crossUnitUser = User::create([
            'name' => 'Cross Unit User',
            'email' => 'cross.unit@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($crossUnitUser, 'documents.view', 'unit', $isolatedUnit->id);
        $this->grantPermissionToUser($crossUnitUser, 'documents.download', 'unit', $isolatedUnit->id);

        $this->authenticateAs($this->adminUser);
        $file = UploadedFile::fake()->createWithContent('secret_charter.pdf', 'SECRET_UNIT_A_CONTENT');
        $createRes = $this->postJson('/api/v1/documents', [
            'title' => 'Internal Unit A Policy Memo',
            'category_id' => DocumentCategory::first()->id,
            'confidentiality_id' => \App\Models\ConfidentialityLevel::first()->id,
            'owner_unit_id' => $this->unit->id,
            'document_date' => '2026-10-29',
            'file' => $file,
        ]);
        $docId = $createRes->json('data.id');
        $doc = Document::with('versions.versionFiles.fileObject')->find($docId);
        $fileObjId = $doc->versions->first()->versionFiles->first()->file_object_id;

        // Cross unit user cannot view or download
        $this->authenticateAs($crossUnitUser);
        $this->getJson("/api/v1/documents/{$docId}")->assertStatus(403);
        $this->getJson("/api/v1/files/{$fileObjId}/download")->assertStatus(403);
    }

    public function test_high_confidentiality_document_access_restriction_for_unauthorized_users(): void
    {
        $confLevel = \App\Models\ConfidentialityLevel::where('code', 'confidential')->first();

        $unauthorizedStaff = User::create([
            'name' => 'Unclear Staff',
            'email' => 'unclear.staff@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        // Has documents.view only in another unit
        $otherUnit = OrganizationUnit::create([
            'unit_type_id' => $this->unit->unit_type_id,
            'code' => 'OTHER-OFFICE',
            'name' => 'Other Office',
            'active' => 1,
        ]);
        $this->grantPermissionToUser($unauthorizedStaff, 'documents.view', 'unit', $otherUnit->id);

        $this->authenticateAs($this->adminUser);
        $file = UploadedFile::fake()->createWithContent('confidential_strategy.pdf', 'CONFIDENTIAL_STRATEGY_DATA');
        $createRes = $this->postJson('/api/v1/documents', [
            'title' => 'Confidential University Strategy 2030',
            'category_id' => DocumentCategory::first()->id,
            'confidentiality_id' => $confLevel->id,
            'owner_unit_id' => $this->unit->id,
            'document_date' => '2026-10-29',
            'file' => $file,
        ]);
        $docId = $createRes->json('data.id');
        $doc = Document::with('versions.versionFiles.fileObject')->find($docId);
        $fileObjId = $doc->versions->first()->versionFiles->first()->file_object_id;

        $this->authenticateAs($unauthorizedStaff);
        $resp = $this->getJson("/api/v1/documents/{$docId}");
        $resp->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden: High confidentiality document access restricted.');

        $downResp = $this->getJson("/api/v1/files/{$fileObjId}/download");
        $downResp->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden: High confidentiality document file download restricted.');
    }
}
