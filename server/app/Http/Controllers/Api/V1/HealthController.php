<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BackupRun;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    /**
     * Public Liveness Probe (Minimal information exposure).
     *
     * Returns only the minimal signal required by institutional reverse proxies,
     * load balancers, and external uptime monitors to establish that the PHP runtime
     * and HTTP application pipeline are operational.
     *
     * Strictly does NOT expose: database details, internal paths, environment keys,
     * credentials, topology, queue payloads, or exception traces.
     */
    public function check(Request $request): JsonResponse
    {
        // Basic ping to verify application runtime is responding
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'application' => [
                'name' => 'Wollo University DMS',
                'version' => '1.0.0',
            ],
        ], 200);
    }

    /**
     * Restricted Operational Readiness Probe.
     *
     * Requires administrative authorization. Inspects database connectivity, latency,
     * queue backlogs, storage writability, scheduler heartbeats, and enterprise backup status.
     * Sanitized against infrastructure leakage.
     */
    public function readiness(Request $request): JsonResponse
    {
        $actor = $request->user();

        if (! $actor) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Operational readiness diagnostics require authentication.',
            ], 401);
        }

        $hasAdminCapability = $actor->hasPermission('system.configure');

        if (! $hasAdminCapability) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Operational readiness diagnostics require administrative privileges.',
            ], 403);
        }

        $overallStatus = 'healthy';
        $diagnostics = [];

        // 1. Database Connectivity & Latency (no credentials or host names exposed)
        $dbStart = microtime(true);
        try {
            DB::select('SELECT 1');
            $dbLatencyMs = round((microtime(true) - $dbStart) * 1000, 2);
            $diagnostics['database'] = [
                'status' => 'up',
                'latency_ms' => $dbLatencyMs,
            ];
        } catch (\Throwable) {
            $overallStatus = 'unhealthy';
            $diagnostics['database'] = [
                'status' => 'down',
            ];
        }

        // 2. Cache Store Readiness
        try {
            $probeKey = 'health_readiness_probe_' . bin2hex(random_bytes(4));
            Cache::put($probeKey, 'ok', 10);
            $cacheUp = Cache::get($probeKey) === 'ok';
            Cache::forget($probeKey);
            $diagnostics['cache'] = [
                'status' => $cacheUp ? 'up' : 'degraded',
            ];
            if (! $cacheUp && $overallStatus !== 'unhealthy') {
                $overallStatus = 'degraded';
            }
        } catch (\Throwable) {
            $diagnostics['cache'] = ['status' => 'down'];
            if ($overallStatus !== 'unhealthy') {
                $overallStatus = 'degraded';
            }
        }

        // 3. Storage Availability (writability check, no filesystem paths exposed)
        try {
            $disk = config('filesystems.default', 'local');
            $testFile = 'probe_' . bin2hex(random_bytes(4)) . '.tmp';
            Storage::disk($disk)->put($testFile, 'probe');
            $storageUp = Storage::disk($disk)->exists($testFile);
            Storage::disk($disk)->delete($testFile);

            $diagnostics['storage'] = [
                'status' => $storageUp ? 'up' : 'degraded',
            ];
            if (! $storageUp && $overallStatus !== 'unhealthy') {
                $overallStatus = 'degraded';
            }
        } catch (\Throwable) {
            $diagnostics['storage'] = ['status' => 'down'];
            if ($overallStatus !== 'unhealthy') {
                $overallStatus = 'degraded';
            }
        }

        // 4. Queue Status (counts only, no payload inspection)
        try {
            $pendingJobs = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();

            $diagnostics['queue'] = [
                'status' => ($failedJobs > 20) ? 'degraded' : 'up',
                'pending_jobs' => $pendingJobs,
                'failed_jobs' => $failedJobs,
            ];
            if ($failedJobs > 20 && $overallStatus !== 'unhealthy') {
                $overallStatus = 'degraded';
            }
        } catch (\Throwable) {
            $diagnostics['queue'] = ['status' => 'unknown'];
        }

        // 5. Scheduler Heartbeat
        $heartbeat = Cache::get('scheduler_heartbeat');
        $schedulerActive = false;
        if ($heartbeat) {
            try {
                $heartbeatTime = Carbon::parse($heartbeat);
                $schedulerActive = $heartbeatTime->diffInMinutes(now()) <= 5;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Scheduler heartbeat parse failed: ' . $e->getMessage());
            }
        }

        $diagnostics['scheduler'] = [
            'status' => $schedulerActive ? 'active' : ($heartbeat ? 'stale' : 'unobserved'),
            'last_heartbeat' => $heartbeat,
        ];

        // 6. Enterprise Backup Status (latest run metadata)
        try {
            $latestBackup = BackupRun::orderByDesc('id')->first();
            $diagnostics['backup'] = [
                'status' => $latestBackup ? $latestBackup->status : 'no_backups_recorded',
                'verification_status' => $latestBackup ? $latestBackup->verification_status : 'unverified',
                'last_completed_at' => $latestBackup?->completed_at?->toIso8601String(),
            ];
        } catch (\Throwable) {
            $diagnostics['backup'] = ['status' => 'unknown'];
        }

        $statusCode = ($overallStatus === 'unhealthy') ? 503 : 200;

        return response()->json([
            'status' => $overallStatus,
            'timestamp' => now()->toIso8601String(),
            'diagnostics' => $diagnostics,
            'application' => [
                'name' => 'Wollo University DMS',
                'version' => '1.0.0',
            ],
        ], $statusCode);
    }
}
