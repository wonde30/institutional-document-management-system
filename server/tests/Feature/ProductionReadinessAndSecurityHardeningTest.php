<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ArchiveCollection;
use App\Models\ArchiveEntry;
use App\Models\CommunicationParty;
use App\Models\Correspondence;
use App\Models\CorrespondenceDispatch;
use App\Models\CorrespondenceParticipant;
use App\Models\CorrespondenceType;
use App\Models\DispatchMethod;
use App\Models\DispositionRequest;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\FileObject;
use App\Models\OfficeProcedureVersion;
use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\Priority;
use App\Models\RetentionPolicy;
use App\Models\RetentionPolicyVersion;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use App\Services\FileSecurityService;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductionReadinessAndSecurityHardeningTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $officer;
    protected User $archivist;
    protected OrganizationUnit $unit;
    protected DocumentCategory $category;
    protected CorrespondenceType $outgoingType;
    protected DispatchMethod $dispatchMethod;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $unitType = UnitType::firstOrCreate(['code' => 'academic_dept'], ['name' => 'Academic Department', 'active' => true]);
        $this->unit = OrganizationUnit::firstOrCreate(
            ['code' => 'COI-CS-TEST'],
            [
                'name' => 'Department of Computer Science Test',
                'unit_type_id' => $unitType->id,
                'active' => true,
            ]
        );

        $this->admin = User::firstOrCreate(
            ['email' => 'admin.hardening.test@wu.edu.et'],
            [
                'name' => 'System Administrator Test',
                'password' => bcrypt('StrongPass123!'),
                'status' => 'active',
                'authz_version' => 1,
            ]
        );

        $this->officer = User::firstOrCreate(
            ['email' => 'officer.hardening.test@wu.edu.et'],
            [
                'name' => 'Correspondence Officer Test',
                'password' => bcrypt('StrongPass123!'),
                'status' => 'active',
                'authz_version' => 1,
            ]
        );

        $this->archivist = User::firstOrCreate(
            ['email' => 'archivist.hardening.test@wu.edu.et'],
            [
                'name' => 'Records Officer Test',
                'password' => bcrypt('StrongPass123!'),
                'status' => 'active',
                'authz_version' => 1,
            ]
        );

        $globalScope = \App\Models\AuthorizationScope::firstOrCreate(['kind' => 'global']);
        $unitScope = \App\Models\AuthorizationScope::firstOrCreate(['kind' => 'unit', 'unit_id' => $this->unit->id]);

        // Super Admin Role
        $superRole = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Administrator', 'scope' => 'all', 'active' => true]);
        $sysConfigPerm = Permission::firstOrCreate(['code' => 'system.configure'], ['name' => 'System Configure', 'module' => 'system', 'active' => true]);
        $dispApprovePerm = Permission::firstOrCreate(['code' => 'archive.approve_disposal'], ['name' => 'Approve Disposal', 'module' => 'archive', 'active' => true]);
        $superRole->permissions()->syncWithoutDetaching([$sysConfigPerm->id, $dispApprovePerm->id]);
        $this->admin->roles()->sync([
            $superRole->id => [
                'scope_id' => $globalScope->id,
                'granted_by' => $this->admin->id,
                'granted_at' => now(),
            ],
        ]);

        // Officer Role
        $corrPerms = [
            'correspondence.view',
            'correspondence.register',
            'correspondence.dispatch',
            'correspondence.manage',
            'documents.create',
            'documents.view',
        ];
        $permIds = [];
        foreach ($corrPerms as $permCode) {
            $p = Permission::firstOrCreate(['code' => $permCode], ['name' => $permCode, 'module' => 'correspondence', 'active' => true]);
            $permIds[] = $p->id;
        }
        $officerRole = Role::firstOrCreate(['code' => 'corr_officer'], ['name' => 'Correspondence Officer', 'scope' => 'unit', 'active' => true]);
        $officerRole->permissions()->sync($permIds);
        $this->officer->roles()->sync([
            $officerRole->id => [
                'scope_id' => $unitScope->id,
                'granted_by' => $this->admin->id,
                'granted_at' => now(),
            ],
        ]);

        // Archivist Role
        $archivePerms = ['archive.view', 'archive.manage', 'archive.approve_disposal', 'documents.view'];
        $archPermIds = [];
        foreach ($archivePerms as $pCode) {
            $p = Permission::firstOrCreate(['code' => $pCode], ['name' => $pCode, 'module' => 'archive', 'active' => true]);
            $archPermIds[] = $p->id;
        }
        $archiveRole = Role::firstOrCreate(['code' => 'archivist_role'], ['name' => 'Archivist', 'scope' => 'all', 'active' => true]);
        $archiveRole->permissions()->sync($archPermIds);
        $this->archivist->roles()->sync([
            $archiveRole->id => [
                'scope_id' => $globalScope->id,
                'granted_by' => $this->admin->id,
                'granted_at' => now(),
            ],
        ]);

        $this->category = DocumentCategory::firstOrCreate(['code' => 'CORR-OFFICIAL'], ['name' => 'Official Correspondence', 'active' => true]);
        $this->outgoingType = CorrespondenceType::where('code', 'outgoing')->first()
            ?? CorrespondenceType::create(['code' => 'outgoing', 'name' => 'Outgoing Letter', 'active' => true]);
        $this->dispatchMethod = DispatchMethod::firstOrCreate(['code' => 'COURIER'], ['name' => 'Courier', 'active' => true]);
    }

    public function test_health_check_endpoint_returns_healthy_status_and_component_metrics(): void
    {
        // Public liveness probe: minimal footprint, no internal infrastructure details
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'healthy',
            'application' => [
                'name' => 'Wollo University DMS',
            ],
        ]);
        $response->assertJsonMissing(['services']);

        // Restricted operational readiness probe: unauthenticated must be rejected
        $unauthReadiness = $this->getJson('/api/v1/health/readiness');
        $unauthReadiness->assertStatus(401);

        // Restricted operational readiness probe: non-admin officer lacks system diagnostics capability
        $forbiddenReadiness = $this->actingAs($this->officer, 'sanctum')->getJson('/api/v1/health/readiness');
        $forbiddenReadiness->assertStatus(403);

        // Restricted operational readiness probe: authorized administrator receives diagnostics
        $adminReadiness = $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/health/readiness');
        $adminReadiness->assertStatus(200);
        $adminReadiness->assertJson([
            'status' => 'healthy',
            'diagnostics' => [
                'database' => ['status' => 'up'],
                'cache' => ['status' => 'up'],
                'storage' => ['status' => 'up'],
            ],
            'application' => [
                'name' => 'Wollo University DMS',
            ],
        ]);
    }

    public function test_file_security_service_blocks_dangerous_scripts_and_executables(): void
    {
        $service = app(FileSecurityService::class);

        $badFiles = [
            UploadedFile::fake()->create('malicious.php', 100, 'text/x-php'),
            UploadedFile::fake()->create('payload.exe', 100, 'application/x-msdownload'),
            UploadedFile::fake()->create('attack.sh', 100, 'application/x-sh'),
            UploadedFile::fake()->create('script.bat', 100, 'application/x-bat'),
            UploadedFile::fake()->create('script.js', 100, 'application/javascript'),
        ];

        foreach ($badFiles as $badFile) {
            try {
                $service->storeUploadedFile($badFile, 'test');
                $this->fail("Expected ValidationException for dangerous file: {$badFile->getClientOriginalName()}");
            } catch (ValidationException $e) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_file_security_service_accepts_legitimate_pdf_documents(): void
    {
        $service = app(FileSecurityService::class);

        // Real PDF magic bytes header (%PDF-1.4)
        $pdfContent = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";
        $validPdf = UploadedFile::fake()->createWithContent('official_letter.pdf', $pdfContent);

        $storedFile = $service->storeUploadedFile($validPdf, 'correspondence', $this->officer->id);

        $this->assertInstanceOf(FileObject::class, $storedFile);
        $this->assertEquals('official_letter.pdf', $storedFile->original_filename);
        $this->assertEquals('application/pdf', $storedFile->detected_mime);
        $this->assertNotNull($storedFile->sha256);
    }

    public function test_outgoing_correspondence_cannot_be_closed_without_recorded_proof_of_delivery(): void
    {
        $confidentiality = \App\Models\ConfidentialityLevel::firstOrCreate(
            ['code' => 'PUBLIC'],
            ['name' => 'Public Information', 'active' => true]
        );

        // 1. Create document and outgoing correspondence
        $doc = Document::create([
            'title' => 'Official Dispatched Notice',
            'category_id' => $this->category->id,
            'owner_unit_id' => $this->unit->id,
            'created_by' => $this->officer->id,
            'confidentiality_id' => $confidentiality->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'reference_number' => 'WU/COI/2026/001',
        ]);

        $ver = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $this->officer->id,
            'created_at' => now(),
        ]);

        $priority = Priority::firstOrCreate(['code' => 'NORMAL'], ['name' => 'Normal', 'active' => true]);

        $corr = Correspondence::create([
            'document_id' => $doc->id,
            'type_id' => $this->outgoingType->id,
            'priority_id' => $priority->id,
            'purpose_code' => 'for_action',
            'correspondence_date' => now()->toDateString(),
            'first_receiving_unit_id' => $this->unit->id,
            'received_at' => now(),
            'registered_by' => $this->officer->id,
            'registered_at' => now(),
        ]);

        $party = CommunicationParty::create(['unit_id' => $this->unit->id]);
        $participant = CorrespondenceParticipant::create([
            'correspondence_id' => $corr->id,
            'party_id' => $party->id,
            'participation_type' => 'to',
        ]);

        $dispatch = CorrespondenceDispatch::create([
            'participant_id' => $participant->id,
            'document_version_id' => $ver->id,
            'method_id' => $this->dispatchMethod->id,
            'dispatched_by' => $this->officer->id,
            'dispatched_at' => now(),
            'tracking_reference' => 'TRK-987654',
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'evidence_file_id' => null, // No delivery proof recorded yet
        ]);

        // 2. Attempt closure before recording delivery evidence -> strictly blocked (422)
        $closeResponse = $this->actingAs($this->officer)
            ->postJson("/api/v1/correspondences/{$corr->id}/close", [
                'reason' => 'Closing without delivery evidence',
            ]);

        $closeResponse->assertStatus(422);
        $closeResponse->assertJsonFragment([
            'success' => false,
        ]);
        $this->assertStringContainsString('lacks recorded proof of delivery', $closeResponse->json('message'));

        // 3. Record delivery evidence with valid PDF proof
        $pdfContent = "%PDF-1.4\nDelivery receipt confirmed\n%%EOF";
        $proofFile = UploadedFile::fake()->createWithContent('signed_delivery_slip.pdf', $pdfContent);

        $evidenceResponse = $this->actingAs($this->officer)
            ->postJson("/api/v1/correspondences/{$corr->id}/dispatches/{$dispatch->id}/delivery-evidence", [
                'file' => $proofFile,
                'delivery_note' => 'Received and signed by Registry Desk',
            ]);

        $evidenceResponse->assertStatus(200);
        $evidenceResponse->assertJson(['success' => true]);

        // Verify dispatch now has evidence file linked
        $dispatch->refresh();
        $this->assertNotNull($dispatch->evidence_file_id);

        // 4. Close correspondence now succeeds
        $secondCloseResponse = $this->actingAs($this->officer)
            ->postJson("/api/v1/correspondences/{$corr->id}/close", [
                'reason' => 'Closed following delivery receipt confirmation',
            ]);

        $secondCloseResponse->assertStatus(200);
        $secondCloseResponse->assertJson(['success' => true]);
    }

    public function test_archival_disposition_strictly_enforces_anti_self_approval_separation_of_duties(): void
    {
        $confidentiality = \App\Models\ConfidentialityLevel::firstOrCreate(
            ['code' => 'CONFIDENTIAL'],
            ['name' => 'Confidential Records', 'active' => true]
        );

        // 1. Create archived document
        $doc = Document::create([
            'title' => 'Archived Financial Audit 2020',
            'category_id' => $this->category->id,
            'owner_unit_id' => $this->unit->id,
            'created_by' => $this->archivist->id,
            'confidentiality_id' => $confidentiality->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'reference_number' => 'WU/ARCH/2020/001',
            'lifecycle' => 'retired',
        ]);

        $collection = ArchiveCollection::create(['code' => 'FIN', 'name' => 'Financial Records', 'active' => true]);
        $policy = RetentionPolicy::create(['code' => 'RET-FIN-5Y', 'name' => '5 Years', 'active' => true]);
        $policyVer = RetentionPolicyVersion::create([
            'policy_id' => $policy->id,
            'version_number' => 1,
            'trigger_code' => 'creation_date',
            'duration_months' => 60,
            'disposition_action' => 'destroy',
            'effective_from' => now()->toDateString(),
        ]);

        $archiveEntry = ArchiveEntry::create([
            'document_id' => $doc->id,
            'collection_id' => $collection->id,
            'retention_policy_version_id' => $policyVer->id,
            'archived_by' => $this->archivist->id,
            'archived_at' => now()->subYears(6),
            'next_review_at' => now()->subDay(),
        ]);

        // 2. Archivist requests disposition
        $dispReq = DispositionRequest::create([
            'archive_entry_id' => $archiveEntry->id,
            'requested_by' => $this->archivist->id,
            'requested_action' => 'destroy_digital',
            'requested_at' => now(),
            'justification' => 'Statutory 5-year retention period expired',
        ]);

        // 3. Archivist attempts to approve their OWN request -> BLOCKED by anti-self-approval rule (422)
        $selfApprovalResponse = $this->actingAs($this->archivist)
            ->postJson("/api/v1/archive/disposition-requests/{$dispReq->id}/decide", [
                'decision' => 'approve',
                'reason' => 'Approving my own disposal request',
            ]);

        $selfApprovalResponse->assertStatus(422);
        $this->assertStringContainsString('The requester must not authorize their own disposition request', $selfApprovalResponse->json('message'));

        // 4. Even if user has system.configure, self-approval is strictly forbidden if they were the requester
        $dispReqAdmin = DispositionRequest::create([
            'archive_entry_id' => $archiveEntry->id,
            'requested_by' => $this->admin->id,
            'requested_action' => 'destroy_digital',
            'requested_at' => now(),
            'justification' => 'Admin requesting disposal',
        ]);

        $adminSelfApproval = $this->actingAs($this->admin)
            ->postJson("/api/v1/archive/disposition-requests/{$dispReqAdmin->id}/decide", [
                'decision' => 'approve',
                'reason' => 'Admin self-approving',
            ]);

        $adminSelfApproval->assertStatus(422);
        $this->assertStringContainsString('The requester must not authorize their own disposition request', $adminSelfApproval->json('message'));

        // 5. Independent second actor (Admin approving Archivist's request) succeeds
        $independentApproval = $this->actingAs($this->admin)
            ->postJson("/api/v1/archive/disposition-requests/{$dispReq->id}/decide", [
                'decision' => 'approve',
                'reason' => 'Independent audit clearance granted',
            ]);

        $independentApproval->assertStatus(200);
        $independentApproval->assertJson(['success' => true]);
    }

    public function test_backup_administration_and_dr_drill_api_endpoints(): void
    {
        // Unauthenticated access blocked
        $this->getJson('/api/v1/admin/backups')->assertStatus(401);

        // Officer without system.configure blocked (403)
        $this->actingAs($this->officer)->getJson('/api/v1/admin/backups')->assertStatus(403);

        // Super Admin with system.configure allowed (200)
        $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/backups');
        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'data']);

        // DR Drills history endpoint
        $drillsResponse = $this->actingAs($this->admin)->getJson('/api/v1/admin/dr-drills');
        $drillsResponse->assertStatus(200);
        $drillsResponse->assertJsonStructure(['success', 'data']);
    }
}
