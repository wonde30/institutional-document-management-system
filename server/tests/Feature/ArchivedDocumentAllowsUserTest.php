<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ArchiveCollection;
use App\Models\ArchiveEntry;
use App\Models\ConfidentialityLevel;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\OrganizationUnit;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ArchivedDocumentAllowsUserTest extends TestCase
{
    use RefreshDatabase;

    protected OrganizationUnit $unitA;
    protected OrganizationUnit $unitB;
    protected User $archivistUnitB;
    protected User $plainStaff;
    protected DocumentCategory $cat;
    protected ConfidentialityLevel $publicConf;
    protected ConfidentialityLevel $restrictedConf;
    protected ArchiveCollection $collection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $units = OrganizationUnit::take(2)->get();
        $this->unitA = $units[0];
        $this->unitB = $units->count() > 1 ? $units[1] : OrganizationUnit::create([
            'parent_id' => $this->unitA->id,
            'unit_type_id' => $this->unitA->unit_type_id,
            'code' => 'UNIT_B_' . Str::random(4),
            'name' => 'Unit B',
            'active' => 1,
        ]);

        $this->cat = DocumentCategory::first();
        $this->publicConf = ConfidentialityLevel::where('code', 'public')->first() ?? ConfidentialityLevel::first();
        $this->restrictedConf = ConfidentialityLevel::where('code', 'restricted')->first() ?? ConfidentialityLevel::where('code', 'confidential')->first();

        $this->collection = ArchiveCollection::create([
            'name' => 'General Institutional Archive Collection',
            'code' => 'ARC-COL-' . Str::random(4),
            'custodian_unit_id' => $this->unitB->id,
        ]);

        // Archivist in Unit B (has archive.view, archive.transfer, archive.dispose in Unit B only)
        $this->archivistUnitB = User::create([
            'name' => 'Archivist Unit B',
            'email' => 'archivist.b.' . Str::random(6) . '@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($this->archivistUnitB, 'archive.view', 'unit', $this->unitB->id);
        $this->grantPermissionToUser($this->archivistUnitB, 'archive.transfer', 'unit', $this->unitB->id);
        $this->grantPermissionToUser($this->archivistUnitB, 'archive.request_disposal', 'unit', $this->unitB->id);

        // Plain staff user (no archive permissions)
        $this->plainStaff = User::create([
            'name' => 'Plain Staff Member',
            'email' => 'staff.' . Str::random(6) . '@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($this->plainStaff, 'documents.view', 'unit', $this->unitA->id);
    }

    protected function createArchivedDocument(OrganizationUnit $ownerUnit, ConfidentialityLevel $conf): Document
    {
        $doc = Document::create([
            'reference_number' => 'WU/ARC/DOC/' . Str::random(6),
            'title' => 'Archived Record ' . $ownerUnit->code,
            'summary' => 'Historical university document',
            'category_id' => $this->cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $ownerUnit->id,
            'created_by' => 1,
            'custodian_id' => 1,
            'document_date' => now()->subYears(5)->toDateString(),
            'registered_at' => now()->subYears(5),
            'lifecycle' => 'retired',
            'lock_version' => 0,
        ]);

        ArchiveEntry::create([
            'document_id' => $doc->id,
            'collection_id' => $this->collection->id,
            'archived_by' => 1,
            'archived_at' => now()->subYears(2),
            'retention_status' => 'retained',
        ]);

        return $doc;
    }

    public function test_archivist_in_unit_b_can_read_non_confidential_archived_record_of_unit_a(): void
    {
        $doc = $this->createArchivedDocument($this->unitA, $this->publicConf);

        // Cross-unit read allowed for archive.view
        $this->assertTrue($doc->allowsUser($this->archivistUnitB, 'archive.view'));
    }

    public function test_archivist_in_unit_b_cannot_read_restricted_archived_record_without_clearance(): void
    {
        $doc = $this->createArchivedDocument($this->unitA, $this->restrictedConf);

        // Archivist lacks staff.view_sensitive clearance for Unit A -> MUST BE BLOCKED
        $this->assertFalse($doc->allowsUser($this->archivistUnitB, 'archive.view'));
    }

    public function test_archivist_in_unit_b_cannot_transfer_or_dispose_archived_record_of_unit_a(): void
    {
        $doc = $this->createArchivedDocument($this->unitA, $this->publicConf);

        // Transfer and disposal are NOT cross-unit; must be checked against document owning unit (Unit A)
        $this->assertFalse($doc->allowsUser($this->archivistUnitB, 'archive.transfer'));
        $this->assertFalse($doc->allowsUser($this->archivistUnitB, 'archive.request_disposal'));
    }

    public function test_plain_staff_user_cannot_read_archived_record(): void
    {
        $doc = $this->createArchivedDocument($this->unitA, $this->publicConf);

        // Plain staff lacks archive.view -> MUST BE BLOCKED
        $this->assertFalse($doc->allowsUser($this->plainStaff, 'archive.view'));
    }

    public function test_archive_view_accessible_by_parity_with_allows_user(): void
    {
        // 1. Non-confidential archived document of Unit A:
        // Opens by ID (allowsUser) AND appears in archive list (accessibleBy) for archivist in Unit B
        $publicDoc = $this->createArchivedDocument($this->unitA, $this->publicConf);
        $this->assertTrue($publicDoc->allowsUser($this->archivistUnitB, 'archive.view'));
        $this->assertTrue(Document::accessibleBy($this->archivistUnitB, 'archive.view')->where('documents.id', $publicDoc->id)->exists());

        $listRes = $this->actingAs($this->archivistUnitB)->getJson('/api/v1/archive/entries');
        $listRes->assertStatus(200);
        $docIds = collect($listRes->json('data'))->pluck('document_id')->all();
        $this->assertContains($publicDoc->id, $docIds);

        // 2. Restricted archived document of Unit A without clearance:
        // Does NOT open by ID (allowsUser = false) AND does NOT appear in archive list (accessibleBy = false)
        $restrictedDoc = $this->createArchivedDocument($this->unitA, $this->restrictedConf);
        $this->assertFalse($restrictedDoc->allowsUser($this->archivistUnitB, 'archive.view'));
        $this->assertFalse(Document::accessibleBy($this->archivistUnitB, 'archive.view')->where('documents.id', $restrictedDoc->id)->exists());

        $listRes2 = $this->actingAs($this->archivistUnitB)->getJson('/api/v1/archive/entries');
        $docIds2 = collect($listRes2->json('data'))->pluck('document_id')->all();
        $this->assertNotContains($restrictedDoc->id, $docIds2);

        // 3. Grant staff.view_sensitive clearance in Unit A to archivist:
        // Now opens by ID AND appears in archive list
        $this->grantPermissionToUser($this->archivistUnitB, 'staff.view_sensitive', 'unit', $this->unitA->id);
        $this->assertTrue($restrictedDoc->allowsUser($this->archivistUnitB, 'archive.view'));
        $this->assertTrue(Document::accessibleBy($this->archivistUnitB, 'archive.view')->where('documents.id', $restrictedDoc->id)->exists());

        $listRes3 = $this->actingAs($this->archivistUnitB)->getJson('/api/v1/archive/entries');
        $docIds3 = collect($listRes3->json('data'))->pluck('document_id')->all();
        $this->assertContains($restrictedDoc->id, $docIds3);

        // 4. Plain staff user (no archive.view):
        // Does NOT open by ID and does NOT appear in list
        $this->assertFalse($publicDoc->allowsUser($this->plainStaff, 'archive.view'));
        $this->assertFalse(Document::accessibleBy($this->plainStaff, 'archive.view')->where('documents.id', $publicDoc->id)->exists());
    }
}
