<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\BackupRun;
use App\Models\RestoreDrill;
use App\Services\Backup\BackupCoordinatorService;
use App\Services\Backup\RestorationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BackupController extends Controller
{
    /**
     * List enterprise backup runs.
     */
    public function index(): JsonResponse
    {
        $runs = BackupRun::orderByDesc('id')->get()->map(function (BackupRun $run) {
            $manifest = null;
            if ($run->manifest_reference && File::exists($run->manifest_reference)) {
                try {
                    $manifest = json_decode(File::get($run->manifest_reference), true);
                } catch (\Throwable) {}
            }

            return [
                'id' => $run->id,
                'backup_id' => $run->provider_job_reference,
                'started_at' => $run->started_at?->toIso8601String(),
                'completed_at' => $run->completed_at?->toIso8601String(),
                'status' => $run->status,
                'verification_status' => $run->verification_status,
                'manifest_reference' => $run->manifest_reference,
                'is_encrypted' => $manifest['is_encrypted'] ?? false,
                'duration_ms' => $manifest['duration_ms'] ?? null,
                'database_size' => $manifest['database']['byte_size'] ?? null,
                'storage_size' => $manifest['document_storage']['byte_size'] ?? null,
                'total_size' => isset($manifest['database']['byte_size'], $manifest['document_storage']['byte_size'])
                    ? ($manifest['database']['byte_size'] + $manifest['document_storage']['byte_size'])
                    : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $runs,
        ]);
    }

    /**
     * Trigger an on-demand coordinated enterprise backup.
     */
    public function store(Request $request, BackupCoordinatorService $coordinator): JsonResponse
    {
        $validated = $request->validate([
            'encrypt' => 'nullable|boolean',
            'secret' => 'nullable|string|min:8',
        ]);

        $encrypt = (bool) ($validated['encrypt'] ?? false);
        $secret = $validated['secret'] ?? null;

        try {
            $result = $coordinator->executeFullBackup($encrypt, $secret);

            return response()->json([
                'success' => true,
                'message' => 'Institutional backup completed and verified successfully.',
                'data' => $result,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Backup execution failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show backup details and manifest.
     */
    public function show(int $id): JsonResponse
    {
        $run = BackupRun::findOrFail($id);
        $manifest = null;

        if ($run->manifest_reference && File::exists($run->manifest_reference)) {
            $manifest = json_decode(File::get($run->manifest_reference), true);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'run' => $run,
                'manifest' => $manifest,
            ],
        ]);
    }

    /**
     * Re-verify SHA-256 integrity of backup artifacts against the manifest.
     */
    public function verify(int $id): JsonResponse
    {
        $run = BackupRun::findOrFail($id);

        if (!$run->manifest_reference || !File::exists($run->manifest_reference)) {
            return response()->json([
                'success' => false,
                'message' => 'Manifest file not found for this backup run.',
            ], 404);
        }

        $manifest = json_decode(File::get($run->manifest_reference), true);
        $backupDir = dirname($run->manifest_reference);

        $dbFile = "{$backupDir}/" . $manifest['database']['file_name'];
        $storageFile = "{$backupDir}/" . $manifest['document_storage']['file_name'];

        $dbHashMatch = File::exists($dbFile) && hash_file('sha256', $dbFile) === $manifest['database']['sha256'];
        $storageHashMatch = File::exists($storageFile) && hash_file('sha256', $storageFile) === $manifest['document_storage']['sha256'];

        $verified = $dbHashMatch && $storageHashMatch;
        $run->update([
            'verification_status' => $verified ? 'verified' : 'corrupted',
        ]);

        AuditEvent::create([
            'actor_id' => auth()->id() ?? 1,
            'action' => 'backup.verified',
            'target_type' => 'backup_run',
            'target_id' => (string) $run->id,
            'request_id' => (string) Str::uuid(),
            'occurred_at' => now()->format('Y-m-d H:i:s.u'),
            'details_json' => [
                'backup_id' => $run->provider_job_reference,
                'database_hash_match' => $dbHashMatch,
                'storage_hash_match' => $storageHashMatch,
                'outcome' => $verified ? 'pass' : 'fail',
            ],
        ]);

        return response()->json([
            'success' => $verified,
            'message' => $verified ? 'Backup integrity verified with 100% SHA-256 match.' : 'Backup artifact integrity check failed!',
            'data' => [
                'database_intact' => $dbHashMatch,
                'storage_intact' => $storageHashMatch,
                'verification_status' => $run->verification_status,
            ],
        ]);
    }

    /**
     * Execute an isolated disaster recovery restoration drill.
     */
    public function runDrill(Request $request, RestorationService $restorationService): JsonResponse
    {
        $validated = $request->validate([
            'backup_id' => 'required|string',
            'secret' => 'nullable|string',
        ]);

        // Safety enforcement: DR Drill ALWAYS targets the isolated recovery database
        $targetDb = 'wu_dms_recovery_test';

        try {
            $drillResult = $restorationService->restoreFromBackup(
                $validated['backup_id'],
                $targetDb,
                null,
                $validated['secret'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Disaster recovery drill completed successfully in isolated test environment.',
                'data' => $drillResult,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Disaster recovery drill failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List historical disaster recovery restoration drills.
     */
    public function drills(): JsonResponse
    {
        $drills = RestoreDrill::with('backupRun')->orderByDesc('id')->get();

        return response()->json([
            'success' => true,
            'data' => $drills,
        ]);
    }
}
