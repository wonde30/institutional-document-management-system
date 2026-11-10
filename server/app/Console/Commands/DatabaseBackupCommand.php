<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupCoordinatorService;
use Illuminate\Console\Command;

class DatabaseBackupCommand extends Command
{
    protected $signature = 'dms:backup 
                            {--encrypt : Encrypt backup artifacts with AES-256-CBC and HMAC authentication}
                            {--secret= : Optional custom encryption secret}';

    protected $description = 'Execute real enterprise backup of MySQL database and document storage';

    public function handle(BackupCoordinatorService $coordinator): int
    {
        $this->info('================================================================');
        $this->info(' Wollo University DMS - Enterprise Backup Execution             ');
        $this->info('================================================================');

        $encrypt = (bool) $this->option('encrypt');
        $secret = $this->option('secret');

        $this->line("--> Encryption: " . ($encrypt ? 'ENABLED (AES-256-CBC + HMAC-SHA256)' : 'DISABLED (Plaintext SQL/ZIP)'));
        $this->line("--> Starting coordinated database and document storage backup...");

        try {
            $result = $coordinator->executeFullBackup($encrypt, $secret);

            $this->info("--> Backup executed successfully!");
            $this->table(
                ['Attribute', 'Value'],
                [
                    ['Backup ID', $result['backup_id']],
                    ['Database Artifact', basename($result['database_artifact'])],
                    ['Storage Artifact', basename($result['storage_artifact'])],
                    ['Manifest Path', $result['manifest_path']],
                    ['Encrypted', $result['is_encrypted'] ? 'YES' : 'NO'],
                    ['Duration', "{$result['duration_ms']} ms"],
                    ['Status', strtoupper($result['status'])],
                ]
            );

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Backup execution failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
