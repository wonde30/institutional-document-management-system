<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\DocumentController;
use App\Models\CommunicationParty;
use App\Models\Correspondence;
use App\Models\CorrespondenceDispatch;
use App\Models\CorrespondenceParticipant;
use App\Models\CorrespondenceType;
use App\Models\DispatchMethod;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentMovement;
use App\Models\DocumentVersion;
use App\Models\FileObject;
use App\Models\OrganizationUnit;
use App\Models\Priority;
use App\Models\User;
use App\Models\VersionFile;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentSecurityRemediationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected OrganizationUnit $unitA;
    protected OrganizationUnit $unitB;
    protected DocumentCategory $category;
    protected \App\Models\ConfidentialityLevel $confidentiality;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        Storage::fake('local');

        $this->unitA = OrganizationUnit::first();
        $this->unitB = OrganizationUnit::create([
            'unit_type_id' => $this->unitA->unit_type_id,
            'code' => 'UNIT-SEC-B',
            'name' => 'Secondary Security Testing Unit',
            'active' => 1,
        ]);

        $this->adminUser = User::where('email', 'admin@wu.edu.et')->first();
        $this->category = DocumentCategory::first();
        $this->confidentiality = \App\Models\ConfidentialityLevel::first();
    }

    /**
     * Helper to create a fully wired document with an initial version and file object.
     */
    protected function createDocumentFixture(
        OrganizationUnit $unit,
        ?User $creator = null,
        ?User $custodian = null,
        string $filename = 'test_security.pdf',
        string $content = 'SECURE_TEST_FILE_CONTENT'
    ): array {
        $creator = $creator ?? $this->adminUser;
        $custodian = $custodian ?? $creator;

        $disk = config('filesystems.default', 'local');
        $storedPath = 'documents/'.date('Y').'/'.Str::random(40).'.pdf';
        Storage::disk($disk)->put($storedPath, $content);
        $hash = hash('sha256', $content);

        $fileObject = FileObject::create([
            'storage_disk' => $disk,
            'storage_key' => $storedPath,
            'original_filename' => $filename,
            'detected_mime' => 'application/pdf',
            'byte_size' => strlen($content),
            'sha256' => $hash,
            'uploaded_by' => $creator->id,
            'uploaded_at' => now(),
            'scan_status' => 'clean',
            'scanned_at' => now(),
            'storage_status' => 'ready',
        ]);

        $ref = sprintf('WU/DOC/%s/%06d', date('Y'), random_int(100000, 999999));

        $document = Document::create([
            'reference_number' => $ref,
            'title' => 'Security Audit Document ' . Str::random(6),
            'summary' => 'Document generated for SEC-001/SEC-002 regression verification.',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $unit->id,
            'created_by' => $creator->id,
            'custodian_id' => $custodian->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $version = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 1,
            'change_note' => 'Initial security test version',
            'created_by' => $creator->id,
            'created_at' => now(),
        ]);

        $versionFile = VersionFile::create([
            'document_version_id' => $version->id,
            'file_object_id' => $fileObject->id,
            'purpose' => 'primary',
            'display_order' => 1,
        ]);

        return [
            'document' => $document,
            'version' => $version,
            'fileObject' => $fileObject,
            'versionFile' => $versionFile,
        ];
    }

    /**
     * Helper to create an active user with specific scoped permissions.
     */
    protected function createUserWithPermissions(string $email, array $permissions = [], ?int $unitId = null): User
    {
        $user = User::create([
            'name' => 'User ' . Str::random(5),
            'email' => $email,
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        foreach ($permissions as $perm) {
            $this->grantPermissionToUser($user, $perm, $unitId ? 'unit' : 'global', $unitId);
        }

        return $user;
    }

    // =========================================================================
    // SEC-001: FILE DOWNLOAD AUTHORIZATION & FAIL-CLOSED BOUNDARIES
    // =========================================================================

    public function test_authorized_document_version_file_download_succeeds(): void
    {
        $fixture = $this->createDocumentFixture($this->unitA);
        $user = $this->createUserWithPermissions('authorized.unit_a@wu.edu.et', ['documents.download', 'documents.view'], $this->unitA->id);

        $this->authenticateAs($user);
        $response = $this->getJson("/api/v1/files/{$fixture['fileObject']->id}/download");

        $response->assertStatus(200);
        $this->assertEquals($fixture['fileObject']->sha256, $response->headers->get('X-Checksum-Sha256'));
    }

    public function test_unauthorized_document_version_file_download_is_denied(): void
    {
        $fixture = $this->createDocumentFixture($this->unitA);
        $unauthorizedUser = $this->createUserWithPermissions('unauth.user@wu.edu.et', ['documents.view', 'documents.download'], $this->unitB->id);

        $this->authenticateAs($unauthorizedUser);
        $response = $this->getJson("/api/v1/files/{$fixture['fileObject']->id}/download");

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_unlinked_file_object_cannot_be_downloaded(): void
    {
        // An unlinked file has no VersionFile, CorrespondenceDispatch, DigitalSignature, or FileDerivative
        $disk = config('filesystems.default', 'local');
        $storedPath = 'unlinked/sensitive_raw_dump.pdf';
        Storage::disk($disk)->put($storedPath, 'UNLINKED_FILE_CONTENT');

        $unlinkedFile = FileObject::create([
            'storage_disk' => $disk,
            'storage_key' => $storedPath,
            'original_filename' => 'sensitive_raw_dump.pdf',
            'detected_mime' => 'application/pdf',
            'byte_size' => 21,
            'sha256' => hash('sha256', 'UNLINKED_FILE_CONTENT'),
            'uploaded_by' => $this->adminUser->id,
            'uploaded_at' => now(),
            'storage_status' => 'ready',
        ]);

        // Attempt as regular user
        $regularUser = $this->createUserWithPermissions('regular.staff@wu.edu.et', ['documents.download']);
        $this->authenticateAs($regularUser);
        $response = $this->getJson("/api/v1/files/{$unlinkedFile->id}/download");

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden: File is not associated with an authorized document record.');

        // Attempt as Super Admin - must fail-closed as well because unlinked files cannot be resolved to any document
        $this->authenticateAs($this->adminUser);
        $adminResponse = $this->getJson("/api/v1/files/{$unlinkedFile->id}/download");

        $adminResponse->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden: File is not associated with an authorized document record.');
    }

    public function test_orphaned_version_file_fails_safely(): void
    {
        $disk = config('filesystems.default', 'local');
        $storedPath = 'orphaned/orphaned_doc.pdf';
        Storage::disk($disk)->put($storedPath, 'ORPHANED_CONTENT');

        $orphanedFile = FileObject::create([
            'storage_disk' => $disk,
            'storage_key' => $storedPath,
            'original_filename' => 'orphaned_doc.pdf',
            'detected_mime' => 'application/pdf',
            'byte_size' => 16,
            'sha256' => hash('sha256', 'ORPHANED_CONTENT'),
            'uploaded_by' => $this->adminUser->id,
            'uploaded_at' => now(),
            'storage_status' => 'ready',
        ]);

        // Create version file pointing to a non-existent document version ID (simulating data corruption)
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        VersionFile::create([
            'document_version_id' => 999999,
            'file_object_id' => $orphanedFile->id,
            'purpose' => 'primary',
            'display_order' => 1,
        ]);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->authenticateAs($this->adminUser);
        $response = $this->getJson("/api/v1/files/{$orphanedFile->id}/download");

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden: File is not associated with an authorized document record.');
    }

    public function test_legitimate_correspondence_dispatch_attachment_access_works_only_for_authorized_users(): void
    {
        $fixture = $this->createDocumentFixture($this->unitA);

        // Create evidence file
        $disk = config('filesystems.default', 'local');
        $storedPath = 'dispatches/evidence_slip.pdf';
        Storage::disk($disk)->put($storedPath, 'DISPATCH_PROOF_EVIDENCE');

        $evidenceFile = FileObject::create([
            'storage_disk' => $disk,
            'storage_key' => $storedPath,
            'original_filename' => 'evidence_slip.pdf',
            'detected_mime' => 'application/pdf',
            'byte_size' => 23,
            'sha256' => hash('sha256', 'DISPATCH_PROOF_EVIDENCE'),
            'uploaded_by' => $this->adminUser->id,
            'uploaded_at' => now(),
            'storage_status' => 'ready',
        ]);

        $method = DispatchMethod::firstOrCreate(
            ['code' => 'postal'],
            ['name' => 'Postal Service', 'requires_tracking' => true, 'is_active' => true]
        );

        $corr = Correspondence::create([
            'document_id' => $fixture['document']->id,
            'type_id' => CorrespondenceType::where('code', 'outgoing')->first()->id,
            'priority_id' => Priority::first()->id,
            'purpose_code' => 'notice',
            'external_reference' => 'EXT-DISP-001',
            'correspondence_date' => now()->toDateString(),
            'registered_by' => $this->adminUser->id,
            'registered_at' => now(),
        ]);

        $party = CommunicationParty::firstOrCreate(
            ['unit_id' => $this->unitA->id, 'user_id' => null, 'external_party_id' => null]
        );

        $participant = CorrespondenceParticipant::create([
            'correspondence_id' => $corr->id,
            'party_id' => $party->id,
            'participation_type' => 'to',
        ]);

        CorrespondenceDispatch::create([
            'participant_id' => $participant->id,
            'document_version_id' => $fixture['version']->id,
            'method_id' => $method->id,
            'dispatched_by' => $this->adminUser->id,
            'dispatched_at' => now(),
            'tracking_reference' => 'ETH-POST-9921',
            'evidence_file_id' => $evidenceFile->id,
            'idempotency_key' => Str::uuid()->toString(),
        ]);

        // Authorized user with documents.download in Unit A can download evidence file
        $authorizedUser = $this->createUserWithPermissions('dispatch.officer@wu.edu.et', ['documents.download', 'documents.view'], $this->unitA->id);
        $this->authenticateAs($authorizedUser);
        $authResponse = $this->getJson("/api/v1/files/{$evidenceFile->id}/download");
        $authResponse->assertStatus(200);

        // Unauthorized user in Unit B cannot download evidence file
        $unauthorizedUser = $this->createUserWithPermissions('unauth.officer@wu.edu.et', ['documents.download', 'documents.view'], $this->unitB->id);
        $this->authenticateAs($unauthorizedUser);
        $unauthResponse = $this->getJson("/api/v1/files/{$evidenceFile->id}/download");
        $unauthResponse->assertStatus(403);
    }

    public function test_file_id_substitution_across_documents_fails_closed(): void
    {
        $fixtureA = $this->createDocumentFixture($this->unitA, filename: 'docA_file.pdf');
        $fixtureB = $this->createDocumentFixture($this->unitB, filename: 'docB_file.pdf');

        // User has access ONLY to Unit A
        $userUnitA = $this->createUserWithPermissions('unit_a.user@wu.edu.et', ['documents.download', 'documents.view'], $this->unitA->id);

        $this->authenticateAs($userUnitA);

        // Legitimate download of Document A file succeeds
        $respA = $this->getJson("/api/v1/files/{$fixtureA['fileObject']->id}/download");
        $respA->assertStatus(200);

        // Substituted file ID from Document B fails closed (403)
        $respB = $this->getJson("/api/v1/files/{$fixtureB['fileObject']->id}/download");
        $respB->assertStatus(403);
    }

    public function test_missing_file_object_fails_safely_with_404(): void
    {
        $this->authenticateAs($this->adminUser);
        $response = $this->getJson('/api/v1/files/999999/download');

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'File object not found.');
    }

    public function test_download_controller_authorization_cannot_be_bypassed_by_direct_invocation(): void
    {
        $fixture = $this->createDocumentFixture($this->unitA);

        // Unlinked file
        $unlinkedFile = FileObject::create([
            'storage_disk' => 'local',
            'storage_key' => 'unlinked/direct_call.pdf',
            'original_filename' => 'direct_call.pdf',
            'detected_mime' => 'application/pdf',
            'byte_size' => 10,
            'sha256' => hash('sha256', 'DIRECT_CALL'),
            'uploaded_by' => $this->adminUser->id,
            'uploaded_at' => now(),
            'storage_status' => 'ready',
        ]);

        $controller = new DocumentController();

        // Direct call for unlinked file returns 403 JsonResponse
        $responseUnlinked = $controller->downloadFile($unlinkedFile->id);
        $this->assertEquals(403, $responseUnlinked->getStatusCode());
        $this->assertStringContainsString('File is not associated with an authorized document record', $responseUnlinked->getContent());

        // Direct call by unauthorized user returns 403 JsonResponse
        $unauthorizedUser = $this->createUserWithPermissions('direct.unauth@wu.edu.et', ['documents.view'], $this->unitB->id);
        $this->actingAs($unauthorizedUser, 'web');
        $responseUnauthorized = $controller->downloadFile($fixture['fileObject']->id);
        $this->assertEquals(403, $responseUnauthorized->getStatusCode());
    }

    // =========================================================================
    // SEC-002: CREATOR & CUSTODIAN MUTATION OVER-PERMISSIONING RESTRICTIONS
    // =========================================================================

    public function test_creator_without_documents_update_cannot_update_document(): void
    {
        $creator = $this->createUserWithPermissions('author.no.update@wu.edu.et', ['documents.view']);
        $fixture = $this->createDocumentFixture($this->unitA, creator: $creator);

        $this->authenticateAs($creator);
        $response = $this->putJson("/api/v1/documents/{$fixture['document']->id}", [
            'title' => 'Tampered Title by Unauthorized Creator',
            'lock_version' => 0,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);

        // Verify document title in database was NOT changed
        $this->assertDatabaseMissing('documents', [
            'id' => $fixture['document']->id,
            'title' => 'Tampered Title by Unauthorized Creator',
        ]);
    }

    public function test_custodian_without_documents_update_cannot_update_document(): void
    {
        $custodian = $this->createUserWithPermissions('custodian.no.update@wu.edu.et', ['documents.view']);
        $fixture = $this->createDocumentFixture($this->unitA, custodian: $custodian);

        $this->authenticateAs($custodian);
        $response = $this->putJson("/api/v1/documents/{$fixture['document']->id}", [
            'title' => 'Tampered Title by Unauthorized Custodian',
            'lock_version' => 0,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('documents', [
            'id' => $fixture['document']->id,
            'title' => 'Tampered Title by Unauthorized Custodian',
        ]);
    }

    public function test_creator_without_documents_version_cannot_upload_new_version(): void
    {
        $creator = $this->createUserWithPermissions('author.no.version@wu.edu.et', ['documents.view']);
        $fixture = $this->createDocumentFixture($this->unitA, creator: $creator);

        $newFile = UploadedFile::fake()->create('unauthorized_v2.pdf', 300, 'application/pdf');

        $this->authenticateAs($creator);
        $response = $this->postJson("/api/v1/documents/{$fixture['document']->id}/versions", [
            'file' => $newFile,
            'change_note' => 'Unauthorized amendment attempt',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);

        // Verify no new version was added
        $this->assertEquals(1, DocumentVersion::where('document_id', $fixture['document']->id)->count());
    }

    public function test_custodian_without_documents_version_cannot_upload_new_version(): void
    {
        $custodian = $this->createUserWithPermissions('custodian.no.version@wu.edu.et', ['documents.view']);
        $fixture = $this->createDocumentFixture($this->unitA, custodian: $custodian);

        $newFile = UploadedFile::fake()->create('unauthorized_custodian_v2.pdf', 300, 'application/pdf');

        $this->authenticateAs($custodian);
        $response = $this->postJson("/api/v1/documents/{$fixture['document']->id}/versions", [
            'file' => $newFile,
            'change_note' => 'Unauthorized custodian amendment attempt',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->assertEquals(1, DocumentVersion::where('document_id', $fixture['document']->id)->count());
    }

    public function test_authorized_creator_with_owner_unit_permission_can_update_and_upload_versions(): void
    {
        $authorizedCreator = $this->createUserWithPermissions(
            'authorized.author@wu.edu.et',
            ['documents.view', 'documents.update', 'documents.version'],
            $this->unitA->id
        );
        $fixture = $this->createDocumentFixture($this->unitA, creator: $authorizedCreator);

        $this->authenticateAs($authorizedCreator);

        // Update document
        $updateResp = $this->putJson("/api/v1/documents/{$fixture['document']->id}", [
            'title' => 'Legitimately Updated Academic Standard',
            'lock_version' => 0,
        ]);

        $updateResp->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Legitimately Updated Academic Standard')
            ->assertJsonPath('data.lock_version', 1);

        // Upload new version
        $v2File = UploadedFile::fake()->createWithContent('authorized_v2.pdf', 'V2_CONTENT_APPROVED');
        $versionResp = $this->postJson("/api/v1/documents/{$fixture['document']->id}/versions", [
            'file' => $v2File,
            'change_note' => 'Legitimate author amendment with owner-unit permission',
        ]);

        $versionResp->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.version_number', 2);

        $this->assertEquals(2, DocumentVersion::where('document_id', $fixture['document']->id)->count());
    }

    public function test_creator_retains_read_and_download_access_even_without_mutating_permissions(): void
    {
        $creator = $this->createUserWithPermissions('read.only.creator@wu.edu.et', ['documents.view']);
        $fixture = $this->createDocumentFixture($this->unitA, creator: $creator);

        // Creator has intrinsic read and download permissions
        $this->authenticateAs($creator);

        $viewResp = $this->getJson("/api/v1/documents/{$fixture['document']->id}");
        $viewResp->assertStatus(200)
            ->assertJsonPath('data.id', $fixture['document']->id);

        $downloadResp = $this->getJson("/api/v1/files/{$fixture['fileObject']->id}/download");
        $downloadResp->assertStatus(200);
    }

    public function test_receiving_unit_users_can_read_but_cannot_update_or_upload_versions(): void
    {
        $fixture = $this->createDocumentFixture($this->unitA);

        // User in receiving Unit B has all permissions within Unit B
        $receiverUser = $this->createUserWithPermissions(
            'receiver.officer@wu.edu.et',
            ['documents.view', 'documents.download', 'documents.update', 'documents.version'],
            $this->unitB->id
        );

        // Move document from Unit A to Unit B
        DocumentMovement::create([
            'document_id' => $fixture['document']->id,
            'from_unit_id' => $this->unitA->id,
            'to_unit_id' => $this->unitB->id,
            'sent_by' => $this->adminUser->id,
            'sent_at' => now(),
            'instruction' => 'For review and feedback only.',
            'idempotency_key' => Str::uuid()->toString(),
        ]);

        $this->authenticateAs($receiverUser);

        // Receiving unit can view
        $viewResp = $this->getJson("/api/v1/documents/{$fixture['document']->id}");
        $viewResp->assertStatus(200);

        // Receiving unit can download file
        $downResp = $this->getJson("/api/v1/files/{$fixture['fileObject']->id}/download");
        $downResp->assertStatus(200);

        // Receiving unit CANNOT update document (mutations are restricted to owner unit)
        $updateResp = $this->putJson("/api/v1/documents/{$fixture['document']->id}", [
            'title' => 'Receiver Attempting Title Overwrite',
            'lock_version' => 0,
        ]);
        $updateResp->assertStatus(403);

        // Receiving unit CANNOT upload a new version
        $v2File = UploadedFile::fake()->create('receiver_v2.pdf', 200, 'application/pdf');
        $versionResp = $this->postJson("/api/v1/documents/{$fixture['document']->id}/versions", [
            'file' => $v2File,
            'change_note' => 'Receiver attempting unauthorized version upload',
        ]);
        $versionResp->assertStatus(403);
    }

    public function test_super_administrator_can_perform_all_document_and_file_operations(): void
    {
        $fixture = $this->createDocumentFixture($this->unitA);

        $this->authenticateAs($this->adminUser);

        // Super Admin can view
        $this->getJson("/api/v1/documents/{$fixture['document']->id}")
            ->assertStatus(200);

        // Super Admin can download
        $this->getJson("/api/v1/files/{$fixture['fileObject']->id}/download")
            ->assertStatus(200);

        // Super Admin can update
        $this->putJson("/api/v1/documents/{$fixture['document']->id}", [
            'title' => 'Super Admin System Governance Update',
            'lock_version' => 0,
        ])->assertStatus(200);

        // Super Admin can upload version
        $vFile = UploadedFile::fake()->create('admin_v2.pdf', 200, 'application/pdf');
        $this->postJson("/api/v1/documents/{$fixture['document']->id}/versions", [
            'file' => $vFile,
            'change_note' => 'Administrative policy amendment',
        ])->assertStatus(201);
    }
}
