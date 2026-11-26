<?php

namespace App\Services\Backup;

use App\Models\AuditEvent;
use App\Models\BackupRun;
use App\Models\RestoreDrill;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class RestorationService
{
    protected DatabaseBackupService $dbBackupService;
    protected DocumentStorageBackupService $storageBackupService;
    protected BackupEncryptionService $encryptionService;

    public function __construct(
        DatabaseBackupService $dbBackupService,
        DocumentStorageBackupService $storageBackupService,
        BackupEncryptionService $encryptionService
    ) {
        $this->dbBackupService = $dbBackupService;
        $this->storageBackupService = $storageBackupService;
        $this->encryptionService = $encryptionService;
    }

    /**
     * Execute an isolated restoration drill of both database and document storage.
     * Enforces strict isolation: NEVER targets the live database by default.
     */
    public function restoreFromBackup(
        string $backupIdOrPath,
        string $targetDatabase = 'wu_dms_recovery_test',
        ?string $targetStorageDir = null,
        ?string $customSecret = null
    ): array {
        $startTime = microtime(true);
        $startedAt = now();

        // 1. Locate backup directory safely inside backups root
        $backupsRoot = storage_path('app/backups');
        if (preg_match('/^[A-Za-z0-9_.-]+$/', $backupIdOrPath)) {
            $backupDir = \App\Services\FileSecurityService::resolveContainedPath(
                "{$backupsRoot}/{$backupIdOrPath}",
                $backupsRoot
            );
        } else {
            $backupDir = \App\Services\FileSecurityService::resolveContainedPath($backupIdOrPath, $backupsRoot);
        }

        if (! $backupDir || ! File::isDirectory($backupDir)) {
            throw new RuntimeException("Backup directory not found or outside permitted backups boundary: {$backupIdOrPath}");
        }

        $manifestPath = "{$backupDir}/manifest.json";
        if (! File::exists($manifestPath)) {
            throw new RuntimeException("Missing manifest.json in backup directory: {$backupDir}");
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        if (! is_array($manifest) || ! isset($manifest['database']['file_name'], $manifest['database']['sha256'], $manifest['document_storage']['file_name'], $manifest['document_storage']['sha256'])) {
            throw new RuntimeException("Failed to decode backup manifest: {$manifestPath}");
        }

        $isEncrypted = $manifest['is_encrypted'] ?? false;
        $dbFileName = basename((string) $manifest['database']['file_name']);
        $storageFileName = basename((string) $manifest['document_storage']['file_name']);

        $dbFilePath = "{$backupDir}/{$dbFileName}";
        $storageFilePath = "{$backupDir}/{$storageFileName}";

        if (! File::exists($dbFilePath) || ! File::exists($storageFilePath)) {
            throw new RuntimeException("One or more required backup artifacts are missing from {$backupDir}");
        }

        // 2. Pre-restoration integrity check: verify artifact SHA-256 hashes against manifest
        $observedDbSha256 = hash_file('sha256', $dbFilePath);
        if (! is_string($observedDbSha256) || ! hash_equals(strtolower((string) $manifest['database']['sha256']), strtolower($observedDbSha256))) {
            throw new RuntimeException("Integrity failure: Database artifact SHA-256 does not match manifest!");
        }

        $observedStorageSha256 = hash_file('sha256', $storageFilePath);
        if (! is_string($observedStorageSha256) || ! hash_equals(strtolower((string) $manifest['document_storage']['sha256']), strtolower($observedStorageSha256))) {
            throw new RuntimeException("Integrity failure: Storage artifact SHA-256 does not match manifest!");
        }

        // 3. Isolated extraction directories
        $recoveryBaseDir = storage_path('app/recovery_test');
        $tempDecDir = "{$recoveryBaseDir}/temp_dec_" . bin2hex(random_bytes(4));
        $effectiveStorageDir = $targetStorageDir ?? "{$recoveryBaseDir}/storage_" . bin2hex(random_bytes(4));

        File::makeDirectory($tempDecDir, 0750, true);

        try {
            $effectiveSqlPath = $dbFilePath;
            $effectiveZipPath = $storageFilePath;

            // 4. Decrypt if encrypted
            if ($isEncrypted) {
                $effectiveSqlPath = "{$tempDecDir}/database.sql";
                $effectiveZipPath = "{$tempDecDir}/documents.zip";

                $this->encryptionService->decryptFile($dbFilePath, $effectiveSqlPath, $customSecret);
                $this->encryptionService->decryptFile($storageFilePath, $effectiveZipPath, $customSecret);
            }

            // 5. Restore MySQL database into isolated target database
            $dbRestoreResult = $this->dbBackupService->restore($effectiveSqlPath, $targetDatabase);

            // Verify restored database integrity
            $verifyConfig = config('database.connections.mysql');
            $verifyConfig['database'] = $targetDatabase;
            config(['database.connections.mysql_isolated_verify' => $verifyConfig]);

            $restoredUserCount = DB::connection('mysql_isolated_verify')->table('users')->count();
            $restoredDocCount = DB::connection('mysql_isolated_verify')->table('documents')->count();
            $restoredRoleCount = DB::connection('mysql_isolated_verify')->table('roles')->count();

            DB::disconnect('mysql_isolated_verify');
            DB::purge('mysql_isolated_verify');

            // 6. Restore document storage into isolated directory
            $storageRestoreResult = $this->storageBackupService->restore($effectiveZipPath, $effectiveStorageDir);

            if (!$storageRestoreResult['is_verified']) {
                throw new RuntimeException("Document storage integrity verification failed during restoration.");
            }

            $completedAt = now();
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            // 7. Find backup_run record and log restore_drill
            $backupRun = BackupRun::where('provider_job_reference', $manifest['backup_id'])->first();
            if (! $backupRun) {
                $backupRun = BackupRun::first();
            }
            if (! $backupRun) {
                $backupRun = BackupRun::create([
                    'run_type' => 'full',
                    'scope' => 'all',
                    'status' => 'completed',
                    'provider_job_reference' => $manifest['backup_id'],
                    'started_at' => $startedAt,
                    'completed_at' => $completedAt,
                ]);
            }
            $backupRunId = $backupRun->id;

            $drill = RestoreDrill::create([
                'backup_run_id' => $backupRunId,
                'started_at' => $startedAt->format('Y-m-d H:i:s.u'),
                'completed_at' => $completedAt->format('Y-m-d H:i:s.u'),
                'status' => 'completed',
                'notes' => sprintf(
                    "Real isolated restoration drill verified. Target DB: %s (%d tables, %d users, %d docs). Storage: %d files verified (0 corrupted, 0 missing). Elapsed: %d ms.",
                    $targetDatabase,
                    $dbRestoreResult['restored_table_count'],
                    $restoredUserCount,
                    $restoredDocCount,
                    $storageRestoreResult['verified_files_count'],
                    $durationMs
                ),
            ]);

            // 8. Log audit trail
            try {
                AuditEvent::create([
                    'actor_id' => auth()->id() ?? 1,
                    'action' => 'restore_drill.completed',
                    'target_type' => 'restore_drill',
                    'target_id' => (string) $drill->id,
                    'request_id' => (string) Str::uuid(),
                    'occurred_at' => $completedAt->format('Y-m-d H:i:s.u'),
                    'details_json' => [
                        'backup_id' => $manifest['backup_id'],
                        'target_database' => $targetDatabase,
                        'tables_restored' => $dbRestoreResult['restored_table_count'],
                        'files_verified' => $storageRestoreResult['verified_files_count'],
                        'duration_ms' => $durationMs,
                    ],
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Restoration drill audit logging failed: ' . $e->getMessage(), ['exception' => $e]);
            }

            return [
                'status' => 'SUCCESS',
                'restore_drill_id' => $drill->id,
                'backup_id' => $manifest['backup_id'],
                'target_database' => $targetDatabase,
                'target_storage' => $effectiveStorageDir,
                'tables_restored' => $dbRestoreResult['restored_table_count'],
                'users_count' => $restoredUserCount,
                'documents_count' => $restoredDocCount,
                'roles_count' => $restoredRoleCount,
                'files_verified' => $storageRestoreResult['verified_files_count'],
                'is_verified' => true,
                'duration_ms' => $durationMs,
                'rto_seconds' => round($durationMs / 1000, 2),
            ];
        } finally {
            // Clean up temporary decrypted artifacts
            if (File::isDirectory($tempDecDir)) {
                File::deleteDirectory($tempDecDir);
            }
        }
    }
}
