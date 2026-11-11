<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DataImportOperation;
use App\Services\AuditService;
use App\Services\Settings\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Removes the temporary dataset files of import operations that were inspected
 * and never executed.
 *
 * An inspected file is the temporary source of a batch that has not been
 * applied. Once the retention window has passed it is no longer needed and it
 * occupies private storage.
 *
 * The files of operations that were executed are never removed: the source file
 * is the only record of what was submitted, and it supports the audit trail.
 */
class CleanupImportUploadsCommand extends Command
{
    protected $signature = 'dms:cleanup-import-uploads
                            {--dry-run : Report what would be removed without removing anything}
                            {--hours= : Override the configured retention window in hours}';

    protected $description = 'Remove the temporary dataset files of inspected import operations that were never executed';

    /**
     * The operation states whose files are temporary and may be removed.
     */
    protected const TEMPORARY_STATES = ['validated', 'uploaded'];

    public function handle(SettingsService $settings): int
    {
        $hours = $this->option('hours');

        if ($hours === null) {
            $hours = $settings->value('data_imports.temporary_file_retention_hours');
        }

        $hours = is_numeric($hours) ? (int) $hours : 168;
        $hours = max(1, $hours);

        $cutoff = now()->subHours($hours);

        $candidates = DataImportOperation::query()
            ->whereIn('status', self::TEMPORARY_STATES)
            ->whereNotNull('storage_path')
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->get();

        $dryRun = (bool) $this->option('dry-run');

        $removed = 0;
        $missing = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($candidates as $operation) {
            $path = (string) $operation->storage_path;

            // Only a path inside the import area of the private disk may ever
            // be removed. Anything else is reported and left untouched.
            if (! str_starts_with($path, 'imports/')) {
                $skipped++;

                continue;
            }

            $disk = Storage::disk('local');

            if (! $disk->exists($path)) {
                // The reference is stale: the file is already gone. The
                // pointer is cleared so the operation history stays truthful.
                $missing++;

                if (! $dryRun) {
                    $operation->update(['storage_path' => null]);
                }

                continue;
            }

            if ($dryRun) {
                $removed++;

                continue;
            }

            try {
                $disk->delete($path);
                $operation->update(['storage_path' => null]);
                $removed++;
            } catch (\Throwable $exception) {
                $failed++;

                $this->error(
                    'Operation '.$operation->id.': the temporary file could not be removed. '.$exception->getMessage()
                );
            }
        }

        // An operation that was discarded still carries a reference to a file
        // that no longer exists. The pointer is cleared, the history is kept.
        $stale = DataImportOperation::query()
            ->where('status', 'discarded')
            ->whereNotNull('storage_path')
            ->get();

        foreach ($stale as $operation) {
            $path = (string) $operation->storage_path;

            if (str_starts_with($path, 'imports/') && ! Storage::disk('local')->exists($path)) {
                $missing++;

                if (! $dryRun) {
                    $operation->update(['storage_path' => null]);
                }
            }
        }

        $this->info('Temporary import file cleanup'.($dryRun ? ' (dry run)' : '').'.');
        $this->line('Retention window: '.$hours.' hour(s).');
        $this->line('Eligible operations: '.$candidates->count().'.');

        if ($dryRun) {
            $this->line('Files that would be removed: '.$removed.'.');
        } else {
            $this->line('Files removed: '.$removed.'.');
        }

        $this->line('Expired references cleared: '.$missing.'.');
        $this->line('Skipped outside the import area: '.$skipped.'.');
        $this->line('Failures: '.$failed.'.');

        if (! $dryRun && $removed > 0) {
            AuditService::log(
                action: 'data.import.cleanup_completed',
                targetType: 'data_import_operation',
                targetId: '0',
                details: [
                    'retention_hours' => $hours,
                    'files_removed' => $removed,
                    'expired_references_cleared' => $missing,
                    'failures' => $failed,
                ],
            );
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
