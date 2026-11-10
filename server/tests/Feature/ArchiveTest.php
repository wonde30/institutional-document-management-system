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
        $this->assertGreaterThanOrEqual(1, count($listRes->json('data.data')));

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
}
