<?php

namespace Tests\Feature;

use App\Models\ConfidentialityLevel;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\FileObject;
use App\Models\OrganizationUnit;
use App\Models\UnitType;
use App\Models\User;
use App\Models\VersionFile;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RealAuthorizationAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $docController;
    protected User $generalStaff;
    protected User $officerUnitA;
    protected User $officerUnitB;
    protected OrganizationUnit $unitA;
    protected OrganizationUnit $unitB;
    protected DocumentCategory $category;
    protected ConfidentialityLevel $internalConf;
    protected ConfidentialityLevel $secretConf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);
        Storage::fake('local');

        $this->superAdmin = User::where('email', 'admin@wu.edu.et')->first();
        $this->category = DocumentCategory::first();
        $this->internalConf = ConfidentialityLevel::where('code', 'internal')->firstOrFail();
        $this->secretConf = ConfidentialityLevel::where('code', 'confidential')->firstOrFail();

        $collegeType = UnitType::firstOrCreate(['code' => 'college'], ['name' => 'College', 'active' => 1]);
        $this->unitA = OrganizationUnit::first();
        $this->unitB = OrganizationUnit::create([
            'parent_id' => $this->unitA->id,
            'unit_type_id' => $collegeType->id,
            'code' => 'UNIT_SEC_B',
            'name' => 'Secondary Security Unit B',
            'active' => 1,
        ]);

        // Document Controller in Unit A
        $this->docController = User::create([
            'name' => 'Test Document Controller',
            'email' => 'doc.controller.test@wu.edu.et',
            'password' => bcrypt('TestSecret123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->docController, 'document_controller', 'unit', $this->unitA->id);

        // General Staff in Unit A
        $this->generalStaff = User::create([
            'name' => 'Test General Staff',
            'email' => 'staff.test@wu.edu.et',
            'password' => bcrypt('TestSecret123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->generalStaff, 'staff', 'unit', $this->unitA->id);

        // Officer in Unit B
        $this->officerUnitB = User::create([
            'name' => 'Test Officer Unit B',
            'email' => 'officer.b.test@wu.edu.et',
            'password' => bcrypt('TestSecret123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->officerUnitB, 'records_officer', 'unit', $this->unitB->id);
    }

    protected function createDocument(OrganizationUnit $unit, User $creator, ?ConfidentialityLevel $conf = null): Document
    {
        $doc = Document::create([
            'reference_number' => 'DOC-TEST-' . uniqid(),
            'title' => 'Test Document ' . uniqid(),
            'category_id' => $this->category->id,
            'confidentiality_id' => ($conf ?? $this->internalConf)->id,
            'owner_unit_id' => $unit->id,
            'created_by' => $creator->id,
            'lifecycle' => 'active',
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
        ]);

        $version = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $creator->id,
            'comment' => 'Initial',
        ]);

        $fileObject = FileObject::create([
            'storage_disk' => 'local',
            'storage_key' => 'documents/test-' . uniqid() . '.pdf',
            'original_filename' => 'test.pdf',
            'detected_mime' => 'application/pdf',
            'byte_size' => 1024,
            'sha256' => hash('sha256', 'content_' . uniqid()),
            'uploaded_by' => $creator->id,
            'uploaded_at' => now(),
            'scan_status' => 'clean',
            'storage_status' => 'ready',
        ]);

        VersionFile::create([
            'document_version_id' => $version->id,
            'file_object_id' => $fileObject->id,
            'purpose' => 'primary',
            'display_order' => 0,
        ]);

        return $doc;
    }

    /**
     * 1. Test Document Controller Allowed Actions:
     * View, create document, view accreditation.
     */
    public function test_document_controller_allowed_actions(): void
    {
        $doc = $this->createDocument($this->unitA, $this->docController);

        $this->authenticateAs($this->docController);

        // View document in scope
        $resp = $this->getJson("/api/v1/documents/{$doc->id}");
        $resp->assertStatus(200);

        // Create document in scope
        $createResp = $this->postJson('/api/v1/documents', [
            'title' => 'Doc Controller Created Doc',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->internalConf->id,
            'owner_unit_id' => $this->unitA->id,
            'document_date' => now()->toDateString(),
        ]);
        $createResp->assertStatus(201);

        // View accreditation
        $accResp = $this->getJson('/api/v1/accreditation/evidence');
        $accResp->assertStatus(200);
    }

    /**
     * 2. Test Document Controller Denied Actions (No privilege escalation):
     * Cannot create users, cannot manage roles, cannot access system operations.
     */
    public function test_document_controller_cannot_perform_administrative_actions(): void
    {
        $this->authenticateAs($this->docController);

        // Cannot create users (requires users.create)
        $userResp = $this->postJson('/api/v1/users', [
            'name' => 'Escalated User',
            'email' => 'escalated@wu.edu.et',
            'password' => 'Pass12345!',
        ]);
        $userResp->assertStatus(403);

        // Cannot create roles (requires roles.create)
        $roleResp = $this->postJson('/api/v1/roles', [
            'code' => 'hacked_role',
            'name' => 'Hacked Role',
        ]);
        $roleResp->assertStatus(403);

        // Cannot access system operations status (requires system.configure)
        $opsResp = $this->getJson('/api/v1/operations/status');
        $opsResp->assertStatus(403);
    }

    /**
     * 3. Test Organizational Scope Restrictions:
     * Officer in Unit B cannot access Unit A documents.
     */
    public function test_scope_restriction_blocks_cross_unit_document_access(): void
    {
        $docA = $this->createDocument($this->unitA, $this->docController);

        $this->authenticateAs($this->officerUnitB);

        $resp = $this->getJson("/api/v1/documents/{$docA->id}");
        $resp->assertStatus(403);
    }

    /**
     * 4. Test Unauthorized Access to High Confidentiality Documents:
     * General staff cannot access 'secret' document without explicit grant.
     */
    public function test_unauthorized_access_to_confidential_document_is_blocked(): void
    {
        $secretDoc = $this->createDocument($this->unitA, $this->superAdmin, $this->secretConf);

        $this->authenticateAs($this->generalStaff);

        $resp = $this->getJson("/api/v1/documents/{$secretDoc->id}");
        $resp->assertStatus(403);
    }

    /**
     * 5. Test Unauthorized Access to Administrative Endpoints by Staff:
     */
    public function test_general_staff_cannot_access_audit_logs_or_settings(): void
    {
        $this->authenticateAs($this->generalStaff);

        // Audit log (requires audit.view)
        $auditResp = $this->getJson('/api/v1/audit-events');
        $auditResp->assertStatus(403);

        // Reports (requires reports.view)
        $repResp = $this->getJson('/api/v1/reports/summary');
        $repResp->assertStatus(403);
    }

    /**
     * 6. Test Unauthorized Access to HR and Sensitive Staff Endpoints:
     */
    public function test_general_staff_cannot_modify_staff_records(): void
    {
        $this->authenticateAs($this->generalStaff);

        // Store staff record requires staff.update
        $resp = $this->postJson('/api/v1/staff/records', [
            'staff_id' => 'WU-STAFF-999',
            'full_name' => 'Unauthorized Employee Record',
        ]);
        $resp->assertStatus(403);
    }

    /**
     * 7. Test Unauthorized Access to Research Publication:
     */
    public function test_general_staff_cannot_publish_research_outputs(): void
    {
        $this->authenticateAs($this->generalStaff);

        // Toggle publication requires research.publish
        $resp = $this->postJson('/api/v1/research/outputs/1/toggle-publication', []);
        $resp->assertStatus(403);
    }

    /**
     * 8. Test Unauthorized Archive Transfer:
     */
    public function test_general_staff_cannot_transfer_to_archive(): void
    {
        $this->authenticateAs($this->generalStaff);

        // Archive transfer requires archive.transfer
        $resp = $this->postJson('/api/v1/archive/transfer', [
            'document_id' => 1,
            'collection_id' => 1,
        ]);
        $resp->assertStatus(403);
    }
}
