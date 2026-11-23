<?php

declare(strict_types=1);

/**
 * WU-DMS Performance Benchmark on Throwaway Database wu_dms_perf
 * 
 * Verifies DB-01 migration with:
 * - 5,000 Document records
 * - 20,000 AuditEvent records
 * - 2,000 WorkflowTask records
 * - Module status records (research, projects, backups, imports/exports)
 * 
 * Reports p95 latency and query counts before and after DB-01.
 * Runs EXPLAIN on real queries, verifies index utilization, and cleans up throwaway database.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

echo "=== INITIALIZING WU-DMS PERFORMANCE BENCHMARK ===" . PHP_EOL;

// 1. Setup throwaway database wu_dms_perf
$defaultDb = Config::get('database.connections.mysql.database');
echo "Default application database: {$defaultDb}" . PHP_EOL;

if ($defaultDb === 'wu_dms_perf') {
    die("FATAL: App DB must not be wu_dms_perf" . PHP_EOL);
}

echo "Creating throwaway database 'wu_dms_perf'..." . PHP_EOL;
DB::connection('mysql')->statement('CREATE DATABASE IF NOT EXISTS wu_dms_perf CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');

// Configure dynamic 'perf' connection
$perfConfig = Config::get('database.connections.mysql');
$perfConfig['database'] = 'wu_dms_perf';
Config::set('database.connections.perf', $perfConfig);
DB::purge('perf');

// Mandatory check: Print the DB name before wipe, migrate or seed
$activePerfDb = DB::connection('perf')->getDatabaseName();
echo "============================================================" . PHP_EOL;
echo "PRINTING DATABASE NAME BEFORE WIPE/MIGRATE/SEED: {$activePerfDb}" . PHP_EOL;
echo "============================================================" . PHP_EOL;

if ($activePerfDb !== 'wu_dms_perf') {
    die("FATAL: Target database is NOT wu_dms_perf! Aborting immediately." . PHP_EOL);
}

// 2. Run all migrations up to DB-01
echo "Running migrations on {$activePerfDb}..." . PHP_EOL;
Artisan::call('migrate:fresh', [
    '--database' => 'perf',
    '--path' => 'database/migrations',
    '--force' => true,
]);

// Roll back the latest migration (DB-01: 2026_11_23_000001_wu_add_status_and_workflow_performance_indexes.php)
$lastMigration = DB::connection('perf')->table('migrations')->orderByDesc('id')->first();
echo "Rolling back last migration: {$lastMigration->migration}..." . PHP_EOL;
Artisan::call('migrate:rollback', [
    '--database' => 'perf',
    '--step' => 1,
    '--force' => true,
]);

$currentLast = DB::connection('perf')->table('migrations')->orderByDesc('id')->first();
echo "Current last migration before DB-01: {$currentLast->migration}" . PHP_EOL;

// 3. Seed Reference Vocabularies & Base Data
echo "Seeding reference vocabularies and base structure..." . PHP_EOL;
Artisan::call('db:seed', [
    '--database' => 'perf',
    '--class' => 'Database\\Seeders\\DatabaseSeeder',
    '--force' => true,
]);
Artisan::call('db:seed', [
    '--database' => 'perf',
    '--class' => 'Database\\Seeders\\WorkflowBaselineSeeder',
    '--force' => true,
]);

$unit = DB::connection('perf')->table('organization_units')->first();
$unitId = $unit->id;
$user = DB::connection('perf')->table('users')->first();
$userId = $user->id;
$cat = DB::connection('perf')->table('document_categories')->first();
$catId = $cat->id ?? 1;
$conf = DB::connection('perf')->table('confidentiality_levels')->first();
$confId = $conf->id ?? 1;
$now = now()->toDateTimeString();

// 4. Bulk Seed: 5,000 Documents
echo "Bulk seeding 5,000 documents..." . PHP_EOL;
$lifecycles = ['active', 'retired', 'tombstoned'];
$docRows = [];

for ($i = 1; $i <= 5000; $i++) {
    $lc = $lifecycles[$i % count($lifecycles)];
    $docRows[] = [
        'reference_number' => sprintf('DEMO-PERF-%06d', $i),
        'title' => "Performance Benchmark Document #{$i} - Institutional Policy on Digital Records and Archival Governance",
        'summary' => "Sample description for performance benchmark record {$i} containing search keywords institutional repository wollo",
        'category_id' => $catId,
        'confidentiality_id' => $confId,
        'owner_unit_id' => $unitId,
        'custodian_id' => $userId,
        'created_by' => $userId,
        'document_date' => '2026-11-23',
        'registered_at' => $now,
        'lifecycle' => $lc,
        'lock_version' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ];

    if (count($docRows) >= 1000) {
        DB::connection('perf')->table('documents')->insert($docRows);
        $docRows = [];
    }
}
if (!empty($docRows)) {
    DB::connection('perf')->table('documents')->insert($docRows);
}
echo "Documents seeded: " . DB::connection('perf')->table('documents')->count() . PHP_EOL;

// 5. Bulk Seed: 20,000 AuditEvents
echo "Bulk seeding 20,000 audit events..." . PHP_EOL;
$actions = ['document.viewed', 'document.updated', 'document.created', 'auth.login', 'report.exported', 'workflow.approved'];
$auditRows = [];

for ($i = 1; $i <= 20000; $i++) {
    $act = $actions[$i % count($actions)];
    $auditRows[] = [
        'actor_id' => $userId,
        'action' => $act,
        'target_type' => 'App\\Models\\Document',
        'target_id' => ($i % 5000) + 1,
        'request_id' => (string) Str::uuid(),
        'occurred_at' => $now,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Mozilla/5.0 PerfTest/1.0',
        'details_json' => json_encode(['test' => true, 'index' => $i]),
    ];

    if (count($auditRows) >= 1000) {
        DB::connection('perf')->table('audit_events')->insert($auditRows);
        $auditRows = [];
    }
}
if (!empty($auditRows)) {
    DB::connection('perf')->table('audit_events')->insert($auditRows);
}
echo "Audit events seeded: " . DB::connection('perf')->table('audit_events')->count() . PHP_EOL;

// 6. Bulk Seed: 2,000 WorkflowTasks
echo "Bulk seeding 2,000 workflow tasks..." . PHP_EOL;
$inst = DB::connection('perf')->table('workflow_instances')->first();
$instanceId = $inst->id ?? null;
if (!$instanceId) {
    $docVersionId = DB::connection('perf')->table('document_versions')->insertGetId([
        'document_id' => 1,
        'version_number' => 1,
        'ingestion_method' => 'digital_upload',
        'created_by' => $userId,
        'created_at' => $now,
    ]);

    $wv = DB::connection('perf')->table('workflow_versions')->first();
    $wvId = $wv ? $wv->id : 1;

    $instanceId = DB::connection('perf')->table('workflow_instances')->insertGetId([
        'document_version_id' => $docVersionId,
        'workflow_version_id' => $wvId,
        'started_by' => $userId,
        'started_at' => $now,
    ]);
}
$state = DB::connection('perf')->table('workflow_states')->first();
$stateId = $state->id ?? 1;

$taskRows = [];
for ($i = 1; $i <= 2000; $i++) {
    $taskRows[] = [
        'instance_id' => $instanceId,
        'state_id' => $stateId,
        'assigned_user_id' => $userId,
        'assigned_unit_id' => $unitId,
        'created_at' => $now,
    ];

    if (count($taskRows) >= 1000) {
        DB::connection('perf')->table('workflow_tasks')->insert($taskRows);
        $taskRows = [];
    }
}
if (!empty($taskRows)) {
    DB::connection('perf')->table('workflow_tasks')->insert($taskRows);
}
echo "Workflow tasks seeded: " . DB::connection('perf')->table('workflow_tasks')->count() . PHP_EOL;

// 7. Bulk Seed: Module Status Tables (for DB-01 verification)
echo "Bulk seeding module status tables (research, projects, backups, imports/exports)..." . PHP_EOL;
$researchStatuses = ['draft', 'submitted', 'under_review', 'approved', 'rejected', 'returned'];
$projectStatuses = ['draft', 'submitted', 'under_review', 'approved', 'rejected', 'returned'];
$backupStatuses = ['running', 'completed', 'failed'];
$drillStatuses = ['running', 'completed', 'failed'];
$importOpStatuses = ['pending', 'processing', 'completed', 'failed'];
$exportOpStatuses = ['pending', 'processing', 'completed', 'failed'];
$batchStatuses = ['pending', 'running', 'completed', 'failed', 'cancelled'];
$rowStatuses = ['pending', 'imported', 'skipped', 'rejected'];

$researchRows = [];
$projectRows = [];
$backupRows = [];
$drillRows = [];
$importOpRows = [];
$exportOpRows = [];
$batchRows = [];
$rowRows = [];

// Seed 1,000 backup runs first
for ($i = 1; $i <= 1000; $i++) {
    $backupRows[] = [
        'provider_job_reference' => sprintf('JOB-%05d', $i),
        'started_at' => $now,
        'completed_at' => $now,
        'status' => $backupStatuses[$i % count($backupStatuses)],
        'manifest_reference' => sprintf('MAN-%05d', $i),
        'verification_status' => 'verified',
    ];
}
DB::connection('perf')->table('backup_runs')->insert($backupRows);

// Seed 1 batch first
$batchId = DB::connection('perf')->table('import_batches')->insertGetId([
    'source_label' => 'HR Import',
    'initiated_by' => $userId,
    'status' => 'running',
    'started_at' => $now,
]);

// Project types
$projType = DB::connection('perf')->table('project_types')->first();
$projTypeId = $projType->id ?? 1;

for ($i = 1; $i <= 1000; $i++) {
    $researchRows[] = [
        'document_id' => $i,
        'research_type' => 'applied',
        'completed_on' => '2026-11-20',
        'external_selection_reference' => sprintf('EXT-REF-%04d', $i),
        'workflow_status' => $researchStatuses[$i % count($researchStatuses)],
        'recorded_by' => $userId,
        'recorded_at' => $now,
    ];

    $projectRows[] = [
        'document_id' => $i,
        'project_type_id' => $projTypeId,
        'workflow_status' => $projectStatuses[$i % count($projectStatuses)],
        'recorded_by' => $userId,
        'recorded_at' => $now,
    ];

    $drillRows[] = [
        'backup_run_id' => $i,
        'started_at' => $now,
        'completed_at' => $now,
        'status' => $drillStatuses[$i % count($drillStatuses)],
        'notes' => 'Routine verification drill',
    ];

    $importOpRows[] = [
        'dataset' => 'users',
        'status' => $importOpStatuses[$i % count($importOpStatuses)],
        'format' => 'csv',
        'original_filename' => 'import.csv',
        'storage_path' => 'imports/import.csv',
        'scope_unit_id' => $unitId,
        'created_by' => $userId,
        'created_at' => $now,
        'updated_at' => $now,
    ];

    $exportOpRows[] = [
        'dataset' => 'documents',
        'status' => $exportOpStatuses[$i % count($exportOpStatuses)],
        'format' => 'csv',
        'storage_path' => 'exports/export.csv',
        'file_size' => 1024,
        'record_count' => 100,
        'filters' => '{}',
        'scope_unit_id' => $unitId,
        'created_by' => $userId,
        'created_at' => $now,
        'updated_at' => $now,
    ];

    $rowRows[] = [
        'batch_id' => $batchId,
        'source_row_key' => "ROW-$i",
        'status' => $rowStatuses[$i % count($rowStatuses)],
    ];
}

DB::connection('perf')->table('research_records')->insert($researchRows);
DB::connection('perf')->table('project_proposals')->insert($projectRows);
DB::connection('perf')->table('restore_drills')->insert($drillRows);
DB::connection('perf')->table('data_import_operations')->insert($importOpRows);
DB::connection('perf')->table('data_export_operations')->insert($exportOpRows);
DB::connection('perf')->table('import_rows')->insert($rowRows);

echo "Module status records seeded successfully." . PHP_EOL;

// 8. Define Benchmark Test Queries
$workloads = [
    'Document Repository Listing' => function ($conn) use ($unitId, $confId) {
        return DB::connection($conn)->table('documents')
            ->where('owner_unit_id', $unitId)
            ->where('lifecycle', 'active')
            ->where('confidentiality_id', $confId)
            ->orderBy('id', 'desc')
            ->limit(25)
            ->get();
    },
    'Document Search by Title' => function ($conn) {
        return DB::connection($conn)->table('documents')
            ->where('lifecycle', 'active')
            ->where('title', 'like', '%Policy%')
            ->limit(25)
            ->get();
    },
    'Audit Log Range Query' => function ($conn) {
        return DB::connection($conn)->table('audit_events')
            ->where('action', 'document.created')
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();
    },
    'Workflow Tasks Inbox' => function ($conn) use ($userId) {
        return DB::connection($conn)->table('workflow_tasks')
            ->where('assigned_user_id', $userId)
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get();
    },
    'Research Workflow Status' => function ($conn) {
        return DB::connection($conn)->table('research_records')
            ->where('workflow_status', 'submitted')
            ->orderBy('id', 'desc')
            ->limit(25)
            ->get();
    },
    'Project Proposals Status' => function ($conn) {
        return DB::connection($conn)->table('project_proposals')
            ->where('workflow_status', 'under_review')
            ->orderBy('id', 'desc')
            ->limit(25)
            ->get();
    },
    'Backup Runs Status' => function ($conn) {
        return DB::connection($conn)->table('backup_runs')
            ->where('status', 'completed')
            ->orderBy('id', 'desc')
            ->limit(25)
            ->get();
    },
    'Restore Drills Status' => function ($conn) {
        return DB::connection($conn)->table('restore_drills')
            ->where('status', 'completed')
            ->orderBy('id', 'desc')
            ->limit(25)
            ->get();
    },
    'Data Import Operations' => function ($conn) {
        return DB::connection($conn)->table('data_import_operations')
            ->where('status', 'failed')
            ->orderBy('id', 'desc')
            ->limit(25)
            ->get();
    },
    'Data Export Operations' => function ($conn) {
        return DB::connection($conn)->table('data_export_operations')
            ->where('status', 'completed')
            ->orderBy('id', 'desc')
            ->limit(25)
            ->get();
    },
    'Import Batches Status' => function ($conn) {
        return DB::connection($conn)->table('import_batches')
            ->where('status', 'processing')
            ->orderBy('id', 'desc')
            ->limit(25)
            ->get();
    },
    'Import Rows Status' => function ($conn) {
        return DB::connection($conn)->table('import_rows')
            ->where('status', 'pending')
            ->orderBy('id', 'desc')
            ->limit(25)
            ->get();
    },
];

function runProfiling(string $conn, array $workloads, int $iterations = 30): array
{
    $results = [];
    foreach ($workloads as $name => $fn) {
        $times = [];
        DB::connection($conn)->enableQueryLog();
        DB::connection($conn)->flushQueryLog();

        for ($i = 0; $i < $iterations; $i++) {
            $t0 = microtime(true);
            $fn($conn);
            $t1 = microtime(true);
            $times[] = ($t1 - $t0) * 1000.0; // milliseconds
        }

        $queryCount = count(DB::connection($conn)->getQueryLog());
        sort($times);
        $p95Index = (int) floor(0.95 * count($times));
        $p95 = $times[$p95Index];
        $avg = array_sum($times) / count($times);

        $results[$name] = [
            'query_count' => (int) round($queryCount / $iterations),
            'avg_ms' => round($avg, 2),
            'p95_ms' => round($p95, 2),
        ];
    }
    return $results;
}

echo PHP_EOL . "--- EXECUTING BENCHMARK BEFORE DB-01 MIGRATION (30 iterations per workload) ---" . PHP_EOL;
$beforeResults = runProfiling('perf', $workloads, 30);
foreach ($beforeResults as $name => $stat) {
    echo sprintf("  %-28s | Queries: %d | p95: %6.2f ms | Avg: %6.2f ms\n", $name, $stat['query_count'], $stat['p95_ms'], $stat['avg_ms']);
}

// 9. Apply DB-01 Migration
echo PHP_EOL . "Applying migration DB-01 (2026_11_23_000001_wu_add_status_and_workflow_performance_indexes.php)..." . PHP_EOL;
Artisan::call('migrate', [
    '--database' => 'perf',
    '--path' => 'database/migrations/2026_11_23_000001_wu_add_status_and_workflow_performance_indexes.php',
    '--force' => true,
]);

// 10. Run EXPLAIN on the status queries to verify index usage
echo PHP_EOL . "--- EXPLAIN ANALYSIS ON REAL STATUS QUERIES ---" . PHP_EOL;
$explainQueries = [
    'research_records' => "EXPLAIN SELECT * FROM research_records WHERE workflow_status = 'submitted'",
    'project_proposals' => "EXPLAIN SELECT * FROM project_proposals WHERE workflow_status = 'under_review'",
    'backup_runs' => "EXPLAIN SELECT * FROM backup_runs WHERE status = 'completed'",
    'restore_drills' => "EXPLAIN SELECT * FROM restore_drills WHERE status = 'completed'",
    'data_import_operations' => "EXPLAIN SELECT * FROM data_import_operations WHERE status = 'failed'",
    'data_export_operations' => "EXPLAIN SELECT * FROM data_export_operations WHERE status = 'completed'",
    'import_batches' => "EXPLAIN SELECT * FROM import_batches WHERE status = 'processing'",
    'import_rows' => "EXPLAIN SELECT * FROM import_rows WHERE status = 'pending'",
];

$indexUtilization = [];
foreach ($explainQueries as $table => $sql) {
    $explain = DB::connection('perf')->select($sql);
    $row = (array) $explain[0];
    $usedKey = $row['key'] ?? 'NONE';
    $rowsExamined = $row['rows'] ?? 'N/A';
    $indexUtilization[$table] = [
        'table' => $row['table'] ?? $table,
        'type' => $row['type'] ?? 'N/A',
        'key' => $usedKey,
        'rows' => $rowsExamined,
        'extra' => $row['Extra'] ?? '',
    ];
    echo sprintf("  Table: %-24s | Type: %-6s | Key: %-42s | Rows: %s\n", $table, $row['type'], $usedKey, $rowsExamined);
}

// 11. Profile workloads AFTER DB-01
echo PHP_EOL . "--- EXECUTING BENCHMARK AFTER DB-01 MIGRATION (30 iterations per workload) ---" . PHP_EOL;
$afterResults = runProfiling('perf', $workloads, 30);
foreach ($afterResults as $name => $stat) {
    $beforeP95 = $beforeResults[$name]['p95_ms'];
    $diff = $beforeP95 - $stat['p95_ms'];
    $pct = $beforeP95 > 0 ? round(($diff / $beforeP95) * 100, 1) : 0;
    echo sprintf("  %-28s | Queries: %d | p95: %6.2f ms | Diff: %+5.2f ms (%+5.1f%%)\n", $name, $stat['query_count'], $stat['p95_ms'], -$diff, $pct);
}

// 12. Drop Throwaway Database
echo PHP_EOL . "Dropping throwaway database 'wu_dms_perf'..." . PHP_EOL;
DB::connection('mysql')->statement('DROP DATABASE IF EXISTS wu_dms_perf;');

// Verify dropped
$dbs = DB::connection('mysql')->select("SHOW DATABASES LIKE 'wu_dms_perf'");
if (empty($dbs)) {
    echo "Pristine cleanup verified: database 'wu_dms_perf' successfully dropped." . PHP_EOL;
} else {
    echo "WARNING: database 'wu_dms_perf' still present!" . PHP_EOL;
}

echo PHP_EOL . "=== PERFORMANCE BENCHMARK COMPLETE ===" . PHP_EOL;
