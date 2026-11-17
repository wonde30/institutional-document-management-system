<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\AuthorizationScope;
use App\Models\CommunicationParty;
use App\Models\ConfidentialityLevel;
use App\Models\Correspondence;
use App\Models\CorrespondenceDispatch;
use App\Models\CorrespondenceParticipant;
use App\Models\CorrespondenceProcedure;
use App\Models\CorrespondenceType;
use App\Models\DispatchMethod;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\FileObject;
use App\Models\OfficeProcedureVersion;
use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\Priority;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStepDefinition;
use App\Models\WorkflowStepInstance;
use App\Models\WorkflowStepParticipant;
use App\Services\FileSecurityService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase5bProductionReleaseGateTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $officer;
    protected User $crossUnitOfficer;
    protected OrganizationUnit $unit;
    protected OrganizationUnit $otherUnit;
    protected DocumentCategory $category;
    protected CorrespondenceType $outgoingType;
    protected DispatchMethod $dispatchMethod;
    protected OfficeProcedureVersion $procVersion;
    protected ConfidentialityLevel $publicConfidentiality;
    protected ConfidentialityLevel $confidentialLevel;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $unitType = UnitType::firstOrCreate(['code' => 'academic_dept'], ['name' => 'Academic Department', 'active' => true]);
        $this->unit = OrganizationUnit::firstOrCreate(
            ['code' => 'CS-GATE-TEST'],
            [
                'name' => 'Department of CS Gate Test',
                'unit_type_id' => $unitType->id,
                'active' => true,
            ]
        );

        $this->otherUnit = OrganizationUnit::firstOrCreate(
            ['code' => 'LAW-GATE-TEST'],
            [
                'name' => 'School of Law Gate Test',
                'unit_type_id' => $unitType->id,
                'active' => true,
            ]
        );

        $this->admin = User::firstOrCreate(
            ['email' => 'admin.gate5b@wu.edu.et'],
            [
                'name' => 'System Administrator 5B',
                'password' => bcrypt('StrongPass123!'),
                'status' => 'active',
                'authz_version' => 1,
            ]
        );

        $this->officer = User::firstOrCreate(
            ['email' => 'officer.gate5b@wu.edu.et'],
            [
                'name' => 'Correspondence Officer 5B',
                'password' => bcrypt('StrongPass123!'),
                'status' => 'active',
                'authz_version' => 1,
            ]
        );

        $this->crossUnitOfficer = User::firstOrCreate(
            ['email' => 'otherofficer.gate5b@wu.edu.et'],
            [
                'name' => 'Other Unit Officer 5B',
                'password' => bcrypt('StrongPass123!'),
                'status' => 'active',
                'authz_version' => 1,
            ]
        );

        $globalScope = AuthorizationScope::firstOrCreate(['kind' => 'global']);
        $unitScope = AuthorizationScope::firstOrCreate(['kind' => 'unit', 'unit_id' => $this->unit->id]);
        $otherUnitScope = AuthorizationScope::firstOrCreate(['kind' => 'unit', 'unit_id' => $this->otherUnit->id]);

        // Super Admin Role
        $superRole = Role::firstOrCreate(['code' => 'super_admin_gate'], ['name' => 'Super Admin Gate', 'scope' => 'all', 'active' => true]);
        $sysConfigPerm = Permission::firstOrCreate(['code' => 'system.configure'], ['name' => 'System Configure', 'module' => 'system', 'active' => true]);
        $superRole->permissions()->syncWithoutDetaching([$sysConfigPerm->id]);
        $this->admin->roles()->sync([
            $superRole->id => [
                'scope_id' => $globalScope->id,
                'granted_by' => $this->admin->id,
                'granted_at' => now(),
            ],
        ]);

        // Officer Role for primary unit
        $corrPerms = [
            'correspondence.view',
            'correspondence.register',
            'correspondence.dispatch',
            'correspondence.manage',
            'documents.create',
            'documents.view',
            'documents.download',
        ];
        $permIds = [];
        foreach ($corrPerms as $permCode) {
            $p = Permission::firstOrCreate(['code' => $permCode], ['name' => $permCode, 'module' => 'correspondence', 'active' => true]);
            $permIds[] = $p->id;
        }
        $officerRole = Role::firstOrCreate(['code' => 'gate_officer_role'], ['name' => 'Gate Officer Role', 'scope' => 'unit', 'active' => true]);
        $officerRole->permissions()->sync($permIds);

        $this->officer->roles()->sync([
            $officerRole->id => [
                'scope_id' => $unitScope->id,
                'granted_by' => $this->admin->id,
                'granted_at' => now(),
            ],
        ]);

        $this->crossUnitOfficer->roles()->sync([
            $officerRole->id => [
                'scope_id' => $otherUnitScope->id,
                'granted_by' => $this->admin->id,
                'granted_at' => now(),
            ],
        ]);

        $this->category = DocumentCategory::firstOrCreate(['code' => 'CORR-GATE'], ['name' => 'Official Gate Correspondence', 'active' => true]);
        $this->outgoingType = CorrespondenceType::where('code', 'outgoing')->first()
            ?? CorrespondenceType::create(['code' => 'outgoing', 'name' => 'Outgoing Letter', 'active' => true]);
        $this->dispatchMethod = DispatchMethod::firstOrCreate(['code' => 'COURIER'], ['name' => 'Courier', 'active' => true]);

        $this->publicConfidentiality = ConfidentialityLevel::firstOrCreate(
            ['code' => 'PUBLIC'],
            ['name' => 'Public Information', 'active' => true]
        );
        $this->confidentialLevel = ConfidentialityLevel::firstOrCreate(
            ['code' => 'CONFIDENTIAL'],
            ['name' => 'Confidential Records', 'active' => true]
        );

        $this->procVersion = OfficeProcedureVersion::firstOrCreate(
            [
                'unit_id' => $this->unit->id,
                'correspondence_type_id' => $this->outgoingType->id,
                'purpose_code' => 'for_action',
                'version_number' => 1,
            ],
            [
                'receipt_mode' => 'direct',
                'registry_unit_id' => null,
                'issue_mode' => 'direct',
                'effective_from' => now()->subDay(),
            ]
        );
    }

    /**
     * SECTION 1: FileSecurityService Verification
     */
    public function test_file_security_service_blocks_malicious_and_executable_uploads(): void
    {
        $service = app(FileSecurityService::class);

        $malicious = [
            UploadedFile::fake()->create('exploit.php', 100, 'text/x-php'),
            UploadedFile::fake()->create('trojan.exe', 100, 'application/x-msdownload'),
            UploadedFile::fake()->create('backdoor.sh', 100, 'application/x-sh'),
            UploadedFile::fake()->create('script.bat', 100, 'application/x-bat'),
            UploadedFile::fake()->create('malicious.js', 100, 'application/javascript'),
        ];

        foreach ($malicious as $file) {
            $threw = false;
            try {
                $service->storeUploadedFile($file, 'documents/test', $this->officer->id);
            } catch (ValidationException $e) {
                $threw = true;
            }
            $this->assertTrue($threw, "Security vulnerability: Malicious file [{$file->getClientOriginalName()}] bypassed FileSecurityService.");
        }
    }

    public function test_file_security_service_stores_valid_pdf_with_sha256_and_private_storage(): void
    {
        $service = app(FileSecurityService::class);

        $validPdf = UploadedFile::fake()->createWithContent(
            'official_directive.pdf',
            "%PDF-1.4\n%WU-DMS Gate Verification Document\n%%EOF"
        );

        $fileObject = $service->storeUploadedFile($validPdf, 'correspondence/evidence', $this->officer->id);

        $this->assertInstanceOf(FileObject::class, $fileObject);
        $this->assertEquals('application/pdf', $fileObject->detected_mime);
        $this->assertNotNull($fileObject->sha256);
        $this->assertEquals(64, strlen($fileObject->sha256));
        $this->assertFalse(str_contains($fileObject->storage_key, '..'));
        Storage::disk($fileObject->storage_disk)->assertExists($fileObject->storage_key);
    }

    public function test_unauthorized_user_cannot_download_restricted_document_file(): void
    {
        $service = app(FileSecurityService::class);
        $validPdf = UploadedFile::fake()->createWithContent('confidential_memo.pdf', "%PDF-1.4\nSecret Content\n%%EOF");
        $storedFile = $service->storeUploadedFile($validPdf, 'documents/confidential', $this->officer->id);

        $doc = Document::create([
            'title' => 'Confidential Executive Memo',
            'category_id' => $this->category->id,
            'owner_unit_id' => $this->unit->id,
            'created_by' => $this->officer->id,
            'confidentiality_id' => $this->confidentialLevel->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'reference_number' => 'WU/CONF/2026/001',
        ]);

        $ver = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $this->officer->id,
            'created_at' => now(),
        ]);

        \App\Models\VersionFile::create([
            'document_version_id' => $ver->id,
            'file_object_id' => $storedFile->id,
            'purpose' => 'primary',
            'display_order' => 1,
        ]);

        // Unauthenticated request receives 401
        $unauth = $this->getJson("/api/v1/files/{$storedFile->id}/download");
        $unauth->assertStatus(401);

        // Cross-unit officer receives 403 Forbidden
        $crossUnit = $this->actingAs($this->crossUnitOfficer, 'sanctum')
            ->getJson("/api/v1/files/{$storedFile->id}/download");
        $crossUnit->assertStatus(403);

        // Authorized officer in owner unit receives 200
        $authorized = $this->actingAs($this->officer, 'sanctum')
            ->getJson("/api/v1/files/{$storedFile->id}/download");
        $authorized->assertStatus(200);
    }

    /**
     * SECTION 2: Delivery Evidence & Correspondence Formal Closure
     */
    public function test_outgoing_correspondence_requires_dispatch_and_delivery_proof_before_closure(): void
    {
        $service = app(FileSecurityService::class);

        $doc = Document::create([
            'title' => 'Official Partnership Protocol',
            'category_id' => $this->category->id,
            'owner_unit_id' => $this->unit->id,
            'created_by' => $this->officer->id,
            'confidentiality_id' => $this->publicConfidentiality->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'reference_number' => 'WU/PART/2026/001',
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

        // Attempting to close outgoing correspondence without dispatch must fail with 422
        $prematureClose = $this->actingAs($this->officer, 'sanctum')
            ->postJson("/api/v1/correspondences/{$corr->id}/close", [
                'reason' => 'Closing prematurely without dispatch',
            ]);
        $prematureClose->assertStatus(422);
        $this->assertStringContainsString('The document has not been dispatched yet', $prematureClose->json('message'));

        // Create participant and dispatch
        $party = CommunicationParty::firstOrCreate(['unit_id' => $this->otherUnit->id]);
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
            'tracking_reference' => 'TRK-GATE-8899',
            'idempotency_key' => 'IDEMP-GATE-001',
        ]);

        // Attempting to close while dispatch lacks delivery proof must fail with 422
        $undeliveredClose = $this->actingAs($this->officer, 'sanctum')
            ->postJson("/api/v1/correspondences/{$corr->id}/close", [
                'reason' => 'Closing without delivery proof',
            ]);
        $undeliveredClose->assertStatus(422);
        $this->assertStringContainsString('lacks recorded proof of delivery', $undeliveredClose->json('message'));

        // Upload delivery evidence
        $evidenceFile = UploadedFile::fake()->createWithContent(
            'signed_delivery_slip.pdf',
            "%PDF-1.4\nSigned Delivery Slip Acknowledgement\n%%EOF"
        );

        $evidenceResponse = $this->actingAs($this->officer, 'sanctum')
            ->postJson("/api/v1/correspondences/{$corr->id}/dispatches/{$dispatch->id}/delivery-evidence", [
                'file' => $evidenceFile,
                'delivery_note' => 'Hand-delivered and signed by recipient registrar',
            ]);
        $evidenceResponse->assertStatus(200);
        $evidenceResponse->assertJson(['success' => true]);

        // Verify dispatch now has evidence file linked
        $dispatch->refresh();
        $this->assertNotNull($dispatch->evidence_file_id);

        // Upload duplicate/replacement evidence: verify replacement audit trail
        $replacementFile = UploadedFile::fake()->createWithContent(
            'replacement_delivery_slip.pdf',
            "%PDF-1.4\nOfficial University Stamp and Seal Delivery Receipt\n%%EOF"
        );

        $replaceResponse = $this->actingAs($this->officer, 'sanctum')
            ->postJson("/api/v1/correspondences/{$corr->id}/dispatches/{$dispatch->id}/delivery-evidence", [
                'file' => $replacementFile,
                'delivery_note' => 'Countersigned official stamp replacement',
            ]);
        $replaceResponse->assertStatus(200);

        // Assert evidence replacement audit event was logged
        $replacementAudit = AuditEvent::where('action', 'correspondence.delivery_evidence_replaced')
            ->where('target_id', (string) $dispatch->id)
            ->first();
        $this->assertNotNull($replacementAudit, 'Evidence replacement must generate an immutable audit log.');

        // Close correspondence now succeeds
        $validClose = $this->actingAs($this->officer, 'sanctum')
            ->postJson("/api/v1/correspondences/{$corr->id}/close", [
                'reason' => 'Successfully delivered and proof verified',
            ]);
        $validClose->assertStatus(200);
        $validClose->assertJson(['success' => true]);

        // Assert document lifecycle is retired
        $doc->refresh();
        $this->assertEquals('retired', $doc->lifecycle);

        // Assert double-closure attempt is rejected with 422
        $doubleClose = $this->actingAs($this->officer, 'sanctum')
            ->postJson("/api/v1/correspondences/{$corr->id}/close", [
                'reason' => 'Attempting duplicate closure',
            ]);
        $doubleClose->assertStatus(422);

        // Assert evidence cannot be attached to a closed correspondence
        $lateEvidence = $this->actingAs($this->officer, 'sanctum')
            ->postJson("/api/v1/correspondences/{$corr->id}/dispatches/{$dispatch->id}/delivery-evidence", [
                'file' => $replacementFile,
            ]);
        $lateEvidence->assertStatus(422);
    }

    /**
     * SECTION 3: Public Liveness vs Restricted Operational Readiness
     */
    public function test_public_health_liveness_exposes_no_infrastructure_internals(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'healthy',
            'application' => [
                'name' => 'Wollo University DMS',
            ],
        ]);

        // Must NOT expose internal infrastructure details to the public
        $response->assertJsonMissing(['diagnostics']);
        $response->assertJsonMissing(['services']);
        $response->assertJsonMissing(['database']);
        $response->assertJsonMissing(['queue']);
        $response->assertJsonMissing(['scheduler']);
        $response->assertJsonMissing(['backup']);
    }

    public function test_readiness_endpoint_requires_administrative_authorization(): void
    {
        // Unauthenticated access must be rejected with 401
        $unauth = $this->getJson('/api/v1/health/readiness');
        $unauth->assertStatus(401);

        // Standard officer lacking system diagnostic capability must receive 403
        $forbidden = $this->actingAs($this->officer, 'sanctum')->getJson('/api/v1/health/readiness');
        $forbidden->assertStatus(403);

        // Authorized administrator receives full operational diagnostics
        $admin = $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/health/readiness');
        $admin->assertStatus(200);
        $admin->assertJson([
            'status' => 'healthy',
            'diagnostics' => [
                'database' => ['status' => 'up'],
                'cache' => ['status' => 'up'],
                'storage' => ['status' => 'up'],
                'queue' => ['status' => 'up'],
            ],
            'application' => [
                'name' => 'Wollo University DMS',
            ],
        ]);
    }

    /**
     * SECTION 4: Audit Event Immutability Defense
     */
    public function test_audit_events_are_strictly_immutable_and_cannot_be_updated_or_deleted(): void
    {
        $audit = AuditEvent::create([
            'actor_id' => $this->officer->id,
            'action' => 'correspondence.verified',
            'target_type' => 'correspondence',
            'target_id' => '999',
            'request_id' => 'req-gate-1234',
            'occurred_at' => now(),
            'details_json' => ['note' => 'Original audit entry'],
        ]);

        $this->assertNotNull($audit->id);

        // Attempting to update the audit event must throw RuntimeException
        $updateThrew = false;
        try {
            $audit->action = 'tampered.action';
            $audit->save();
        } catch (\RuntimeException $e) {
            $updateThrew = true;
            $this->assertStringContainsString('immutable', $e->getMessage());
        }
        $this->assertTrue($updateThrew, 'Security violation: Audit events must not be editable.');

        // Attempting to delete the audit event must throw RuntimeException
        $deleteThrew = false;
        try {
            $audit->delete();
        } catch (\RuntimeException $e) {
            $deleteThrew = true;
            $this->assertStringContainsString('permanent and cannot be deleted', $e->getMessage());
        }
        $this->assertTrue($deleteThrew, 'Security violation: Audit events must not be deletable.');
    }
}
