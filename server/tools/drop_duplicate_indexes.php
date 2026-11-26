<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

echo "Current DB: " . config('database.connections.mysql.database') . PHP_EOL;

try {
    Schema::table('data_import_operations', function (Blueprint $table) {
        $table->dropIndex('data_import_operations_status_idx');
    });
    echo "Dropped duplicate data_import_operations_status_idx\n";
} catch (\Exception $e) {
    echo "data_import_operations: " . $e->getMessage() . "\n";
}

try {
    Schema::table('data_export_operations', function (Blueprint $table) {
        $table->dropIndex('data_export_operations_status_idx');
    });
    echo "Dropped duplicate data_export_operations_status_idx\n";
} catch (\Exception $e) {
    echo "data_export_operations: " . $e->getMessage() . "\n";
}
