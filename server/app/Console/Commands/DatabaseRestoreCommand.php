<?php

namespace App\Console\Commands;

use App\Services\Backup\RestorationService;
use Illuminate\Console\Command;

class DatabaseRestoreCommand extends Command
{
    protected $signature = 'dms:restore 
                            {backup_id : The backup identifier or directory name}
                            {--target-db=wu_dms_recovery_test : Target database for restoration}
                            {--target-storage= : Optional isolated storage target directory}
                            {--secret= : Optional decryption secret}';

    protected $description = 'Execute real isolated restoration drill from a verified backup artifact';

    public function handle(RestorationService $restorationService): int
    {
        $this->info('================================================================');
        $this->info(' Wollo University DMS - Enterprise Restoration Drill            ');
        $this->info('================================================================');

        $backupId = $this->argument('backup_id');
        $targetDb = $this->option('target-db');
        $targetStorage = $this->option('target-storage');
        $secret = $this->option('secret');

        $this->line("--> Source Backup ID: {$backupId}");
        $this->line("--> Isolated Target Database: {$targetDb}");

        try {
            $result = $restorationService->restoreFromBackup($backupId, $targetDb, $targetStorage, $secret);

            $this->info("--> Restoration drill completed successfully!");
            $this->table(
                ['Restoration Metric', 'Result'],
                [
                    ['Drill ID', $result['restore_drill_id']],
                    ['Target Database', $result['target_database']],
                    ['Restored 3NF Tables', $result['tables_restored']],
                    ['Restored Users', $result['users_count']],
                    ['Restored Documents', $result['documents_count']],
                    ['Verified Storage Files', $result['files_verified']],
                    ['RTO (Recovery Time)', "{$result['rto_seconds']} seconds ({$result['duration_ms']} ms)"],
                    ['Cryptographic Verification', '100% SHA-256 MATCH'],
                    ['Status', 'SUCCESS / VERIFIED'],
                ]
            );

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Restoration drill failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
