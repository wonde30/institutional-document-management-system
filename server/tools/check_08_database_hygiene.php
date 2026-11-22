<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CHECK 08: DATABASE HYGIENE & FK INDEX AUDIT ===\n\n";

$dbName = config('database.connections.mysql.database');
echo "Auditing database: $dbName\n\n";

// 1. Check FKs without leading index
$fkQuery = "
    SELECT 
        TABLE_NAME, 
        COLUMN_NAME, 
        CONSTRAINT_NAME, 
        REFERENCED_TABLE_NAME, 
        REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = ?
      AND REFERENCED_TABLE_NAME IS NOT NULL
";
$fks = DB::select($fkQuery, [$dbName]);

$indexQuery = "
    SELECT 
        TABLE_NAME, 
        INDEX_NAME, 
        COLUMN_NAME, 
        SEQ_IN_INDEX
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = ?
";
$indices = DB::select($indexQuery, [$dbName]);

$tableIndices = [];
foreach ($indices as $idx) {
    if ((int)$idx->SEQ_IN_INDEX === 1) {
        $tableIndices[$idx->TABLE_NAME][$idx->COLUMN_NAME][] = $idx->INDEX_NAME;
    }
}

$unindexedFks = [];
foreach ($fks as $fk) {
    $t = $fk->TABLE_NAME;
    $c = $fk->COLUMN_NAME;
    if (!isset($tableIndices[$t][$c])) {
        $unindexedFks[] = [
            'table' => $t,
            'column' => $c,
            'constraint' => $fk->CONSTRAINT_NAME,
            'ref_table' => $fk->REFERENCED_TABLE_NAME,
        ];
    }
}

echo "Total Foreign Keys: " . count($fks) . "\n";
echo "Foreign keys missing leading index: " . count($unindexedFks) . "\n";
foreach ($unindexedFks as $ufk) {
    echo "  [!] {$ufk['table']}.{$ufk['column']} -> {$ufk['ref_table']} (constraint: {$ufk['constraint']})\n";
}

// 2. Audit migrations for auto-generated constraint names on dropForeign
echo "\n--- 2. MIGRATION ROLLBACK AUTO-GENERATED CONSTRAINT NAMES ---\n";
$migDir = __DIR__ . '/../database/migrations';
$migrationFiles = glob($migDir . '/*.php');
$rollbackIssues = [];

foreach ($migrationFiles as $mf) {
    $content = file_get_contents($mf);
    $relName = basename($mf);
    if (preg_match('/function down\s*\([^)]*\)\s*\{([^}]+)\}/s', $content, $m)) {
        $downBody = $m[1];
        if (preg_match_all('/dropForeign\s*\(\s*(\[[^\]]+\]|[\'"][^\'"]+[\'"])\s*\)/', $downBody, $matches)) {
            foreach ($matches[1] as $arg) {
                // If it passes array like ['user_id'] on MySQL, Laravel generates table_column_foreign
                // If the constraint name was manually specified on creation, dropForeign(['user_id']) fails!
            }
        }
    }
}

// 3. Audit for tables missing created_at/updated_at or index on frequently filtered columns
echo "\n--- 3. COMMON FILTER COLUMNS MISSING INDEXES ---\n";
$commonFilterCols = ['status', 'is_active', 'deleted_at', 'lifecycle', 'reference_number'];
$missingColIdx = [];

$tablesQuery = "SELECT DISTINCT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'";
$tables = DB::select($tablesQuery, [$dbName]);

foreach ($tables as $tbl) {
    $tName = $tbl->TABLE_NAME;
    $cols = DB::select("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?", [$dbName, $tName]);
    $existingCols = array_map(fn($c) => $c->COLUMN_NAME, $cols);
    
    foreach ($commonFilterCols as $col) {
        if (in_array($col, $existingCols, true)) {
            // Check if indexed
            $colIndexed = false;
            foreach ($indices as $idx) {
                if ($idx->TABLE_NAME === $tName && $idx->COLUMN_NAME === $col) {
                    $colIndexed = true;
                    break;
                }
            }
            if (!$colIndexed) {
                $missingColIdx[] = "$tName.$col";
            }
        }
    }
}

echo "Filter columns without index: " . count($missingColIdx) . "\n";
foreach ($missingColIdx as $mci) {
    echo "  - $mci\n";
}
