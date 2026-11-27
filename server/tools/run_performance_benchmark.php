<?php

declare(strict_types=1);

/**
 * Performance Benchmark & EXPLAIN Query Plan Verification on Explicitly Configured Disposable Database
 *
 * Measures:
 * 1. p95 HTTP latency over Sanctum authentication for the 5 target endpoints
 * 2. Query count via DB::listen
 * 3. Before and After audit_events index comparison (on the disposable benchmark DB only)
 * 4. Raw EXPLAIN query plans for actor, target, and occurred_at on audit_events
 * 5. Automatic cleanup: Drops the verified disposable benchmark database
 *
 * Usage:
 *   BENCHMARK_DB_DATABASE=wu_dms_perf_tmp php tools/run_performance_benchmark.php
 *   php tools/run_performance_benchmark.php --database=wu_dms_perf_tmp
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Resolve and strictly validate the isolated disposable benchmark database name.
 * Fails closed on missing configuration, invalid identifiers, or protected databases.
 */
function resolveBenchmarkDatabaseName(array $argv): string
{
    $argDb = null;
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--database=')) {
            $argDb = substr($arg, strlen('--database='));
            break;
        }
    }

    $envDb = getenv('BENCHMARK_DB_DATABASE');
    $benchmarkDb = trim((string) ($argDb !== null ? $argDb : ($envDb !== false ? $envDb : '')));

    if ($benchmarkDb === '') {
        fwrite(STDERR, "FAIL CLOSED: No isolated benchmark database configured. Provide BENCHMARK_DB_DATABASE or --database=<name>.\n");
        exit(1);
    }

    if (! preg_match('/^[A-Za-z0-9_]+$/', $benchmarkDb)) {
        fwrite(STDERR, "FAIL CLOSED: Invalid benchmark database identifier '{$benchmarkDb}'.\n");
        exit(1);
    }

    $protectedDatabases = array_filter(array_unique([
        'wu_dms',
        'wu_dms_test',
        'wu_dms_phpunit',
        'wu_dms_e2e',
        'wu_dms_schema_verify',
        'wollo_dms_test',
        'mysql',
        'information_schema',
        'performance_schema',
        'sys',
        (string) config('database.connections.mysql.database'),
        (string) (getenv('DB_DATABASE') !== false ? getenv('DB_DATABASE') : ''),
    ]));

    foreach ($protectedDatabases as $protected) {
        if (strcasecmp($benchmarkDb, $protected) === 0) {
            fwrite(STDERR, "FAIL CLOSED: Database '{$benchmarkDb}' is a protected or configured application/test database.\n");
            exit(1);
        }
    }

    if (! preg_match('/_(?:perf|benchmark)(?:_[A-Za-z0-9_]+)?$/i', $benchmarkDb)) {
        fwrite(STDERR, "FAIL CLOSED: Benchmark database '{$benchmarkDb}' must end with '_perf', '_benchmark', or '_perf_<suffix>' / '_benchmark_<suffix>' to verify disposability.\n");
        exit(1);
    }

    return $benchmarkDb;
}

$originalDb = (string) config('database.connections.mysql.database');
$benchmarkDb = resolveBenchmarkDatabaseName($argv ?? []);

echo "=== INSTITUTIONAL DMS PERFORMANCE BENCHMARK ({$benchmarkDb}) ===\n\n";

// 1. Verify target database identity & disposability before any schema changes
echo "--- 1. VERIFYING ISOLATED DISPOSABLE DATABASE {$benchmarkDb} ---\n";
$existingSchema = DB::selectOne(
    'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?',
    [$benchmarkDb]
);

if ($existingSchema !== null) {
    $tableCount = (int) DB::selectOne(
        'SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ?',
        [$benchmarkDb]
    )->cnt;

    if ($tableCount > 0 && ! in_array('--allow-existing-disposable', $argv ?? [], true)) {
        fwrite(STDERR, "FAIL CLOSED: Database '{$benchmarkDb}' already exists with {$tableCount} tables. Pass --allow-existing-disposable or choose a fresh disposable database name.\n");
        exit(1);
    }
}

DB::statement("CREATE DATABASE IF NOT EXISTS `{$benchmarkDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;");

putenv("DB_DATABASE={$benchmarkDb}");
$_ENV['DB_DATABASE'] = $benchmarkDb;
$_SERVER['DB_DATABASE'] = $benchmarkDb;

DB::purge('mysql');
config(['database.connections.mysql.database' => $benchmarkDb]);
DB::reconnect('mysql');

