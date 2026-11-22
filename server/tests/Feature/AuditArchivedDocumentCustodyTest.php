<?php

namespace Tests\Feature;

use App\Models\ArchiveCollection;
use App\Models\ArchiveEntry;
use App\Models\ConfidentialityLevel;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\OrganizationUnit;
use App\Models\RetentionPolicy;
use App\Models\RetentionPolicyVersion;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditArchivedDocumentCustodyTest extends TestCase
{
    use RefreshDatabase;

    public function test_archivist_can_access_archived_document_across_units(): void
    {
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $admin = User::where('email', 'admin@wu.edu.et')->first();
        $units = OrganizationUnit::take(2)->get();
        $unitA = $units[0];
        $unitArchive = $units[1];

        $category = DocumentCategory::first();
        $conf = ConfidentialityLevel::where('code', 'internal')->first();

        $doc = Document::create([
            'reference_number' => 'DOC-ARCHIVE-CUSTODY-001',
            'category_id' => $category->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $unitA->id,
            'title' => 'Unit A Record for Archival',
            'summary' => 'Summary',
            'created_by' => $admin->id,
            'lifecycle' => 'active',
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
        ]);

        $collection = ArchiveCollection::create(['code' => 'COLL_CUST', 'name' => 'Collection']);
        $policy = RetentionPolicy::create(['code' => 'POL_CUST', 'name' => 'Policy', 'active' => true]);
        $policyVersion = RetentionPolicyVersion::create([
            'policy_id' => $policy->id,
            'version_number' => 1,
            'duration_months' => 12,
            'trigger_code' => 'creation_date',
            'effective_from' => now()->toDateString(),
            'disposition_action' => 'destroy',
        ]);

        $entry = ArchiveEntry::create([
            'document_id' => $doc->id,
            'collection_id' => $collection->id,
            'retention_policy_version_id' => $policyVersion->id,
            'archived_by' => $admin->id,
            'archived_at' => now(),
        ]);

        // Create archivist scoped only to unitArchive
        $archivist = User::create([
            'name' => 'Test Archivist User',
            'email' => 'test.archivist@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($archivist, 'archivist', 'unit', $unitArchive->id);
        \App\Models\OrganizationMembership::create([
            'user_id' => $archivist->id,
            'unit_id' => $unitArchive->id,
            'position_title' => 'Archivist',
            'starts_at' => now(),
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Archivist can view disposal eligibility
        $this->actingAs($archivist);
        $response = $this->getJson("/api/v1/archive/entries/{$entry->id}/disposal-eligibility");
        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Regular staff member in unitArchive should be denied
        $regularStaff = User::create([
            'name' => 'Regular Staff UnitArchive',
            'email' => 'regular.staff@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($regularStaff, 'staff', 'unit', $unitArchive->id);
        $this->actingAs($regularStaff);

        $staffResponse = $this->getJson("/api/v1/archive/entries/{$entry->id}/disposal-eligibility");
        $staffResponse->assertStatus(403);
    }
}
