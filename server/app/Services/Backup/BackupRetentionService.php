<?php

namespace App\Services\Backup;

use App\Models\AuditEvent;
use App\Models\BackupRun;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BackupRetentionService
{
    /**
     * Enforce backup retention policy with safety locks to guarantee disaster recovery capability.
     * NEVER deletes the last known good backup.
     */
    public function enforceRetention(int $retentionDays = 30, int $minBackupsToKeep = 3): array
    {
        $backupsBaseDir = storage_path('app/backups');
        if (!File::isDirectory($backupsBaseDir)) {
            return ['deleted_count' => 0, 'kept_count' => 0, 'deleted_backups' => []];
        }

        // List all backup directories
        $dirs = File::directories($backupsBaseDir);
        usort($dirs, function ($a, $b) {
            return filemtime($b) <=> filemtime($a); // Descending by creation time
        });

        $totalBackups = count($dirs);
        if ($totalBackups <= $minBackupsToKeep) {
            return [
                'deleted_count' => 0,
                'kept_count' => $totalBackups,
                'note' => "Total backups ({$totalBackups}) <= minimum threshold ({$minBackupsToKeep}). Deletion skipped.",
                'deleted_backups' => [],
            ];
        }

        $cutoffDate = Carbon::now()->subDays($retentionDays);
        $deleted = [];
        $kept = 0;

        foreach ($dirs as $index => $dir) {
            // Guarantee minimum backups retained
            if ($kept < $minBackupsToKeep) {
                $kept++;
                continue;
            }

            $modTime = Carbon::createFromTimestamp(filemtime($dir));
            if ($modTime->lt($cutoffDate)) {
                $backupId = basename($dir);
                File::deleteDirectory($dir);
                $deleted[] = $backupId;

                // Update backup_runs if exists
                BackupRun::where('provider_job_reference', $backupId)->update([
                    'status' => 'purged',
                ]);
            } else {
                $kept++;
            }
        }

        if (!empty($deleted)) {
            try {
                AuditEvent::create([
                    'actor_id' => auth()->id() ?? 1,
                    'action' => 'backup.retention_purged',
                    'target_type' => 'backup_retention',
                    'target_id' => (string) count($deleted),
                    'request_id' => (string) Str::uuid(),
                    'occurred_at' => now()->format('Y-m-d H:i:s.u'),
                    'details_json' => [
                        'retention_days' => $retentionDays,
                        'purged_count' => count($deleted),
                        'purged_ids' => $deleted,
                        'retained_count' => $kept,
                    ],
                ]);
            } catch (\Throwable $e) {
                // Non-blocking
            }
        }

        return [
            'deleted_count' => count($deleted),
            'kept_count' => $kept,
            'deleted_backups' => $deleted,
        ];
    }
}
