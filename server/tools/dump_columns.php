<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

DB::statement("DROP DATABASE IF EXISTS wu_dms_recovery_test");
echo "Dropped wu_dms_recovery_test" . PHP_EOL;

$dbs = DB::select("SHOW DATABASES");
echo "=== CURRENT DATABASES ===" . PHP_EOL;
foreach ($dbs as $db) {
    echo "  - " . ($db->Database ?? reset($db)) . PHP_EOL;
}
