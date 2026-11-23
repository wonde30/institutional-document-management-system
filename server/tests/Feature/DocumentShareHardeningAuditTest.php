<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ConfidentialityLevel;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DocumentShareHardeningAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $sharer;
    protected User $targetWithoutClearance;
    protected User $targetWithClearance;
    protected OrganizationUnit $unitA;
    protected OrganizationUnit $unitB;
    protected DocumentCategory $category;
    protected ConfidentialityLevel $confPublic;
    protected ConfidentialityLevel $confRestricted;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\BaselineReferenceVocabularySeeder::class);
        $this->seed(\Database\Seeders\InstitutionalCredentialSeeder::class);

        $unitType = \App\Models\UnitType::first();

        $this->unitA = OrganizationUnit::create([
            'code' => 'UNIT_A_' . uniqid(),
            'name' => 'Department A',
            'unit_type_id' => $unitType->id,
            'active' => true,
        ]);

        $this->unitB = OrganizationUnit::create([
            'code' => 'UNIT_B_' . uniqid(),
            'name' => 'Department B',
            'unit_type_id' => $unitType->id,
            'active' => true,
        ]);

        $this->category = DocumentCategory::firstOrCreate(
            ['code' => 'policy'],
            ['name' => 'Policy Document', 'active' => true]
        );

        $this->confPublic = ConfidentialityLevel::firstOrCreate(
            ['code' => 'public'],
            ['name' => 'Public', 'rank' => 10]
        );

        $this->confRestricted = ConfidentialityLevel::firstOrCreate(
            ['code' => 'restricted'],
            ['name' => 'Restricted', 'rank' => 30]
        );

        // Admin
        $this->admin = User::factory()->create(['status' => 'active', 'authz_version' => 1]);
        $this->grantPermissionToUser($this->admin, 'system.configure', 'global', null);
        $this->grantPermissionToUser($this->admin, 'documents.share', 'global', null);
        $this->grantPermissionToUser($this->admin, 'documents.view', 'global', null);
        $this->grantPermissionToUser($this->admin, 'documents.download', 'global', null);
        $this->grantPermissionToUser($this->admin, 'staff.view_sensitive', 'global', null);

        // Sharer (in Unit A, holds documents.share, documents.view, staff.view_sensitive)
        $this->sharer = User::factory()->create(['status' => 'active', 'authz_version' => 1]);
        $this->grantPermissionToUser($this->sharer, 'documents.share', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($this->sharer, 'documents.view', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($this->sharer, 'staff.view_sensitive', 'unit', $this->unitA->id);

        // Target without clearance (in Unit B)
        $this->targetWithoutClearance = User::factory()->create(['status' => 'active', 'authz_version' => 1]);
        $this->grantPermissionToUser($this->targetWithoutClearance, 'documents.view', 'unit', $this->unitB->id);

        // Target with clearance (in Unit B)
        $this->targetWithClearance = User::factory()->create(['status' => 'active', 'authz_version' => 1]);
        $this->grantPermissionToUser($this->targetWithClearance, 'documents.view', 'unit', $this->unitB->id);
        $this->grantPermissionToUser($this->targetWithClearance, 'staff.view_sensitive', 'unit', $this->unitA->id);
    }

    public function test_target_without_clearance_is_rejected_with_422(): void
    {
        $doc = Document::create([
            'reference_number' => 'WU/SHR/CONF/' . uniqid(),
            'title' => 'Classified Budget Proposal',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confRestricted->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->sharer->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $res = $this->actingAs($this->sharer)
            ->postJson("/api/v1/documents/{$doc->id}/share", [
                'target_user_id' => $this->targetWithoutClearance->id,
                'permission_code' => 'documents.view',
            ]);

        $res->assertStatus(422);
    }

    public function test_duplicate_active_grant_is_rejected_with_422(): void
    {
        $doc = Document::create([
            'reference_number' => 'WU/SHR/DUP/' . uniqid(),
            'title' => 'General Policy Guidelines',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confPublic->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->sharer->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        // First share -> 201
        $res1 = $this->actingAs($this->sharer)
            ->postJson("/api/v1/documents/{$doc->id}/share", [
                'target_user_id' => $this->targetWithoutClearance->id,
                'permission_code' => 'documents.view',
            ]);
        $res1->assertStatus(201);

        // Duplicate active share -> 422
        $res2 = $this->actingAs($this->sharer)
            ->postJson("/api/v1/documents/{$doc->id}/share", [
                'target_user_id' => $this->targetWithoutClearance->id,
                'permission_code' => 'documents.view',
            ]);
        $res2->assertStatus(422);
    }

    public function test_exactly_one_of_target_user_or_unit_required(): void
    {
        $doc = Document::create([
            'reference_number' => 'WU/SHR/ONE/' . uniqid(),
            'title' => 'Academic Standards',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confPublic->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->sharer->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        // Both provided -> 422
        $resBoth = $this->actingAs($this->sharer)
            ->postJson("/api/v1/documents/{$doc->id}/share", [
                'target_user_id' => $this->targetWithoutClearance->id,
                'target_unit_id' => $this->unitB->id,
                'permission_code' => 'documents.view',
            ]);
        $resBoth->assertStatus(422);

        // Neither provided -> 422
        $resNeither = $this->actingAs($this->sharer)
            ->postJson("/api/v1/documents/{$doc->id}/share", [
                'permission_code' => 'documents.view',
            ]);
        $resNeither->assertStatus(422);
    }

    public function test_expired_grant_gives_no_access_through_allows_user_and_accessible_by(): void
    {
        $doc = Document::create([
            'reference_number' => 'WU/SHR/EXP/' . uniqid(),
            'title' => 'Expired Share Document',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confPublic->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->admin->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $permId = DB::table('permissions')->where('code', 'documents.view')->value('id');

        DB::table('document_grants')->insert([
            'document_id' => $doc->id,
            'permission_id' => $permId,
            'user_id' => $this->targetWithoutClearance->id,
            'granted_by' => $this->admin->id,
            'granted_at' => now()->subHours(2),
            'expires_at' => now()->subHour(),
        ]);

        // 1. allowsUser gives no access
        $this->assertFalse($doc->allowsUser($this->targetWithoutClearance, 'documents.view'));

        // 2. accessibleBy gives no access
        $accessible = Document::accessibleBy($this->targetWithoutClearance, 'documents.view')
            ->where('documents.id', $doc->id)
            ->exists();
        $this->assertFalse($accessible);
    }

    public function test_revoked_grant_gives_no_access(): void
    {
        $doc = Document::create([
            'reference_number' => 'WU/SHR/RVK/' . uniqid(),
            'title' => 'Revoked Share Document',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confPublic->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->sharer->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        // Share
        $shareRes = $this->actingAs($this->sharer)
            ->postJson("/api/v1/documents/{$doc->id}/share", [
                'target_user_id' => $this->targetWithoutClearance->id,
                'permission_code' => 'documents.view',
            ]);
        $shareRes->assertStatus(201);
        $grantId = $shareRes->json('data.grant_id');

        $this->assertTrue($doc->allowsUser($this->targetWithoutClearance, 'documents.view'));
        $this->assertTrue(Document::accessibleBy($this->targetWithoutClearance, 'documents.view')->where('documents.id', $doc->id)->exists());

        // Revoke
        $revokeRes = $this->actingAs($this->sharer)
            ->deleteJson("/api/v1/documents/{$doc->id}/share/{$grantId}");
        $revokeRes->assertStatus(200);

        // No access after revoke
        $this->assertFalse($doc->allowsUser($this->targetWithoutClearance, 'documents.view'));
        $this->assertFalse(Document::accessibleBy($this->targetWithoutClearance, 'documents.view')->where('documents.id', $doc->id)->exists());
    }

    public function test_sharing_above_own_access_returns_403(): void
    {
        $doc = Document::create([
            'reference_number' => 'WU/SHR/EXCEED/' . uniqid(),
            'title' => 'Download Exceed Document',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confPublic->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->admin->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        // Sharer lacks documents.download
        $res = $this->actingAs($this->sharer)
            ->postJson("/api/v1/documents/{$doc->id}/share", [
                'target_user_id' => $this->targetWithoutClearance->id,
                'permission_code' => 'documents.download',
            ]);

        $res->assertStatus(403);
    }
}
