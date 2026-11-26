<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AccreditationCycle;
use App\Models\AccreditationFramework;
use App\Models\AccreditationFrameworkVersion;
use App\Models\AccreditationStandard;
use App\Models\ArchiveCollection;
use App\Models\ArchiveEntry;
use App\Models\AuditEvent;
use App\Models\ConfidentialityLevel;
use App\Models\Correspondence;
use App\Models\CorrespondenceType;
use App\Models\DataExportOperation;
use App\Models\DispatchMethod;
use App\Models\Document;
use App\Models\DocumentAssignment;
use App\Models\DocumentCategory;
use App\Models\DocumentComment;
use App\Models\DocumentTemplate;
use App\Models\DocumentTextExtraction;
use App\Models\DocumentVersion;
use App\Models\FileObject;
use App\Models\NotificationEvent;
use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\Priority;
use App\Models\RetentionPolicy;
use App\Models\RetentionPolicyVersion;
use App\Models\Role;
use App\Models\TemplateVersion;
use App\Models\UnitType;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\VersionFile;
use App\Services\Backup\DocumentStorageBackupService;
use App\Services\Pki\PdfSignatureVerificationService;
use App\Services\Settings\SettingsService;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class MasterAuditRemediationRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $unitAStaff;
    protected User $unitBStaff;
    protected OrganizationUnit $unitA;
    protected OrganizationUnit $unitB;
    protected DocumentCategory $category;
    protected ConfidentialityLevel $internalConf;
    protected ConfidentialityLevel $restrictedConf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $unitType = UnitType::first();
        $this->unitA = OrganizationUnit::create([
            'code' => 'AUD_A_' . Str::random(4),
            'name' => 'Audit Unit Alpha',
            'unit_type_id' => $unitType->id,
            'active' => true,
        ]);
        $this->unitB = OrganizationUnit::create([
            'code' => 'AUD_B_' . Str::random(4),
            'name' => 'Audit Unit Beta',
            'unit_type_id' => $unitType->id,
            'active' => true,
        ]);

        $this->category = DocumentCategory::first();
        $this->internalConf = ConfidentialityLevel::where('code', 'internal')->first() ?? ConfidentialityLevel::first();
        $this->restrictedConf = ConfidentialityLevel::where('code', 'restricted')->first() ?? $this->internalConf;

        $this->admin = User::where('email', 'admin@wu.edu.et')->first();

        $this->unitAStaff = User::create([
            'name' => 'Alpha Officer',
            'email' => 'alpha.' . Str::random(5) . '@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->unitBStaff = User::create([
            'name' => 'Beta Officer',
            'email' => 'beta.' . Str::random(5) . '@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
    }

    public function test_document_show_with_comments_and_retention_policies_endpoints_do_not_crash(): void
    {
        $this->authenticateAs($this->admin);

        $doc = Document::create([
            'reference_number' => 'WU/REG/' . Str::random(6),
            'title' => 'Commented Policy',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->internalConf->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->admin->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        DocumentComment::create([
            'document_id' => $doc->id,
            'author_id' => $this->admin->id,
            'body' => 'Audit review comment',
            'created_at' => now(),
        ]);

        $this->getJson("/api/v1/documents/{$doc->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $policy = RetentionPolicy::first();
        $this->getJson('/api/v1/admin/retention-policies')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        if ($policy) {
            $this->getJson("/api/v1/admin/retention-policies/{$policy->id}")
                ->assertStatus(200)
                ->assertJsonPath('success', true);
        }

        // Verify fixed Eloquent relations execute cleanly without SQL errors
        $this->assertCount(0, $doc->releaseEvents()->get());
        $this->assertCount(0, $this->admin->correspondenceParticipations()->get());
    }

    public function test_correspondence_store_with_uploaded_file_persists_version_file_and_sha256(): void
    {
        Storage::fake('local');
        $this->grantPermissionToUser($this->unitAStaff, 'correspondence.register', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($this->unitAStaff, 'documents.download', 'unit', $this->unitA->id);
        $this->authenticateAs($this->unitAStaff);

        $type = CorrespondenceType::where('code', 'incoming')->first() ?? CorrespondenceType::first();
        $priority = Priority::first();

        $pdfPayload = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";
        $expectedSha = hash('sha256', $pdfPayload);
        $uploadedFile = UploadedFile::fake()->createWithContent('official_incoming.pdf', $pdfPayload);

        $res = $this->post('/api/v1/correspondences', [
            'title' => 'Incoming Inter-University Letter',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->internalConf->id,
            'owner_unit_id' => $this->unitA->id,
            'type_id' => $type->id,
            'priority_id' => $priority->id,
            'purpose_code' => 'general_correspondence',
            'correspondence_date' => now()->toDateString(),
            'file' => $uploadedFile,
        ], ['Accept' => 'application/json']);

        $res->assertStatus(201)->assertJsonPath('success', true);
        $docId = (int) $res->json('data.document_id');
        $version = DocumentVersion::where('document_id', $docId)->firstOrFail();
        $vf = VersionFile::with('fileObject')->where('document_version_id', $version->id)->firstOrFail();

        $this->assertSame($expectedSha, $vf->fileObject->sha256);
        Storage::disk('local')->assertExists($vf->fileObject->storage_key);

        // Verify download returns exact SHA-256 bytes and security headers
        $dl = $this->get("/api/v1/files/{$vf->file_object_id}/download");
        $dl->assertStatus(200);
        $dl->assertHeader('X-Content-Type-Options', 'nosniff');
        $dl->assertHeader('X-Checksum-Sha256', $expectedSha);
        $this->assertStringContainsString('no-store', (string) $dl->headers->get('Cache-Control'));
        $this->assertSame('sandbox', $dl->headers->get('Content-Security-Policy'));
        $this->assertSame($expectedSha, hash_file('sha256', $dl->getFile()->getPathname()));
    }

    public function test_digitize_rejects_malicious_file_and_download_blocks_tombstoned_and_tampered_files(): void
    {
        Storage::fake('local');
        $this->grantPermissionToUser($this->unitAStaff, 'documents.create', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($this->unitAStaff, 'documents.version', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($this->unitAStaff, 'documents.download', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($this->unitAStaff, 'documents.view', 'unit', $this->unitA->id);
        $this->authenticateAs($this->unitAStaff);

        $doc = Document::create([
            'reference_number' => 'WU/DIG/' . Str::random(6),
            'title' => 'Digitizable Record',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->internalConf->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->unitAStaff->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        // 1. Reject PHP upload via digitize
        $badUpload = UploadedFile::fake()->createWithContent('shell.php', '<?php echo "pwn"; ?>');
        $this->post("/api/v1/documents/{$doc->id}/digitize", ['file' => $badUpload], ['Accept' => 'application/json'])
            ->assertStatus(422);

        // 2. Reject HTML disguised as .txt
        $htmlAsTxt = UploadedFile::fake()->createWithContent('evil.txt', '<html><script>alert(1)</script></html>');
        $this->post("/api/v1/documents/{$doc->id}/digitize", ['file' => $htmlAsTxt], ['Accept' => 'application/json'])
            ->assertStatus(422);

        // 3. Accept valid PDF
        $pdfBytes = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";
        $goodPdf = UploadedFile::fake()->createWithContent('../../traversal.pdf', $pdfBytes);
        $digRes = $this->post("/api/v1/documents/{$doc->id}/digitize", ['file' => $goodPdf], ['Accept' => 'application/json']);
        $digRes->assertStatus(201);
        $fileId = (int) $digRes->json('data.version_files.0.file_object.id');
        $fileObj = FileObject::findOrFail($fileId);
        $this->assertStringNotContainsString('..', $fileObj->storage_key);
        $this->assertSame('traversal.pdf', $fileObj->original_filename);

        // 4. Tampered bytes on disk must fail integrity check at download
        Storage::disk('local')->put($fileObj->storage_key, $pdfBytes . 'TAMPERED');
        $this->get("/api/v1/files/{$fileId}/download")->assertStatus(500);

        // Restore valid bytes, then mark tombstoned -> must return 404
        Storage::disk('local')->put($fileObj->storage_key, $pdfBytes);
        $doc->update(['lifecycle' => 'tombstoned']);
        $this->get("/api/v1/files/{$fileId}/download")->assertStatus(404);
    }

    public function test_share_after_expired_grant_and_physical_original_retention_check(): void
    {
        $this->grantPermissionToUser($this->unitAStaff, 'documents.share', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($this->unitAStaff, 'documents.view', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($this->unitAStaff, 'documents.create', 'unit', $this->unitA->id);
        $this->authenticateAs($this->unitAStaff);

        $doc = Document::create([
            'reference_number' => 'WU/SHR/' . Str::random(6),
            'title' => 'Shareable Doc',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->internalConf->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->unitAStaff->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $permViewId = Permission::where('code', 'documents.view')->value('id');
        DB::table('document_grants')->insert([
            'document_id' => $doc->id,
            'permission_id' => $permViewId,
            'user_id' => $this->unitBStaff->id,
            'granted_by' => $this->unitAStaff->id,
            'granted_at' => now()->subDays(10)->format('Y-m-d H:i:s.u'),
            'expires_at' => now()->subDay()->format('Y-m-d H:i:s.u'),
        ]);

        // Re-sharing after expiry with ISO-8601 date must succeed with 201
        $this->postJson("/api/v1/documents/{$doc->id}/share", [
            'target_user_id' => $this->unitBStaff->id,
            'permission_code' => 'documents.view',
            'expires_at' => now()->addDays(5)->toISOString(),
        ])->assertStatus(201);

        // Physical original valid enum values matching CHECK constraint
        $this->postJson("/api/v1/documents/{$doc->id}/physical-originals", [
            'physical_reference' => 'BOX-2026-01',
            'retention_requirement' => 'not_required',
        ])->assertStatus(201);
    }

    public function test_rbac_privilege_escalation_and_super_admin_takeover_are_blocked(): void
    {
        // Unit A user has roles.create, roles.update, roles.assign, permissions.assign, users.update, users.view
        // but NOT system.configure or users.deactivate
        foreach (['roles.create', 'roles.update', 'roles.assign', 'permissions.assign', 'users.update', 'users.view'] as $perm) {
            $this->grantPermissionToUser($this->unitAStaff, $perm, 'unit', $this->unitA->id);
        }
        $this->authenticateAs($this->unitAStaff);

        $sysConfigPermId = Permission::where('code', 'system.configure')->value('id');
        $docViewPermId = Permission::where('code', 'documents.view')->value('id');

        // 1. Cannot create role containing system.configure
        $this->postJson('/api/v1/roles', [
            'code' => 'fake_super_' . Str::random(4),
            'name' => 'Fake Super',
            'permission_ids' => [$sysConfigPermId],
        ])->assertStatus(403);

        // 2. Cannot sync system.configure onto a custom role
        $customRole = Role::create([
            'code' => 'custom_unit_' . Str::random(4),
            'name' => 'Custom Unit Role',
            'active' => true,
            'is_system' => false,
        ]);
        $this->postJson("/api/v1/roles/{$customRole->id}/permissions", [
            'permission_ids' => [$sysConfigPermId],
        ])->assertStatus(403);

        // 3. Cannot modify a system role's permissions
        $staffRole = Role::where('code', 'staff')->firstOrFail();
        $this->postJson("/api/v1/roles/{$staffRole->id}/permissions", [
            'permission_ids' => [$docViewPermId],
        ])->assertStatus(403);

        // 4. Cannot grant global role or global permission when only unit-scoped
        $this->postJson('/api/v1/user-roles', [
            'user_id' => $this->unitBStaff->id,
            'role_id' => $customRole->id,
            'scope_kind' => 'global',
        ])->assertStatus(403);

        $this->postJson('/api/v1/user-permissions', [
            'user_id' => $this->unitBStaff->id,
            'permission_id' => $docViewPermId,
            'scope_kind' => 'global',
        ])->assertStatus(403);

        // 5. Cannot overwrite super_admin user via PUT /users/{id}
        $this->putJson("/api/v1/users/{$this->admin->id}", [
            'password' => 'Hijacked123!',
        ])->assertStatus(403);

        // 6. Cannot change status via PUT /users/{id} without users.deactivate
        $this->putJson("/api/v1/users/{$this->unitBStaff->id}", [
            'status' => 'locked',
        ])->assertStatus(403);
    }

    public function test_health_readiness_and_legacy_transition_token_enforce_active_authz_version(): void
    {
        $this->authenticateAs($this->unitAStaff);
        $this->getJson('/api/v1/health/readiness')->assertStatus(403);

        $this->authenticateAs($this->admin);
        $this->getJson('/api/v1/health/readiness')->assertStatus(200);

        // LegacyTransitionToken with JSON-encoded session payload & mismatched authz_version must fail
        $token = Str::random(60);
        DB::table('sessions')->insert([
            'id' => $token,
            'user_id' => $this->unitAStaff->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'payload' => base64_encode(json_encode(['authz_version' => 999])),
            'last_activity' => time(),
        ]);

        $this->assertNull(\App\Models\LegacyTransitionToken::findToken($token));

        DB::table('sessions')->where('id', $token)->update([
            'payload' => base64_encode(json_encode(['authz_version' => $this->unitAStaff->authz_version])),
        ]);
        $this->assertNotNull(\App\Models\LegacyTransitionToken::findToken($token));
    }

    public function test_correspondence_dispatch_rejects_foreign_document_version_and_closed_lifecycle(): void
    {
        $this->grantPermissionToUser($this->unitAStaff, 'correspondence.dispatch', 'unit', $this->unitA->id);
        $this->authenticateAs($this->unitAStaff);

        $type = CorrespondenceType::where('code', 'outgoing')->first() ?? CorrespondenceType::first();
        $priority = Priority::first();
        $method = DispatchMethod::first();

        $doc1 = Document::create([
            'reference_number' => 'WU/D1/' . Str::random(6),
            'title' => 'Corr Doc 1',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->internalConf->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->unitAStaff->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);
        $ver1 = DocumentVersion::create([
            'document_id' => $doc1->id,
            'version_number' => 1,
            'created_by' => $this->unitAStaff->id,
            'created_at' => now(),
            'change_note' => 'v1',
        ]);
        $corr1 = Correspondence::create([
            'document_id' => $doc1->id,
            'type_id' => $type->id,
            'priority_id' => $priority->id,
            'purpose_code' => 'general_correspondence',
            'correspondence_date' => now()->toDateString(),
            'registered_by' => $this->unitAStaff->id,
            'registered_at' => now(),
        ]);

        $foreignDoc = Document::create([
            'reference_number' => 'WU/D2/' . Str::random(6),
            'title' => 'Foreign Doc',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->internalConf->id,
            'owner_unit_id' => $this->unitB->id,
            'created_by' => $this->unitBStaff->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);
        $foreignVer = DocumentVersion::create([
            'document_id' => $foreignDoc->id,
            'version_number' => 1,
            'created_by' => $this->unitBStaff->id,
            'created_at' => now(),
            'change_note' => 'foreign v1',
        ]);

        $this->postJson("/api/v1/correspondences/{$corr1->id}/dispatch", [
            'document_version_id' => $foreignVer->id,
            'method_id' => $method->id,
        ])->assertStatus(422);

        $doc1->update(['lifecycle' => 'retired']);
        $this->postJson("/api/v1/correspondences/{$corr1->id}/dispatch", [
            'document_version_id' => $ver1->id,
            'method_id' => $method->id,
        ])->assertStatus(422);
    }

    public function test_assignments_confinement_and_record_event_authorization(): void
    {
        $this->grantPermissionToUser($this->unitBStaff, 'documents.view', 'unit', $this->unitB->id);
        $this->grantPermissionToUser($this->unitBStaff, 'documents.create', 'unit', $this->unitB->id);

        $restrictedDocA = Document::create([
            'reference_number' => 'WU/CONF/' . Str::random(6),
            'title' => 'Secret Directive Unit A',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->restrictedConf->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->unitAStaff->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        DocumentAssignment::create([
            'document_id' => $restrictedDocA->id,
            'assigned_unit_id' => $this->unitA->id,
            'assigned_by' => $this->unitAStaff->id,
            'assigned_at' => now(),
            'instruction' => 'Confidential Unit A directive',
        ]);

        $docB = Document::create([
            'reference_number' => 'WU/UNITB/' . Str::random(6),
            'title' => 'Unit B Doc',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->internalConf->id,
            'owner_unit_id' => $this->unitB->id,
            'created_by' => $this->unitBStaff->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $assignB = DocumentAssignment::create([
            'document_id' => $docB->id,
            'assigned_unit_id' => $this->unitB->id,
            'assigned_by' => $this->unitAStaff->id,
            'assigned_at' => now(),
            'instruction' => 'Unit B Task',
        ]);

        $this->authenticateAs($this->unitBStaff);
        $listRes = $this->getJson('/api/v1/assignments');
        $listRes->assertStatus(200);
        $titles = collect($listRes->json('data'))->pluck('document.title')->all();
        $this->assertNotContains('Secret Directive Unit A', $titles);

        // Read-only documents.view user in Unit B cannot record 'completed' event
        $this->postJson("/api/v1/assignments/{$assignB->id}/events", [
            'action' => 'completed',
        ])->assertStatus(403);

        // Cannot link foreign restricted Doc A as response to Unit B assignment
        $this->postJson("/api/v1/assignments/{$assignB->id}/responses", [
            'response_document_id' => $restrictedDocA->id,
        ])->assertStatus(403);
    }

    public function test_pki_verification_rejects_untrusted_self_signed_certificate(): void
    {
        // Generate an untrusted self-signed keypair and embed it into a minimal PKCS#7 signed PDF
        app(\App\Services\Pki\CertificateService::class);
        $osslCfg = ['config' => storage_path('app/pki/openssl.cnf')];
        $privKey = openssl_pkey_new($osslCfg + ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => 'Attacker Self Signed'], $privKey, $osslCfg);
        $selfCert = openssl_csr_sign($csr, null, $privKey, 365, $osslCfg + ['digest_alg' => 'sha256']);
        openssl_x509_export($selfCert, $certPem);
        openssl_pkey_export($privKey, $keyPem, null, $osslCfg);

        $sourcePdf = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";
        $placeholderHex = str_repeat('0', 8192);
        $brPlaceholder = '/ByteRange [ 0000000000 0000000000 0000000000 0000000000 ]';
        $sigObj = "\n3 0 obj\n<<\n/Type /Sig\n/Filter /Adobe.PPKLite\n/SubFilter /adbe.pkcs7.detached\n{$brPlaceholder}\n/Contents <{$placeholderHex}>\n>>\nendobj\n";
        $fullPdf = $sourcePdf . $sigObj;
        $len1 = strpos($fullPdf, '/Contents <') + strlen('/Contents ');
        $offset2 = $len1 + 1 + 8192 + 1;
        $len2 = strlen($fullPdf) - $offset2;
        $pdfPrepared = str_replace($brPlaceholder, sprintf('/ByteRange [ %010d %010d %010d %010d ]', 0, $len1, $offset2, $len2), $fullPdf);

        $dataToSign = substr($pdfPrepared, 0, $len1) . substr($pdfPrepared, $offset2, $len2);
        $tmpIn = tempnam(sys_get_temp_dir(), 'att_in_');
        $tmpOut = tempnam(sys_get_temp_dir(), 'att_out_');
        $tmpCert = tempnam(sys_get_temp_dir(), 'att_cert_');
        file_put_contents($tmpIn, $dataToSign);
        file_put_contents($tmpCert, $certPem);
        $signed = openssl_pkcs7_sign($tmpIn, $tmpOut, $certPem, $keyPem, [], PKCS7_DETACHED | PKCS7_BINARY);
        $this->assertTrue($signed, 'OpenSSL PKCS7 sign failed: ' . openssl_error_string());
        $smime = file_get_contents($tmpOut);
        preg_match('/filename="smime\.p7s"[^\r\n]*\r?\n(?:[^\r\n]+\r?\n)*\r?\n([A-Za-z0-9+\/=\r\n]+)/', $smime, $m);
        $derHex = bin2hex(base64_decode(preg_replace('/[^A-Za-z0-9+\/=]/', '', $m[1])));
        $padded = str_pad($derHex, 8192, '0', STR_PAD_RIGHT);
        $signedPdf = substr($pdfPrepared, 0, $len1) . '<' . $padded . '>' . substr($pdfPrepared, $offset2);

        $tmpPdf = tempnam(sys_get_temp_dir(), 'att_pdf_') . '.pdf';
        file_put_contents($tmpPdf, $signedPdf);

        try {
            $verifier = app(PdfSignatureVerificationService::class);
            $result = $verifier->verifyPdf($tmpPdf);
            $this->assertSame('invalid', $result['outcome']);
            $this->assertSame('untrusted_ca', $result['certificate_status']);
        } finally {
            @unlink($tmpIn);
            @unlink($tmpOut);
            @unlink($tmpCert);
            @unlink($tmpPdf);
        }
    }

    public function test_ocr_verify_idor_and_processing_status_persistence_and_accreditation_checks(): void
    {
        $this->grantPermissionToUser($this->unitBStaff, 'documents.update', 'unit', $this->unitB->id);
        $this->grantPermissionToUser($this->unitBStaff, 'accreditation.submit_evidence', 'unit', $this->unitB->id);
        $this->grantPermissionToUser($this->unitBStaff, 'accreditation.review_evidence', 'unit', $this->unitB->id);
        $this->authenticateAs($this->unitBStaff);

        $docA = Document::create([
            'reference_number' => 'WU/OCRA/' . Str::random(6),
            'title' => 'Unit A OCR Doc',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->internalConf->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->unitAStaff->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);
        $verA = DocumentVersion::create([
            'document_id' => $docA->id,
            'version_number' => 1,
            'created_by' => $this->unitAStaff->id,
            'created_at' => now(),
            'change_note' => 'v1',
        ]);
        $fileObj = FileObject::create([
            'storage_disk' => 'local',
            'storage_key' => 'documents/' . Str::uuid() . '.pdf',
            'original_filename' => 'ocr-src.pdf',
            'detected_mime' => 'application/pdf',
            'byte_size' => 64,
            'sha256' => hash('sha256', 'ocr-src'),
            'uploaded_by' => $this->unitAStaff->id,
            'uploaded_at' => now(),
            'scan_status' => 'clean',
            'storage_status' => 'ready',
        ]);
        $extA = DocumentTextExtraction::create([
            'document_version_id' => $verA->id,
            'file_object_id' => $fileObj->id,
            'ocr_engine' => 'tesseract',
            'raw_text' => 'Original text',
            'processing_status' => 'failed',
            'error_details' => 'Missing binary',
        ]);
        $this->assertSame('failed', $extA->fresh()->processing_status);
        $this->assertSame('Missing binary', $extA->fresh()->error_details);

        $docB = Document::create([
            'reference_number' => 'WU/OCRB/' . Str::random(6),
            'title' => 'Unit B Doc',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->internalConf->id,
            'owner_unit_id' => $this->unitB->id,
            'created_by' => $this->unitBStaff->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);
        $verB = DocumentVersion::create([
            'document_id' => $docB->id,
            'version_number' => 1,
            'created_by' => $this->unitBStaff->id,
            'created_at' => now(),
            'change_note' => 'v1',
        ]);

        // Cross-document OCR verify IDOR must fail (404)
        $this->postJson("/api/v1/documents/{$docB->id}/versions/{$verB->id}/extractions/{$extA->id}/verify", [
            'verified_text' => 'Tampered text',
        ])->assertStatus(404);

        // Accreditation: cannot submit Doc A evidence into Unit B cycle, and cannot self-review
        $fw = AccreditationFramework::create(['code' => 'FW_' . Str::random(4), 'name' => 'HERQA', 'active' => true]);
        $fwVer = AccreditationFrameworkVersion::create(['framework_id' => $fw->id, 'version_label' => 'v1', 'published_at' => now()]);
        $std = AccreditationStandard::create(['framework_version_id' => $fwVer->id, 'code' => 'STD-1', 'title' => 'Standard 1']);
        $cycleB = AccreditationCycle::create(['framework_version_id' => $fwVer->id, 'unit_id' => $this->unitB->id, 'name' => 'Cycle B', 'starts_on' => now()->subDay(), 'ends_on' => now()->addDays(30)]);

        $this->postJson('/api/v1/accreditation/evidence', [
            'cycle_id' => $cycleB->id,
            'standard_id' => $std->id,
            'document_version_id' => $verA->id,
        ])->assertStatus(403);

        $evRes = $this->postJson('/api/v1/accreditation/evidence', [
            'cycle_id' => $cycleB->id,
            'standard_id' => $std->id,
            'document_version_id' => $verB->id,
        ])->assertStatus(201);
        $evId = (int) $evRes->json('data.id');

        $this->postJson("/api/v1/accreditation/evidence/{$evId}/reviews", [
            'decision' => 'accepted',
            'comment' => 'Self review attempt',
        ])->assertStatus(422);
    }

    public function test_archive_dispose_requires_approval_permission_and_export_idor_blocked_and_settings_return_key(): void
    {
        $this->grantPermissionToUser($this->unitAStaff, 'archive.request_disposal', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($this->unitAStaff, 'archive.view', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($this->unitAStaff, 'archive.manage_retention', 'unit', $this->unitA->id);
        $this->authenticateAs($this->unitAStaff);

        $doc = Document::create([
            'reference_number' => 'WU/ARC/' . Str::random(6),
            'title' => 'Archived Record',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->internalConf->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->unitAStaff->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'retired',
            'lock_version' => 0,
        ]);

        $collection = ArchiveCollection::first();
        $retVer = RetentionPolicyVersion::first();
        $initialReviewAt = now()->addYear()->format('Y-m-d H:i:s.u');
        $entry = ArchiveEntry::create([
            'document_id' => $doc->id,
            'collection_id' => $collection->id,
            'retention_policy_version_id' => $retVer->id,
            'archived_by' => $this->unitAStaff->id,
            'archived_at' => now(),
            'trigger_occurred_at' => now(),
            'next_review_at' => $initialReviewAt,
        ]);

        // Requester-only user cannot bypass multi-actor disposal via /dispose
        $this->postJson("/api/v1/archive/entries/{$entry->id}/dispose", [
            'requested_action' => 'destroy_digital',
            'justification' => 'Self-approved shortcut attempt',
        ])->assertStatus(403);

        // Retention review records true previous_next_review_at
        $this->postJson("/api/v1/archive/entries/{$entry->id}/review", [
            'review_action' => 'extend',
            'months_extended' => 6,
            'notes' => 'Extended retention review',
        ])->assertStatus(200);

        $audit = AuditEvent::where('action', 'archive.retention_reviewed')
            ->where('target_id', (string) $entry->id)
            ->latest('id')
            ->firstOrFail();
        $this->assertNotSame($audit->details_json['previous_next_review_at'], $audit->details_json['new_next_review_at']);

        // DataTransfer export IDOR blocked
        $this->grantPermissionToUser($this->unitBStaff, 'data.export', 'unit', $this->unitB->id);
        $exportOp = DataExportOperation::create([
            'dataset' => 'documents',
            'format' => 'csv',
            'scope_unit_id' => $this->unitA->id,
            'filters_json' => [],
            'record_count' => 1,
            'storage_path' => 'exports/fake.csv',
            'sha256' => hash('sha256', 'fake'),
            'status' => 'completed',
            'created_by' => $this->admin->id,
            'created_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        $this->authenticateAs($this->unitBStaff);
        $this->get("/api/v1/data/exports/{$exportOp->id}/download")->assertStatus(403);

        // SettingsService::set returns the updated key (not index 0) and preserves null
        $settings = app(SettingsService::class);
        $req = Request::create('/api/v1/settings', 'PUT');
        $req->setUserResolver(fn () => $this->admin);
        $res = $settings->set('institution.letter_fax', null, null, $req);
        $this->assertSame('institution.letter_fax', $res['setting']['key']);
        $this->assertNull($res['setting']['value']);

        // NotificationController masks restricted reference_number for uncleared users
        $restrictedDoc = Document::create([
            'reference_number' => 'WU/TOPSECRET/999',
            'title' => 'Top Secret Merger',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->restrictedConf->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->admin->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);
        $notifEv = NotificationEvent::create([
            'type_code' => 'workflow.assigned',
            'document_id' => $restrictedDoc->id,
            'actor_id' => $this->admin->id,
            'occurred_at' => now(),
        ]);
        UserNotification::create([
            'event_id' => $notifEv->id,
            'user_id' => $this->unitBStaff->id,
            'created_at' => now(),
        ]);
        $notifRes = $this->getJson('/api/v1/notifications')->assertStatus(200);
        $item = $notifRes->json('data.items.0');
        $this->assertSame('[Restricted Institutional Record]', $item['event']['document']['title']);
        $this->assertSame('[Restricted]', $item['event']['document']['reference_number']);
    }

    public function test_zip_slip_is_rejected_during_backup_storage_restore(): void
    {
        $tmpZip = tempnam(sys_get_temp_dir(), 'zipslip_') . '.zip';
        $targetDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wu_zip_target_' . Str::random(6);
        mkdir($targetDir, 0777, true);

        $zip = new ZipArchive();
        $zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('manifest.json', json_encode(['files' => []]));
        $zip->addFromString('../evil.txt', 'zipslip');
        $zip->close();

        try {
            $svc = app(DocumentStorageBackupService::class);
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Unsafe archive entry path detected');
            $svc->restore($tmpZip, $targetDir);
        } finally {
            @unlink($tmpZip);
            @rmdir($targetDir);
        }
    }
}
