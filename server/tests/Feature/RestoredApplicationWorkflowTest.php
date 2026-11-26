<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Backup\BackupCoordinatorService;
use App\Services\Backup\RestorationService;
use App\Services\Pki\PdfSignatureVerificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RestoredApplicationWorkflowTest extends TestCase
{
    protected string $isolatedDb = 'wu_dms_recovery_test';
    protected string $isolatedStorageDir;
    protected string $backupId;
    protected array $originalDbConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->isolatedStorageDir = storage_path('app/recovery_test/app_workflow_storage');
        if (File::isDirectory($this->isolatedStorageDir)) {
            File::deleteDirectory($this->isolatedStorageDir);
        }

        // Ensure recovery database exists before restore
        try {
            DB::statement("CREATE DATABASE IF NOT EXISTS `{$this->isolatedDb}`");
        } catch (\Throwable $e) {
            // Best effort
        }

        // 1. Generate full real backup
        $coordinator = app(BackupCoordinatorService::class);
        $backupResult = $coordinator->executeFullBackup(false);
        $this->backupId = $backupResult['backup_id'];

        // 2. Restore into isolated recovery environment
        $restorationService = app(RestorationService::class);
        $restorationService->restoreFromBackup(
            $this->backupId,
            $this->isolatedDb,
            $this->isolatedStorageDir
        );

        // 3. Reconfigure default DB connection to target isolated restored database
        $this->originalDbConfig = config('database.connections.mysql');
        $recoveryConfig = $this->originalDbConfig;
        $recoveryConfig['database'] = $this->isolatedDb;

        config(['database.connections.mysql' => $recoveryConfig]);
        DB::purge('mysql');
        DB::reconnect('mysql');
    }

    protected function tearDown(): void
    {
        // Restore original database configuration FIRST
        if (!empty($this->originalDbConfig)) {
            config(['database.connections.mysql' => $this->originalDbConfig]);
            DB::purge('mysql');
            DB::reconnect('mysql');
        }

        // Drop the isolated database to prevent pollution
        try {
            $tempConnection = config('database.default');
            config(['database.default' => 'mysql']);
            DB::reconnect('mysql');
            DB::statement("DROP DATABASE IF EXISTS `{$this->isolatedDb}`");
        } catch (\Throwable $e) {
            // Best effort cleanup
        }

        if (File::isDirectory($this->isolatedStorageDir)) {
            File::deleteDirectory($this->isolatedStorageDir);
        }

        parent::tearDown();
    }

    public function test_restored_application_authenticates_admin_user(): void
    {
        $admin = User::where('email', 'admin@wu.edu.et')->first() ?? User::first();
        $this->assertNotNull($admin, 'Restored database must contain at least one user');

        $this->authenticateAs($admin);

        $response = $this->getJson('/api/v1/auth/me');
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('user.email', $admin->email);
        $response->assertJsonPath('user.status', 'active');
    }

    public function test_restored_application_executes_document_retrieval_and_listing(): void
    {
        $admin = User::where('email', 'admin@wu.edu.et')->first() ?? User::first();
        $this->authenticateAs($admin);

        $response = $this->getJson('/api/v1/documents');
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertIsArray($response->json('data'));

        // If documents exist, verify detail route
        $firstDoc = DB::table('documents')->first();
        if ($firstDoc) {
            $detailResponse = $this->getJson("/api/v1/documents/{$firstDoc->id}");
            $detailResponse->assertStatus(200);
            $detailResponse->assertJsonPath('data.id', $firstDoc->id);
            $detailResponse->assertJsonPath('data.title', $firstDoc->title);
        }
    }

    public function test_restored_application_retrieves_correspondence_and_workflow(): void
    {
        $admin = User::where('email', 'admin@wu.edu.et')->first() ?? User::first();
        $this->authenticateAs($admin);

        // Test correspondence listing
        $corrResponse = $this->getJson('/api/v1/correspondences');
        $corrResponse->assertStatus(200);
        $corrResponse->assertJsonPath('success', true);

        // Test workflow tasks listing
        $wfResponse = $this->getJson('/api/v1/workflows/tasks');
        $wfResponse->assertStatus(200);
        $wfResponse->assertJsonPath('success', true);
    }

    public function test_restored_application_retrieves_accreditation_cycles(): void
    {
        $admin = User::where('email', 'admin@wu.edu.et')->first() ?? User::first();
        $this->authenticateAs($admin);

        $accResponse = $this->getJson('/api/v1/accreditation/cycles');
        $accResponse->assertStatus(200);
        $accResponse->assertJsonPath('success', true);
    }

    public function test_restored_application_verifies_cryptographic_signatures_on_restored_files(): void
    {
        $verifier = app(PdfSignatureVerificationService::class);
        $restoredDocsDir = "{$this->isolatedStorageDir}/storage/documents";

        if (!File::isDirectory($restoredDocsDir)) {
            $this->markTestSkipped('No restored documents directory available.');
        }

        $signedPdfs = File::glob("{$restoredDocsDir}/signed_*.pdf");
        if (empty($signedPdfs)) {
            $this->markTestSkipped('No signed PDFs found in restored documents storage.');
        }

        $validCount = 0;
        foreach ($signedPdfs as $pdfPath) {
            $pdfBytes = file_get_contents($pdfPath);
            $result = $verifier->verifyPdf($pdfBytes);

            if ($result['outcome'] === 'valid') {
                $this->assertFalse($result['details']['tamper_detected'], "Valid restored PDF {$pdfPath} must exhibit zero tamper detection");
                $validCount++;
            } else {
                $this->assertTrue($result['details']['tamper_detected'], "Tampered restored PDF {$pdfPath} must be flagged as tampered");
            }
        }

        $this->assertGreaterThan(0, $validCount, 'At least one authentic signed PDF must pass verification in restored storage');
    }

    public function test_restored_application_audit_trail_integrity(): void
    {
        $admin = User::where('email', 'admin@wu.edu.et')->first() ?? User::first();
        $this->authenticateAs($admin);

        $auditCount = DB::table('audit_events')->count();
        $this->assertGreaterThan(0, $auditCount, 'Restored database must contain audit events');

        $latestEvent = DB::table('audit_events')->orderByDesc('id')->first();
        $this->assertNotNull($latestEvent);
        $this->assertNotEmpty($latestEvent->action);
    }
}
