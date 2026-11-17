<?php

namespace Tests\Feature;

use App\Models\ArchiveCollection;
use App\Models\ArchiveEntry;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\OrganizationUnit;
use App\Models\RetentionPolicyVersion;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected User $archivist;
    protected User $regularStaff;
    protected Document $document;
    protected ArchiveCollection $collection;
    protected RetentionPolicyVersion $retentionVersion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $unit = OrganizationUnit::first();
        $category = DocumentCategory::first();
        $confidentiality = \App\Models\ConfidentialityLevel::first();

        $this->archivist = User::create([
            'name' => 'Institutional Archivist',
            'email' => 'archivist.test@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->archivist, 'archivist');

        $this->regularStaff = User::create([
            'name' => 'Regular Staff Member',
            'email' => 'staff.test@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->regularStaff, 'staff');

        $this->document = Document::create([
            'reference_number' => 'WU/DOC/1998/000001',
            'title' => 'Historical Campus Blueprint (1998)',
            'summary' => 'Original architectural foundation survey',
            'category_id' => $category->id,
            'confidentiality_id' => $confidentiality->id,
            'owner_unit_id' => $unit->id,
            'created_by' => $this->archivist->id,
            'document_date' => '1998-05-15',
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $this->collection = ArchiveCollection::first();
        $this->retentionVersion = RetentionPolicyVersion::first();
    }

    public function test_can_transfer_document_to_archive_and_retire_lifecycle(): void
    {
        $this->authenticateAs($this->archivist);

        $response = $this->postJson('/api/v1/archive/transfer', [
            'document_id' => $this->document->id,
            'collection_id' => $this->collection->id,
            'retention_policy_version_id' => $this->retentionVersion->id,
            'next_review_at' => now()->addYears(5)->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('archive_entries', [
            'document_id' => $this->document->id,
            'collection_id' => $this->collection->id,
            'retention_policy_version_id' => $this->retentionVersion->id,
        ]);

        // Lifecycle of document is now retired
        $doc = $this->document->fresh();
        $this->assertEquals('retired', $doc->lifecycle);
    }

    public function test_cannot_transfer_already_archived_document(): void
    {
        $this->authenticateAs($this->archivist);

        // First transfer succeeds
        $this->postJson('/api/v1/archive/transfer', [
            'document_id' => $this->document->id,
            'collection_id' => $this->collection->id,
            'retention_policy_version_id' => $this->retentionVersion->id,
        ])->assertStatus(201);

        // Second transfer fails validation (unique constraint)
        $secondRes = $this->postJson('/api/v1/archive/transfer', [
            'document_id' => $this->document->id,
            'collection_id' => $this->collection->id,
            'retention_policy_version_id' => $this->retentionVersion->id,
        ]);

        $secondRes->assertStatus(422)
            ->assertJsonValidationErrors(['document_id']);
    }

    public function test_archive_entries_list_and_collection_retrieval(): void
    {
        $this->authenticateAs($this->archivist);

        // Transfer document
        $this->postJson('/api/v1/archive/transfer', [
            'document_id' => $this->document->id,
            'collection_id' => $this->collection->id,
            'retention_policy_version_id' => $this->retentionVersion->id,
        ]);

        $listRes = $this->getJson('/api/v1/archive/entries');

        $listRes->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(1, count($listRes->json('data')));

        // Collections listing
        $colRes = $this->getJson('/api/v1/archive/collections');
        $colRes->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_unauthorized_staff_cannot_transfer_to_archive(): void
    {
        $this->authenticateAs($this->regularStaff);

        $response = $this->postJson('/api/v1/archive/transfer', [
            'document_id' => $this->document->id,
            'collection_id' => $this->collection->id,
            'retention_policy_version_id' => $this->retentionVersion->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_can_create_archive_collection(): void
    {
        $this->authenticateAs($this->archivist);

        $response = $this->postJson('/api/v1/archive/collections', [
            'code' => 'TEST_COL_2026',
            'name' => 'Wollo Historical Engineering Series',
            'description' => 'Preserved physical blueprints and foundational engineering artifacts.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'TEST_COL_2026');

        $this->assertDatabaseHas('archive_collections', [
            'code' => 'TEST_COL_2026',
        ]);
    }

    public function test_legal_hold_lifecycle_and_disposal_block(): void
    {
        $this->authenticateAs($this->archivist);

        // 1. Transfer document to archive
        $transferRes = $this->postJson('/api/v1/archive/transfer', [
            'document_id' => $this->document->id,
            'collection_id' => $this->collection->id,
            'retention_policy_version_id' => $this->retentionVersion->id,
        ]);
        $transferRes->assertStatus(201);
        $entryId = $transferRes->json('data.id');

        // 2. Place a legal hold on this document
        $holdRes = $this->postJson('/api/v1/archive/legal-holds', [
            'code' => 'HOLD-AUDIT-2026-001',
            'reason' => 'Federal auditor investigation into historical campus construction deeds.',
            'document_ids' => [$this->document->id],
        ]);
        $holdRes->assertStatus(201)
            ->assertJsonPath('success', true);
        $holdId = $holdRes->json('data.id');

        $this->assertDatabaseHas('legal_holds', [
            'code' => 'HOLD-AUDIT-2026-001',
        ]);
        $this->assertDatabaseHas('legal_hold_documents', [
            'hold_id' => $holdId,
            'document_id' => $this->document->id,
        ]);

        // 3. Check disposal eligibility: MUST be ineligible due to active hold
        $eligibilityRes = $this->getJson("/api/v1/archive/entries/{$entryId}/disposal-eligibility");
        $eligibilityRes->assertStatus(200)
            ->assertJsonPath('data.eligible', false);
        $this->assertNotEmpty($eligibilityRes->json('data.active_holds'));

        // 4. Attempt disposal while hold is active: MUST be strictly rejected (422)
        $disposeAttemptRes = $this->postJson("/api/v1/archive/entries/{$entryId}/dispose", [
            'requested_action' => 'destroy_digital',
            'justification' => 'Attempting scheduled disposal prematurely.',
        ]);
        $disposeAttemptRes->assertStatus(422)
            ->assertJsonPath('success', false);

        // Document remains retired, not tombstoned
        $this->assertEquals('retired', $this->document->fresh()->lifecycle);

        // 5. Release the legal hold
        $releaseRes = $this->postJson("/api/v1/archive/legal-holds/{$holdId}/release");
        $releaseRes->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertNotNull($releaseRes->json('data.released_at'));

        // 6. Now execute disposal: succeeds and marks tombstoned
        $disposeSuccessRes = $this->postJson("/api/v1/archive/entries/{$entryId}/dispose", [
            'requested_action' => 'destroy_digital',
            'justification' => 'Auditor clearance received and retention period finalized.',
        ]);
        $disposeSuccessRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals('tombstoned', $this->document->fresh()->lifecycle);
        $this->assertDatabaseHas('disposition_requests', [
            'archive_entry_id' => $entryId,
            'requested_action' => 'destroy_digital',
        ]);
    }

    public function test_independent_disposition_authorization_and_separation_of_duties(): void
    {
        // Second archivist for independent authorization
        $secondArchivist = User::create([
            'name' => 'Independent Chief Archivist',
            'email' => 'chief.archivist@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($secondArchivist, 'archivist');

        $this->authenticateAs($this->archivist);

        // 1. Transfer to archive
        $transferRes = $this->postJson('/api/v1/archive/transfer', [
            'document_id' => $this->document->id,
            'collection_id' => $this->collection->id,
            'retention_policy_version_id' => $this->retentionVersion->id,
        ]);
        $entryId = $transferRes->json('data.id');

        // 2. Submit disposition request for physical destruction
        $reqRes = $this->postJson("/api/v1/archive/entries/{$entryId}/request-disposal", [
            'requested_action' => 'destroy_physical',
            'justification' => 'Mandatory 25-year retention completed and physical degradation verified.',
        ]);
        $reqRes->assertStatus(201)
            ->assertJsonPath('success', true);
        $requestId = $reqRes->json('data.id');

        // 3. Self-approval attempt: Requester attempts to approve own request
        // MUST BE REJECTED (422) under archival separation of duties
        $selfApproveRes = $this->postJson("/api/v1/archive/disposition-requests/{$requestId}/decide", [
            'decision' => 'approve',
            'reason' => 'Self-approving my own disposal request.',
        ]);
        $selfApproveRes->assertStatus(422)
            ->assertJsonPath('success', false);

        // 4. Independent authorized officer reviews and approves
        $this->authenticateAs($secondArchivist);
        $approveRes = $this->postJson("/api/v1/archive/disposition-requests/{$requestId}/decide", [
            'decision' => 'approve',
            'reason' => 'Independent audit clearance granted following physical inspection.',
        ]);
        $approveRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // 5. Attempt physical execution without recorded institutional evidence
        // MUST BE REJECTED (422)
        $noEvidenceRes = $this->postJson("/api/v1/archive/disposition-requests/{$requestId}/execute", []);
        $noEvidenceRes->assertStatus(422)
            ->assertJsonPath('success', false);

        // 6. Execute with recorded institutional disposal certificate/receipt
        $executeRes = $this->postJson("/api/v1/archive/disposition-requests/{$requestId}/execute", [
            'external_receipt' => 'CERT-WU-DISP-2026-0881',
        ]);
        $executeRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals('tombstoned', $this->document->fresh()->lifecycle);
    }

    public function test_can_restore_archived_document_to_active_service(): void
    {
        $this->authenticateAs($this->archivist);

        // 1. Transfer to archive
        $transferRes = $this->postJson('/api/v1/archive/transfer', [
            'document_id' => $this->document->id,
            'collection_id' => $this->collection->id,
            'retention_policy_version_id' => $this->retentionVersion->id,
        ]);
        $entryId = $transferRes->json('data.id');
        $this->assertEquals('retired', $this->document->fresh()->lifecycle);

        // 2. Restore to active service
        $restoreRes = $this->postJson("/api/v1/archive/entries/{$entryId}/restore");
        $restoreRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals('active', $this->document->fresh()->lifecycle);
    }
}
