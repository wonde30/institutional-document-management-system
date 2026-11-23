<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('backup_runs', function (Blueprint $table) {
            $table->index('status', 'backup_runs_status_idx');
        });

        Schema::table('restore_drills', function (Blueprint $table) {
            $table->index('status', 'restore_drills_status_idx');
        });

        Schema::table('research_records', function (Blueprint $table) {
            $table->index('workflow_status', 'research_records_workflow_status_idx');
        });

        Schema::table('project_proposals', function (Blueprint $table) {
            $table->index('workflow_status', 'project_proposals_workflow_status_idx');
        });

        Schema::table('import_batches', function (Blueprint $table) {
            $table->index('status', 'import_batches_status_idx');
        });

        Schema::table('import_rows', function (Blueprint $table) {
            $table->index('status', 'import_rows_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('import_rows', function (Blueprint $table) {
            $table->dropIndex('import_rows_status_idx');
        });

        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropIndex('import_batches_status_idx');
        });

        Schema::table('project_proposals', function (Blueprint $table) {
            $table->dropIndex('project_proposals_workflow_status_idx');
        });

        Schema::table('research_records', function (Blueprint $table) {
            $table->dropIndex('research_records_workflow_status_idx');
        });

        Schema::table('restore_drills', function (Blueprint $table) {
            $table->dropIndex('restore_drills_status_idx');
        });

        Schema::table('backup_runs', function (Blueprint $table) {
            $table->dropIndex('backup_runs_status_idx');
        });
    }
};
