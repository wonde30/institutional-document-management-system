<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ConfidentialityLevel;
use App\Models\DigitalSignature;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\FileObject;
use App\Models\OrganizationUnit;
use App\Models\SignatureRequest;
use App\Models\SignatureVerification;
use App\Models\SigningIdentity;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class DigitalSignaturePkiTest extends TestCase
{
    private User $admin;
    private User $dean;
    private User $unauthorizedUser;
    private OrganizationUnit $unit;
    private DocumentVersion $docVersion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $this->unit = OrganizationUnit::first();
        $this->admin = User::where('email', 'admin@wu.edu.et')->first();

        $this->dean = User::create([
            'name' => 'Prof. Dean Executive',
            'email' => 'dean.exec.' . Str::random(6) . '@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($this->dean, 'signatures.request', 'global');

        $this->unauthorizedUser = User::create([
            'name' => 'Unauthorized Staff',
            'email' => 'unauth.' . Str::random(6) . '@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        $cat = DocumentCategory::first();
        $conf = ConfidentialityLevel::first();

        $doc = Document::create([
            'reference_number' => 'WU/SIG/TEST/' . Str::random(6),
            'title' => 'Executive Accreditation Resolution',
            'summary' => 'Senate endorsement resolution for institutional quality assurance',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $this->unit->id,
            'custodian_id' => $this->dean->id,
            'created_by' => $this->dean->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $this->docVersion = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $this->dean->id,
            'created_at' => now(),
            'change_note' => 'Final version submitted for digital signing',
        ]);
    }

    public function test_authorized_user_can_request_and_execute_genuine_digital_signature(): void
    {
        $this->authenticateAs($this->dean);

        // 1. Dispatch signature request
        $reqRes = $this->postJson('/api/v1/signatures', [
            'document_version_id' => $this->docVersion->id,
            'signer_id' => $this->dean->id,
            'purpose' => 'Presidential Endorsement of Institutional Policy',
            'expires_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
        ]);

        $reqRes->assertStatus(201)
            ->assertJsonPath('success', true);

        $reqId = $reqRes->json('data.id');
        $this->assertNotNull($reqId);

        // 2. Execute signature with authentic PKI engine
        $execRes = $this->postJson("/api/v1/signatures/{$reqId}/execute");

        $execRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.signature_format', 'PAdES-B-LT');

        $sigId = $execRes->json('data.id');
        $signedFileId = $execRes->json('data.signed_file_id');

        $this->assertNotNull($sigId);
        $this->assertNotNull($signedFileId);

        // 3. Verify digital signature and verification in database
        $sig = DigitalSignature::with('signedFile', 'signingIdentity')->findOrFail($sigId);
        $this->assertNotNull($sig->signedFile);
        $this->assertNotNull($sig->signingIdentity);

        // Assert file exists on disk
        $filePath = storage_path('app/' . $sig->signedFile->storage_key);
        $this->assertTrue(File::exists($filePath));

        // Assert cryptographic verification record
        $verification = SignatureVerification::where('signature_id', $sigId)->first();
        $this->assertNotNull($verification);
        $this->assertEquals('valid', $verification->outcome);
        $this->assertEquals('trusted_valid', $verification->certificate_status);
        $this->assertEquals('trusted_rfc3161', $verification->timestamp_status);
        $this->assertFalse($verification->details_json['tamper_detected']);
        $this->assertTrue($verification->details_json['byte_range_valid']);

        // Clean up
        @unlink($filePath);
    }

    public function test_unauthorized_user_cannot_execute_signature(): void
    {
        // Dean creates request assigned to Dean
        $req = SignatureRequest::create([
            'document_version_id' => $this->docVersion->id,
            'requested_by' => $this->dean->id,
            'signer_id' => $this->dean->id,
            'requested_at' => now(),
            'purpose' => 'Confidential Endorsement',
        ]);

        // Unauthorized user attempts execution
        $this->authenticateAs($this->unauthorizedUser);
        $res = $this->postJson("/api/v1/signatures/{$req->id}/execute");

        // Fails with 403 Forbidden
        $res->assertStatus(403);
    }

    public function test_independent_signature_verification_endpoint(): void
    {
        $this->authenticateAs($this->dean);

        // Request and execute
        $reqRes = $this->postJson('/api/v1/signatures', [
            'document_version_id' => $this->docVersion->id,
            'signer_id' => $this->dean->id,
            'purpose' => 'Verification Endpoint Test',
        ]);
        $reqId = $reqRes->json('data.id');

        $execRes = $this->postJson("/api/v1/signatures/{$reqId}/execute");
        $sigId = $execRes->json('data.id');

        // Call independent verification endpoint
        $verifyRes = $this->postJson("/api/v1/signatures/{$sigId}/verify");

        $verifyRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.outcome', 'valid')
            ->assertJsonPath('data.certificate_status', 'trusted_valid')
            ->assertJsonPath('data.details.tamper_detected', false);

        $sig = DigitalSignature::with('signedFile')->find($sigId);
        if ($sig?->signedFile?->storage_key) {
            @unlink(storage_path('app/' . $sig->signedFile->storage_key));
        }
    }

    public function test_independent_signature_verification_detects_disk_file_tampering(): void
    {
        $this->authenticateAs($this->dean);

        // Request and execute
        $req = SignatureRequest::create([
            'document_version_id' => $this->docVersion->id,
            'requested_by' => $this->dean->id,
            'signer_id' => $this->dean->id,
            'requested_at' => now(),
            'purpose' => 'Tamper Detection Test',
        ]);

        $execRes = $this->postJson("/api/v1/signatures/{$req->id}/execute");
        $sigId = $execRes->json('data.id');

        $sig = DigitalSignature::with('signedFile')->findOrFail($sigId);
        $fullPath = storage_path('app/' . $sig->signedFile->storage_key);

        // Mutate byte on disk
        $bytes = File::get($fullPath);
        $bytes[20] = chr(ord($bytes[20]) ^ 0x01);
        File::put($fullPath, $bytes);

        // Run independent verification
        $verifyRes = $this->postJson("/api/v1/signatures/{$sigId}/verify");

        $verifyRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.outcome', 'invalid')
            ->assertJsonPath('data.details.tamper_detected', true);

        @unlink($fullPath);
    }

    public function test_signing_identities_listing_and_certificate_metadata(): void
    {
        $this->authenticateAs($this->dean);

        // Trigger identity provisioning
        $custody = app(\App\Services\Pki\KeyCustodyService::class);
        $custody->getOrCreateIdentity($this->dean);

        $res = $this->getJson('/api/v1/signatures/identities');

        $res->assertStatus(200)
            ->assertJsonPath('success', true);

        $identities = $res->json('data');
        $this->assertNotEmpty($identities);

        $deanIdentity = collect($identities)->firstWhere('user_id', $this->dean->id);
        $this->assertNotNull($deanIdentity);
        $this->assertEquals('wollo_institutional_pki', $deanIdentity['provider_code']);
        $this->assertTrue($deanIdentity['active']);
        $this->assertNotNull($deanIdentity['parsed_certificate']);
        $this->assertStringContainsString('Wollo University', $deanIdentity['parsed_certificate']['organization']);
    }

    public function test_revoked_signer_identity_is_detected(): void
    {
        $this->authenticateAs($this->dean);

        $req = SignatureRequest::create([
            'document_version_id' => $this->docVersion->id,
            'requested_by' => $this->dean->id,
            'signer_id' => $this->dean->id,
            'requested_at' => now(),
            'purpose' => 'Revocation Check Test',
        ]);

        $execRes = $this->postJson("/api/v1/signatures/{$req->id}/execute");
        $sigId = $execRes->json('data.id');

        $sig = DigitalSignature::with('signedFile', 'signingIdentity')->findOrFail($sigId);

        // Revoke the signing identity
        $sig->signingIdentity->update(['revoked_at' => now()->subHour()]);

        // Run independent verification
        $verifyRes = $this->postJson("/api/v1/signatures/{$sigId}/verify");

        $verifyRes->assertStatus(200)
            ->assertJsonPath('data.outcome', 'invalid')
            ->assertJsonPath('data.certificate_status', 'revoked');

        if ($sig->signedFile?->storage_key) {
            @unlink(storage_path('app/' . $sig->signedFile->storage_key));
        }
    }
}
