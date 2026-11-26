<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== PERFORMANCE BENCHMARK ON THROWAWAY DATABASE: wu_dms_perf ===\n\n";

// 1. Create throwaway database
DB::statement("CREATE DATABASE IF NOT EXISTS `wu_dms_perf` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "1. Created throwaway database: wu_dms_perf\n";

// Switch connection to wu_dms_perf
config(['database.connections.perf' => array_merge(config('database.connections.mysql'), [
    'database' => 'wu_dms_perf',
])]);
$perf = DB::connection('perf');

// 2. Create benchmark table without index
$perf->statement("DROP TABLE IF EXISTS `perf_backup_runs`");
$perf->statement("CREATE TABLE `perf_backup_runs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `backup_type` VARCHAR(32) NOT NULL,
    `status` VARCHAR(32) NOT NULL,
    `bytes_size` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

// 3. Seed 50,000 rows in batches
echo "2. Seeding 50,000 sample rows into perf_backup_runs...\n";
$statuses = ['pending', 'in_progress', 'completed', 'failed', 'verified'];
$rows = [];
$batchSize = 2500;
for ($i = 1; $i <= 50000; $i++) {
    $rows[] = [
        'backup_type' => 'full_database',
        'status' => $statuses[$i % 5],
        'bytes_size' => rand(1000000, 500000000),
        'created_at' => date('Y-m-d H:i:s'),
    ];
    if (count($rows) >= $batchSize) {
        $perf->table('perf_backup_runs')->insert($rows);
        $rows = [];
    }
}
echo "   Seeded 50,000 rows successfully.\n\n";

// 4. Benchmark BEFORE index
echo "--- BEFORE INDEX (Full Table Scan) ---\n";
$explainBefore = $perf->select("EXPLAIN SELECT * FROM perf_backup_runs WHERE status = 'completed'");
echo "EXPLAIN Plan (type=" . $explainBefore[0]->type . ", possible_keys=" . ($explainBefore[0]->possible_keys ?? 'NULL') . ", rows=" . $explainBefore[0]->rows . "):\n";
print_r($explainBefore[0]);

$start = microtime(true);
for ($q = 0; $q < 100; $q++) {
    $res = $perf->select("SELECT * FROM perf_backup_runs WHERE status = 'completed' LIMIT 50");
}
$durationBefore = (microtime(true) - $start) * 1000;
echo "Timing for 100 queries BEFORE index: " . number_format($durationBefore, 2) . " ms (avg " . number_format($durationBefore / 100, 3) . " ms/query)\n\n";

// 5. Add index
echo "3. Creating index: perf_backup_runs_status_idx on column `status`...\n";
$perf->statement("ALTER TABLE `perf_backup_runs` ADD INDEX `perf_backup_runs_status_idx` (`status`)");

// 6. Benchmark AFTER index
echo "\n--- AFTER INDEX (Index Range/Ref Scan) ---\n";
$explainAfter = $perf->select("EXPLAIN SELECT * FROM perf_backup_runs WHERE status = 'completed'");
echo "EXPLAIN Plan (type=" . $explainAfter[0]->type . ", key=" . ($explainAfter[0]->key ?? 'NULL') . ", rows=" . $explainAfter[0]->rows . "):\n";
print_r($explainAfter[0]);

$start = microtime(true);
for ($q = 0; $q < 100; $q++) {
    $res = $perf->select("SELECT * FROM perf_backup_runs WHERE status = 'completed' LIMIT 50");
}
$durationAfter = (microtime(true) - $start) * 1000;
echo "Timing for 100 queries AFTER index: " . number_format($durationAfter, 2) . " ms (avg " . number_format($durationAfter / 100, 3) . " ms/query)\n\n";

$speedup = $durationBefore > 0 ? ($durationBefore / max(0.001, $durationAfter)) : 1.0;
echo "Speedup Factor: " . number_format($speedup, 2) . "x faster with index.\n\n";

// 7. Cleanup: Drop throwaway database
$perf->disconnect();
DB::statement("DROP DATABASE IF EXISTS `wu_dms_perf`");
echo "4. Dropped throwaway database: wu_dms_perf (Cleaned up).\n";
