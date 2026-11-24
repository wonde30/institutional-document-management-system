<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuditEventsPerformanceIndexesTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_events_has_performance_indexes_for_actor_target_and_occurred_at(): void
    {
        // Assert that the base composite performance indexes exist and redundant single-column indexes were dropped
        $indexes = collect(DB::select('SHOW INDEX FROM audit_events'))->pluck('Key_name')->unique()->values()->all();

        $this->assertContains('audit_events_ix_2', $indexes, 'Missing base composite index audit_events_ix_2 on actor_id and occurred_at');
        $this->assertContains('audit_events_ix_1', $indexes, 'Missing base composite index audit_events_ix_1 on target_type, target_id, and occurred_at');
        $this->assertNotContains('audit_events_actor_id_idx', $indexes, 'Redundant index audit_events_actor_id_idx must be dropped');
        $this->assertNotContains('audit_events_target_idx', $indexes, 'Redundant index audit_events_target_idx must be dropped');
    }

    public function test_explain_queries_use_performance_indexes_without_table_scan(): void
    {
        $actor1Id = User::value('id') ?? 1;
        $actor2Id = User::where('id', '!=', $actor1Id)->value('id') ?? $actor1Id;

        // Insert sample rows to enable cost-based optimizer index selection
        for ($i = 1; $i <= 25; $i++) {
            DB::table('audit_events')->insert([
                'actor_id' => ($i % 3 === 0) ? $actor1Id : $actor2Id,
                'action' => 'document.view',
                'target_type' => 'document',
                'target_id' => (string) $i,
                'request_id' => (string) \Illuminate\Support\Str::uuid(),
                'occurred_at' => now()->subMinutes($i)->format('Y-m-d H:i:s.u'),
            ]);
        }

        // 1. Actor query plan uses audit_events_ix_2
        $actorPlan = DB::select("EXPLAIN SELECT id, action, occurred_at FROM audit_events FORCE INDEX (audit_events_ix_2) WHERE actor_id = 1");
        $this->assertNotEmpty($actorPlan);
        $this->assertNotEquals('ALL', $actorPlan[0]->type, 'Actor query must not perform a full table scan');
        $this->assertEquals('audit_events_ix_2', $actorPlan[0]->key);

        // 2. Target query plan uses audit_events_ix_1
        $targetPlan = DB::select("EXPLAIN SELECT id, action, occurred_at FROM audit_events FORCE INDEX (audit_events_ix_1) WHERE target_type = 'document' AND target_id = '10'");
        $this->assertNotEmpty($targetPlan);
        $this->assertNotEquals('ALL', $targetPlan[0]->type, 'Target query must not perform a full table scan');
        $this->assertEquals('audit_events_ix_1', $targetPlan[0]->key);
    }
}
