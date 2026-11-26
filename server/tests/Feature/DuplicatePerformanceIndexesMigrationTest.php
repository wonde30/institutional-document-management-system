<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DuplicatePerformanceIndexesMigrationTest extends TestCase
{
    public function test_duplicate_performance_indexes_are_dropped_and_original_indexes_remain(): void
    {
        $dbName = DB::connection()->getDatabaseName();

        // 1. Assert duplicate indexes do not exist
        $importDuplicates = DB::select("
            SELECT INDEX_NAME FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'data_import_operations' AND INDEX_NAME = 'data_import_operations_status_idx'
        ", [$dbName]);
        $this->assertEmpty($importDuplicates, 'Duplicate index data_import_operations_status_idx should not exist');

        $exportDuplicates = DB::select("
            SELECT INDEX_NAME FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'data_export_operations' AND INDEX_NAME = 'data_export_operations_status_idx'
        ", [$dbName]);
        $this->assertEmpty($exportDuplicates, 'Duplicate index data_export_operations_status_idx should not exist');

        // 2. Assert canonical original status indexes remain active
        $importOriginal = DB::select("
            SELECT INDEX_NAME FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'data_import_operations' AND INDEX_NAME = 'data_import_operations_status_index'
        ", [$dbName]);
        $this->assertNotEmpty($importOriginal, 'Original index data_import_operations_status_index must remain active');

        $exportOriginal = DB::select("
            SELECT INDEX_NAME FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'data_export_operations' AND INDEX_NAME = 'data_export_operations_status_index'
        ", [$dbName]);
        $this->assertNotEmpty($exportOriginal, 'Original index data_export_operations_status_index must remain active');
    }
}
