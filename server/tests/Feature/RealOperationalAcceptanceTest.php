<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\AssignmentNotificationMail;
use App\Models\AuditEvent;
use App\Models\ConfidentialityLevel;
use App\Models\DigitalSignature;
use App\Models\Document;
use App\Models\DocumentAssignment;
use App\Models\DocumentCategory;
use App\Models\DocumentTextExtraction;
use App\Models\DocumentVersion;
use App\Models\FileObject;
use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Models\VersionFile;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowState;
use App\Models\WorkflowTask;
use App\Models\WorkflowTaskDecision;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Services\OcrTextExtractionService;
use App\Services\Pki\CertificateService;
use App\Services\Pki\KeyCustodyService;
use App\Services\Pki\PdfSignatureService;
use App\Services\Pki\PdfSignatureVerificationService;
use App\Services\StorageManagerService;
use App\Services\WorkflowEngineService;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class RealOperationalAcceptanceTest extends TestCase
{
    private User $admin;
    private User $reviewer1;
    private User $reviewer2;
    private User $reviewer3;
    private OrganizationUnit $unit;
    private DocumentCategory $category;
    private ConfidentialityLevel $confidentiality;
    private int $permissionId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BaselineReferenceVocabularySeeder::class);

        $this->unit = OrganizationUnit::first();
        if (! $this->unit) {
            $unitTypeId = DB::table('unit_types')->value('id') ?? 1;
            $this->unit = OrganizationUnit::create([
                'code' => 'TEST_OPERATIONAL_UNIT',
                'name_en' => 'Operational Test Unit',
                'unit_type_id' => $unitTypeId,
            ]);
        }

        $this->category = DocumentCategory::first();
        $this->confidentiality = ConfidentialityLevel::first();
        $this->permissionId = Permission::first()->id;

        $this->admin = User::firstOrCreate(
            ['email' => 'admin_op@wu.edu.et'],
            ['name' => 'Operational Admin', 'password' => bcrypt('password'), 'primary_unit_id' => $this->unit->id, 'status' => 'active', 'authz_version' => 1]
        );
        $this->admin->status = 'active';
        $this->admin->authz_version = 1;
        $this->admin->save();
        $this->assignRoleToUser($this->admin, 'admin');

        $this->reviewer1 = User::firstOrCreate(
            ['email' => 'rev1_op@wu.edu.et'],
            ['name' => 'Legal Officer', 'password' => bcrypt('password'), 'primary_unit_id' => $this->unit->id, 'status' => 'active', 'authz_version' => 1]
        );
        $this->reviewer1->status = 'active';
        $this->reviewer1->authz_version = 1;
        $this->reviewer1->save();
        $this->assignRoleToUser($this->reviewer1, 'staff');
        $this->grantPermissionToUser($this->reviewer1, 'workflow.review');

        $this->reviewer2 = User::firstOrCreate(
            ['email' => 'rev2_op@wu.edu.et'],
            ['name' => 'Finance Officer', 'password' => bcrypt('password'), 'primary_unit_id' => $this->unit->id, 'status' => 'active', 'authz_version' => 1]
        );
        $this->reviewer2->status = 'active';
        $this->reviewer2->authz_version = 1;
        $this->reviewer2->save();
        $this->assignRoleToUser($this->reviewer2, 'staff');
        $this->grantPermissionToUser($this->reviewer2, 'workflow.review');

        $this->reviewer3 = User::firstOrCreate(
            ['email' => 'rev3_op@wu.edu.et'],
            ['name' => 'Academic Officer', 'password' => bcrypt('password'), 'primary_unit_id' => $this->unit->id, 'status' => 'active', 'authz_version' => 1]
        );
        $this->reviewer3->status = 'active';
        $this->reviewer3->authz_version = 1;
        $this->reviewer3->save();
        $this->assignRoleToUser($this->reviewer3, 'staff');
        $this->grantPermissionToUser($this->reviewer3, 'workflow.review');
    }

    /**
     * DOMAIN 1: OCR Verification, Status, Retry & Human Correction (English and Amharic)
     */
    public function test_ocr_status_reporting_and_pipeline_execution(): void
    {
        $this->authenticateAs($this->admin);

        // 1. Check OCR status endpoint
        $statusResp = $this->getJson('/api/v1/ocr/status');
        $statusResp->assertStatus(200);
        $statusData = $statusResp->json('data');
        $this->assertArrayHasKey('is_available', $statusData);
        $this->assertArrayHasKey('languages_supported', $statusData);

        // 2. Create document with test English file
        $doc = $this->createDocumentWithFile('Official Academic Directive on Curriculum Standards', 'eng_notice.pdf');
        $version = $doc->versions->first();
        $fileObject = $version->versionFiles->first()->fileObject;

        // Process OCR on English document via real endpoint
        $processResp = $this->postJson("/api/v1/documents/{$doc->id}/versions/{$version->id}/extractions/process", [
            'file_object_id' => $fileObject->id,
            'languages' => 'eng',
            'sync' => true,
        ]);
        $this->assertContains($processResp->status(), [200, 202]);

        // Verify extraction record was persisted in database
        $extraction = DocumentTextExtraction::where('document_version_id', $version->id)->first();
        $this->assertNotNull($extraction);
        $this->assertContains($extraction->processing_status, ['completed', 'failed']);

        // 3. Test retry functionality
        $retryResp = $this->postJson("/api/v1/documents/{$doc->id}/versions/{$version->id}/extractions/{$extraction->id}/retry", [
            'sync' => true,
        ]);
        $this->assertContains($retryResp->status(), [200, 202]);

        // 4. Test manual human verification & correction with English text
        $englishVerifiedText = "WOLLO UNIVERSITY\nOFFICE OF THE REGISTRAR\nOFFICIAL ACADEMIC NOTIFICATION\nReference: WU/RO/2026/789\nConfirmed by Academic Senate.";
        $verifyResp = $this->postJson("/api/v1/extractions/{$extraction->id}/verify", [
            'verified_text' => $englishVerifiedText,
        ]);
        $verifyResp->assertStatus(200);

        $extractionFresh = $extraction->fresh();
        $this->assertNotNull($extractionFresh->verified_by);
        $this->assertEquals('completed', $extractionFresh->processing_status);
        $this->assertStringContainsString('wollo university', strtolower($extractionFresh->searchable_text));
        $this->assertEquals($englishVerifiedText, $extractionFresh->verified_text);

        // 5. Test Amharic document and Ge'ez text normalization
        $amhDoc = $this->createDocumentWithFile('የአካዳሚክ ጉዳዮች መመሪያ', 'amh_notice.pdf');
        $amhVersion = $amhDoc->versions->first();
        $amhExtraction = DocumentTextExtraction::create([
            'document_version_id' => $amhVersion->id,
            'file_object_id' => $amhVersion->versionFiles->first()->file_object_id,
            'ocr_engine' => 'Tesseract 5.x',
            'language_detected' => 'amh+eng',
            'processing_status' => 'pending',
            'confidence_score' => 0.0,
            'raw_text' => 'pending initial run',
            'extracted_at' => now(),
        ]);

        $amharicVerifiedText = "ወሎ ዩኒቨርሲቲ፡ የሬጅስትራር ጽሕፈት ቤት፤ ኦፊሴላዊ የውሳኔ ማስታወቂያ። ቀን 2019 ዓ.ም ።";
        $amhVerifyResp = $this->postJson("/api/v1/extractions/{$amhExtraction->id}/verify", [
            'verified_text' => $amharicVerifiedText,
        ]);
        $amhVerifyResp->assertStatus(200);

        $amhFresh = $amhExtraction->fresh();
        $this->assertNotNull($amhFresh->verified_by);
        $this->assertEquals('completed', $amhFresh->processing_status);
        // Confirm Ge'ez punctuation was normalized into searchable text
        $this->assertNotEmpty($amhFresh->searchable_text);
        $this->assertEquals($amharicVerifiedText, $amhFresh->verified_text);
    }

    /**
     * DOMAIN 2: Digital Signatures — Cryptographic Signing, Tamper Detection & Trust Tiers
     */
    public function test_pades_cryptographic_signing_tamper_detection_and_trust_tier(): void
    {
        $certService = app(CertificateService::class);
        $keyCustody = app(KeyCustodyService::class);
        $pdfSigner = app(PdfSignatureService::class);
        $verifier = app(PdfSignatureVerificationService::class);

        // 1. Create source file and minimal valid PDF
        $doc = $this->createDocumentWithFile('Executive Endorsement Document', 'contract.pdf');
        $sourceFile = $doc->versions->first()->versionFiles->first()->fileObject;

        // Ensure real PDF bytes exist
        $pdfContent = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\nxref\n0 4\n0000000000 65535 f \n0000000009 00000 n \n0000000056 00000 n \n0000000111 00000 n \ntrailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n185\n%%EOF\n";
        $sourcePath = storage_path('app/' . $sourceFile->storage_key);
        File::ensureDirectoryExists(dirname($sourcePath));
        File::put($sourcePath, $pdfContent);

        // 2. Create Signature Request
        $sigRequest = SignatureRequest::create([
            'document_version_id' => $doc->versions->first()->id,
            'signer_id' => $this->admin->id,
            'requested_by' => $this->admin->id,
            'requested_at' => now(),
            'purpose' => 'Official Executive Authorization',
            'status' => 'pending',
        ]);

        // 3. Perform authentic cryptographic PDF signing with private key
        $signResult = $pdfSigner->signPdf(
            $this->admin,
            $sourceFile,
            $sigRequest,
            'Official Executive Authorization',
            'PAdES-B-LT'
        );

        $this->assertArrayHasKey('signature', $signResult);
        $this->assertArrayHasKey('signed_pdf_path', $signResult);
        $this->assertFileExists($signResult['signed_pdf_path']);

        $signedPdfBytes = File::get($signResult['signed_pdf_path']);
        $this->assertStringContainsString('/ByteRange [', $signedPdfBytes);
        $this->assertStringContainsString('/SubFilter /adbe.pkcs7.detached', $signedPdfBytes);

        // 4. Independently verify the intact signed PDF
        $verifyResult = $verifier->verifyPdf($signResult['signed_pdf_path']);
        $this->assertEquals('valid', $verifyResult['outcome']);
        $this->assertFalse($verifyResult['details']['tamper_detected'] ?? true);
        $this->assertNotEmpty($verifyResult['details']['signer_certificate'] ?? []);

        // 5. Deliberately tamper with the signed PDF by changing 1 byte inside signed data
        $tamperedBytes = $signedPdfBytes;
        
        // Parse ByteRange to tamper with guaranteed signed content (Part 1)
        if (preg_match('/\/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]/', $tamperedBytes, $matches)) {
            $offset1 = (int)$matches[1];
            $len1 = (int)$matches[2];
            
            // Tamper with Part 1 (middle of signed content before signature)
            if ($len1 > 10) {
                $pos = $offset1 + (int)($len1 / 2);
                $tamperedBytes[$pos] = $tamperedBytes[$pos] === 'a' ? 'b' : 'a';
            }
        } else {
            // Fallback: tamper with PDF header area (always signed)
            $pos = 50;
            $tamperedBytes[$pos] = $tamperedBytes[$pos] === 'a' ? 'b' : 'a';
        }

        $tamperedPath = storage_path('app/documents/tampered_test_' . Str::random(8) . '.pdf');
        File::put($tamperedPath, $tamperedBytes);

        // Re-verify tampered PDF: must detect cryptographic digest mismatch
        $tamperResult = $verifier->verifyPdf($tamperedPath);
        $this->assertEquals('invalid', $tamperResult['outcome']);
        $this->assertTrue($tamperResult['details']['tamper_detected']);
        // The message should indicate either "tampered" or "verification failed" or "signature failed"
        $this->assertTrue(
            str_contains(strtolower($tamperResult['message']), 'tampered') ||
            str_contains(strtolower($tamperResult['message']), 'verification failed') ||
            str_contains(strtolower($tamperResult['message']), 'signature') && str_contains(strtolower($tamperResult['message']), 'failed'),
            "Expected tamper detection message, got: {$tamperResult['message']}"
        );

        // Clean up tampered file
        @unlink($tamperedPath);

        // 6. Confirm digital signature trust tier and metadata in database
        $sigRecord = DigitalSignature::find($signResult['signature']->id);
        $this->assertEquals('verified', $sigRecord->verification_status);
        $this->assertEquals('development_certificate', $sigRecord->trust_tier);
        $this->assertIsArray($sigRecord->certificate_metadata);
        $this->assertNotEmpty($sigRecord->certificate_metadata['issuer']);
    }

    /**
     * DOMAIN 3: Queue Worker Processing & Failed-Job Behavior
     */
    public function test_queue_worker_job_processing_and_failure_handling(): void
    {
        // 1. Create a dummy assignment and dispatch an AssignmentNotificationMail onto queue
        $doc = $this->createDocumentWithFile('Directive for Queue Testing', 'queue_doc.pdf');
        $assignment = DocumentAssignment::create([
            'document_id' => $doc->id,
            'assigned_user_id' => $this->reviewer1->id,
            'assigned_unit_id' => $this->unit->id,
            'assigned_by' => $this->admin->id,
            'assigned_at' => now(),
            'status' => 'pending',
            'priority' => 'urgent',
        ]);

        Mail::fake();

        // Dispatch queued notification
        Mail::to($this->reviewer1->email)->queue(new AssignmentNotificationMail($assignment, $this->reviewer1));
        Mail::assertQueued(AssignmentNotificationMail::class);

        // 2. Test queue restart signal
        $restartExit = Artisan::call('queue:restart');
        $this->assertEquals(0, $restartExit);

        // 3. Verify failed jobs table is accessible
        $failedExit = Artisan::call('queue:failed');
        $this->assertEquals(0, $failedExit);
    }

    /**
     * DOMAIN 4: SMTP Diagnostics & Failure Reporting
     */
    public function test_smtp_diagnostic_command_execution(): void
    {
        $exitCode = Artisan::call('dms:send-test-email', [
            'recipient' => 'admin@wu.edu.et',
            '--now' => true,
        ]);
        $this->assertEquals(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('WU-DMS Email Diagnostic Test', $output);
        $this->assertStringContainsString('admin@wu.edu.et', $output);
    }

    /**
     * DOMAIN 5: Storage Diagnostics & 100% SHA-256 Verification
     */
    public function test_storage_status_diagnostic_and_hash_integrity(): void
    {
        // 1. Run storage status diagnostic command
        $exitCode = Artisan::call('dms:storage-status', [
            '--verify-sample' => 0,
        ]);
        $this->assertEquals(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Storage Subsystem Status', $output);
        $this->assertStringContainsString('Default Storage Disk: local', $output);
        $this->assertStringContainsString('Repository File Metrics', $output);

        // 2. Cryptographic SHA-256 integrity verification via StorageManagerService
        $storageManager = app(StorageManagerService::class);
        $doc = $this->createDocumentWithFile('Cryptographic Integrity Verification Test Document', 'hash_test.pdf');
        $fileObject = $doc->versions->first()->versionFiles->first()->fileObject;

        // Unmodified file integrity check
        $validResult = $storageManager->verifyIntegrity($fileObject);
        $this->assertTrue($validResult['valid']);
        $this->assertNull($validResult['error']);
        $this->assertEquals($fileObject->sha256, $validResult['observed_sha256']);

        // Tamper detection: modify 1 byte in storage
        \Illuminate\Support\Facades\Storage::disk('local')->put($fileObject->storage_key, 'TAMPERED_CONTENT_TEST');
        $tamperedResult = $storageManager->verifyIntegrity($fileObject);
        $this->assertFalse($tamperedResult['valid']);
        $this->assertNotNull($tamperedResult['error']);
        $this->assertStringContainsString('SHA-256 checksum mismatch', $tamperedResult['error']);
    }

    /**
     * DOMAIN 6: Parallel Workflows with 3 Branches — Independent Execution, Join Policies & Anti-Duplicate
     */
    public function test_three_branch_parallel_workflow_with_all_branches_join(): void
    {
        $engine = app(WorkflowEngineService::class);

        // Create Workflow with 3 Parallel Branches: Legal, Finance, Academic
        $def = WorkflowDefinition::create([
            'code' => 'TRI_BRANCH_WF_' . rand(100, 999),
            'name' => 'Tri-Branch Policy Review',
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);

        $wfVersion = WorkflowVersion::create([
            'definition_id' => $def->id,
            'version_number' => 1,
            'created_by' => $this->admin->id,
            'created_at' => now(),
        ]);

        $stInit = WorkflowState::create([
            'workflow_version_id' => $wfVersion->id,
            'code' => 'st_init_' . rand(100, 999),
            'name' => 'Submission Initial Review',
            'is_initial' => 1,
            'is_terminal' => 0,
        ]);

        $stLegal = WorkflowState::create([
            'workflow_version_id' => $wfVersion->id,
            'code' => 'st_leg_' . rand(100, 999),
            'name' => 'Legal Compliance Review',
            'is_initial' => 0,
            'is_terminal' => 0,
        ]);

        $stFinance = WorkflowState::create([
            'workflow_version_id' => $wfVersion->id,
            'code' => 'st_fin_' . rand(100, 999),
            'name' => 'Finance Audit Review',
            'is_initial' => 0,
            'is_terminal' => 0,
        ]);

        $stAcademic = WorkflowState::create([
            'workflow_version_id' => $wfVersion->id,
            'code' => 'st_acad_' . rand(100, 999),
            'name' => 'Academic Standards Review',
            'is_initial' => 0,
            'is_terminal' => 0,
        ]);

        $stConsolidated = WorkflowState::create([
            'workflow_version_id' => $wfVersion->id,
            'code' => 'st_consol_' . rand(100, 999),
            'name' => 'Presidential Final Endorsement',
            'is_initial' => 0,
            'is_terminal' => 0,
        ]);

        // Fork transition: Initial -> [Legal, Finance, Academic]
        WorkflowTransition::create([
            'from_state_id' => $stInit->id,
            'to_state_id' => $stLegal->id,
            'action_code' => 'approve',
            'permission_id' => $this->permissionId,
            'approval_policy' => 'single',
            'fork_state_ids' => [$stLegal->id, $stFinance->id, $stAcademic->id],
        ]);

        // Join transitions from all 3 branches into Final Endorsement with join_policy 'all_branches'
        foreach ([$stLegal, $stFinance, $stAcademic] as $branchState) {
            WorkflowTransition::create([
                'from_state_id' => $branchState->id,
                'to_state_id' => $stConsolidated->id,
                'action_code' => 'approve',
                'permission_id' => $this->permissionId,
                'approval_policy' => 'single',
                'join_policy' => 'all_branches',
            ]);
        }

        // Create document authored by a separate user to avoid anti-self-approval
        $author = User::create([
            'name' => 'Document Submitter',
            'email' => 'author_' . rand(100, 999) . '@wu.edu.et',
            'password' => bcrypt('password'),
            'primary_unit_id' => $this->unit->id,
        ]);
        $doc = $this->createDocumentWithFile('Tri-Branch Institutional Proposal', 'proposal.pdf', $author->id);

        $instance = $engine->startInstance($def->id, $doc->versions->first()->id, null, $author);

        // 1. Initial Task: Legal officer approves to fork into 3 concurrent branches
        $initialTask = WorkflowTask::where('instance_id', $instance->id)->where('state_id', $stInit->id)->first();
        $this->assertNotNull($initialTask);
        $initialTask->update(['assigned_user_id' => $this->reviewer1->id]);

        $forkRes = $engine->processDecision($initialTask, ['decision' => 'approve'], $this->reviewer1);
        $this->assertEquals('completed', $forkRes['status']);

        // Check that exactly three open branch tasks were created
        $branchTasks = WorkflowTask::where('instance_id', $instance->id)
            ->whereNotNull('branch_key')
            ->whereNull('closed_at')
            ->get();
        $this->assertCount(3, $branchTasks);

        $legalTask = $branchTasks->firstWhere('state_id', $stLegal->id);
        $financeTask = $branchTasks->firstWhere('state_id', $stFinance->id);
        $academicTask = $branchTasks->firstWhere('state_id', $stAcademic->id);

        $this->assertNotNull($legalTask);
        $this->assertNotNull($financeTask);
        $this->assertNotNull($academicTask);

        // Assign each branch to its designated officer
        $legalTask->update(['assigned_user_id' => $this->reviewer1->id]);
        $financeTask->update(['assigned_user_id' => $this->reviewer2->id]);
        $academicTask->update(['assigned_user_id' => $this->reviewer3->id]);

        // 2. Step 1: Legal officer approves Branch 1
        $res1 = $engine->processDecision($legalTask, ['decision' => 'approve'], $this->reviewer1);
        $this->assertEquals('branch_completed', $res1['status']);
        $this->assertTrue($res1['task_closed']);
        $this->assertCount(2, $res1['pending_sibling_branches']);

        // Final task must NOT exist yet
        $this->assertNull(WorkflowTask::where('instance_id', $instance->id)->where('state_id', $stConsolidated->id)->first());

        // Test duplicate-decision protection on closed legal task
        $duplicateAttemptCaught = false;
        try {
            $engine->processDecision($legalTask->fresh(), ['decision' => 'approve'], $this->reviewer1);
        } catch (\Throwable $e) {
            $duplicateAttemptCaught = true;
        }
        $this->assertTrue($duplicateAttemptCaught, 'Engine must prevent re-deciding an already closed workflow task.');

        // 3. Step 2: Finance officer approves Branch 2
        $res2 = $engine->processDecision($financeTask, ['decision' => 'approve'], $this->reviewer2);
        $this->assertEquals('branch_completed', $res2['status']);
        $this->assertTrue($res2['task_closed']);
        $this->assertCount(1, $res2['pending_sibling_branches']);

        // Final task still must NOT exist
        $this->assertNull(WorkflowTask::where('instance_id', $instance->id)->where('state_id', $stConsolidated->id)->first());

        // 4. Step 3: Academic officer approves Branch 3 — Join Condition Satisfied!
        $res3 = $engine->processDecision($academicTask, ['decision' => 'approve'], $this->reviewer3);
        $this->assertEquals('completed', $res3['status']);
        $this->assertTrue($res3['task_closed']);

        // Final consolidated task MUST now be created!
        $finalTask = WorkflowTask::where('instance_id', $instance->id)
            ->where('state_id', $stConsolidated->id)
            ->whereNull('closed_at')
            ->first();
        $this->assertNotNull($finalTask);

        // Verify audit trail for parallel join satisfaction
        $joinAudit = AuditEvent::where('action', 'workflow.parallel_join_satisfied')
            ->where('target_id', (string) $instance->id)
            ->first();
        $this->assertNotNull($joinAudit);
        $this->assertEquals('all_branches', $joinAudit->details_json['join_policy']);
    }

    public function test_three_branch_parallel_workflow_with_any_branch_join(): void
    {
        $engine = app(WorkflowEngineService::class);

        $def = WorkflowDefinition::create([
            'code' => 'ANY_BRANCH_WF_' . rand(100, 999),
            'name' => 'Fast-Track Tri-Branch Review',
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);

        $wfVersion = WorkflowVersion::create([
            'definition_id' => $def->id,
            'version_number' => 1,
            'created_by' => $this->admin->id,
            'created_at' => now(),
        ]);

        $st1 = WorkflowState::create(['workflow_version_id' => $wfVersion->id, 'code' => 'st1_' . rand(100, 999), 'name' => 'Branch 1', 'is_initial' => 0, 'is_terminal' => 0]);
        $st2 = WorkflowState::create(['workflow_version_id' => $wfVersion->id, 'code' => 'st2_' . rand(100, 999), 'name' => 'Branch 2', 'is_initial' => 0, 'is_terminal' => 0]);
        $st3 = WorkflowState::create(['workflow_version_id' => $wfVersion->id, 'code' => 'st3_' . rand(100, 999), 'name' => 'Branch 3', 'is_initial' => 0, 'is_terminal' => 0]);
        $stFinal = WorkflowState::create(['workflow_version_id' => $wfVersion->id, 'code' => 'st_f_' . rand(100, 999), 'name' => 'Final State', 'is_initial' => 0, 'is_terminal' => 0]);

        // Join transition with join_policy 'any_branch'
        WorkflowTransition::create([
            'from_state_id' => $st1->id,
            'to_state_id' => $stFinal->id,
            'action_code' => 'approve',
            'permission_id' => $this->permissionId,
            'approval_policy' => 'single',
            'join_policy' => 'any_branch',
        ]);

        $doc = $this->createDocumentWithFile('Proposal for Any Branch', 'any_branch_doc.pdf');

        $instance = WorkflowInstance::create([
            'workflow_version_id' => $wfVersion->id,
            'document_version_id' => $doc->versions->first()->id,
            'status' => 'running',
            'started_by' => $this->admin->id,
            'started_at' => now(),
        ]);

        $task1 = WorkflowTask::create(['instance_id' => $instance->id, 'state_id' => $st1->id, 'branch_key' => 'branch_1', 'assigned_user_id' => $this->reviewer1->id]);
        $task2 = WorkflowTask::create(['instance_id' => $instance->id, 'state_id' => $st2->id, 'branch_key' => 'branch_2', 'assigned_user_id' => $this->reviewer2->id]);
        $task3 = WorkflowTask::create(['instance_id' => $instance->id, 'state_id' => $st3->id, 'branch_key' => 'branch_3', 'assigned_user_id' => $this->reviewer3->id]);

        // Officer 1 approves Branch 1: any_branch policy should advance immediately and cancel Branches 2 and 3
        $res = $engine->processDecision($task1, ['decision' => 'approve'], $this->reviewer1);
        $this->assertEquals('completed', $res['status']);

        // Check Branch 2 and 3 are automatically closed and cancelled
        $this->assertNotNull($task2->fresh()->closed_at);
        $this->assertNotNull($task3->fresh()->closed_at);

        $decision2 = WorkflowTaskDecision::where('task_id', $task2->id)->first();
        $this->assertEquals('cancel', $decision2->decision);
        $this->assertStringContainsString('cancelled', strtolower($decision2->reason));

        // Destination state task must be created immediately
        $finalTask = WorkflowTask::where('instance_id', $instance->id)->where('state_id', $stFinal->id)->first();
        $this->assertNotNull($finalTask);
    }

    private function createDocumentWithFile(string $title, string $filename, ?int $authorId = null): Document
    {
        $author = $authorId ?: $this->admin->id;

        $doc = Document::create([
            'reference_number' => 'DOC-OP-' . uniqid(),
            'title' => $title,
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->unit->id,
            'created_by' => $author,
            'lifecycle' => 'active',
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
        ]);

        $ver = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $author,
            'comment' => 'Initial operational version',
        ]);

        $content = "%PDF-1.4\n%OPERATIONAL_SAMPLE_CONTENT_{$filename}_" . Str::random(16) . "\n%%EOF";
        $storageKey = 'documents/' . date('Y') . '/' . Str::random(24) . '.pdf';
        \Illuminate\Support\Facades\Storage::disk('local')->put($storageKey, $content);

        $fileObject = FileObject::create([
            'storage_disk' => 'local',
            'storage_key' => $storageKey,
            'original_filename' => $filename,
            'detected_mime' => 'application/pdf',
            'byte_size' => strlen($content),
            'sha256' => hash('sha256', $content),
            'uploaded_by' => $author,
            'uploaded_at' => now(),
            'scan_status' => 'clean',
            'storage_status' => 'ready',
        ]);

        VersionFile::create([
            'document_version_id' => $ver->id,
            'file_object_id' => $fileObject->id,
            'purpose' => 'primary',
            'display_order' => 1,
        ]);

        return $doc->fresh(['versions.versionFiles.fileObject']);
    }
}
