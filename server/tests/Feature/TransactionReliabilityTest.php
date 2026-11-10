<?php

namespace Tests\Feature;

use App\Models\ArchiveCollection;
use App\Models\ArchiveEntry;
use App\Models\AuditEvent;
use App\Models\CommunicationParty;
use App\Models\ConfidentialityLevel;
use App\Models\Correspondence;
use App\Models\CorrespondenceDispatch;
use App\Models\CorrespondenceParticipant;
use App\Models\CorrespondenceType;
use App\Models\DispatchMethod;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentMovement;
use App\Models\DocumentRelation;
use App\Models\DocumentRelationType;
use App\Models\DocumentVersion;
use App\Models\MovementReceipt;
use App\Models\OrganizationUnit;
use App\Models\Priority;
use App\Models\ReferenceSequence;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTask;
use App\Models\WorkflowTaskDecision;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionReliabilityTest extends TestCase
{
    use RefreshDatabase;

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

    /**
     * Test 1: Forced failure during document registration rolls back document and version completely.
     */
    public function test_document_registration_rolls_back_atomically_on_partial_failure(): void
    {
        $initialDocCount = Document::count();
        $initialVersionCount = DocumentVersion::count();

        $refNumber = 'WU/DOC/ROLLBACK/' . uniqid();

        try {
            DB::transaction(function () use ($refNumber) {
                // Step 1: Create parent document
                $doc = Document::create([
                    'title' => 'Volatile Document Pending Failure',
                    'category_id' => $this->category->id,
                    'confidentiality_id' => $this->confidentiality->id,
                    'owner_unit_id' => $this->unit->id,
                    'created_by' => $this->admin->id,
                    'reference_number' => $refNumber,
                    'document_date' => '2026-10-30',
                    'registered_at' => now(),
                    'lifecycle' => 'active',
                ]);

                // Step 2: Create version 1
                DocumentVersion::create([
                    'document_id' => $doc->id,
                    'version_number' => 1,
                    'created_by' => $this->admin->id,
                    'created_at' => now(),
                ]);

                // Step 3: Trigger simulated failure
                throw new \RuntimeException('Simulated catastrophic storage failure during document registration');
            });
        } catch (\RuntimeException $e) {
            $this->assertEquals('Simulated catastrophic storage failure during document registration', $e->getMessage());
        }

        // Verify zero orphan documents or versions were committed to the database
        $this->assertEquals($initialDocCount, Document::count());
        $this->assertEquals($initialVersionCount, DocumentVersion::count());
        $this->assertNull(Document::where('reference_number', $refNumber)->first());
    }

    /**
     * Test 2: Foreign key constraint enforcement rolls back transaction on orphan reference.
     */
    public function test_foreign_key_violation_triggers_automatic_transaction_rollback(): void
    {
        $initialVersionCount = DocumentVersion::count();

        try {
            DB::transaction(function () {
                // Attempt to insert version referencing non-existent document ID 999999
                DocumentVersion::create([
                    'document_id' => 999999,
                    'version_number' => 1,
                    'created_by' => $this->admin->id,
                    'created_at' => now(),
                ]);
            });
            $this->fail('Expected QueryException due to foreign key constraint violation');
        } catch (QueryException $e) {
            // MySQL error 1452: Cannot add or update a child row: a foreign key constraint fails
            $this->assertStringContainsString('foreign key constraint fails', $e->getMessage());
        }

        $this->assertEquals($initialVersionCount, DocumentVersion::count());
    }

    /**
     * Test 3: Unique constraint violation on reference sequence or unique index rolls back cleanly.
     */
    public function test_unique_constraint_violation_triggers_rollback(): void
    {
        $refNumber = 'WU/DOC/UNIQUE/2026/001';

        // Pre-create document with unique reference
        Document::create([
            'title' => 'Original Document',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->unit->id,
            'created_by' => $this->admin->id,
            'reference_number' => $refNumber,
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $initialDocCount = Document::count();

        try {
            DB::transaction(function () use ($refNumber) {
                // Step 1: Create another document
                Document::create([
                    'title' => 'Legitimate Sibling Document',
                    'category_id' => $this->category->id,
                    'confidentiality_id' => $this->confidentiality->id,
                    'owner_unit_id' => $this->unit->id,
                    'created_by' => $this->admin->id,
                    'reference_number' => 'WU/DOC/UNIQUE/2026/002',
                    'document_date' => '2026-10-30',
                    'registered_at' => now(),
                    'lifecycle' => 'active',
                ]);

                // Step 2: Attempt duplicate reference number
                Document::create([
                    'title' => 'Conflicting Duplicate Document',
                    'category_id' => $this->category->id,
                    'confidentiality_id' => $this->confidentiality->id,
                    'owner_unit_id' => $this->unit->id,
                    'created_by' => $this->admin->id,
                    'reference_number' => $refNumber, // DUPLICATE
                    'document_date' => '2026-10-30',
                    'registered_at' => now(),
                    'lifecycle' => 'active',
                ]);
            });
            $this->fail('Expected UniqueConstraintViolationException or QueryException');
        } catch (\Throwable $e) {
            $this->assertTrue(
                $e instanceof UniqueConstraintViolationException || $e instanceof QueryException,
                'Exception must be a unique constraint violation'
            );
        }

        // Assert Step 1 was also rolled back completely!
        $this->assertEquals($initialDocCount, Document::count());
        $this->assertNull(Document::where('reference_number', 'WU/DOC/UNIQUE/2026/002')->first());
    }

    /**
     * Test 4: Movement acknowledgment transaction rolls back both receipt and movement status on error.
     */
    public function test_movement_acknowledgment_transaction_atomicity(): void
    {
        $doc = Document::create([
            'title' => 'Movement Test Document',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->unit->id,
            'created_by' => $this->admin->id,
            'reference_number' => 'WU/MOV/TEST/001',
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $recipientUnit = OrganizationUnit::firstOrCreate(
            ['code' => 'TEST_RECIPIENT_UNIT'],
            ['name' => 'Test Recipient Department', 'unit_type_id' => $this->unit->unit_type_id, 'active' => true]
        );

        $movement = DocumentMovement::create([
            'document_id' => $doc->id,
            'from_unit_id' => $this->unit->id,
            'to_unit_id' => $recipientUnit->id,
            'sent_by' => $this->admin->id,
            'sent_at' => now(),
            'idempotency_key' => (string) Str::uuid(),
        ]);

        try {
            DB::transaction(function () use ($movement) {
                // Step 1: Create movement receipt
                MovementReceipt::create([
                    'movement_id' => $movement->id,
                    'acknowledged_by' => $this->admin->id,
                    'acknowledged_at' => now(),
                ]);

                // Step 2: Simulate failure before audit log
                throw new \RuntimeException('Failure during acknowledgment receipt notification dispatch');
            });
        } catch (\RuntimeException $e) {
            // Expected catch
        }

        // Verify receipt was rolled back and movement remains unacknowledged
        $this->assertNull(MovementReceipt::where('movement_id', $movement->id)->first());
    }

    /**
     * Test 5: Workflow decision transaction atomicity: rolls back task closure and decision on failure.
     */
    public function test_workflow_decision_rolls_back_task_closure_on_failure(): void
    {
        $doc = Document::create([
            'title' => 'Workflow Atomicity Document',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->unit->id,
            'created_by' => $this->admin->id,
            'reference_number' => 'WU/WF/ATOM/001',
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
            ['code' => 'TEST_WF_DEF'],
            ['name' => 'Test Workflow Protocol', 'active' => true]
        );

        $wfVersion = \App\Models\WorkflowVersion::create([
            'definition_id' => $wfDef->id,
            'version_number' => 1,
            'created_by' => $this->admin->id,
            'created_at' => now(),
            'published_at' => now(),
        ]);

        $state = \App\Models\WorkflowState::create([
            'workflow_version_id' => $wfVersion->id,
            'code' => 'review',
            'name' => 'Review State',
            'is_initial' => true,
        ]);

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

        $this->assertNull($task->closed_at);

        try {
            DB::transaction(function () use ($task) {
                // Step 1: Close task
                $task->update(['closed_at' => now()]);

                // Step 2: Record decision
                WorkflowTaskDecision::create([
                    'task_id' => $task->id,
                    'decided_by' => $this->admin->id,
                    'decision' => 'approve',
                    'reason' => 'Approved under atomicity test',
                    'decided_at' => now(),
                ]);

                // Step 3: Simulate failure before digital stamp execution
                throw new \RuntimeException('Signature certificate provider timeout');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        // Verify task closure was reversed and no decision was committed
        $this->assertNull($task->fresh()->closed_at);
        $this->assertNull(WorkflowTaskDecision::where('task_id', $task->id)->first());
    }

    /**
     * Test 6: Archive transfer transaction atomicity: archive entry and document lifecycle.
     */
    public function test_archive_transfer_atomicity(): void
    {
        $doc = Document::create([
            'title' => 'Archive Transfer Test Document',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->unit->id,
            'created_by' => $this->admin->id,
            'reference_number' => 'WU/ARC/ATOM/001',
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $collection = ArchiveCollection::firstOrCreate(
            ['code' => 'TEST-COL-01'],
            ['name' => 'Test Archival Fond', 'parent_id' => null]
        );

        try {
            DB::transaction(function () use ($doc, $collection) {
                // Step 1: Update document lifecycle to retired
                $doc->update(['lifecycle' => 'retired']);

                // Step 2: Create archive entry
                ArchiveEntry::create([
                    'document_id' => $doc->id,
                    'collection_id' => $collection->id,
                    'archived_by' => $this->admin->id,
                    'archived_at' => now(),
                ]);

                // Step 3: Simulate unhandled failure
                throw new \RuntimeException('Archival storage node unreachable');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        // Document lifecycle must remain 'active', and no archive entry should exist
        $this->assertEquals('active', $doc->fresh()->lifecycle);
        $this->assertNull(ArchiveEntry::where('document_id', $doc->id)->first());
    }

    /**
     * Test 7: Linked outgoing response transaction atomicity across 3NF tables.
     */
    public function test_linked_response_creation_atomicity(): void
    {
        $incomingDoc = Document::create([
            'title' => 'Incoming Letter for Response',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->unit->id,
            'created_by' => $this->admin->id,
            'reference_number' => 'WU/INC/RESP/001',
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $incomingCorr = Correspondence::create([
            'document_id' => $incomingDoc->id,
            'type_id' => CorrespondenceType::where('code', 'incoming')->first()->id,
            'priority_id' => Priority::first()->id,
            'purpose_code' => 'inquiry',
            'external_reference' => 'EXT-INQ-999',
            'correspondence_date' => '2026-10-30',
            'registered_by' => $this->admin->id,
            'registered_at' => now(),
        ]);

        $initialRelationCount = DocumentRelation::count();
        $outgoingRef = 'WU/CORR/OUT/ROLLBACK/001';

        try {
            DB::transaction(function () use ($incomingDoc, $outgoingRef) {
                // Step 1: Create outgoing document
                $outDoc = Document::create([
                    'title' => 'Outgoing Response Draft',
                    'category_id' => $this->category->id,
                    'confidentiality_id' => $this->confidentiality->id,
                    'owner_unit_id' => $this->unit->id,
                    'created_by' => $this->admin->id,
                    'reference_number' => $outgoingRef,
                    'document_date' => '2026-10-30',
                    'registered_at' => now(),
                    'lifecycle' => 'active',
                ]);

                // Step 2: Create 3NF DocumentRelation (responds_to)
                DocumentRelation::create([
                    'source_id' => $outDoc->id,
                    'target_id' => $incomingDoc->id,
                    'relation_type_id' => DocumentRelationType::where('code', 'responds_to')->first()->id,
                ]);

                // Step 3: Simulate failure during correspondence registry write
                throw new \RuntimeException('Failed to register outgoing correspondence record');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        // Verify zero relations or outgoing documents were committed
        $this->assertNull(Document::where('reference_number', $outgoingRef)->first());
        $this->assertEquals($initialRelationCount, DocumentRelation::count());
    }
}
