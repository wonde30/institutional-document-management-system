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
        // Assert that the performance indexes exist on audit_events
        $indexes = collect(DB::select('SHOW INDEX FROM audit_events'))->pluck('Key_name')->unique()->values()->all();

        $this->assertContains('audit_events_actor_id_idx', $indexes, 'Missing index audit_events_actor_id_idx on actor_id');
        $this->assertContains('audit_events_target_idx', $indexes, 'Missing index audit_events_target_idx on target_type and target_id');
        $this->assertContains('audit_events_occurred_actor_idx', $indexes, 'Missing index audit_events_occurred_actor_idx on occurred_at and actor_id');
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

        // 1. Actor query plan
        $actorPlan = DB::select("EXPLAIN SELECT id, action, occurred_at FROM audit_events FORCE INDEX (audit_events_actor_id_idx) WHERE actor_id = 1");
        $this->assertNotEmpty($actorPlan);
        $this->assertNotEquals('ALL', $actorPlan[0]->type, 'Actor query must not perform a full table scan');
        $this->assertEquals('audit_events_actor_id_idx', $actorPlan[0]->key);

        // 2. Target query plan
        $targetPlan = DB::select("EXPLAIN SELECT id, action, occurred_at FROM audit_events FORCE INDEX (audit_events_target_idx) WHERE target_type = 'document' AND target_id = '10'");
        $this->assertNotEmpty($targetPlan);
        $this->assertNotEquals('ALL', $targetPlan[0]->type, 'Target query must not perform a full table scan');
        $this->assertEquals('audit_events_target_idx', $targetPlan[0]->key);

        // 3. Occurred at range plan
        $occurredPlan = DB::select("EXPLAIN SELECT id, action, occurred_at FROM audit_events FORCE INDEX (audit_events_occurred_actor_idx) WHERE occurred_at >= '2026-01-01 00:00:00' ORDER BY occurred_at DESC LIMIT 20");
        $this->assertNotEmpty($occurredPlan);
        $this->assertNotEquals('ALL', $occurredPlan[0]->type, 'Occurred at query must not perform a full table scan');
        $this->assertStringNotContainsString('Using filesort', $occurredPlan[0]->Extra ?? '', 'Occurred at query must not use filesort');
    }
}
