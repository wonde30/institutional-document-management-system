<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FileObject;
use App\Services\StorageManagerService;
use Illuminate\Console\Command;

class StorageStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dms:storage-status 
                            {--verify-sample=10 : Number of random stored files to verify cryptographic SHA-256 integrity}
                            {--verify-all : Verify SHA-256 checksums across all stored files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Inspect WU-DMS storage drivers, MinIO/S3 connectivity, disk metrics, and cryptographic file integrity';

    /**
     * Execute the console command.
     */
    public function handle(StorageManagerService $storageManager): int
    {
        $defaultDisk = config('filesystems.default', 'local');
        $this->info("=== WU-DMS Storage Subsystem Status ===");
        $this->line("Default Storage Disk: <comment>{$defaultDisk}</comment>\n");

        // 1. Check configured disks
        $configuredDisks = array_keys(config('filesystems.disks', []));
        $diskRows = [];

        foreach ($configuredDisks as $disk) {
            $status = $storageManager->checkDiskStatus($disk);
            $diskRows[] = [
                $disk,
                $status['driver'] ?? config("filesystems.disks.{$disk}.driver", 'unknown'),
                $status['available'] ? '<info>AVAILABLE</info>' : '<error>OFFLINE</error>',
                $status['status'],
                $status['latency_ms'] . ' ms',
                $status['error'] ?: 'OK',
            ];
        }

        $this->table(['Disk', 'Driver', 'Availability', 'Status', 'Probe Latency', 'Details / Error'], $diskRows);

        // 2. Database FileObject Metrics
        $totalFiles = FileObject::count();
        $totalBytes = (int) FileObject::sum('byte_size');
        $cleanFiles = FileObject::where('scan_status', 'clean')->count();
        $readyFiles = FileObject::where('storage_status', 'ready')->count();

        $mbSize = round($totalBytes / (1024 * 1024), 2);

        $this->line("\n=== Repository File Metrics ===");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Stored Files', (string) $totalFiles],
                ['Total Repository Size', "{$mbSize} MB ({$totalBytes} bytes)"],
                ['Clean Antivirus Scans', "{$cleanFiles} / {$totalFiles}"],
                ['Ready Storage Status', "{$readyFiles} / {$totalFiles}"],
            ]
        );

        // 3. Cryptographic Integrity Verification
        $verifyAll = (bool) $this->option('verify-all');
        $sampleSize = $verifyAll ? $totalFiles : (int) $this->option('verify-sample');

        if ($totalFiles > 0 && $sampleSize > 0) {
            $this->line("\n=== Verifying Cryptographic SHA-256 Integrity (" . ($verifyAll ? 'ALL' : "Sample of {$sampleSize}") . ") ===");

            $query = FileObject::query();
            if (! $verifyAll) {
                $query->inRandomOrder()->limit($sampleSize);
            }
            $sampleFiles = $query->get();

            $verifiedCount = 0;
            $failedCount = 0;
            $integrityRows = [];

            foreach ($sampleFiles as $file) {
                $check = $storageManager->verifyIntegrity($file);
                if ($check['valid']) {
                    $verifiedCount++;
                } else {
                    $failedCount++;
                }

                $statusLabel = $check['valid'] ? '<info>VERIFIED</info>' : '<error>FAILED</error>';
                $integrityRows[] = [
                    $file->id,
                    $file->original_filename,
                    $file->storage_disk,
                    substr($file->sha256, 0, 16) . '...',
                    $statusLabel,
                    $check['error'] ?: 'Match',
                ];
            }

            $this->table(['File ID', 'Filename', 'Disk', 'Expected Hash', 'Result', 'Notes'], $integrityRows);

            if ($failedCount > 0) {
                $this->error("INTEGRITY CHECK COMPLETED: {$verifiedCount} passed, {$failedCount} FAILED.");
                return Command::FAILURE;
            } else {
                $this->info("INTEGRITY CHECK PASSED: {$verifiedCount}/{$verifiedCount} file objects cryptographically verified.");
            }
        }

        return Command::SUCCESS;
    }
}
