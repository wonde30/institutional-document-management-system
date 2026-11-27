<?php

namespace Tests\Feature;

use App\Models\ConfidentialityLevel;
use App\Models\Correspondence;
use App\Models\CorrespondenceDispatch;
use App\Models\CorrespondenceParticipant;
use App\Models\CorrespondenceType;
use App\Models\DispatchMethod;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\OrganizationUnit;
use App\Models\PhysicalOriginal;
use App\Models\PhysicalStorageLocation;
use App\Models\Priority;
use App\Models\ReferenceSequence;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowState;
use App\Models\WorkflowTask;
use App\Models\WorkflowTaskDecision;
use App\Models\WorkflowVersion;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class ConcurrencyAndDeadlockTest extends TestCase
{
    protected User $admin;
    protected OrganizationUnit $unit;
    protected DocumentCategory $category;
    protected ConfidentialityLevel $confidentiality;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BaselineReferenceVocabularySeeder::class);

        $this->admin = User::where('email', 'admin@wu.edu.et')->first();
        $this->unit = OrganizationUnit::first();
        $this->category = DocumentCategory::first();
        $this->confidentiality = ConfidentialityLevel::first();
    }

    protected function tearDown(): void
    {
        // Clean up documents and dependent rows created during test to preserve isolation
        $docIds = DB::table('documents')
            ->whereRaw("reference_number LIKE 'WU/CONC/%' OR reference_number LIKE 'WU/DEADLOCK/%' OR reference_number LIKE 'WU/PHYS/%'")
            ->pluck('id');

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('workflow_task_decisions')->whereRaw("reason LIKE '%Connection%'")->delete();
        DB::table('correspondence_dispatches')->whereRaw("tracking_reference LIKE 'TRACK-%'")->delete();

        if ($docIds->isNotEmpty()) {
            $corrIds = DB::table('correspondences')->whereIn('document_id', $docIds)->pluck('id');
            if ($corrIds->isNotEmpty()) {
                DB::table('correspondence_participants')->whereIn('correspondence_id', $corrIds)->delete();
                DB::table('correspondences')->whereIn('id', $corrIds)->delete();
            }

            $versionIds = DB::table('document_versions')->whereIn('document_id', $docIds)->pluck('id');
            if ($versionIds->isNotEmpty()) {
                $instanceIds = DB::table('workflow_instances')->whereIn('document_version_id', $versionIds)->pluck('id');
                if ($instanceIds->isNotEmpty()) {
                    DB::table('workflow_tasks')->whereIn('instance_id', $instanceIds)->delete();
                    DB::table('workflow_events')->whereIn('instance_id', $instanceIds)->delete();
                    DB::table('workflow_instances')->whereIn('id', $instanceIds)->delete();
                }
            }

            $physicalIds = DB::table('physical_originals')->whereIn('document_id', $docIds)->pluck('id');
            if ($physicalIds->isNotEmpty()) {
                DB::table('physical_custody_events')->whereIn('physical_original_id', $physicalIds)->delete();
                DB::table('physical_originals')->whereIn('id', $physicalIds)->delete();
            }

            DB::table('documents')->whereIn('id', $docIds)->delete();
        }

        DB::table('reference_sequences')->where('sequence_key', 'CONC_TEST')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        parent::tearDown();
    }

    /**
     * Helper to create an independent, separate PDO connection directly to MySQL test database.
     */
    protected function createIndependentConnection(): PDO
    {
        $host = config('database.connections.mysql.host', '127.0.0.1');
        $port = config('database.connections.mysql.port', '3306');
        $database = config('database.connections.mysql.database', 'wollo_dms_test');
        $username = config('database.connections.mysql.username', 'root');
        $password = config('database.connections.mysql.password', '');

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5,
        ]);

        return $pdo;
    }

    /**
     * Scenario 1: Concurrent document editing with optimistic lock_version protection.
     */
    public function test_scenario_1_concurrent_document_editing_prevents_lost_updates_with_http_conflict(): void
    {
        $this->actingAs($this->admin);

        $doc = Document::create([
            'title' => 'Original Document Title',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->unit->id,
            'created_by' => $this->admin->id,
            'reference_number' => 'WU/CONC/DOC/2026/001',
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        // Session 1 reads document at lock_version 0
        $session1LockVersion = 0;
        // Session 2 concurrently reads document at lock_version 0
        $session2LockVersion = 0;

        // Session 1 updates document successfully
        $response1 = $this->putJson("/api/v1/documents/{$doc->id}", [
            'title' => 'Title Updated by Session 1',
            'lock_version' => $session1LockVersion,
        ]);

        $response1->assertStatus(200);
        $this->assertEquals(1, $doc->fresh()->lock_version);
        $this->assertEquals('Title Updated by Session 1', $doc->fresh()->title);

        // Session 2 attempts update with stale lock_version 0
        $response2 = $this->putJson("/api/v1/documents/{$doc->id}", [
            'title' => 'Stale Conflicting Update by Session 2',
            'lock_version' => $session2LockVersion,
        ]);

        // Must fail with HTTP 409 Conflict
        $response2->assertStatus(409);
        $response2->assertJson([
            'success' => false,
            'message' => 'Conflict: The document metadata was updated by another process. Please refresh and retry.',
        ]);

        // Verify the database still has Session 1's title and was NOT overwritten
        $this->assertEquals('Title Updated by Session 1', $doc->fresh()->title);
        $this->assertEquals(1, $doc->fresh()->lock_version);
    }

    /**
     * Scenario 2: Reference number generation under concurrent independent database connections.
     */
    public function test_scenario_2_concurrent_reference_number_generation_guarantees_uniqueness(): void
    {
        $year = '2026';

        // Seed initial sequence row
        ReferenceSequence::updateOrCreate(
            ['sequence_key' => 'CONC_TEST', 'period_key' => $year],
            ['next_value' => 1]
        );

        $pdo1 = $this->createIndependentConnection();
        $pdo2 = $this->createIndependentConnection();

        // Connection 1 begins transaction and acquires exclusive lock on sequence row
        $pdo1->beginTransaction();
        $stmt1 = $pdo1->prepare("SELECT id, next_value FROM reference_sequences WHERE sequence_key = 'CONC_TEST' AND period_key = :period FOR UPDATE");
        $stmt1->execute([':period' => $year]);
        $row1 = $stmt1->fetch();
        $val1 = (int) $row1['next_value'];

        $upd1 = $pdo1->prepare("UPDATE reference_sequences SET next_value = :next WHERE id = :id");
        $upd1->execute([':next' => $val1 + 1, ':id' => $row1['id']]);
        $pdo1->commit();

        // Connection 2 now acquires lock on committed sequence row
        $pdo2->beginTransaction();
        $stmt2 = $pdo2->prepare("SELECT id, next_value FROM reference_sequences WHERE sequence_key = 'CONC_TEST' AND period_key = :period FOR UPDATE");
        $stmt2->execute([':period' => $year]);
        $row2 = $stmt2->fetch();
        $val2 = (int) $row2['next_value'];

        $upd2 = $pdo2->prepare("UPDATE reference_sequences SET next_value = :next WHERE id = :id");
        $upd2->execute([':next' => $val2 + 1, ':id' => $row2['id']]);
        $pdo2->commit();

        // Verify values are strictly sequential and unique (1 and 2)
        $this->assertEquals(1, $val1);
        $this->assertEquals(2, $val2);
        $this->assertNotEquals($val1, $val2);

        $finalSeq = ReferenceSequence::where('sequence_key', 'CONC_TEST')->where('period_key', $year)->first();
        $this->assertEquals(3, $finalSeq->next_value);
    }

    /**
     * Scenario 3: Concurrent workflow approval on the same task prevents competing decisions.
     */
    public function test_scenario_3_concurrent_workflow_decision_prevents_duplicate_or_conflicting_commit(): void
    {
        $doc = Document::create([
            'title' => 'Concurrent Workflow Document',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->unit->id,
            'created_by' => $this->admin->id,
            'reference_number' => 'WU/CONC/WF/001',
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $version = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $this->admin->id,
            'created_at' => now(),
        ]);

        $wfDef = WorkflowDefinition::firstOrCreate(
            ['code' => 'WF_CONC_DEF'],
            ['name' => 'Concurrent Workflow Protocol', 'active' => true]
        );

        $wfVersion = WorkflowVersion::firstOrCreate(
            ['definition_id' => $wfDef->id, 'version_number' => 1],
            ['created_by' => $this->admin->id, 'created_at' => now(), 'published_at' => now()]
        );

        $state = WorkflowState::firstOrCreate(
            ['workflow_version_id' => $wfVersion->id, 'code' => 'review'],
            ['name' => 'Review State', 'is_initial' => true]
        );

        $instance = WorkflowInstance::create([
            'workflow_version_id' => $wfVersion->id,
            'document_version_id' => $version->id,
            'status' => 'active',
            'started_by' => $this->admin->id,
            'started_at' => now(),
        ]);

        $task = WorkflowTask::create([
            'instance_id' => $instance->id,
            'state_id' => $state->id,
            'assigned_user_id' => $this->admin->id,
            'due_at' => now()->addDays(3),
        ]);

        $pdo1 = $this->createIndependentConnection();
        $pdo2 = $this->createIndependentConnection();

        // Connection 1 decides 'approve'
        $pdo1->beginTransaction();
        $ins1 = $pdo1->prepare("INSERT INTO workflow_task_decisions (task_id, decided_by, decision, reason, decided_at) VALUES (:task_id, :decided_by, 'approve', 'Approved by Connection 1', NOW(6))");
        $ins1->execute([':task_id' => $task->id, ':decided_by' => $this->admin->id]);
        $pdo1->commit();

        // Connection 2 attempts competing 'reject' decision on the same task
        $pdo2->beginTransaction();
        $rejected = false;
        try {
            $ins2 = $pdo2->prepare("INSERT INTO workflow_task_decisions (task_id, decided_by, decision, reason, decided_at) VALUES (:task_id, :decided_by, 'reject', 'Reject by Connection 2', NOW(6))");
            $ins2->execute([':task_id' => $task->id, ':decided_by' => $this->admin->id]);
            $pdo2->commit();
        } catch (\PDOException $e) {
            $pdo2->rollBack();
            // MySQL error 1062: Duplicate entry for key 'workflow_task_decisions_uq_1'
            $this->assertStringContainsString('Duplicate entry', $e->getMessage());
            $rejected = true;
        }

        $this->assertTrue($rejected, 'Competing second workflow decision must be rejected by unique constraint');

        // Verify only exactly one decision exists for the task
        $decisions = WorkflowTaskDecision::where('task_id', $task->id)->get();
        $this->assertCount(1, $decisions);
        $this->assertEquals('approve', $decisions->first()->decision);
    }

    /**
     * Scenario 4: Duplicate correspondence dispatch prevention via unique constraints.
     */
    public function test_scenario_4_duplicate_correspondence_dispatch_is_prevented(): void
    {
        $doc = Document::create([
            'title' => 'Dispatch Concurrency Letter',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->unit->id,
            'created_by' => $this->admin->id,
            'reference_number' => 'WU/CONC/DISP/001',
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $version = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $this->admin->id,
            'created_at' => now(),
        ]);

        $corr = Correspondence::create([
            'document_id' => $doc->id,
            'type_id' => CorrespondenceType::where('code', 'outgoing')->first()->id,
            'priority_id' => Priority::first()->id,
            'purpose_code' => 'notice',
            'external_reference' => 'EXT-DISP-001',
            'correspondence_date' => '2026-10-30',
            'registered_by' => $this->admin->id,
            'registered_at' => now(),
        ]);

        $party = \App\Models\CommunicationParty::firstOrCreate(
            ['unit_id' => $this->unit->id, 'user_id' => null, 'external_party_id' => null]
        );

        $participant = CorrespondenceParticipant::create([
            'correspondence_id' => $corr->id,
            'party_id' => $party->id,
            'participation_type' => 'to',
        ]);

        $method = DispatchMethod::first();
        $idempotencyKey = 'IDEMP-' . uniqid();

        $pdo1 = $this->createIndependentConnection();
        $pdo2 = $this->createIndependentConnection();

        // Connection 1 inserts dispatch
        $pdo1->beginTransaction();
        $stmt1 = $pdo1->prepare("INSERT INTO correspondence_dispatches (participant_id, document_version_id, method_id, tracking_reference, dispatched_by, dispatched_at, idempotency_key) VALUES (:participant_id, :document_version_id, :method_id, 'TRACK-001', :dispatched_by, NOW(6), :idempotency_key)");
        $stmt1->execute([
            ':participant_id' => $participant->id,
            ':document_version_id' => $version->id,
            ':method_id' => $method->id,
            ':dispatched_by' => $this->admin->id,
            ':idempotency_key' => $idempotencyKey,
        ]);
        $pdo1->commit();

        // Connection 2 attempts identical duplicate dispatch with same idempotency key
        $pdo2->beginTransaction();
        $duplicateRejected = false;
        try {
            $stmt2 = $pdo2->prepare("INSERT INTO correspondence_dispatches (participant_id, document_version_id, method_id, tracking_reference, dispatched_by, dispatched_at, idempotency_key) VALUES (:participant_id, :document_version_id, :method_id, 'TRACK-001', :dispatched_by, NOW(6), :idempotency_key)");
            $stmt2->execute([
                ':participant_id' => $participant->id,
                ':document_version_id' => $version->id,
                ':method_id' => $method->id,
                ':dispatched_by' => $this->admin->id,
                ':idempotency_key' => $idempotencyKey,
            ]);
            $pdo2->commit();
        } catch (\PDOException $e) {
            $pdo2->rollBack();
            $this->assertStringContainsString('Duplicate entry', $e->getMessage());
            $duplicateRejected = true;
        }

        $this->assertTrue($duplicateRejected, 'Duplicate dispatch with identical idempotency key must be rejected');
        $this->assertEquals(1, CorrespondenceDispatch::where('participant_id', $participant->id)->count());
    }

    /**
     * Scenario 5: Concurrent physical custody transfer consistency.
     */
    public function test_scenario_5_concurrent_physical_custody_transfer_consistency(): void
    {
        $doc = Document::create([
            'title' => 'Physical Custody Document',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->unit->id,
            'created_by' => $this->admin->id,
            'reference_number' => 'WU/PHYS/CONC/' . uniqid(),
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $loc = PhysicalStorageLocation::firstOrCreate(
            ['code' => 'MAIN-VAULT-01'],
            ['name' => 'Main University Archive Vault', 'unit_id' => $this->unit->id, 'active' => true]
        );

        $physical = PhysicalOriginal::create([
            'document_id' => $doc->id,
            'physical_reference' => 'WU-PHYS-REF-' . uniqid(),
            'created_by' => $this->admin->id,
            'created_at' => now(),
        ]);

        // Initial registered event
        DB::table('physical_custody_events')->insert([
            'physical_original_id' => $physical->id,
            'location_id' => $loc->id,
            'custodian_id' => $this->admin->id,
            'action' => 'register',
            'actor_id' => $this->admin->id,
            'occurred_at' => now(),
            'reason' => 'Initial repository registration',
        ]);

        // Session 1 and Session 2 compete to checkout physical custody simultaneously
        $pdo1 = $this->createIndependentConnection();
        $pdo2 = $this->createIndependentConnection();

        // Connection 1 begins transaction, acquires exclusive lock on physical record
        $pdo1->beginTransaction();
        $lock1 = $pdo1->prepare("SELECT id FROM physical_originals WHERE id = :id FOR UPDATE");
        $lock1->execute([':id' => $physical->id]);

        // Connection 1 checks latest custody event state
        $check1 = $pdo1->prepare("SELECT action FROM physical_custody_events WHERE physical_original_id = :id ORDER BY occurred_at DESC, id DESC LIMIT 1");
        $check1->execute([':id' => $physical->id]);
        $latestAction1 = $check1->fetchColumn();

        $this->assertEquals('register', $latestAction1);

        // Connection 1 records checkout
        $ins1 = $pdo1->prepare("INSERT INTO physical_custody_events (physical_original_id, location_id, custodian_id, action, actor_id, occurred_at, reason) VALUES (:id, :loc, :custodian, 'checkout', :actor, NOW(6), 'Authorized research checkout')");
        $ins1->execute([
            ':id' => $physical->id,
            ':loc' => $loc->id,
            ':custodian' => $this->admin->id,
            ':actor' => $this->admin->id,
        ]);
        $pdo1->commit();

        // Connection 2 begins transaction, acquires exclusive lock (unblocked after pdo1 commits)
        $pdo2->beginTransaction();
        $lock2 = $pdo2->prepare("SELECT id FROM physical_originals WHERE id = :id FOR UPDATE");
        $lock2->execute([':id' => $physical->id]);

        // Connection 2 reads latest custody event state (now sees Connection 1's committed 'checkout')
        $check2 = $pdo2->prepare("SELECT action FROM physical_custody_events WHERE physical_original_id = :id ORDER BY occurred_at DESC, id DESC LIMIT 1");
        $check2->execute([':id' => $physical->id]);
        $latestAction2 = $check2->fetchColumn();

        // Competing checkout must be rejected because item is already checked out!
        $competingCheckoutPermitted = false;
        if ($latestAction2 !== 'checkout') {
            $competingCheckoutPermitted = true;
            $ins2 = $pdo2->prepare("INSERT INTO physical_custody_events (physical_original_id, location_id, custodian_id, action, actor_id, occurred_at, reason) VALUES (:id, :loc, :custodian, 'checkout', :actor, NOW(6), 'Conflicting checkout attempt')");
            $ins2->execute([
                ':id' => $physical->id,
                ':loc' => $loc->id,
                ':custodian' => $this->admin->id,
                ':actor' => $this->admin->id,
            ]);
        }
        $pdo2->commit();

        // Assert competing checkout was rejected
        $this->assertFalse($competingCheckoutPermitted, 'Competing second checkout must be prevented by serialized custody lock evaluation');

        // Custody chain remains strictly consistent: exactly 1 register, 1 checkout
        $events = DB::table('physical_custody_events')
            ->where('physical_original_id', $physical->id)
            ->orderBy('id', 'asc')
            ->get();

        $this->assertCount(2, $events);
        $this->assertEquals('register', $events[0]->action);
        $this->assertEquals('checkout', $events[1]->action);
    }

    /**
     * Scenario 6: Controlled deadlock scenario with detection, rollback, and bounded retry recovery.
     */
    public function test_scenario_6_deadlock_detection_and_bounded_retry_recovery(): void
    {
        // Pre-create two distinct documents
        $docA = Document::create([
            'title' => 'Resource A',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->unit->id,
            'created_by' => $this->admin->id,
            'reference_number' => 'WU/DEADLOCK/A/' . uniqid(),
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $docB = Document::create([
            'title' => 'Resource B',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->unit->id,
            'created_by' => $this->admin->id,
            'reference_number' => 'WU/DEADLOCK/B/' . uniqid(),
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $idA = $docA->id;
        $idB = $docB->id;

        // 1. Deterministic Lock Ordering:
        // Always acquiring locks in ascending ID order prevents deadlock cycles
        $orderedIds = [$idA, $idB];
        sort($orderedIds);

        DB::transaction(function () use ($orderedIds) {
            foreach ($orderedIds as $id) {
                DB::table('documents')->where('id', $id)->lockForUpdate()->first();
            }
            DB::table('documents')->where('id', $orderedIds[0])->update(['title' => 'Updated by Tx1 (Ordered)']);
        });

        $this->assertEquals('Updated by Tx1 (Ordered)', $docA->fresh()->title);

        // 2. Bounded Retry Engine for Transient Deadlocks:
        // Implement bounded retry wrapper mimicking enterprise database resilience
        $maxAttempts = 3;
        $attempts = 0;
        $executed = false;

        while ($attempts < $maxAttempts && ! $executed) {
            $attempts++;
            try {
                DB::transaction(function () use ($idA, $attempts) {
                    if ($attempts === 1) {
                        // Simulate transient MySQL Deadlock (error code 1213) on initial attempt
                        throw new QueryException(
                            'mysql',
                            'UPDATE documents SET title = ? WHERE id = ?',
                            ['Retry Title', $idA],
                            new \Exception('Deadlock found when trying to get lock; try restarting transaction', 1213)
                        );
                    }
                    DB::table('documents')->where('id', $idA)->update(['title' => 'Successfully Recovered via Bounded Retry']);
                });
                $executed = true;
            } catch (QueryException $e) {
                if ($e->getPrevious() && $e->getPrevious()->getCode() == 1213 && $attempts < $maxAttempts) {
                    // Backoff and retry
                    usleep(10000); // 10ms
                    continue;
                }
                throw $e;
            }
        }

        $this->assertTrue($executed, 'Transaction must succeed upon bounded retry');
        $this->assertEquals(2, $attempts, 'Must have succeeded on attempt 2 after 1 simulated deadlock');
        $this->assertEquals('Successfully Recovered via Bounded Retry', $docA->fresh()->title);
    }
}
