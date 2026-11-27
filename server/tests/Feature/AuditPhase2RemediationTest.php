<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AccreditationCycle;
use App\Models\AccreditationFrameworkVersion;
use App\Models\AccreditationStandard;
use App\Models\AuditEvent;
use App\Models\ConfidentialityLevel;
use App\Models\CorrespondenceType;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\FileObject;
use App\Models\OrganizationUnit;
use App\Models\Priority;
use App\Models\StampDefinition;
use App\Models\StampVersion;
use App\Models\UnitType;
use App\Models\User;
use App\Models\VersionFile;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditPhase2RemediationTest extends TestCase
{
    use RefreshDatabase;

    protected OrganizationUnit $unitA;

    protected OrganizationUnit $unitB;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $this->admin = User::where('email', 'admin@wu.edu.et')->firstOrFail();
        $this->unitA = OrganizationUnit::firstOrFail();
        $deptType = UnitType::where('code', 'department')->first()
            ?? UnitType::create(['code' => 'department', 'name' => 'Department', 'active' => 1]);

        $this->unitB = OrganizationUnit::create([
            'parent_id' => $this->unitA->id,
            'unit_type_id' => $deptType->id,
            'code' => 'PH2_B_'.Str::random(4),
            'name' => 'Phase 2 Isolation Unit B',
            'active' => true,
        ]);
    }

    public function test_accreditation_standards_and_evidence_enforce_cycle_unit_scope(): void
    {
        $fwVersion = AccreditationFrameworkVersion::firstOrFail();
        $cycleA = AccreditationCycle::create([
            'framework_version_id' => $fwVersion->id,
            'unit_id' => $this->unitA->id,
            'name' => 'Unit A QA Cycle 2026',
            'starts_on' => now()->subDays(10)->toDateString(),
            'ends_on' => now()->addMonths(6)->toDateString(),
        ]);
        $cycleB = AccreditationCycle::create([
            'framework_version_id' => $fwVersion->id,
            'unit_id' => $this->unitB->id,
            'name' => 'Unit B QA Cycle 2026',
            'starts_on' => now()->subDays(10)->toDateString(),
            'ends_on' => now()->addMonths(6)->toDateString(),
        ]);

        $unitAOfficer = User::create([
            'name' => 'Unit A QA Officer',
            'email' => 'qa.a.'.Str::random(5).'@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($unitAOfficer, 'accreditation.view', 'unit', $this->unitA->id);
        $this->authenticateAs($unitAOfficer);

        // Foreign unit cycle_id must be denied on standards & evidence
        $this->getJson("/api/v1/accreditation/standards?cycle_id={$cycleB->id}")->assertStatus(403);
        $this->getJson("/api/v1/accreditation/evidence?cycle_id={$cycleB->id}")->assertStatus(403);
        $this->getJson("/api/v1/accreditation/evidence?unit_id={$this->unitB->id}")->assertStatus(403);

        // Invalid cycle_id must return validation 422
        $this->getJson('/api/v1/accreditation/standards?cycle_id=999999')->assertStatus(422);

        // Own unit cycle_id and unscoped baseline remain accessible
        $this->getJson("/api/v1/accreditation/standards?cycle_id={$cycleA->id}")->assertStatus(200);
        $this->getJson('/api/v1/accreditation/standards')->assertStatus(200);

        // Global admin retains cross-unit access
        $this->authenticateAs($this->admin);
        $this->getJson("/api/v1/accreditation/standards?cycle_id={$cycleB->id}")->assertStatus(200);
    }

    public function test_stamp_apply_requires_authoritative_permission_and_allows_stamps_manage(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('stamps/ph2-seal.png', 'PNGDATA');
        Storage::disk('local')->put('documents/ph2-doc.pdf', 'PDFDATA');

        $imageFile = FileObject::create([
            'storage_disk' => 'local',
            'storage_key' => 'stamps/ph2-seal.png',
            'original_filename' => 'ph2-seal.png',
            'detected_mime' => 'image/png',
            'byte_size' => 7,
            'sha256' => hash('sha256', 'PNGDATA'),
            'uploaded_by' => $this->admin->id,
            'uploaded_at' => now()->format('Y-m-d H:i:s.u'),
            'scan_status' => 'clean',
            'storage_status' => 'ready',
        ]);

        $stamp = StampDefinition::create([
            'unit_id' => $this->unitA->id,
            'name' => 'Phase 2 Stamp',
            'active' => true,
        ]);
        StampVersion::create([
            'stamp_id' => $stamp->id,
            'version_number' => 1,
            'image_file_id' => $imageFile->id,
            'effective_from' => now()->subDay()->format('Y-m-d H:i:s.u'),
        ]);

        $cat = DocumentCategory::firstOrFail();
        $conf = ConfidentialityLevel::firstOrFail();
        $doc = Document::create([
            'reference_number' => 'WU/PH2/STP/'.Str::random(5),
            'title' => 'Stamp Target Doc',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->admin->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now()->format('Y-m-d H:i:s.u'),
            'lifecycle' => 'active',
        ]);
        $ver = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $this->admin->id,
            'created_at' => now()->format('Y-m-d H:i:s.u'),
        ]);
        $docFile = FileObject::create([
            'storage_disk' => 'local',
            'storage_key' => 'documents/ph2-doc.pdf',
            'original_filename' => 'ph2-doc.pdf',
            'detected_mime' => 'application/pdf',
            'byte_size' => 7,
            'sha256' => hash('sha256', 'PDFDATA'),
            'uploaded_by' => $this->admin->id,
            'uploaded_at' => now()->format('Y-m-d H:i:s.u'),
            'scan_status' => 'clean',
            'storage_status' => 'ready',
        ]);
        VersionFile::create([
            'document_version_id' => $ver->id,
            'file_object_id' => $docFile->id,
            'purpose' => 'primary',
            'display_order' => 1,
        ]);

        // Sign-only user without stamps permission or StampAuthorization row is denied
        $signerOnly = User::create([
            'name' => 'Signer Without Stamp',
            'email' => 'sigonly.'.Str::random(5).'@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($signerOnly, 'signatures.request', 'unit', $this->unitA->id);
        $this->authenticateAs($signerOnly);
        $this->postJson('/api/v1/stamps/apply', [
            'document_version_id' => $ver->id,
            'stamp_id' => $stamp->id,
            'reason' => 'Unauthorized seal attempt',
        ])->assertStatus(403);

        // User holding stamps.manage for Unit A is authorized
        $stampManager = User::create([
            'name' => 'Stamp Manager',
            'email' => 'stpmgr.'.Str::random(5).'@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($stampManager, 'stamps.manage', 'unit', $this->unitA->id);
        $this->authenticateAs($stampManager);
        $this->postJson('/api/v1/stamps/apply', [
            'document_version_id' => $ver->id,
            'stamp_id' => $stamp->id,
            'reason' => 'Approved seal placement',
        ])->assertStatus(201);
    }

    public function test_accreditation_evidence_rejects_mismatched_document_id_and_version_id(): void
    {
        $fwVersion = AccreditationFrameworkVersion::firstOrFail();
        $standard = AccreditationStandard::where('framework_version_id', $fwVersion->id)->firstOrFail();
        $cycle = AccreditationCycle::create([
            'framework_version_id' => $fwVersion->id,
            'unit_id' => $this->unitA->id,
            'name' => 'Evidence Mapping Cycle '.Str::random(4),
            'starts_on' => now()->subDays(5)->toDateString(),
            'ends_on' => now()->addMonths(3)->toDateString(),
        ]);

        $cat = DocumentCategory::firstOrFail();
        $conf = ConfidentialityLevel::firstOrFail();
        $doc1 = Document::create([
            'reference_number' => 'WU/EV/1/'.Str::random(5),
            'title' => 'Evidence Doc 1',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->admin->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);
        $doc2 = Document::create([
            'reference_number' => 'WU/EV/2/'.Str::random(5),
            'title' => 'Evidence Doc 2',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->admin->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);
        $ver2 = DocumentVersion::create(['document_id' => $doc2->id, 'version_number' => 1, 'created_by' => $this->admin->id, 'created_at' => now()]);

        $this->authenticateAs($this->admin);

        // Mismatched doc1.id paired with doc2's version_id -> 422
        $this->postJson('/api/v1/accreditation/evidence', [
            'cycle_id' => $cycle->id,
            'standard_id' => $standard->id,
            'document_id' => $doc1->id,
            'document_version_id' => $ver2->id,
        ])->assertStatus(422);

        // Missing version_id -> 422
        $this->postJson('/api/v1/accreditation/evidence', [
            'cycle_id' => $cycle->id,
            'standard_id' => $standard->id,
        ])->assertStatus(422);

        // Valid matching doc2.id + ver2.id -> 201
        $this->postJson('/api/v1/accreditation/evidence', [
            'cycle_id' => $cycle->id,
            'standard_id' => $standard->id,
            'document_id' => $doc2->id,
            'document_version_id' => $ver2->id,
        ])->assertStatus(201);
    }

    public function test_correspondence_persists_external_parties_and_related_document_with_scope_checks(): void
    {
        $cat = DocumentCategory::firstOrFail();
        $confPublic = ConfidentialityLevel::where('code', 'internal')->first() ?? ConfidentialityLevel::firstOrFail();
        $confSecret = ConfidentialityLevel::where('code', 'confidential')->first() ?? $confPublic;
        $corrType = CorrespondenceType::firstOrFail();
        $priority = Priority::firstOrFail();

        $allowedDoc = Document::create([
            'reference_number' => 'WU/REL/OK/'.Str::random(5),
            'title' => 'Related Public Policy',
            'category_id' => $cat->id,
            'confidentiality_id' => $confPublic->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->admin->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $foreignDoc = Document::create([
            'reference_number' => 'WU/REL/FOR/'.Str::random(5),
            'title' => 'Foreign Restricted Doc',
            'category_id' => $cat->id,
            'confidentiality_id' => $confSecret->id,
            'owner_unit_id' => $this->unitB->id,
            'created_by' => $this->admin->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $unitAOfficer = User::create([
            'name' => 'Correspondence Clerk A',
            'email' => 'corr.a.'.Str::random(5).'@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($unitAOfficer, 'correspondence.register', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($unitAOfficer, 'correspondence.view', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($unitAOfficer, 'documents.view', 'unit', $this->unitA->id);
        $this->authenticateAs($unitAOfficer);

        // Forbidden related document in Unit B -> 403 and no correspondence persisted
        $deniedTitle = 'Blocked Foreign Related '.Str::random(5);
        $this->postJson('/api/v1/correspondences', [
            'title' => $deniedTitle,
            'category_id' => $cat->id,
            'confidentiality_id' => $confPublic->id,
            'owner_unit_id' => $this->unitA->id,
            'type_id' => $corrType->id,
            'priority_id' => $priority->id,
            'purpose_code' => 'official_communication',
            'correspondence_date' => now()->toDateString(),
            'first_receiving_unit_id' => $this->unitA->id,
            'related_document_id' => $foreignDoc->id,
        ])->assertStatus(403);
        $this->assertDatabaseMissing('documents', ['title' => $deniedTitle]);

        // Valid external sender, external recipient, and accessible related_document_id -> 201 & persisted
        $res = $this->postJson('/api/v1/correspondences', [
            'title' => 'MOE External Grant Circular '.Str::random(4),
            'category_id' => $cat->id,
            'confidentiality_id' => $confPublic->id,
            'owner_unit_id' => $this->unitA->id,
            'type_id' => $corrType->id,
            'priority_id' => $priority->id,
            'purpose_code' => 'official_communication',
            'external_reference' => 'MOE/EXT/'.Str::random(6),
            'correspondence_date' => now()->toDateString(),
            'first_receiving_unit_id' => $this->unitA->id,
            'sender_external_name' => 'Ministry of Education',
            'to_external_name' => 'HERQA Directorate',
            'related_document_id' => $allowedDoc->id,
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.sender_external_name', 'Ministry of Education')
            ->assertJsonPath('data.to_external_name', 'HERQA Directorate')
            ->assertJsonPath('data.related_document_id', $allowedDoc->id);

        $corrId = (int) $res->json('data.id');
        $this->getJson("/api/v1/correspondences/{$corrId}")
            ->assertStatus(200)
            ->assertJsonPath('data.sender_external_name', 'Ministry of Education')
            ->assertJsonPath('data.to_external_name', 'HERQA Directorate')
            ->assertJsonPath('data.related_document_id', $allowedDoc->id);
    }

    public function test_index_endpoints_enforce_pagination_and_reject_oversized_per_page(): void
    {
        $this->authenticateAs($this->admin);

        foreach ([
            '/api/v1/accreditation/cycles',
            '/api/v1/accreditation/evidence',
            '/api/v1/assignments',
            '/api/v1/research/outputs',
            '/api/v1/staff/records',
            '/api/v1/projects',
            '/api/v1/procedures',
        ] as $endpoint) {
            $this->getJson("{$endpoint}?per_page=1")
                ->assertStatus(200)
                ->assertJsonPath('success', true);

            $this->getJson("{$endpoint}?per_page=999")
                ->assertStatus(422);
        }
    }

    public function test_multi_table_admin_mutations_roll_back_atomically_when_audit_event_fails(): void
    {
        $this->authenticateAs($this->admin);

        AuditEvent::creating(function () {
            throw new \RuntimeException('Simulated audit failure for rollback verification');
        });

        try {
            $roleCode = 'rb_role_'.strtolower(Str::random(5));
            $this->postJson('/api/v1/roles', [
                'code' => $roleCode,
                'name' => 'Rollback Test Role',
            ])->assertStatus(500);
            $this->assertDatabaseMissing('roles', ['code' => $roleCode]);

            $catCode = 'rb_cat_'.strtolower(Str::random(5));
            $this->postJson('/api/v1/metadata/categories', [
                'code' => $catCode,
                'name' => 'Rollback Test Category',
            ])->assertStatus(500);
            $this->assertDatabaseMissing('document_categories', ['code' => $catCode]);

            $polCode = 'RBPOL_'.strtoupper(Str::random(4));
            $this->postJson('/api/v1/admin/numbering-policies', [
                'code' => $polCode,
                'name' => 'Rollback Policy',
                'prefix' => 'RB',
                'year_format' => 'calendar_year',
                'sequence_length' => 4,
                'reset_policy' => 'yearly',
            ])->assertStatus(500);
            $this->assertDatabaseMissing('numbering_policies', ['code' => $polCode]);
        } finally {
            AuditEvent::flushEventListeners();
        }
    }
}
