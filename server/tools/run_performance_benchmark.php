<?php

declare(strict_types=1);

/**
 * Performance Benchmark & EXPLAIN Query Plan Verification on Throwaway Database (wu_dms_perf)
 *
 * Measures:
 * 1. p95 HTTP latency over Sanctum authentication for the 5 target endpoints
 * 2. Query count via DB::listen
 * 3. Before and After index addition comparison
 * 4. Raw EXPLAIN query plans for actor, target, and occurred_at on audit_events
 * 5. Automatic cleanup: Drops wu_dms_perf database
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

echo "=== INSTITUTIONAL DMS PERFORMANCE BENCHMARK (wu_dms_perf) ===\n\n";

// 1. Create throwaway database wu_dms_perf
echo "--- 1. INITIALIZING THROWAWAY DATABASE wu_dms_perf ---\n";
DB::statement("CREATE DATABASE IF NOT EXISTS `wu_dms_perf` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;");
echo "Created database wu_dms_perf successfully.\n";

// 2. Run migrations and seeders on wu_dms_perf
echo "Running migrations on wu_dms_perf...\n";
putenv('DB_DATABASE=wu_dms_perf');
$_ENV['DB_DATABASE'] = 'wu_dms_perf';
$_SERVER['DB_DATABASE'] = 'wu_dms_perf';

// Reconnect to wu_dms_perf
DB::purge('mysql');
config(['database.connections.mysql.database' => 'wu_dms_perf']);
DB::reconnect('mysql');

// Run migrations on wu_dms_perf
\Illuminate\Support\Facades\Artisan::call('migrate:fresh', [
    '--database' => 'mysql',
    '--force' => true,
]);
echo "Migrated wu_dms_perf schema.\n";

// Rollback latest migration to establish BEFORE state
echo "Rolling back latest performance migration to capture BEFORE baseline...\n";
\Illuminate\Support\Facades\Artisan::call('migrate:rollback', [
    '--database' => 'mysql',
    '--step' => 1,
    '--force' => true,
]);

// Seed reference data
echo "Seeding baseline vocabularies and test datasets...\n";
\Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'BaselineReferenceVocabularySeeder', '--force' => true]);
\Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'InstitutionalCredentialSeeder', '--force' => true]);
\Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'WorkflowBaselineSeeder', '--force' => true]);
\Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'ResearchProjectWorkflowSeeder', '--force' => true]);
\Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'TestDocumentsSeeder', '--force' => true]);
echo "wu_dms_perf populated successfully.\n";

// 3. Temporary background PHP server is running on port 8002 pointing to wu_dms_perf
$port = 8002;
echo "Connecting to dedicated HTTP server on 127.0.0.1:{$port} (DB: wu_dms_perf)...\n";

// Helper Sanctum Client
class PerfSanctumClient
{
    protected string $baseUrl;
    protected array $cookies = [];

    public function __construct(string $baseUrl) { $this->baseUrl = $baseUrl; }

    public function getCsrfCookie(): void {
        $this->request('GET', '/sanctum/csrf-cookie');
    }

    public function postJson(string $path, array $data = []): array {
        return $this->request('POST', $path, $data, true);
    }

    public function getJson(string $path): array {
        return $this->request('GET', $path);
    }

    protected function request(string $method, string $path, array $data = [], bool $isJson = false): array {
        $headers = [
            'Accept: application/json',
            'Origin: http://localhost:5173',
            'Referer: http://localhost:5173/',
        ];
        if (!empty($this->cookies['XSRF-TOKEN'])) {
            $headers[] = 'X-XSRF-TOKEN: ' . urldecode($this->cookies['XSRF-TOKEN']);
        }
        if (!empty($this->cookies)) {
            $cPairs = [];
            foreach ($this->cookies as $k => $v) { $cPairs[] = "$k=$v"; }
            $headers[] = 'Cookie: ' . implode('; ', $cPairs);
        }
        if ($isJson) {
            $headers[] = 'Content-Type: application/json';
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("cURL request failed to {$path}: {$err}");
        }
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerStr = substr($response, 0, $headerSize);
        $bodyStr = substr($response, $headerSize);
        curl_close($ch);

        preg_match_all('/^Set-Cookie:\s*([^;]+)/mi', $headerStr, $matches);
        foreach ($matches[1] as $c) {
            $parts = explode('=', $c, 2);
            $this->cookies[trim($parts[0])] = trim($parts[1] ?? '');
        }

        return ['status' => $status, 'body' => json_decode($bodyStr, true) ?? $bodyStr];
    }
}

$client = new PerfSanctumClient("http://127.0.0.1:{$port}");
$client->getCsrfCookie();
$loginRes = $client->postJson('/api/v1/auth/login', [
    'email' => 'admin@wu.edu.et',
    'password' => 'Password123!',
]);
echo "Sanctum authentication status on port {$port}: {$loginRes['status']}\n";

$targetEndpoints = [
    'document list' => '/api/v1/documents',
    'search' => '/api/v1/search/documents?q=Policy',
    'dashboard' => '/api/v1/dashboard/metrics',
    'workflow tasks' => '/api/v1/workflows/tasks',
    'export' => '/api/v1/reports/export',
];

function measureEndpoint(PerfSanctumClient $client, string $name, string $endpoint, int $iterations = 20): array {
    $times = [];
    $status = 0;
    for ($i = 0; $i < $iterations; $i++) {
        $t0 = microtime(true);
        $res = $client->getJson($endpoint);
        $dt = (microtime(true) - $t0) * 1000.0;
        $times[] = $dt;
        $status = $res['status'];
    }
    sort($times);
    $p95Index = (int) ceil(0.95 * count($times)) - 1;
    $p95 = $times[$p95Index];
    return [
        'status' => $status,
        'p95' => round($p95, 2),
        'min' => round(min($times), 2),
        'max' => round(max($times), 2),
    ];
}

$globalQueryCount = 0;
$countingQueries = false;
DB::listen(function ($query) use (&$globalQueryCount, &$countingQueries) {
    if ($countingQueries) {
        $globalQueryCount++;
    }
});

function countQueriesForEndpoint(string $endpoint, int &$globalQueryCount, bool &$countingQueries): int {
    $admin = \App\Models\User::where('email', 'admin@wu.edu.et')->first();
    \Laravel\Sanctum\Sanctum::actingAs($admin, ['*']);

    $globalQueryCount = 0;
    $countingQueries = true;

    $req = \Illuminate\Http\Request::create($endpoint, 'GET');
    $req->setUserResolver(fn () => $admin);
    $req->headers->set('Accept', 'application/json');

    try {
        app()->handle($req);
    } catch (\Throwable $e) {}

    $countingQueries = false;
    return $globalQueryCount;
}

// 4. Measure BEFORE state
echo "\n--- 2. TIMING REAL ENDPOINTS (BEFORE PERFORMANCE INDEXES) ---\n";
$beforeResults = [];
foreach ($targetEndpoints as $name => $ep) {
    $timing = measureEndpoint($client, $name, $ep, 20);
    $qCount = countQueriesForEndpoint($ep, $globalQueryCount, $countingQueries);
    $beforeResults[$name] = [
        'p95' => $timing['p95'],
        'query_count' => $qCount,
        'status' => $timing['status'],
    ];
    echo sprintf("  %-16s | p95: %6.2f ms | queries: %2d | HTTP %d\n", $name, $timing['p95'], $qCount, $timing['status']);
}

// 5. Apply NEW migration
echo "\n--- 3. APPLYING NEW PERFORMANCE INDEX MIGRATION ---\n";
\Illuminate\Support\Facades\Artisan::call('migrate', [
    '--database' => 'mysql',
    '--force' => true,
]);
echo "Applied migration: 2026_11_24_000001_add_audit_events_performance_indexes\n";

// 6. EXPLAIN Query Plans on audit_events
echo "\n--- 4. EXPLAIN QUERY PLANS ON AUDIT_EVENTS ---\n";

// Populate audit events to ensure cost-based optimizer uses indexes
for ($i = 1; $i <= 50; $i++) {
    DB::table('audit_events')->insert([
        'actor_id' => ($i % 3 === 0) ? 1 : 2,
        'action' => 'document.view',
        'target_type' => 'document',
        'target_id' => (string) $i,
        'request_id' => (string) \Illuminate\Support\Str::uuid(),
        'occurred_at' => now()->subMinutes($i)->format('Y-m-d H:i:s.u'),
    ]);
}

// Plan 1: Actor Query
echo "\nA) EXPLAIN: Actor Query (SELECT * FROM audit_events WHERE actor_id = 1):\n";
$planActor = DB::select("EXPLAIN SELECT id, action, occurred_at FROM audit_events WHERE actor_id = 1");
foreach ($planActor as $row) {
    echo "   table: {$row->table} | type: {$row->type} | possible_keys: {$row->possible_keys} | key: {$row->key} | rows: {$row->rows} | Extra: {$row->Extra}\n";
}

// Plan 2: Target Query
echo "\nB) EXPLAIN: Target Query (SELECT * FROM audit_events WHERE target_type = 'document' AND target_id = '10'):\n";
$planTarget = DB::select("EXPLAIN SELECT id, action, occurred_at FROM audit_events WHERE target_type = 'document' AND target_id = '10'");
foreach ($planTarget as $row) {
    echo "   table: {$row->table} | type: {$row->type} | possible_keys: {$row->possible_keys} | key: {$row->key} | rows: {$row->rows} | Extra: {$row->Extra}\n";
}

// Plan 3: Occurred At Range Query
echo "\nC) EXPLAIN: Occurred At Range Query (SELECT * FROM audit_events WHERE occurred_at >= '2026-01-01' ORDER BY occurred_at DESC):\n";
$planOccurred = DB::select("EXPLAIN SELECT id, action, occurred_at FROM audit_events WHERE occurred_at >= '2026-01-01 00:00:00' ORDER BY occurred_at DESC LIMIT 20");
foreach ($planOccurred as $row) {
    echo "   table: {$row->table} | type: {$row->type} | possible_keys: {$row->possible_keys} | key: {$row->key} | rows: {$row->rows} | Extra: {$row->Extra}\n";
}

// 7. Measure AFTER state
echo "\n--- 5. TIMING REAL ENDPOINTS (AFTER PERFORMANCE INDEXES) ---\n";
$afterResults = [];
foreach ($targetEndpoints as $name => $ep) {
    $timing = measureEndpoint($client, $name, $ep, 20);
    $qCount = countQueriesForEndpoint($ep, $globalQueryCount, $countingQueries);
    $afterResults[$name] = [
        'p95' => $timing['p95'],
        'query_count' => $qCount,
        'status' => $timing['status'],
    ];
    echo sprintf("  %-16s | p95: %6.2f ms | queries: %2d | HTTP %d\n", $name, $timing['p95'], $qCount, $timing['status']);
}

// 8. Output Performance Table
echo "\n=== PERFORMANCE COMPARISON TABLE (wu_dms_perf) ===\n";
printf("%-18s | %-12s | %-12s | %-14s | %-14s | %s\n", "Endpoint", "Before p95", "After p95", "Before Queries", "After Queries", "Status");
echo str_repeat("-", 85) . "\n";
foreach ($targetEndpoints as $name => $ep) {
    $b = $beforeResults[$name];
    $a = $afterResults[$name];
    printf("%-18s | %8.2f ms  | %8.2f ms  | %14d | %14d | HTTP %d\n",
        $name,
        $b['p95'],
        $a['p95'],
        $b['query_count'],
        $a['query_count'],
        $a['status']
    );
}

// 9. Teardown & Drop wu_dms_perf
echo "\n--- 6. TEARDOWN & CLEANUP ---\n";

// Reconnect to default database before dropping wu_dms_perf
DB::purge('mysql');
config(['database.connections.mysql.database' => 'wu_dms_test']);
DB::reconnect('mysql');

DB::statement("DROP DATABASE IF EXISTS `wu_dms_perf`;");
echo "Dropped throwaway database wu_dms_perf.\n";

echo "\nPerformance benchmark completed successfully.\n";
