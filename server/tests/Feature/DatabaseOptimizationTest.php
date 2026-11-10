<?php

namespace Tests\Feature;

use App\Models\Correspondence;
use App\Models\CorrespondenceType;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\OrganizationUnit;
use App\Models\Priority;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected OrganizationUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $this->admin = User::where('email', 'admin@wu.edu.et')->first();
        $this->unit = OrganizationUnit::first();
    }

    public function test_documents_composite_index_avoids_filesort_on_ordered_listing(): void
    {
        // Execute EXPLAIN with documents_ix_1 composite index
        $sql = "EXPLAIN SELECT id, reference_number, registered_at FROM documents FORCE INDEX (documents_ix_1) WHERE owner_unit_id = {$this->unit->id} AND lifecycle = 'active' ORDER BY registered_at DESC LIMIT 25";
        $plan = DB::select($sql);

        $this->assertNotEmpty($plan);
        $explainRow = $plan[0];

        $this->assertEquals('documents', $explainRow->table);
        $this->assertEquals('ref', $explainRow->type);
        $this->assertEquals('documents_ix_1', $explainRow->key);
        $this->assertStringContainsString('Backward index scan', $explainRow->Extra);
        $this->assertStringNotContainsString('Using filesort', $explainRow->Extra);
    }

    public function test_correspondences_composite_index_avoids_filesort_on_type_listing(): void
    {
        $type = CorrespondenceType::first();

        $sql = "EXPLAIN SELECT id, purpose_code, registered_at FROM correspondences FORCE INDEX (correspondences_ix_1) WHERE type_id = {$type->id} ORDER BY registered_at DESC LIMIT 25";
        $plan = DB::select($sql);

        $this->assertNotEmpty($plan);
        $explainRow = $plan[0];

        $this->assertEquals('correspondences', $explainRow->table);
        $this->assertEquals('ref', $explainRow->type);
        $this->assertEquals('correspondences_ix_1', $explainRow->key);
        $this->assertStringContainsString('Backward index scan', $explainRow->Extra);
        $this->assertStringNotContainsString('Using filesort', $explainRow->Extra);
    }

    public function test_external_reference_index_lookup_efficiency(): void
    {
        $sql = "EXPLAIN SELECT id, document_id, external_reference FROM correspondences FORCE INDEX (correspondences_ix_3) WHERE external_reference = 'WU/EXT/TEST/REF-001' LIMIT 1";
        $plan = DB::select($sql);

        $this->assertNotEmpty($plan);
        $explainRow = $plan[0];

        $this->assertEquals('correspondences', $explainRow->table);
        $this->assertEquals('ref', $explainRow->type);
        $this->assertEquals('correspondences_ix_3', $explainRow->key);
        $this->assertLessThanOrEqual(10, $explainRow->rows);
    }

    public function test_optimistic_concurrency_lock_version_prevents_dirty_writes(): void
    {
        $doc = Document::create([
            'title' => 'Concurrency Guarded Document',
            'category_id' => DocumentCategory::first()->id,
            'confidentiality_id' => \App\Models\ConfidentialityLevel::first()->id,
            'owner_unit_id' => $this->unit->id,
            'created_by' => $this->admin->id,
            'reference_number' => 'WU/DOC/CONC/2026/001',
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $this->assertEquals(0, $doc->lock_version);

        // Transaction 1 reads doc at lock_version 0 and updates successfully to 1
        $affectedRowsT1 = DB::table('documents')
            ->where('id', $doc->id)
            ->where('lock_version', 0)
            ->update([
                'title' => 'Updated by Session 1',
                'lock_version' => 1,
            ]);

        $this->assertEquals(1, $affectedRowsT1);
        $this->assertEquals(1, $doc->fresh()->lock_version);

        // Transaction 2 (concurrent session that read stale lock_version 0) attempts to update
        $affectedRowsT2 = DB::table('documents')
            ->where('id', $doc->id)
            ->where('lock_version', 0)
            ->update([
                'title' => 'Conflicting write from Stale Session 2',
                'lock_version' => 1,
            ]);

        // Stale update affects 0 rows, completely preventing lost updates / dirty write!
        $this->assertEquals(0, $affectedRowsT2);
        $this->assertEquals('Updated by Session 1', $doc->fresh()->title);
    }

    public function test_transaction_rollback_guarantees_atomicity_on_failure(): void
    {
        $doc = Document::create([
            'title' => 'Atomic Transaction Document',
            'category_id' => DocumentCategory::first()->id,
            'confidentiality_id' => \App\Models\ConfidentialityLevel::first()->id,
            'owner_unit_id' => $this->unit->id,
            'created_by' => $this->admin->id,
            'reference_number' => 'WU/DOC/ATOM/2026/001',
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $originalTitle = $doc->title;

        try {
            DB::transaction(function () use ($doc) {
                // Step 1: Update document title
                DB::table('documents')->where('id', $doc->id)->update([
                    'title' => 'Tentative Title Update in Transaction',
                ]);

                // Step 2: Simulate unhandled runtime exception or constraint failure
                throw new \RuntimeException('Simulated catastrophic database dispatch failure');
            });
        } catch (\RuntimeException $e) {
            // Expected catch
        }

        // Assert transaction rollback restored original database state completely
        $this->assertEquals($originalTitle, $doc->fresh()->title);
    }

    public function test_audit_events_composite_index_eliminates_filesort(): void
    {
        $sql = "EXPLAIN SELECT id, actor_id, action, occurred_at FROM audit_events FORCE INDEX (audit_events_ix_5) WHERE target_type = 'document' AND occurred_at >= '2026-01-01 00:00:00' ORDER BY occurred_at DESC LIMIT 50";
        $plan = DB::select($sql);

        $this->assertNotEmpty($plan);
        $row = $plan[0];

        $this->assertEquals('audit_events', $row->table);
        $this->assertEquals('audit_events_ix_5', $row->key);
        $this->assertStringContainsString('Backward index scan', $row->Extra);
        $this->assertStringNotContainsString('Using filesort', $row->Extra);
    }

    public function test_workflow_task_decisions_composite_index_eliminates_table_scan(): void
    {
        $sql = "EXPLAIN SELECT wtd.id, wtd.decision FROM workflow_task_decisions wtd FORCE INDEX (workflow_task_decisions_ix_2) WHERE wtd.decision = 'approve' AND wtd.task_id = 1";
        $plan = DB::select($sql);

        $this->assertNotEmpty($plan);
        $row = $plan[0];

        $this->assertEquals('wtd', $row->table);
        $this->assertEquals('ref', $row->type);
        $this->assertEquals('workflow_task_decisions_ix_2', $row->key);
        $this->assertNotEquals('ALL', $row->type);
        $this->assertStringContainsString('Using index', $row->Extra);
    }

    public function test_user_notifications_index_eliminates_filesort_on_inbox_listing(): void
    {
        $sql = "EXPLAIN SELECT id, user_id, event_id, read_at, created_at FROM user_notifications FORCE INDEX (user_notifications_ix_2) WHERE user_id = 1 ORDER BY created_at DESC LIMIT 20";
        $plan = DB::select($sql);

        $this->assertNotEmpty($plan);
        $row = $plan[0];

        $this->assertEquals('user_notifications', $row->table);
        $this->assertEquals('ref', $row->type);
        $this->assertEquals('user_notifications_ix_2', $row->key);
        $this->assertStringContainsString('Backward index scan', $row->Extra);
        $this->assertStringNotContainsString('Using filesort', $row->Extra);
    }

    public function test_document_relations_covering_index_satisfies_linked_response_lookup(): void
    {
        $sql = "EXPLAIN SELECT target_id FROM document_relations FORCE INDEX (document_relations_ix_3) WHERE source_id = 1 AND relation_type_id = 1";
        $plan = DB::select($sql);

        $this->assertNotEmpty($plan);
        $row = $plan[0];

        $this->assertEquals('document_relations', $row->table);
        $this->assertEquals('ref', $row->type);
        $this->assertEquals('document_relations_ix_3', $row->key);
        $this->assertStringContainsString('Using index', $row->Extra);
    }

    public function test_document_movements_composite_index_satisfies_inter_office_tracking(): void
    {
        $sql = "EXPLAIN SELECT id, document_id, to_unit_id, sent_at FROM document_movements FORCE INDEX (document_movements_ix_5) WHERE document_id = 1 AND to_unit_id = 2 ORDER BY sent_at DESC LIMIT 10";
        $plan = DB::select($sql);

        $this->assertNotEmpty($plan);
        $row = $plan[0];

        $this->assertEquals('document_movements', $row->table);
        $this->assertEquals('ref', $row->type);
        $this->assertEquals('document_movements_ix_5', $row->key);
        $this->assertStringContainsString('Backward index scan', $row->Extra);
        $this->assertStringContainsString('Using index', $row->Extra);
    }
}
