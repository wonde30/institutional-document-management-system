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
     * Performance optimization: Add missing indexes on audit_events for actor, target,
     * and occurred_at to eliminate full table scans (type: ALL) and filesorts on
     * institutional audit trails and administrative query paths.
     */
    public function up(): void
    {
        Schema::table('audit_events', function (Blueprint $table) {
            $table->index('actor_id', 'audit_events_actor_id_idx');
            $table->index(['target_type', 'target_id'], 'audit_events_target_idx');
            $table->index(['occurred_at', 'actor_id'], 'audit_events_occurred_actor_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_events', function (Blueprint $table) {
            $table->dropIndex('audit_events_actor_id_idx');
            $table->dropIndex('audit_events_target_idx');
            $table->dropIndex('audit_events_occurred_actor_idx');
        });
    }
};
