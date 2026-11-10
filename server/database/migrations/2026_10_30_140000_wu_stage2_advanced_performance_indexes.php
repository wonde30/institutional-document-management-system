<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Stage 2 Database Optimization: Evidence-backed composite and covering indexes
     * designed to eliminate full table scans, filesorts, and hash joins on hot
     * institutional query paths.
     */
    public function up(): void
    {
        Schema::table('audit_events', function (Blueprint $table) {
            // Eliminates filesort on audit log queries filtering by target_type ordered by occurred_at DESC
            $table->index(['target_type', 'occurred_at'], 'audit_events_ix_5');
        });

        Schema::table('workflow_task_decisions', function (Blueprint $table) {
            // Eliminates full table scan (ALL) and hash join on exact-version workflow approval verification
            $table->index(['decision', 'task_id'], 'workflow_task_decisions_ix_2');
            $table->index(['task_id', 'decision'], 'workflow_task_decisions_ix_3');
        });

        Schema::table('user_notifications', function (Blueprint $table) {
            // Eliminates filesort on user notification inbox queries ordered by created_at DESC
            $table->index(['user_id', 'created_at'], 'user_notifications_ix_2');
        });

        Schema::table('document_relations', function (Blueprint $table) {
            // Covering index for linked response lookups by source document and relation type
            $table->index(['source_id', 'relation_type_id', 'target_id'], 'document_relations_ix_3');
        });

        Schema::table('document_movements', function (Blueprint $table) {
            // Composite index for inter-office routing queries filtering by document and recipient unit
            $table->index(['document_id', 'to_unit_id', 'sent_at'], 'document_movements_ix_5');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_movements', function (Blueprint $table) {
            $table->dropIndex('document_movements_ix_5');
        });

        Schema::table('document_relations', function (Blueprint $table) {
            $table->dropIndex('document_relations_ix_3');
        });

        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropIndex('user_notifications_ix_2');
        });

        Schema::table('workflow_task_decisions', function (Blueprint $table) {
            $table->dropIndex('workflow_task_decisions_ix_2');
            $table->dropIndex('workflow_task_decisions_ix_3');
        });

        Schema::table('audit_events', function (Blueprint $table) {
            $table->dropIndex('audit_events_ix_5');
        });
    }
};
