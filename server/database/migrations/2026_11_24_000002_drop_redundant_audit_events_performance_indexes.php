<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Drop redundant indexes on audit_events introduced by 2026_11_24_000001.
     * EXPLAIN query plan analysis demonstrated that MySQL's optimizer already
     * uses the existing composite indexes audit_events_ix_1 (target_type, target_id, occurred_at)
     * and audit_events_ix_2 (actor_id, occurred_at) for actor and target lookups,
     * leaving the execution plan identical and making these indexes redundant overhead on writes.
     */
    public function up(): void
    {
        Schema::table('audit_events', function (Blueprint $table) {
            $table->dropIndex('audit_events_actor_id_idx');
            $table->dropIndex('audit_events_target_idx');
            $table->dropIndex('audit_events_occurred_actor_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_events', function (Blueprint $table) {
            $table->index('actor_id', 'audit_events_actor_id_idx');
            $table->index(['target_type', 'target_id'], 'audit_events_target_idx');
            $table->index(['occurred_at', 'actor_id'], 'audit_events_occurred_actor_idx');
        });
    }
};
