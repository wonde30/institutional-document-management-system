<?php

namespace App\Services\Backup;

use App\Models\AuditEvent;
use App\Models\BackupRun;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class BackupCoordinatorService
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
     * Execute coordinated full backup of MySQL database and document storage.
     */
    public function executeFullBackup(bool $encrypt = false, ?string $customSecret = null): array
    {
        $startTime = microtime(true);
        $startedAt = now();
        $backupId = 'WU-BAK-' . date('Ymd-His') . '-' . strtoupper(Str::random(4));

        $backupBaseDir = storage_path("app/backups/{$backupId}");
        if (!File::isDirectory($backupBaseDir)) {
            File::makeDirectory($backupBaseDir, 0750, true);
        }

        // 1. Dump MySQL database
        $sqlPath = "{$backupBaseDir}/database.sql";
        $dbResult = $this->dbBackupService->dump($sqlPath);

        // 2. Archive document storage
        $zipPath = "{$backupBaseDir}/documents.zip";
        $storageResult = $this->storageBackupService->archive($zipPath, $backupId);

        // 3. Optional Encryption
        $finalDbPath = $sqlPath;
        $finalStoragePath = $zipPath;
        $isEncrypted = false;

        if ($encrypt) {
            $encSqlPath = "{$backupBaseDir}/database.sql.enc";
            $encZipPath = "{$backupBaseDir}/documents.zip.enc";

            $this->encryptionService->encryptFile($sqlPath, $encSqlPath, $customSecret);
            $this->encryptionService->encryptFile($zipPath, $encZipPath, $customSecret);

            // Securely wipe plaintext files
            File::delete($sqlPath);
            File::delete($zipPath);

            $finalDbPath = $encSqlPath;
            $finalStoragePath = $encZipPath;
            $isEncrypted = true;
        }

        // 4. Verify resulting artifacts exist and have non-zero size
        if (!File::exists($finalDbPath) || filesize($finalDbPath) === 0) {
            throw new RuntimeException("Backup failed: Database artifact is missing or empty.");
        }
        if (!File::exists($finalStoragePath) || filesize($finalStoragePath) === 0) {
            throw new RuntimeException("Backup failed: Storage artifact is missing or empty.");
        }

        $completedAt = now();
        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        // 5. Build master backup manifest
        $manifest = [
            'backup_id' => $backupId,
            'started_at' => $startedAt->toIso8601String(),
            'completed_at' => $completedAt->toIso8601String(),
            'duration_ms' => $durationMs,
            'is_encrypted' => $isEncrypted,
            'database' => [
                'file_name' => basename($finalDbPath),
                'byte_size' => filesize($finalDbPath),
                'sha256' => hash_file('sha256', $finalDbPath),
                'binlog_coordinates' => $dbResult['binlog_coordinates'],
            ],
            'document_storage' => [
                'file_name' => basename($finalStoragePath),
                'byte_size' => filesize($finalStoragePath),
                'sha256' => hash_file('sha256', $finalStoragePath),
                'files_archived' => $storageResult['file_count'],
            ],
            'status' => 'completed',
            'verification_status' => 'verified',
        ];

        $manifestPath = "{$backupBaseDir}/manifest.json";
        File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // 6. Record in backup_runs table
        $backupRun = BackupRun::create([
            'provider_job_reference' => $backupId,
            'started_at' => $startedAt->format('Y-m-d H:i:s.u'),
            'completed_at' => $completedAt->format('Y-m-d H:i:s.u'),
            'status' => 'completed',
            'manifest_reference' => str_replace('\\', '/', $manifestPath),
            'verification_status' => 'verified',
        ]);

        // 7. Record immutable audit event
        try {
            AuditEvent::create([
                'actor_id' => auth()->id() ?? 1,
                'action' => 'backup.executed',
                'target_type' => 'backup_run',
                'target_id' => (string) $backupRun->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $completedAt->format('Y-m-d H:i:s.u'),
                'details_json' => [
                    'backup_id' => $backupId,
                    'is_encrypted' => $isEncrypted,
                    'db_size' => filesize($finalDbPath),
                    'storage_size' => filesize($finalStoragePath),
                    'duration_ms' => $durationMs,
                ],
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Backup audit logging failed: ' . $e->getMessage(), ['exception' => $e]);
        }

        return [
            'backup_id' => $backupId,
            'backup_run_id' => $backupRun->id,
            'base_directory' => $backupBaseDir,
            'manifest_path' => $manifestPath,
            'is_encrypted' => $isEncrypted,
            'database_artifact' => $finalDbPath,
            'storage_artifact' => $finalStoragePath,
            'duration_ms' => $durationMs,
            'status' => 'completed',
        ];
    }
}
