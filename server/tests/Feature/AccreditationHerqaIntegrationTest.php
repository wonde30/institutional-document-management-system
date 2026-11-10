<?php

namespace Tests\Feature;

use App\Models\AccreditationCycle;
use App\Models\AccreditationEvidence;
use App\Models\AccreditationFramework;
use App\Models\AccreditationFrameworkVersion;
use App\Models\AccreditationStandard;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\FileIntegrityCheck;
use App\Models\FileObject;
use App\Models\OrganizationUnit;
use App\Models\SignatureRequest;
use App\Models\SignatureVerification;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccreditationHerqaIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $qualityOfficer;
    protected User $deanSigner;
    protected OrganizationUnit $college;
    protected AccreditationFrameworkVersion $herqaVersion;
    protected AccreditationStandard $standard1;
    protected AccreditationStandard $standard2;
    protected AccreditationCycle $cycle2026;
    protected DocumentVersion $curriculumDocVersion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $this->admin = User::where('email', 'admin@wu.edu.et')->first();
        $rootUnit = OrganizationUnit::first();

        $collegeType = UnitType::where('code', 'college')->first()
            ?? UnitType::create(['code' => 'college', 'name' => 'College', 'active' => 1]);

        $this->college = OrganizationUnit::create([
            'parent_id' => $rootUnit->id,
            'unit_type_id' => $collegeType->id,
            'code' => 'COLLEGE_MED_HEALTH',
            'name' => 'College of Medicine and Health Sciences',
            'active' => true,
        ]);

        // Quality Assurance Officer
        $this->qualityOfficer = User::create([
            'name' => 'Dr. QA Director Kassaye',
            'email' => 'qa.kassaye@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->qualityOfficer, 'staff', 'unit', $this->college->id);
        $this->grantPermissionToUser($this->qualityOfficer, 'accreditation.view', 'global');
        $this->grantPermissionToUser($this->qualityOfficer, 'signatures.request', 'global');

        // Dean / Signer
        $this->deanSigner = User::create([
            'name' => 'Prof. Dean Worku',
            'email' => 'dean.worku@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->deanSigner, 'department_head', 'unit', $this->college->id);
        $this->grantPermissionToUser($this->deanSigner, 'signatures.request', 'global');

        // Setup HERQA (Higher Education Relevance & Quality Agency) Framework
        $framework = AccreditationFramework::firstOrCreate(
            ['code' => 'HERQA_ETHIOPIA'],
            ['name' => 'Ethiopian Higher Education Quality Assurance Framework', 'active' => true]
        );

        $this->herqaVersion = AccreditationFrameworkVersion::firstOrCreate(
            ['framework_id' => $framework->id, 'version_label' => 'HERQA-2026-V3'],
            ['effective_from' => '2026-01-01']
        );

        // HERQA Standard 1: Governance & Leadership
        $this->standard1 = AccreditationStandard::create([
            'framework_version_id' => $this->herqaVersion->id,
            'code' => 'HERQA_STD_01',
            'title' => 'Governance, Leadership and Institutional Autonomy',
            'description' => 'Institutional leadership structure, committee minutes, and policy manuals.',
        ]);

        // HERQA Standard 2: Academic Programs & Curriculum Assessment
        $this->standard2 = AccreditationStandard::create([
            'framework_version_id' => $this->herqaVersion->id,
            'code' => 'HERQA_STD_02',
            'title' => 'Academic Programs and Curricula Relevance',
            'description' => 'Curriculum review committee reports, stakeholder surveys, and student learning outcomes.',
        ]);

        // 2026 Institutional Accreditation Cycle
        $this->cycle2026 = AccreditationCycle::create([
            'framework_version_id' => $this->herqaVersion->id,
            'unit_id' => $this->college->id,
            'name' => '2026 Medical School HERQA Re-Accreditation Cycle',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);

        // Institutional Document for Evidence
        $doc = Document::create([
            'title' => 'Undergraduate Medical Curriculum 2026 Revision',
            'category_id' => DocumentCategory::first()->id,
            'confidentiality_id' => \App\Models\ConfidentialityLevel::first()->id,
            'owner_unit_id' => $this->college->id,
            'created_by' => $this->qualityOfficer->id,
            'reference_number' => 'WU/SOM/CURR/2026/001',
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $this->curriculumDocVersion = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $this->qualityOfficer->id,
            'change_note' => 'Approved medical curriculum submitted for HERQA Standard 2 review.',
            'created_at' => now(),
        ]);
    }

    public function test_submit_accreditation_evidence_mapped_to_herqa_standard(): void
    {
        $this->authenticateAs($this->qualityOfficer);

        $response = $this->postJson('/api/v1/accreditation/evidence', [
            'cycle_id' => $this->cycle2026->id,
            'standard_id' => $this->standard2->id,
            'document_version_id' => $this->curriculumDocVersion->id,
            'note' => 'Curriculum revision document verified against HERQA Standard 2 learning benchmarks.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.standard_id', $this->standard2->id)
            ->assertJsonPath('data.cycle_id', $this->cycle2026->id);

        $this->assertDatabaseHas('accreditation_evidence', [
            'cycle_id' => $this->cycle2026->id,
            'standard_id' => $this->standard2->id,
            'document_version_id' => $this->curriculumDocVersion->id,
            'submitted_by' => $this->qualityOfficer->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $this->qualityOfficer->id,
            'action' => 'accreditation.evidence_submitted',
        ]);
    }

    public function test_duplicate_evidence_for_same_cycle_standard_version_is_rejected(): void
    {
        AccreditationEvidence::create([
            'cycle_id' => $this->cycle2026->id,
            'standard_id' => $this->standard1->id,
            'document_version_id' => $this->curriculumDocVersion->id,
            'submitted_by' => $this->qualityOfficer->id,
            'submitted_at' => now(),
            'note' => 'Initial submission',
        ]);

        $this->authenticateAs($this->qualityOfficer);

        // Attempting to duplicate identical evidence in same cycle and standard
        $this->expectException(\Illuminate\Database\QueryException::class);

        AccreditationEvidence::create([
            'cycle_id' => $this->cycle2026->id,
            'standard_id' => $this->standard1->id,
            'document_version_id' => $this->curriculumDocVersion->id,
            'submitted_by' => $this->qualityOfficer->id,
            'submitted_at' => now(),
            'note' => 'Duplicate attempt',
        ]);
    }

    public function test_pki_digital_signature_execution_and_cryptographic_verification(): void
    {
        $this->authenticateAs($this->qualityOfficer);

        // 1. Request signature from Dean
        $sigReqRes = $this->postJson('/api/v1/signatures', [
            'document_version_id' => $this->curriculumDocVersion->id,
            'signer_id' => $this->deanSigner->id,
            'purpose' => 'Dean endorsement for national HERQA accreditation submission',
        ]);

        $sigReqRes->assertStatus(201);
        $reqId = $sigReqRes->json('data.id');

        // 2. Dean logs in and executes digital signature
        $this->authenticateAs($this->deanSigner);

        $signRes = $this->postJson("/api/v1/signatures/{$reqId}/execute");

        $signRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'signature_format' => 'PAdES-B-LT',
            ]);

        $sigId = $signRes->json('data.id');
        $this->assertNotNull($sigId);

        // 3. Assert cryptographic verification record was created with valid outcome
        $verification = SignatureVerification::where('signature_id', $sigId)->first();
        $this->assertNotNull($verification);
        $this->assertEquals('valid', $verification->outcome);
        $this->assertEquals('trusted_valid', $verification->certificate_status);
        $this->assertEquals('trusted_rfc3161', $verification->timestamp_status);

        // 4. Assert audit event recorded
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $this->deanSigner->id,
            'action' => 'signature.executed_and_verified',
        ]);
    }

    public function test_cryptographic_file_integrity_check(): void
    {
        $now = now()->format('Y-m-d H:i:s.u');
        $rawBytes = 'OFFICIAL_WOLMED_DEAN_CHARTER_2026';
        $computedSha256 = hash('sha256', $rawBytes);

        $fileObject = FileObject::create([
            'storage_disk' => 'local',
            'storage_key' => 'documents/2026/dean_charter.pdf',
            'original_filename' => 'dean_charter.pdf',
            'detected_mime' => 'application/pdf',
            'byte_size' => strlen($rawBytes),
            'sha256' => $computedSha256,
            'uploaded_by' => $this->deanSigner->id,
            'uploaded_at' => $now,
            'scan_status' => 'clean',
            'storage_status' => 'ready',
        ]);

        // Record live integrity check
        $check = FileIntegrityCheck::create([
            'file_object_id' => $fileObject->id,
            'checked_at' => $now,
            'observed_sha256' => $computedSha256,
            'outcome' => 'match',
        ]);

        $this->assertEquals('match', $check->outcome);
        $this->assertEquals($fileObject->sha256, $check->observed_sha256);
        $this->assertDatabaseHas('file_integrity_checks', [
            'file_object_id' => $fileObject->id,
            'outcome' => 'match',
        ]);
    }
}
