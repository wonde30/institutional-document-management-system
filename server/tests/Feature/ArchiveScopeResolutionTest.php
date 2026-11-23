<?php

namespace Tests\Feature;

use App\Models\ArchiveCollection;
use App\Models\ArchiveEntry;
use App\Models\ConfidentialityLevel;
use App\Models\DispositionRequest;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\OrganizationUnit;
use App\Models\RetentionPolicyVersion;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveScopeResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_archivist_unit_scope_and_independent_approval_lifecycle(): void
    {
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $unitA = OrganizationUnit::first();
        $unitB = OrganizationUnit::skip(1)->first();
        $this->assertNotNull($unitA);
        $this->assertNotNull($unitB);

        $category = DocumentCategory::first();
        $conf = ConfidentialityLevel::first();
        $collection = ArchiveCollection::first();
        $retentionVersion = RetentionPolicyVersion::first();

        $archivistA = User::create([
            'name' => 'Archivist Alpha',
            'email' => 'archivist.alpha@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($archivistA, 'archivist', 'unit', $unitA->id);

        $archivistB = User::create([
            'name' => 'Archivist Beta',
            'email' => 'archivist.beta@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($archivistB, 'archivist', 'unit', $unitB->id);

        $admin = User::create([
            'name' => 'Central Admin',
            'email' => 'admin.test@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($admin, 'super_admin', 'global');

        // Document in Unit A
        $docA = Document::create([
            'reference_number' => 'WU/ARCH/UA/' . uniqid(),
            'title' => 'Unit A Archived Record',
            'summary' => 'Unit A Record for Scope Test',
            'category_id' => $category->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $unitA->id,
            'created_by' => $admin->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);
        $entryA = ArchiveEntry::create([
            'document_id' => $docA->id,
            'collection_id' => $collection->id,
            'retention_policy_version_id' => $retentionVersion->id,
            'archived_by' => $admin->id,
            'archived_at' => now(),
        ]);

        // Document in Unit B
        $docB = Document::create([
            'reference_number' => 'WU/ARCH/UB/' . uniqid(),
            'title' => 'Unit B Archived Record',
            'summary' => 'Unit B Record for Scope Test',
            'category_id' => $category->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $unitB->id,
            'created_by' => $admin->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);
        $entryB = ArchiveEntry::create([
            'document_id' => $docB->id,
            'collection_id' => $collection->id,
            'retention_policy_version_id' => $retentionVersion->id,
            'archived_by' => $admin->id,
            'archived_at' => now(),
        ]);

        // 1. Archivist A (Unit A) attempting disposal request on Unit B record must be BLOCKED (403)
        $this->authenticateAs($archivistA);
        $forbiddenRes = $this->postJson("/api/v1/archive/entries/{$entryB->id}/request-disposal", [
            'requested_action' => 'destroy_digital',
            'justification' => 'Cross-unit disposal request should be denied',
        ]);
        $forbiddenRes->assertStatus(403);

        // 2. Archivist A requesting disposal on Unit A record must SUCCEED (201)
        $requestRes = $this->postJson("/api/v1/archive/entries/{$entryA->id}/request-disposal", [
            'requested_action' => 'destroy_digital',
            'justification' => 'Retention schedule elapsed, Unit A disposal justified',
        ]);
        $requestRes->assertStatus(201);
        $dispRequestId = $requestRes->json('data.id');
        $this->assertNotNull($dispRequestId);

        // 3. Archivist A attempting self-approval on own request must be REJECTED (422)
        $selfApproveRes = $this->postJson("/api/v1/archive/disposition-requests/{$dispRequestId}/decide", [
            'decision' => 'approve',
            'reason' => 'Archivist attempting self-approval on own disposal request',
        ]);
        $selfApproveRes->assertStatus(422);

        // 4. Independent Admin approving disposition request must SUCCEED (200)
        $this->authenticateAs($admin);
        $adminApproveRes = $this->postJson("/api/v1/archive/disposition-requests/{$dispRequestId}/decide", [
            'decision' => 'approve',
            'reason' => 'Independent institutional approval granted by central administrator',
        ]);
        $adminApproveRes->assertStatus(200);

        // 5. Execution of approved disposal tombstones target document (200)
        $execRes = $this->postJson("/api/v1/archive/disposition-requests/{$dispRequestId}/execute", [
            'certificate_reference' => 'DISP-EXEC-CERT-' . uniqid(),
        ]);
        $execRes->assertStatus(200);

        $this->assertEquals('tombstoned', $docA->fresh()->lifecycle);
    }
}