$activeDb = (string) DB::connection('mysql')->getPdo()->query('SELECT DATABASE()')->fetchColumn();
if (strcasecmp($activeDb, $benchmarkDb) !== 0) {
    fwrite(STDERR, "FAIL CLOSED: Active PDO connection points to '{$activeDb}', expected '{$benchmarkDb}'.\n");
    exit(1);
}
echo "Verified active connection to disposable database: {$activeDb}\n";

// 2. Run the 130 baseline migrations cleanly on the disposable database (no migrate:rollback)
echo "Running standard Laravel migrations on {$benchmarkDb}...\n";
$migrateExit = Artisan::call('migrate', [
    '--database' => 'mysql',
    '--force' => true,
]);
if ($migrateExit !== 0) {
    fwrite(STDERR, "FAIL CLOSED: Migration execution failed on '{$benchmarkDb}': " . Artisan::output() . "\n");
    exit(1);
}
echo "Migrated {$benchmarkDb} schema cleanly (" . DB::table('migrations')->count() . " migrations recorded).\n";

// Capture BEFORE baseline for audit_events index comparison by dropping only audit_events secondary indexes on this disposable DB
echo "Temporarily removing audit_events secondary indexes on {$benchmarkDb} to capture BEFORE baseline...\n";
$auditIndexesToToggle = [
    'audit_events_ix_2' => 'CREATE INDEX `audit_events_ix_2` ON `audit_events` (`actor_id`, `occurred_at`)',
    'audit_events_ix_4' => 'CREATE INDEX `audit_events_ix_4` ON `audit_events` (`occurred_at`)',
    'audit_events_ix_1' => 'CREATE INDEX `audit_events_ix_1` ON `audit_events` (`target_type`, `target_id`, `occurred_at`)',
];
foreach (array_keys($auditIndexesToToggle) as $indexName) {
    $exists = DB::selectOne(
        'SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
        [$benchmarkDb, 'audit_events', $indexName]
    );
    if ($exists && (int) $exists->cnt > 0) {
        DB::statement("ALTER TABLE `audit_events` DROP INDEX `{$indexName}`");
    }
}

// Seed reference data
echo "Seeding baseline vocabularies and test datasets...\n";
Artisan::call('db:seed', ['--class' => 'BaselineReferenceVocabularySeeder', '--force' => true]);
Artisan::call('db:seed', ['--class' => 'InstitutionalCredentialSeeder', '--force' => true]);
Artisan::call('db:seed', ['--class' => 'WorkflowBaselineSeeder', '--force' => true]);
Artisan::call('db:seed', ['--class' => 'ResearchProjectWorkflowSeeder', '--force' => true]);
Artisan::call('db:seed', ['--class' => 'TestDocumentsSeeder', '--force' => true]);
echo "{$benchmarkDb} populated successfully.\n";

// 3. Dedicated HTTP server on port 8002 pointing to the disposable benchmark DB
$port = 8002;
$fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
if (! $fp) {
    echo "Starting dedicated HTTP server on 127.0.0.1:{$port} (DB: {$benchmarkDb})...\n";
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        pclose(popen("cmd /c \"set DB_DATABASE={$benchmarkDb}&& start /B php artisan serve --host=127.0.0.1 --port={$port} > NUL 2>&1\"", 'r'));
    } else {
        exec("DB_DATABASE={$benchmarkDb} php artisan serve --host=127.0.0.1 --port={$port} > /dev/null 2>&1 &");
    }
    sleep(3);
} else {
    fclose($fp);
}
echo "Connected to dedicated HTTP server on 127.0.0.1:{$port} (DB: {$benchmarkDb}).\n";

class PerfSanctumClient
{
    protected string $baseUrl;
    protected array $cookies = [];

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    public function getCsrfCookie(): void
    {
        $this->request('GET', '/sanctum/csrf-cookie');
    }

    public function postJson(string $path, array $data = []): array
    {
        return $this->request('POST', $path, $data, true);
    }

    public function getJson(string $path): array
    {
        return $this->request('GET', $path);
    }

