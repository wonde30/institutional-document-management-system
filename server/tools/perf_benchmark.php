<?php

declare(strict_types=1);

/**
 * Phase 1C: Performance Benchmark Script
 * Creates a throwaway database wu_dms_perf, seeds 5,000 documents,
 * 20,000 audit events, and 2,000 workflow tasks, measures endpoint p95,
 * and drops wu_dms_perf upon completion.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

echo "=== PHASE 1C: DATABASE & ENDPOINT PERFORMANCE BENCHMARK ===" . PHP_EOL . PHP_EOL;

$defaultConn = Config::get('database.default');
$defaultDb = Config::get("database.connections.{$defaultConn}.database");

echo "Default application database: {$defaultDb}" . PHP_EOL;
if ($defaultDb !== 'wu_dms_test') {
    echo "ERROR: Expected default app database to be wu_dms_test!" . PHP_EOL;
    exit(1);
}

$perfDb = 'wu_dms_perf';
echo "Target throwaway database: {$perfDb}" . PHP_EOL . PHP_EOL;

// 1. Create wu_dms_perf
echo "1. Creating throwaway database {$perfDb}..." . PHP_EOL;
DB::statement("CREATE DATABASE IF NOT EXISTS `{$perfDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

// Switch database connection dynamically to wu_dms_perf
Config::set("database.connections.{$defaultConn}.database", $perfDb);
DB::purge($defaultConn);
DB::reconnect($defaultConn);

echo "Connected to: " . DB::connection()->getDatabaseName() . PHP_EOL;

// 2. Run migrations
echo "2. Running migrations on {$perfDb}..." . PHP_EOL;
Artisan::call('migrate:fresh', ['--force' => true]);
echo "Migrations completed." . PHP_EOL;

// 3. Seed baseline vocabulary
echo "3. Seeding baseline vocabulary..." . PHP_EOL;
Artisan::call('db:seed', ['--class' => 'BaselineReferenceVocabularySeeder', '--force' => true]);
Artisan::call('db:seed', ['--class' => 'WorkflowBaselineSeeder', '--force' => true]);
echo "Baseline reference vocabulary and workflows seeded." . PHP_EOL;

// 4. Bulk seed 5,000 documents, 20,000 audit events, 2,000 workflow tasks
echo "4. Bulk seeding performance dataset..." . PHP_EOL;
$now = now()->format('Y-m-d H:i:s.u');

// Get reference IDs
$unitIds = DB::table('organization_units')->pluck('id')->all();
$catIds = DB::table('document_categories')->pluck('id')->all();
$confIds = DB::table('confidentiality_levels')->pluck('id')->all();
$userId = DB::table('users')->value('id') ?? 1;

// Seed Documents in chunks of 500
echo "  Seeding 5,000 documents..." . PHP_EOL;
$docBatch = [];
for ($i = 1; $i <= 5000; $i++) {
    $unitId = $unitIds[$i % count($unitIds)];
    $catId = $catIds[$i % count($catIds)];
    $confId = $confIds[$i % count($confIds)];
    $year = 2026;
    $ref = sprintf('WU/DOC/%d/%04d/%05d', $year, $unitId, $i);

    $docBatch[] = [
        'reference_number' => $ref,
        'title' => ($i % 5 === 0 ? 'Academic Policy Document ' : ($i % 3 === 0 ? 'Financial Procurement Audit ' : 'Administrative Institutional Notice ')) . $i,
        'summary' => 'Comprehensive institutional record generated for benchmark analysis ' . $i,
        'category_id' => $catId,
        'confidentiality_id' => $confId,
        'owner_unit_id' => $unitId,
        'custodian_id' => $userId,
        'created_by' => $userId,
        'document_date' => date('Y-m-d', strtotime("-{$i} hours")),
        'registered_at' => $now,
        'lifecycle' => ($i % 20 === 0 ? 'retired' : 'active'),
        'lock_version' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ];

    if (count($docBatch) >= 500) {
        DB::table('documents')->insert($docBatch);
        $docBatch = [];
    }
}
if (!empty($docBatch)) {
    DB::table('documents')->insert($docBatch);
}
echo "  Documents seeded: " . DB::table('documents')->count() . PHP_EOL;

// Seed Document Versions for first 2000 documents
echo "  Seeding 2,000 document versions..." . PHP_EOL;
$verBatch = [];
for ($i = 1; $i <= 2000; $i++) {
    $verBatch[] = [
        'document_id' => $i,
        'version_number' => 1,
        'created_by' => $userId,
        'change_note' => 'Initial version',
        'created_at' => $now,
    ];
    if (count($verBatch) >= 500) {
        DB::table('document_versions')->insert($verBatch);
        $verBatch = [];
    }
}
if (!empty($verBatch)) {
    DB::table('document_versions')->insert($verBatch);
}

// Seed 2,000 workflow instances & tasks
echo "  Seeding 2,000 workflow tasks..." . PHP_EOL;
$wfDefId = DB::table('workflow_definitions')->value('id') ?? 1;
$wfVerId = DB::table('workflow_versions')->where('definition_id', $wfDefId)->value('id') ?? 1;
$wfStateId = DB::table('workflow_states')->where('workflow_version_id', $wfVerId)->value('id') ?? 1;

$instBatch = [];
for ($i = 1; $i <= 2000; $i++) {
    $instBatch[] = [
        'workflow_version_id' => $wfVerId,
        'document_version_id' => $i,
        'started_by' => $userId,
        'started_at' => $now,
    ];
    if (count($instBatch) >= 500) {
        DB::table('workflow_instances')->insert($instBatch);
        $instBatch = [];
    }
}
if (!empty($instBatch)) {
    DB::table('workflow_instances')->insert($instBatch);
}

$taskBatch = [];
for ($i = 1; $i <= 2000; $i++) {
    $taskBatch[] = [
        'instance_id' => $i,
        'state_id' => $wfStateId,
        'assigned_user_id' => $userId,
        'assigned_unit_id' => $unitIds[$i % count($unitIds)],
        'created_at' => $now,
    ];
    if (count($taskBatch) >= 500) {
        DB::table('workflow_tasks')->insert($taskBatch);
        $taskBatch = [];
    }
}
if (!empty($taskBatch)) {
    DB::table('workflow_tasks')->insert($taskBatch);
}
echo "  Workflow tasks seeded: " . DB::table('workflow_tasks')->count() . PHP_EOL;

// Seed 20,000 audit events
echo "  Seeding 20,000 audit events..." . PHP_EOL;
$auditBatch = [];
$actions = ['document.created', 'document.viewed', 'document.downloaded', 'workflow.approved', 'user.login'];
for ($i = 1; $i <= 20000; $i++) {
    $action = $actions[$i % count($actions)];
    $auditBatch[] = [
        'actor_id' => $userId,
        'action' => $action,
        'target_type' => 'document',
        'target_id' => (string) (($i % 5000) + 1),
        'request_id' => (string) Str::uuid(),
        'occurred_at' => date('Y-m-d H:i:s.u', strtotime("-{$i} seconds")),
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Mozilla/5.0 Benchmarking Client',
        'details_json' => json_encode(['iteration' => $i]),
    ];
    if (count($auditBatch) >= 1000) {
        DB::table('audit_events')->insert($auditBatch);
        $auditBatch = [];
    }
}
if (!empty($auditBatch)) {
    DB::table('audit_events')->insert($auditBatch);
}
echo "  Audit events seeded: " . DB::table('audit_events')->count() . PHP_EOL . PHP_EOL;

// 5. Benchmark queries and endpoint queries (25 iterations each)
echo "5. Measuring p95 latency across operational endpoints..." . PHP_EOL;

function measureP95(callable $fn, int $iterations = 25): float {
    $times = [];
    // Warm up
    $fn();
    for ($i = 0; $i < $iterations; $i++) {
        $start = microtime(true);
        $fn();
        $elapsed = (microtime(true) - $start) * 1000.0; // ms
        $times[] = $elapsed;
    }
    sort($times);
    $idx = (int) ceil(0.95 * count($times)) - 1;
    return round($times[$idx], 2);
}

// A. Document Listing p95
$listP95 = measureP95(function () use ($unitIds) {
    DB::table('documents')
        ->join('document_categories', 'documents.category_id', '=', 'document_categories.id')
        ->join('confidentiality_levels', 'documents.confidentiality_id', '=', 'confidentiality_levels.id')
        ->where('documents.owner_unit_id', $unitIds[0])
        ->where('documents.lifecycle', 'active')
        ->orderByDesc('documents.registered_at')
        ->limit(25)
        ->get(['documents.id', 'documents.reference_number', 'documents.title', 'documents.registered_at', 'document_categories.name as category_name']);
});

// B. Document Search p95
$searchP95 = measureP95(function () use ($unitIds) {
    DB::table('documents')
        ->where('owner_unit_id', $unitIds[0])
        ->where(function ($q) {
            $q->where('title', 'like', '%Academic%')
              ->orWhere('reference_number', 'like', '%DOC%');
        })
        ->orderByDesc('registered_at')
        ->limit(25)
        ->get(['id', 'reference_number', 'title', 'registered_at']);
});

// C. Dashboard Metrics p95
$dashboardP95 = measureP95(function () use ($unitIds, $userId) {
    $activeDocs = DB::table('documents')->where('owner_unit_id', $unitIds[0])->where('lifecycle', 'active')->count();
    $openTasks = DB::table('workflow_tasks')->where('assigned_user_id', $userId)->whereNull('closed_at')->count();
    $archivedDocs = DB::table('documents')->where('owner_unit_id', $unitIds[0])->where('lifecycle', 'retired')->count();
    $recentEvents = DB::table('audit_events')->where('actor_id', $userId)->orderByDesc('occurred_at')->limit(5)->get();
});

// D. Export Operation Query p95
$exportP95 = measureP95(function () use ($unitIds) {
    DB::table('documents')
        ->join('document_categories', 'documents.category_id', '=', 'document_categories.id')
        ->where('documents.owner_unit_id', $unitIds[0])
        ->select(['documents.id', 'documents.reference_number', 'documents.title', 'documents.document_date', 'document_categories.name as category_name'])
        ->limit(500)
        ->get();
});

echo PHP_EOL . "=== PERFORMANCE BENCHMARK RESULTS (5,000 Docs / 20,000 Audits / 2,000 Tasks) ===" . PHP_EOL;
echo sprintf("%-30s | %-15s | %-15s\n", 'Operation / Endpoint', 'Target SLA', 'Observed p95 (ms)');
echo str_repeat('-', 65) . PHP_EOL;
echo sprintf("%-30s | %-15s | %-15s\n", 'Document List (Filtered & Join)', '< 100ms', $listP95 . ' ms');
echo sprintf("%-30s | %-15s | %-15s\n", 'Document Search (ILIKE/Wildcard)', '< 150ms', $searchP95 . ' ms');
echo sprintf("%-30s | %-15s | %-15s\n", 'Dashboard Metrics Aggregation', '< 80ms', $dashboardP95 . ' ms');
echo sprintf("%-30s | %-15s | %-15s\n", 'Dataset Export Query (500 rows)', '< 200ms', $exportP95 . ' ms');
echo str_repeat('-', 65) . PHP_EOL . PHP_EOL;

// 6. Clean up: Drop throwaway database
echo "6. Dropping throwaway database {$perfDb}..." . PHP_EOL;
Config::set("database.connections.{$defaultConn}.database", $defaultDb);
DB::purge($defaultConn);
DB::reconnect($defaultConn);

DB::statement("DROP DATABASE IF EXISTS `{$perfDb}`");
echo "Database {$perfDb} dropped cleanly. Default app database {$defaultDb} preserved." . PHP_EOL;
