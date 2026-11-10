<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupRetentionService;
use Illuminate\Console\Command;

class BackupRetentionCommand extends Command
{
    protected $signature = 'dms:backup-cleanup 
                            {--keep-days=30 : Number of days to retain backups}
                            {--min-backups=3 : Minimum number of guaranteed backups to preserve}';

    protected $description = 'Enforce backup retention policy with safety preservation locks';

    public function handle(BackupRetentionService $retentionService): int
    {
        $this->info('================================================================');
        $this->info(' Wollo University DMS - Backup Retention Enforcement            ');
        $this->info('================================================================');

        $keepDays = (int) $this->option('keep-days');
        $minBackups = (int) $this->option('min-backups');

        $result = $retentionService->enforceRetention($keepDays, $minBackups);

        $this->table(
            ['Metric', 'Value'],
            [
                ['Retention Window', "{$keepDays} days"],
                ['Minimum Threshold Lock', "{$minBackups} backups"],
                ['Purged Backups', $result['deleted_count']],
                ['Retained Backups', $result['kept_count']],
            ]
        );

        if (!empty($result['deleted_backups'])) {
            $this->warn("Purged backup IDs: " . implode(', ', $result['deleted_backups']));
        }

        return Command::SUCCESS;
    }
}
