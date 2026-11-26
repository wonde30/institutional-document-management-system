<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ConfidentialityLevel;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\OrganizationUnit;
use App\Models\SignatureRequest;
use App\Models\UnitType;
use App\Models\User;
use App\Services\Pki\CertificateService;
use App\Services\Pki\KeyCustodyService;
use App\Services\Pki\PdfSignatureService;
use App\Services\Pki\PdfSignatureVerificationService;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class PkiKeyEncryptionAtRestTest extends TestCase
{
    private string $isolatedPkiDir;
    private string $isolatedBackupDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $suffix = Str::random(10);
        $this->isolatedPkiDir = storage_path("framework/testing/pki_isolated_{$suffix}");
        $this->isolatedBackupDir = storage_path("framework/testing/pki_backups_isolated_{$suffix}");

        File::ensureDirectoryExists($this->isolatedPkiDir, 0700, true);
        File::ensureDirectoryExists($this->isolatedBackupDir, 0700, true);

        config([
            'services.pki.base_path' => $this->isolatedPkiDir,
            'services.pki.backup_path' => $this->isolatedBackupDir,
        ]);
    }

    protected function tearDown(): void
    {
        config([
            'services.pki.base_path' => null,
            'services.pki.backup_path' => null,
        ]);

        if (File::isDirectory($this->isolatedPkiDir)) {
            File::deleteDirectory($this->isolatedPkiDir);
        }
        if (File::isDirectory($this->isolatedBackupDir)) {
            File::deleteDirectory($this->isolatedBackupDir);
        }

        parent::tearDown();
    }

    public function test_ca_private_key_is_stored_encrypted_not_plaintext(): void
    {
        $certService = app(CertificateService::class);
        $caKeyPath = $this->isolatedPkiDir . '/ca/institutional_ca.key';

        $ca = $certService->getOrGenerateInstitutionalCa();

        $this->assertNotEmpty($ca['cert_pem']);
        $this->assertNotEmpty($ca['key_pem']);
        $this->assertStringStartsWith('-----BEGIN', $ca['key_pem']);

        $this->assertFileExists($caKeyPath);
        $rawOnDisk = File::get($caKeyPath);
        $this->assertStringNotContainsString('-----BEGIN', $rawOnDisk);
        $this->assertStringStartsWith('eyJ', $rawOnDisk);

        $decrypted = Crypt::decryptString($rawOnDisk);
        $this->assertStringStartsWith('-----BEGIN', $decrypted);
    }

    public function test_user_private_key_is_stored_encrypted_not_plaintext(): void
    {
        $user = User::create([
            'name' => 'PKI Enc Test User',
            'email' => 'pkienc.' . Str::random(6) . '@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        $custody = app(KeyCustodyService::class);
        $custody->getOrCreateIdentity($user);

        $keyPath = $this->isolatedPkiDir . "/keys/user_{$user->id}_key.pem";
        $this->assertFileExists($keyPath);

        $rawOnDisk = File::get($keyPath);
        $this->assertStringNotContainsString('-----BEGIN', $rawOnDisk);
        $this->assertStringStartsWith('eyJ', $rawOnDisk);

        $decrypted = Crypt::decryptString($rawOnDisk);
        $this->assertStringStartsWith('-----BEGIN', $decrypted);
    }

    public function test_signing_works_after_key_is_stored_encrypted(): void
    {
        $user = User::create([
            'name' => 'PKI Sign Test User',
            'email' => 'pkisign.' . Str::random(6) . '@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        $custody = app(KeyCustodyService::class);
        $custody->getOrCreateIdentity($user);

        $data = 'Test signing payload: ' . Str::random(32);
        $signature = $custody->signData($user, $data);

        $this->assertNotEmpty($signature);
        $certPem = $custody->getUserCertificatePem($user->id);
        $pubKey = openssl_get_publickey($certPem);
        $this->assertSame(1, openssl_verify($data, $signature, $pubKey, OPENSSL_ALGO_SHA256));
    }

    public function test_runtime_operations_reject_plaintext_private_keys(): void
    {
        $user = User::create([
            'name' => 'PKI Plaintext Reject User',
            'email' => 'pkireject.' . Str::random(6) . '@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        $certService = app(CertificateService::class);
        $custody = app(KeyCustodyService::class);
        $custody->getOrCreateIdentity($user);

        $tempKey = openssl_pkey_new([
            'config' => $this->isolatedPkiDir . '/openssl.cnf',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($tempKey, $plainPem, null, ['config' => $this->isolatedPkiDir . '/openssl.cnf']);

        // 1. Direct decryptPrivateKeyPem() must reject plaintext
        try {
            $certService->decryptPrivateKeyPem($plainPem);
            $this->fail('Expected decryptPrivateKeyPem() to reject plaintext PEM at runtime.');
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString('-----BEGIN', $e->getMessage());
            $this->assertStringContainsString('rejected at runtime', $e->getMessage());
        }

        // 2. KeyCustodyService::signData() must reject plaintext user key on disk
        $userKeyPath = $this->isolatedPkiDir . "/keys/user_{$user->id}_key.pem";
        File::put($userKeyPath, $plainPem);

        try {
            $custody->signData($user, 'should-fail');
            $this->fail('Expected signData() to reject plaintext key at runtime.');
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString('-----BEGIN', $e->getMessage());
        }

        // 3. CertificateService::getOrGenerateInstitutionalCa() must reject plaintext CA key on disk
        $caKeyPath = $this->isolatedPkiDir . '/ca/institutional_ca.key';
        File::put($caKeyPath, $plainPem);

        try {
            $certService->getOrGenerateInstitutionalCa();
            $this->fail('Expected getOrGenerateInstitutionalCa() to reject plaintext CA key at runtime.');
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString('-----BEGIN', $e->getMessage());
        }
    }

    public function test_incorrect_app_key_and_tampered_ciphertext_fail_cleanly_without_secret_exposure(): void
    {
        $certService = app(CertificateService::class);
        $custody = app(KeyCustodyService::class);

        $user = User::create([
            'name' => 'PKI Wrong Key User',
            'email' => 'pkiwrong.' . Str::random(6) . '@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $custody->getOrCreateIdentity($user);

        $userKeyPath = $this->isolatedPkiDir . "/keys/user_{$user->id}_key.pem";
        $encryptedPayload = File::get($userKeyPath);

        // 1. Tampered ciphertext must fail cleanly without leaking ciphertext or key
        $tampered = substr($encryptedPayload, 0, -4) . 'AAAA';
        try {
            $certService->decryptPrivateKeyPem($tampered);
            $this->fail('Expected tampered ciphertext to fail decryption.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Unable to decrypt private key', $e->getMessage());
            $this->assertStringNotContainsString('-----BEGIN', $e->getMessage());
            $this->assertStringNotContainsString($tampered, $e->getMessage());
        }

        // 2. Wrong APP_KEY must fail decryption cleanly
        $originalEncrypter = Crypt::getFacadeRoot();
        try {
            $wrongEncrypter = new Encrypter(random_bytes(32), 'AES-256-CBC');
            Crypt::swap($wrongEncrypter);

            try {
                $certService->decryptPrivateKeyPem($encryptedPayload);
                $this->fail('Expected wrong APP_KEY to fail decryption.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('Unable to decrypt private key', $e->getMessage());
                $this->assertStringNotContainsString('-----BEGIN', $e->getMessage());
                $this->assertStringNotContainsString($encryptedPayload, $e->getMessage());
            }
        } finally {
            Crypt::swap($originalEncrypter);
        }

        // 3. Correct APP_KEY still decrypts cleanly
        $decrypted = $certService->decryptPrivateKeyPem($encryptedPayload);
        $this->assertStringStartsWith('-----BEGIN', $decrypted);
    }

    public function test_raw_file_content_is_not_valid_pem(): void
    {
        $user = User::create([
            'name' => 'PKI Raw File Test User',
            'email' => 'pkiraw.' . Str::random(6) . '@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        $custody = app(KeyCustodyService::class);
        $custody->getOrCreateIdentity($user);

        $keyPath = $this->isolatedPkiDir . "/keys/user_{$user->id}_key.pem";
        $rawOnDisk = File::get($keyPath);

        $this->assertFalse(@openssl_pkey_get_private($rawOnDisk));
        $this->assertStringNotContainsString('-----BEGIN RSA PRIVATE KEY-----', $rawOnDisk);
        $this->assertStringNotContainsString('-----BEGIN PRIVATE KEY-----', $rawOnDisk);
    }

    public function test_controlled_migration_dry_run_and_write_mode_preserve_signing_and_verification(): void
    {
        $certService = app(CertificateService::class);
        $custody = app(KeyCustodyService::class);

        $user = User::create([
            'name' => 'PKI Migration User',
            'email' => 'pkimig.' . Str::random(6) . '@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        // Generate valid CA + user cert + key encrypted at rest
        $identity = $custody->getOrCreateIdentity($user);
        $caKeyPath = $this->isolatedPkiDir . '/ca/institutional_ca.key';
        $userKeyPath = $this->isolatedPkiDir . "/keys/user_{$user->id}_key.pem";

        // Simulate legacy pre-migration plaintext keys on an isolated copy
        $plainCaKey = Crypt::decryptString(File::get($caKeyPath));
        $plainUserKey = Crypt::decryptString(File::get($userKeyPath));
        File::put($caKeyPath, $plainCaKey);
        File::put($userKeyPath, $plainUserKey);

        // 1. --dry-run must NOT modify files
        $this->artisan('pki:encrypt-keys-at-rest', ['--dry-run' => true])->assertSuccessful();
        $this->assertStringStartsWith('-----BEGIN', File::get($caKeyPath));
        $this->assertStringStartsWith('-----BEGIN', File::get($userKeyPath));
        $this->assertEmpty(File::directories($this->isolatedBackupDir));

        // 2. Real write mode must back up and encrypt every key
        $this->artisan('pki:encrypt-keys-at-rest')->assertSuccessful();

        $afterCa = File::get($caKeyPath);
        $afterUser = File::get($userKeyPath);
        $this->assertStringNotContainsString('-----BEGIN', $afterCa);
        $this->assertStringNotContainsString('-----BEGIN', $afterUser);
        $this->assertStringStartsWith('eyJ', $afterCa);
        $this->assertStringStartsWith('eyJ', $afterUser);
        $this->assertSame($plainCaKey, $certService->decryptPrivateKeyPem($afterCa));
        $this->assertSame($plainUserKey, $certService->decryptPrivateKeyPem($afterUser));

        // Verified backup created in isolated backup directory
        $backupDirs = File::directories($this->isolatedBackupDir);
        $this->assertCount(1, $backupDirs);
        $this->assertFileExists($backupDirs[0] . '/ca/institutional_ca.key');
        $this->assertFileExists($backupDirs[0] . "/keys/user_{$user->id}_key.pem");

        // 3. Second run must be idempotent
        $this->artisan('pki:encrypt-keys-at-rest')->assertSuccessful();
        $this->assertSame($afterCa, File::get($caKeyPath));
        $this->assertSame($afterUser, File::get($userKeyPath));

        // 4. Existing CA chain, PDF signing, and verification continue to work post-migration
        $unitType = UnitType::first() ?? UnitType::create(['code' => 'DEPT', 'name' => 'Department']);
        $unit = OrganizationUnit::first() ?? OrganizationUnit::create([
            'code' => 'PKI-UNIT',
            'name' => 'PKI Unit',
            'unit_type_id' => $unitType->id,
        ]);
        $cat = DocumentCategory::first();
        $conf = ConfidentialityLevel::first();

        $this->grantPermissionToUser($user, 'signatures.request', 'global');
        $this->authenticateAs($user);

        $doc = Document::create([
            'reference_number' => 'WU/PKI/MIG/' . Str::random(6),
            'title' => 'Post-Migration Signing Verification',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $unit->id,
            'created_by' => $user->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);
        $ver = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $user->id,
            'created_at' => now(),
            'change_note' => 'v1',
        ]);
        $req = SignatureRequest::create([
            'document_version_id' => $ver->id,
            'requested_by' => $user->id,
            'signer_id' => $user->id,
            'requested_at' => now(),
            'purpose' => 'Post-migration verification',
            'status' => 'pending',
        ]);

        $execRes = $this->postJson("/api/v1/signatures/{$req->id}/execute");
        $execRes->assertStatus(200)->assertJsonPath('success', true);

        $execBody = $execRes->getContent() ?: '';
        $this->assertStringNotContainsString('PRIVATE KEY', $execBody);
        $this->assertStringNotContainsString($afterUser, $execBody);

        $sigId = $execRes->json('data.id');
        $verifyRes = $this->postJson("/api/v1/signatures/{$sigId}/verify");
        $verifyRes->assertStatus(200)
            ->assertJsonPath('data.outcome', 'valid')
            ->assertJsonPath('data.certificate_status', 'trusted_valid');

        $identRes = $this->getJson('/api/v1/signatures/identities');
        $identRes->assertStatus(200);
        $identBody = $identRes->getContent() ?: '';
        $this->assertStringNotContainsString('PRIVATE KEY', $identBody);
        $this->assertStringNotContainsString($afterUser, $identBody);

        // Clean up generated signed PDF from local storage
        $signedPath = storage_path('app/' . $execRes->json('data.signed_file.storage_key'));
        if (File::exists($signedPath)) {
            @unlink($signedPath);
        }
        $this->assertNotNull($identity);
    }
}
