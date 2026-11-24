<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Drop duplicate status indexes on data transfer operations tables
     * that were already indexed in 2026_11_11_000001_wu_add_data_transfer_operations
     * as data_import_operations_status_index and data_export_operations_status_index.
     */
    public function up(): void
    {
        $dbName = DB::connection()->getDatabaseName();

        $importIdxExists = DB::select("
            SELECT INDEX_NAME FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'data_import_operations' AND INDEX_NAME = 'data_import_operations_status_idx'
        ", [$dbName]);

        if (! empty($importIdxExists)) {
            Schema::table('data_import_operations', function (Blueprint $table) {
                $table->dropIndex('data_import_operations_status_idx');
            });
        }

        $exportIdxExists = DB::select("
            SELECT INDEX_NAME FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'data_export_operations' AND INDEX_NAME = 'data_export_operations_status_idx'
        ", [$dbName]);

        if (! empty($exportIdxExists)) {
            Schema::table('data_export_operations', function (Blueprint $table) {
                $table->dropIndex('data_export_operations_status_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $dbName = DB::connection()->getDatabaseName();

        $importIdxExists = DB::select("
            SELECT INDEX_NAME FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'data_import_operations' AND INDEX_NAME = 'data_import_operations_status_idx'
        ", [$dbName]);

        if (empty($importIdxExists)) {
            Schema::table('data_import_operations', function (Blueprint $table) {
                $table->index('status', 'data_import_operations_status_idx');
            });
        }

        $exportIdxExists = DB::select("
            SELECT INDEX_NAME FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'data_export_operations' AND INDEX_NAME = 'data_export_operations_status_idx'
        ", [$dbName]);

        if (empty($exportIdxExists)) {
            Schema::table('data_export_operations', function (Blueprint $table) {
                $table->index('status', 'data_export_operations_status_idx');
            });
        }
    }
};
