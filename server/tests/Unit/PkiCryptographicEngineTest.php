<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Pki\CertificateService;
use App\Services\Pki\KeyCustodyService;
use App\Services\Pki\PdfSignatureService;
use App\Services\Pki\PdfSignatureVerificationService;
use Tests\TestCase;

class PkiCryptographicEngineTest extends TestCase
{
    private CertificateService $certService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\BaselineReferenceVocabularySeeder::class);
        $this->certService = new CertificateService();
    }

    public function test_institutional_ca_generation_and_attributes(): void
    {
        $ca = $this->certService->getOrGenerateInstitutionalCa();

        $this->assertNotEmpty($ca['cert_pem']);
        $this->assertNotEmpty($ca['key_pem']);
        $this->assertEquals('ET', $ca['parsed']['country']);
        $this->assertEquals('Wollo University', $ca['parsed']['organization']);
        $this->assertTrue($ca['parsed']['is_self_signed']);
        $this->assertFalse($ca['parsed']['is_expired']);
    }

    public function test_user_signing_certificate_issuance_and_validation(): void
    {
        $userCert = $this->certService->generateUserSigningCertificate(
            999,
            'Dr. Test Signer',
            'test.signer@wu.edu.et',
            'Faculty of Technology'
        );

        $this->assertNotEmpty($userCert['certificate_pem']);
        $this->assertNotEmpty($userCert['private_key_pem']);
        $this->assertEquals('Dr. Test Signer', $userCert['parsed']['common_name']);
        $this->assertEquals('test.signer@wu.edu.et', $userCert['parsed']['email']);
        $this->assertFalse($userCert['parsed']['is_expired']);
        $this->assertFalse($userCert['parsed']['is_self_signed']);

        // Validate certificate
        $validation = $this->certService->validateCertificate($userCert['certificate_pem']);
        $this->assertTrue($validation['valid']);
        $this->assertEquals('test_valid_unaccredited', $validation['status']);
    }

    public function test_expired_certificate_detection(): void
    {
        // Issue cert with 30 days validity
        $cert = $this->certService->generateUserSigningCertificate(
            998,
            'Temporary Signer',
            'temp@wu.edu.et',
            'Testing Unit',
            30
        );

        // Advance system time past validity window
        \Illuminate\Support\Carbon::setTestNow(now()->addDays(60));

        $validation = $this->certService->validateCertificate($cert['certificate_pem']);
        $this->assertFalse($validation['valid']);
        $this->assertEquals('expired', $validation['status']);

        // Reset test time
        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_rfc3161_timestamp_service_offline_and_verification(): void
    {
        $tsaService = new \App\Services\Pki\Rfc3161TimestampService();
        $sampleDigest = hash('sha256', 'SAMPLE_PAYLOAD_FOR_TSA');

        $tsaRes = $tsaService->requestTimestamp($sampleDigest);
        $this->assertEquals('offline_test_tsa', $tsaRes['status']);
        $this->assertNotEmpty($tsaRes['token']);
        $this->assertFalse($tsaRes['is_trusted_government_tsa']);

        // Verify token
        $verifyRes = $tsaService->verifyTimestampToken($tsaRes['token'], $sampleDigest);
        $this->assertTrue($verifyRes['valid']);
        $this->assertEquals('offline_test_tsa', $verifyRes['outcome']);
    }

    public function test_revocation_service_detection(): void
    {
        $revocationService = new \App\Services\Pki\RevocationService();

        // Active identity
        $activeIdentity = new \App\Models\SigningIdentity([
            'user_id' => 1,
            'revoked_at' => null,
        ]);
        $resActive = $revocationService->checkRevocation($activeIdentity);
        $this->assertEquals('good', $resActive['status']);
        $this->assertFalse($resActive['revoked']);

        // Revoked identity
        $revokedIdentity = new \App\Models\SigningIdentity([
            'user_id' => 2,
            'revoked_at' => now()->subDay(),
        ]);
        $resRevoked = $revocationService->checkRevocation($revokedIdentity);
        $this->assertEquals('revoked', $resRevoked['status']);
        $this->assertTrue($resRevoked['revoked']);
    }

    public function test_tamper_detection_on_signed_pdf(): void
    {
        $custody = new KeyCustodyService($this->certService);
        $pdfSigner = new PdfSignatureService($custody, $this->certService);
        $verifier = new PdfSignatureVerificationService($this->certService);

        $user = \App\Models\User::first() ?? \App\Models\User::create([
            'name' => 'Dean Test Signer',
            'email' => 'dean.crypto.test@wu.edu.et',
            'password' => bcrypt('Secret123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        $now = now()->format('Y-m-d H:i:s.u');
        $file = \App\Models\FileObject::create([
            'storage_disk' => 'local',
            'storage_key' => 'documents/test_source_' . \Illuminate\Support\Str::random(8) . '.pdf',
            'original_filename' => 'test_source.pdf',
            'detected_mime' => 'application/pdf',
            'byte_size' => 1024,
            'sha256' => hash('sha256', 'SAMPLE_SOURCE_DOC'),
            'uploaded_by' => $user->id,
            'uploaded_at' => $now,
            'scan_status' => 'clean',
            'storage_status' => 'ready',
        ]);

        $unit = \App\Models\OrganizationUnit::first() ?? \App\Models\OrganizationUnit::create([
            'name' => 'Test Department',
            'code' => 'TEST_DEPT_' . \Illuminate\Support\Str::random(4),
            'unit_type_id' => \App\Models\UnitType::value('id') ?? 1,
            'active' => true,
        ]);
        $cat = \App\Models\DocumentCategory::first();
        $conf = \App\Models\ConfidentialityLevel::first();

        $doc = \App\Models\Document::create([
            'reference_number' => 'WU/DOC/TEST/' . \Illuminate\Support\Str::random(6),
            'title' => 'Unit Test Document',
            'summary' => 'Test Summary',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $unit->id,
            'custodian_id' => $user->id,
            'created_by' => $user->id,
            'document_date' => now()->toDateString(),
            'registered_at' => $now,
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $version = \App\Models\DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $user->id,
            'created_at' => $now,
            'change_note' => 'Initial',
        ]);

        $req = \App\Models\SignatureRequest::create([
            'document_version_id' => $version->id,
            'requested_by' => $user->id,
            'signer_id' => $user->id,
            'requested_at' => $now,
            'purpose' => 'Cryptographic Unit Testing',
        ]);

        $signResult = $pdfSigner->signPdf($user, $file, $req, 'Testing tamper proofing');
        $signedPdfPath = $signResult['signed_pdf_path'];

        // 1. Verify authentic PDF passes
        $verifyAuthentic = $verifier->verifyPdf($signedPdfPath);
        $this->assertEquals('valid', $verifyAuthentic['outcome']);
        $this->assertFalse($verifyAuthentic['details']['tamper_detected']);

        // 2. Tamper byte inside range 1
        $tamperedBytes = \Illuminate\Support\Facades\File::get($signedPdfPath);
        $tamperedBytes[15] = chr(ord($tamperedBytes[15]) ^ 0x01); // flip 1 bit
        $verifyTampered = $verifier->verifyPdf($tamperedBytes);

        $this->assertEquals('invalid', $verifyTampered['outcome']);
        $this->assertTrue($verifyTampered['details']['tamper_detected']);

        // 3. Post-signing modifications (append trailing unauthorized bytes)
        $modifiedBytes = \Illuminate\Support\Facades\File::get($signedPdfPath) . "\n% UNAUTHORIZED ALTERATION AFTER SIGNING";
        $verifyModified = $verifier->verifyPdf($modifiedBytes);

        $this->assertEquals('invalid', $verifyModified['outcome']);
        $this->assertTrue($verifyModified['details']['post_signing_modifications']);

        @unlink($signedPdfPath);
    }
}
