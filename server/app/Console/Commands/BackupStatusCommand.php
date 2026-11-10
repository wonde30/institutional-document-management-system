<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Models\RestoreDrill;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BackupStatusCommand extends Command
{
    protected $signature = 'dms:backup-status';
    protected $description = 'Display comprehensive disaster recovery health, recent backups, and restoration drill status';

    public function handle(): int
    {
        $this->info('================================================================');
        $this->info(' Wollo University DMS - Disaster Recovery Health Dashboard       ');
        $this->info('================================================================');

        $backupsDir = storage_path('app/backups');
        $onDiskCount = File::isDirectory($backupsDir) ? count(File::directories($backupsDir)) : 0;

        $latestRun = BackupRun::latest('id')->first();
        $latestDrill = RestoreDrill::latest('id')->first();

        $rows = [
            ['On-Disk Backup Packages', (string) $onDiskCount],
            ['Total Database Backup Runs', (string) BackupRun::count()],
            ['Total Restore Drills', (string) RestoreDrill::count()],
            ['Last Backup Reference', $latestRun ? $latestRun->provider_job_reference : 'None'],
            ['Last Backup Status', $latestRun ? strtoupper($latestRun->status) : 'None'],
            ['Last Backup Verification', $latestRun ? strtoupper($latestRun->verification_status ?? 'N/A') : 'None'],
            ['Last Backup Completed At', $latestRun && $latestRun->completed_at ? Carbon::parse($latestRun->completed_at)->toIso8601String() : 'None'],
            ['Last Restore Drill Status', $latestDrill ? strtoupper($latestDrill->status) : 'None'],
            ['Last Restore Drill Completed At', $latestDrill && $latestDrill->completed_at ? Carbon::parse($latestDrill->completed_at)->toIso8601String() : 'None'],
        ];

        $this->table(['Disaster Recovery Attribute', 'Status / Metric'], $rows);

        // List last 5 backup runs
        $this->line('');
        $this->info('Recent Backup History:');
        $recentRuns = BackupRun::orderByDesc('id')->take(5)->get();
        $tableData = [];
        foreach ($recentRuns as $run) {
            $tableData[] = [
                'ID' => $run->id,
                'Reference' => $run->provider_job_reference,
                'Started' => $run->started_at ? Carbon::parse($run->started_at)->format('Y-m-d H:i:s') : 'N/A',
                'Completed' => $run->completed_at ? Carbon::parse($run->completed_at)->format('Y-m-d H:i:s') : 'N/A',
                'Status' => strtoupper($run->status),
                'Verified' => strtoupper($run->verification_status ?? 'N/A'),
            ];
        }
        $this->table(['ID', 'Reference', 'Started', 'Completed', 'Status', 'Verified'], $tableData);

        return Command::SUCCESS;
    }
}