    protected function request(string $method, string $path, array $data = [], bool $isJson = false): array
    {
        $headers = [
            'Accept: application/json',
            'Origin: http://localhost:5173',
            'Referer: http://localhost:5173/',
        ];
        if (! empty($this->cookies['XSRF-TOKEN'])) {
            $headers[] = 'X-XSRF-TOKEN: ' . urldecode($this->cookies['XSRF-TOKEN']);
        }
        if (! empty($this->cookies)) {
            $cPairs = [];
            foreach ($this->cookies as $k => $v) {
                $cPairs[] = "$k=$v";
            }
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
        if (! empty($data)) {
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

function measureEndpoint(PerfSanctumClient $client, string $name, string $endpoint, int $iterations = 20): array
{
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

function countQueriesForEndpoint(string $endpoint, int &$globalQueryCount, bool &$countingQueries): int
{
    $admin = \App\Models\User::where('email', 'admin@wu.edu.et')->first();
    \Laravel\Sanctum\Sanctum::actingAs($admin, ['*']);

    $globalQueryCount = 0;
    $countingQueries = true;

    $req = \Illuminate\Http\Request::create($endpoint, 'GET');
    $req->setUserResolver(fn () => $admin);
    $req->headers->set('Accept', 'application/json');

    try {
        app()->handle($req);
    } catch (\Throwable $e) {
    }

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

// 5. Restore audit_events secondary indexes on the disposable DB
echo "\n--- 3. RESTORING AUDIT_EVENTS PERFORMANCE INDEXES ---\n";
foreach ($auditIndexesToToggle as $indexName => $createSql) {
    DB::statement($createSql);
    echo "Restored index: {$indexName}\n";
}

// 6. EXPLAIN Query Plans on audit_events
echo "\n--- 4. EXPLAIN QUERY PLANS ON AUDIT_EVENTS ---\n";
for ($i = 1; $i <= 50; $i++) {
    DB::table('audit_events')->insert([
        'actor_id' => ($i % 3 === 0) ? 1 : 2,
        'action' => 'document.view',
        'target_type' => 'document',
        'target_id' => (string) $i,
        'request_id' => (string) Str::uuid(),
        'occurred_at' => now()->subMinutes($i)->format('Y-m-d H:i:s.u'),
    ]);
}

echo "\nA) EXPLAIN: Actor Query (SELECT id, action, occurred_at FROM audit_events WHERE actor_id = 1):\n";
foreach (DB::select('EXPLAIN SELECT id, action, occurred_at FROM audit_events WHERE actor_id = 1') as $row) {
    echo "   table: {$row->table} | type: {$row->type} | possible_keys: {$row->possible_keys} | key: {$row->key} | rows: {$row->rows} | Extra: {$row->Extra}\n";
}

echo "\nB) EXPLAIN: Target Query (SELECT id, action, occurred_at FROM audit_events WHERE target_type = 'document' AND target_id = '10'):\n";
foreach (DB::select("EXPLAIN SELECT id, action, occurred_at FROM audit_events WHERE target_type = 'document' AND target_id = '10'") as $row) {
    echo "   table: {$row->table} | type: {$row->type} | possible_keys: {$row->possible_keys} | key: {$row->key} | rows: {$row->rows} | Extra: {$row->Extra}\n";
}

echo "\nC) EXPLAIN: Occurred At Range Query (SELECT id, action, occurred_at FROM audit_events WHERE occurred_at >= '2026-01-01 00:00:00' ORDER BY occurred_at DESC LIMIT 20):\n";
foreach (DB::select("EXPLAIN SELECT id, action, occurred_at FROM audit_events WHERE occurred_at >= '2026-01-01 00:00:00' ORDER BY occurred_at DESC LIMIT 20") as $row) {
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
echo "\n=== PERFORMANCE COMPARISON TABLE ({$benchmarkDb}) ===\n";
printf("%-18s | %-12s | %-12s | %-14s | %-14s | %s\n", 'Endpoint', 'Before p95', 'After p95', 'Before Queries', 'After Queries', 'Status');
echo str_repeat('-', 85) . "\n";
foreach ($targetEndpoints as $name => $ep) {
    $b = $beforeResults[$name];
    $a = $afterResults[$name];
    printf(
        "%-18s | %8.2f ms  | %8.2f ms  | %14d | %14d | HTTP %d\n",
        $name,
        $b['p95'],
        $a['p95'],
        $b['query_count'],
        $a['query_count'],
        $a['status']
    );
}

// 9. Teardown & Drop verified disposable benchmark database only
echo "\n--- 6. TEARDOWN & CLEANUP ---\n";
if (! preg_match('/_(?:perf|benchmark)(?:_[A-Za-z0-9_]+)?$/i', $benchmarkDb)) {
    fwrite(STDERR, "FAIL CLOSED: Refusing to drop non-disposable database '{$benchmarkDb}'.\n");
    exit(1);
}

DB::purge('mysql');
config(['database.connections.mysql.database' => $originalDb]);
DB::reconnect('mysql');

DB::statement("DROP DATABASE IF EXISTS `{$benchmarkDb}`;");
echo "Dropped disposable benchmark database {$benchmarkDb}.\n";

echo "\nPerformance benchmark completed successfully.\n";
