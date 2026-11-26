<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ConfidentialityLevel;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\FileObject;
use App\Models\OrganizationUnit;
use App\Models\SignatureRequest;
use App\Models\UnitType;
use App\Models\User;
use App\Services\Pki\CertificateService;
use App\Services\Pki\PdfSignatureService;
use App\Services\Pki\PdfSignatureVerificationService;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MultiSignaturePdfTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);
    }

    private function createSignerAndVersion(): array
    {
        $unitType = UnitType::first() ?? UnitType::create(['code' => 'DEPT', 'name' => 'Department']);
        $unit = OrganizationUnit::first() ?? OrganizationUnit::create([
            'code' => 'TEST-UNIT',
            'name' => 'Test Unit',
            'unit_type_id' => $unitType->id,
        ]);
        $cat = DocumentCategory::first();
        $conf = ConfidentialityLevel::first();

        $user1 = User::create([
            'name' => 'Signer One',
            'email' => 'signer1.' . Str::random(6) . '@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $user2 = User::create([
            'name' => 'Signer Two',
            'email' => 'signer2.' . Str::random(6) . '@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $user3 = User::create([
            'name' => 'Signer Three',
            'email' => 'signer3.' . Str::random(6) . '@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        /** @var PdfSignatureService $sigService */
        $sigService = app(PdfSignatureService::class);
        $basePdf = $sigService->generateStandardInstitutionalPdf(
            'Multi-Sig Acceptance Doc',
            'WU-MS-' . Str::random(6),
            'Multi-signature verification'
        );

        $key = 'documents/multisig_base_' . Str::random(10) . '.pdf';
        Storage::disk('local')->put($key, $basePdf);
        File::ensureDirectoryExists(dirname(storage_path('app/' . $key)));
        File::put(storage_path('app/' . $key), $basePdf);

        $file = FileObject::create([
            'storage_disk' => 'local',
            'storage_key' => $key,
            'original_filename' => 'base.pdf',
            'detected_mime' => 'application/pdf',
            'byte_size' => strlen($basePdf),
            'sha256' => hash('sha256', $basePdf),
            'uploaded_by' => $user1->id,
            'uploaded_at' => now()->format('Y-m-d H:i:s.u'),
            'scan_status' => 'clean',
            'storage_status' => 'ready',
        ]);

        $doc = Document::create([
            'reference_number' => 'WU-DOC-' . Str::random(8),
            'title' => 'Multi-Signature Test Document',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $unit->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now()->format('Y-m-d H:i:s.u'),
            'created_by' => $user1->id,
            'status' => 'active',
        ]);

        $version = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'primary_file_id' => $file->id,
            'created_by' => $user1->id,
        ]);

        $req1 = SignatureRequest::create([
            'document_version_id' => $version->id,
            'requested_by' => $user1->id,
            'signer_id' => $user1->id,
            'requested_at' => now()->format('Y-m-d H:i:s.u'),
            'purpose' => 'First endorsement',
            'status' => 'pending',
        ]);
        $req2 = SignatureRequest::create([
            'document_version_id' => $version->id,
            'requested_by' => $user1->id,
            'signer_id' => $user2->id,
            'requested_at' => now()->format('Y-m-d H:i:s.u'),
            'purpose' => 'Second endorsement',
            'status' => 'pending',
        ]);
        $req3 = SignatureRequest::create([
            'document_version_id' => $version->id,
            'requested_by' => $user1->id,
            'signer_id' => $user3->id,
            'requested_at' => now()->format('Y-m-d H:i:s.u'),
            'purpose' => 'Third endorsement',
            'status' => 'pending',
        ]);

        return [$user1, $user2, $user3, $file, $req1, $req2, $req3];
    }

    public function test_generated_pdf_has_accurate_xref_and_startxref(): void
    {
        /** @var PdfSignatureService $sigService */
        $sigService = app(PdfSignatureService::class);
        $pdf = $sigService->generateStandardInstitutionalPdf('Xref Check', 'WU-XREF-1', 'ISO 32000-1 verification');

        $this->assertStringStartsWith('%PDF-1.7', $pdf);
        $actualXrefPos = strpos($pdf, "\nxref\n");
        $this->assertNotFalse($actualXrefPos);
        $actualXrefPos += 1; // position of 'xref'

        $this->assertMatchesRegularExpression('/startxref\s+' . $actualXrefPos . '\s+%%EOF/', $pdf);

        // Every object offset listed in xref must point to "<id> 0 obj"
        preg_match_all('/^(\d{10}) 00000 n /m', $pdf, $entries);
        $this->assertNotEmpty($entries[1]);
        foreach ($entries[1] as $offsetStr) {
            $offset = (int) $offsetStr;
            $this->assertMatchesRegularExpression('/^\d+ 0 obj/', substr($pdf, $offset, 16));
        }
    }

    public function test_unsigned_pdf_reports_no_signature(): void
    {
        /** @var PdfSignatureService $sigService */
        $sigService = app(PdfSignatureService::class);
        /** @var PdfSignatureVerificationService $verifier */
        $verifier = app(PdfSignatureVerificationService::class);

        $pdf = $sigService->generateStandardInstitutionalPdf('Unsigned', 'WU-001', 'Test');
        $res = $verifier->verifyPdf($pdf);

        $this->assertSame('invalid', $res['outcome']);
        $this->assertSame('not_present', $res['certificate_status']);
    }

    public function test_triple_signed_pdf_preserves_all_previous_signatures_and_detects_tampering(): void
    {
        [$u1, $u2, $u3, $file0, $req1, $req2, $req3] = $this->createSignerAndVersion();

        /** @var PdfSignatureService $sigService */
        $sigService = app(PdfSignatureService::class);
        /** @var PdfSignatureVerificationService $verifier */
        $verifier = app(PdfSignatureVerificationService::class);

        // Signature 1
        $r1 = $sigService->signPdf($u1, $file0, $req1, 'First Approval');
        $v1 = $verifier->verifyPdf($r1['signed_pdf_path']);
        $this->assertSame('valid', $v1['outcome'], $v1['message'] ?? '');
        $this->assertSame(1, $v1['signature_count']);

        // Signature 2 on top of Signature 1's PDF
        $r2 = $sigService->signPdf($u2, $r1['signed_file'], $req2, 'Second Approval');
        $v2 = $verifier->verifyPdf($r2['signed_pdf_path']);
        $this->assertSame('valid', $v2['outcome'], $v2['message'] ?? '');
        $this->assertSame(2, $v2['signature_count']);
        $this->assertSame('valid', $v2['signatures'][0]['outcome']);
        $this->assertSame('valid', $v2['signatures'][1]['outcome']);

        // Signature 3 on top of Signature 2's PDF
        $r3 = $sigService->signPdf($u3, $r2['signed_file'], $req3, 'Third Approval');
        $v3 = $verifier->verifyPdf($r3['signed_pdf_path']);
        $this->assertSame('valid', $v3['outcome'], $v3['message'] ?? '');
        $this->assertSame(3, $v3['signature_count']);
        $this->assertSame('valid', $v3['signatures'][0]['outcome']);
        $this->assertSame('valid', $v3['signatures'][1]['outcome']);
        $this->assertSame('valid', $v3['signatures'][2]['outcome']);

        // Every incremental trailer in the triple-signed PDF must include /Root and chained /Prev
        $signedBytes = File::get($r3['signed_pdf_path']);
        $this->assertSame(4, preg_match_all('/\/Root\s+1\s+0\s+R/', $signedBytes));
        $this->assertSame(3, preg_match_all('/\/Prev\s+\d+/', $signedBytes));

        // Tamper original PDF content -> must invalidate all signatures
        $tamperedContent = str_replace('Multi-Sig Acceptance Doc', 'Tampered Acceptance Doc', $signedBytes);
        $vTampered = $verifier->verifyPdf($tamperedContent);
        $this->assertSame('invalid', $vTampered['outcome']);

        // Append unauthorized trailing bytes after 3rd signature -> must be detected
        $trailingTampered = $signedBytes . "\n% unauthorized trailing modification\n";
        $vTrailing = $verifier->verifyPdf($trailingTampered);
        $this->assertNotSame('valid', $vTrailing['outcome']);

        // Download integrity check for the 3x signed file
        $this->grantPermissionToUser($u3, 'documents.view', 'global');
        $this->grantPermissionToUser($u3, 'documents.download', 'global');
        $this->authenticateAs($u3);

        $downloadRes = $this->get("/api/v1/files/{$r3['signed_file']->id}/download");
        $downloadRes->assertStatus(200);
        $downloadedPath = $downloadRes->getFile()->getPathname();
        $this->assertSame($r3['signed_sha256'], hash_file('sha256', $downloadedPath));
        $vDownloaded = $verifier->verifyPdf($downloadedPath);
        $this->assertSame('valid', $vDownloaded['outcome']);
        $this->assertSame(3, $vDownloaded['signature_count']);
    }

    public function test_malformed_byterange_and_gap_smuggling_are_rejected(): void
    {
        /** @var PdfSignatureVerificationService $verifier */
        $verifier = app(PdfSignatureVerificationService::class);

        // 1. Non-zero start offset
        $badStart = "%PDF-1.7\n1 0 obj << /Type /Sig /ByteRange [ 10 20 50 30 ] /Contents <" . str_repeat('0', 128) . "> >> endobj\n%%EOF\n";
        $res1 = $verifier->verifyPdf($badStart);
        $this->assertSame('invalid', $res1['outcome']);
        $this->assertStringContainsString('Malformed /ByteRange', $res1['message']);

        // 2. Overlapping / inverted offset2 <= len1
        $badOverlap = "%PDF-1.7\n1 0 obj << /Type /Sig /ByteRange [ 0 50 40 30 ] /Contents <" . str_repeat('0', 128) . "> >> endobj\n%%EOF\n";
        $res2 = $verifier->verifyPdf($badOverlap);
        $this->assertSame('invalid', $res2['outcome']);

        // 3. Truncated / ByteRange exceeds file size
        $badTruncated = "%PDF-1.7\n1 0 obj << /Type /Sig /ByteRange [ 0 50 100 99999 ] /Contents <" . str_repeat('0', 128) . "> >> endobj\n%%EOF\n";
        $res3 = $verifier->verifyPdf($badTruncated);
        $this->assertSame('invalid', $res3['outcome']);
        $this->assertStringContainsString('truncated', strtolower($res3['message']));

        // 4. ByteRange gap smuggling (unsigned bytes inside len1..offset2 around <hex>)
        $prefix = "%PDF-1.7\n1 0 obj << /Type /Sig /ByteRange [ 0 0000000060 0000000210 0000000018 ] ";
        $gap = "EVIL_UNSIGNED<" . str_repeat('0', 135) . ">";
        $suffix = " >> endobj\n%%EOF\n";
        $smuggled = str_replace('0000000060', sprintf('%010d', strlen($prefix)), $prefix)
            . $gap . $suffix;
        $smuggled = str_replace('0000000210', sprintf('%010d', strlen($prefix) + strlen($gap)), $smuggled);
        $smuggled = str_replace('0000000018', sprintf('%010d', strlen($suffix)), $smuggled);
        $res4 = $verifier->verifyPdf($smuggled);
        $this->assertSame('invalid', $res4['outcome']);
    }

    public function test_missing_signer_certificate_in_pkcs7_is_rejected(): void
    {
        app(CertificateService::class);
        $verifier = app(PdfSignatureVerificationService::class);

        $cfg = [
            'config' => storage_path('app/pki/openssl.cnf'),
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        $pk = openssl_pkey_new($cfg);
        $csr = openssl_csr_new(['CN' => 'NoCert Signer'], $pk, $cfg);
        $cert = openssl_csr_sign($csr, null, $pk, 30, $cfg + ['digest_alg' => 'sha256']);
        openssl_x509_export($cert, $certPem);
        openssl_pkey_export($pk, $keyPem, null, $cfg);

        $basePdf = "%PDF-1.7\n1 0 obj << >> endobj\ntrailer << /Size 2 /Root 1 0 R >>\n%%EOF\n";
        $placeholderHex = str_repeat('0', 8192);
        $brPlaceholder = '/ByteRange [ 0000000000 0000000000 0000000000 0000000000 ]';
        $sigObj = "\n2 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached {$brPlaceholder} /Contents <{$placeholderHex}> >>\nendobj\n";
        $fullPdf = $basePdf . $sigObj;

        $len1 = strpos($fullPdf, '/Contents <') + strlen('/Contents ');
        $offset2 = $len1 + 1 + 8192 + 1;
        $len2 = strlen($fullPdf) - $offset2;
        $prepared = str_replace(
            $brPlaceholder,
            sprintf('/ByteRange [ %010d %010d %010d %010d ]', 0, $len1, $offset2, $len2),
            $fullPdf
        );

        $dataToSign = substr($prepared, 0, $len1) . substr($prepared, $offset2, $len2);
        $tmpIn = tempnam(sys_get_temp_dir(), 'nocert_in_');
        $tmpOut = tempnam(sys_get_temp_dir(), 'nocert_out_');
        file_put_contents($tmpIn, $dataToSign);

        // PKCS7_NOCERTS strips signer certificates from the CMS SignedData
        $ok = openssl_pkcs7_sign($tmpIn, $tmpOut, $certPem, $keyPem, [], PKCS7_DETACHED | PKCS7_BINARY | PKCS7_NOCERTS);
        $this->assertTrue($ok);
        $smime = file_get_contents($tmpOut);
        @unlink($tmpIn);
        @unlink($tmpOut);

        preg_match('/filename="smime\.p7s"[^\r\n]*\r?\n(?:[^\r\n]+\r?\n)*\r?\n([A-Za-z0-9+\/=\r\n]+)/', $smime, $m);
        $derHex = strtoupper(bin2hex(base64_decode(preg_replace('/[^A-Za-z0-9+\/=]/', '', $m[1]))));
        $paddedHex = str_pad($derHex, 8192, '0', STR_PAD_RIGHT);
        $signedPdf = substr($prepared, 0, $len1) . '<' . $paddedHex . '>' . substr($prepared, $offset2);

        $res = $verifier->verifyPdf($signedPdf);
        $this->assertSame('invalid', $res['outcome']);
        $this->assertSame('missing', $res['certificate_status']);
    }

    public function test_malformed_pdf_fails_safely(): void
    {
        /** @var PdfSignatureVerificationService $verifier */
        $verifier = app(PdfSignatureVerificationService::class);
        $res = $verifier->verifyPdf('NOT A PDF');
        $this->assertSame('error', $res['outcome']);
    }

    public function test_triple_signed_pdf_links_all_signatures_via_acroform_and_sig_fields(): void
    {
        [$u1, $u2, $u3, $file0, $req1, $req2, $req3] = $this->createSignerAndVersion();

        /** @var PdfSignatureService $sigService */
        $sigService = app(PdfSignatureService::class);

        $r1 = $sigService->signPdf($u1, $file0, $req1, 'First Approval');
        $r2 = $sigService->signPdf($u2, $r1['signed_file'], $req2, 'Second Approval');
        $r3 = $sigService->signPdf($u3, $r2['signed_file'], $req3, 'Third Approval');

        $bytes = File::get($r3['signed_pdf_path']);

        // Catalog must reference /AcroForm and all 3 /FT /Sig field widgets must be present with /V references
        $this->assertSame(1, preg_match('/\/AcroForm\s+\d+\s+0\s+R/', $bytes));
        $this->assertSame(1, preg_match('/\/SigFlags\s+3\b/', $bytes));
        $this->assertSame(3, preg_match_all('/\/FT\s+\/Sig\b/', $bytes));
        $this->assertSame(3, preg_match_all('/\/T\s+\(Signature_\d+\)/', $bytes, $fieldNames));
        $this->assertCount(3, array_unique($fieldNames[0]));

        // Final /AcroForm /Fields array must reference all 3 signature fields
        preg_match_all('/\/Fields\s*\[\s*([^\]]+)\]/', $bytes, $fieldsMatches);
        $this->assertNotEmpty($fieldsMatches[1]);
        $finalFieldsList = end($fieldsMatches[1]);
        $this->assertSame(3, preg_match_all('/\d+\s+0\s+R/', $finalFieldsList));
    }

    public function test_user_signing_certificate_uses_usr_cert_extensions_and_not_ca_true(): void
    {
        $isoPki = storage_path('framework/testing/pki_ext_' . Str::random(8));
        File::ensureDirectoryExists($isoPki, 0700, true);
        config(['services.pki.base_path' => $isoPki]);

        try {
            $certService = new CertificateService();
            $ca = $certService->getOrGenerateInstitutionalCa();
            $caParsed = openssl_x509_parse($ca['cert_pem']);
            $this->assertIsArray($caParsed);
            $this->assertStringContainsString('CA:TRUE', $caParsed['extensions']['basicConstraints'] ?? '');

            $userCert = $certService->generateUserSigningCertificate(
                999001,
                'Extension Check Signer',
                'extcheck.' . Str::random(6) . '@wu.edu.et'
            );
            $userParsed = openssl_x509_parse($userCert['certificate_pem']);
            $this->assertIsArray($userParsed);
            $this->assertStringContainsString('CA:FALSE', $userParsed['extensions']['basicConstraints'] ?? '');
            $this->assertStringNotContainsString('CA:TRUE', $userParsed['extensions']['basicConstraints'] ?? '');
            $this->assertStringContainsString('Non Repudiation', $userParsed['extensions']['keyUsage'] ?? '');
            $this->assertStringContainsString('Digital Signature', $userParsed['extensions']['keyUsage'] ?? '');
            $this->assertStringNotContainsString('Certificate Sign', $userParsed['extensions']['keyUsage'] ?? '');
        } finally {
            config(['services.pki.base_path' => null]);
            File::deleteDirectory($isoPki);
        }
    }
}
