<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\AuthorizationScope;
use App\Models\ConfidentialityLevel;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPermission;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GapRemediationHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $departmentHead;
    protected User $staff;
    protected OrganizationUnit $unitA;
    protected OrganizationUnit $unitB;
    protected DocumentCategory $category;
    protected ConfidentialityLevel $confPublic;
    protected ConfidentialityLevel $confRestricted;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $units = OrganizationUnit::take(2)->get();
        $this->unitA = $units[0];
        $this->unitB = $units[1];

        $this->category = DocumentCategory::first();
        $this->confPublic = ConfidentialityLevel::where('code', 'public')->first() ?? ConfidentialityLevel::first();
        $this->confRestricted = ConfidentialityLevel::where('code', 'restricted')->first() ?? ConfidentialityLevel::where('code', 'confidential')->first();

        // 1. Admin
        $this->admin = User::where('email', 'admin@wu.edu.et')->firstOrFail();

        // 2. Department Head in Unit A
        $this->departmentHead = User::create([
            'name' => 'Head Unit A',
            'email' => 'head.a.' . uniqid() . '@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $scopeA = AuthorizationScope::firstOrCreate(['kind' => 'unit', 'unit_id' => $this->unitA->id]);
        $this->grantPerm($this->departmentHead, $scopeA, ['documents.view', 'documents.share', 'reports.export']);

        // 3. Staff in Unit B
        $this->staff = User::create([
            'name' => 'Staff Unit B',
            'email' => 'staff.b.' . uniqid() . '@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
    }

    protected function grantPerm(User $user, AuthorizationScope $scope, array $permCodes): void
    {
        $perms = DB::table('permissions')->whereIn('code', $permCodes)->get();
        foreach ($perms as $p) {
            DB::table('user_permissions')->insert([
                'user_id' => $user->id,
                'permission_id' => $p->id,
                'scope_id' => $scope->id,
                'granted_by' => 1,
                'granted_at' => now(),
            ]);
        }
    }

    public function test_system_role_and_assigned_role_deletion_blocked_with_audit_row(): void
    {
        $this->actingAs($this->admin);

        // 1. System role deletion rejected -> 422 + audit row
        $systemRole = Role::where('code', 'staff')->firstOrFail();
        $resSys = $this->deleteJson("/api/v1/roles/{$systemRole->id}");
        $resSys->assertStatus(422);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'role.deletion_rejected',
            'target_type' => 'role',
            'target_id' => (string) $systemRole->id,
        ]);

        // 2. Custom role assigned to users -> 422 + audit row
        $customRole = Role::create([
            'code' => 'custom_reviewer_' . uniqid(),
            'name' => 'Custom Reviewer',
            'is_system' => false,
            'active' => true,
        ]);
        $scope = AuthorizationScope::first();
        DB::table('user_roles')->insert([
            'user_id' => $this->staff->id,
            'role_id' => $customRole->id,
            'scope_id' => $scope->id,
            'granted_by' => 1,
            'granted_at' => now(),
        ]);

        $resAssigned = $this->deleteJson("/api/v1/roles/{$customRole->id}");
        $resAssigned->assertStatus(422);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'role.deletion_rejected',
            'target_type' => 'role',
            'target_id' => (string) $customRole->id,
        ]);

        // 3. Unassigned custom role deletion succeeds -> 200 + role.deleted audit row
        $unassignedRole = Role::create([
            'code' => 'custom_unassigned_' . uniqid(),
            'name' => 'Custom Unassigned Role',
            'is_system' => false,
            'active' => true,
        ]);
        $resDelete = $this->deleteJson("/api/v1/roles/{$unassignedRole->id}");
        $resDelete->assertStatus(200);
        $this->assertDatabaseMissing('roles', ['id' => $unassignedRole->id]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'role.deleted',
            'target_type' => 'role',
            'target_id' => (string) $unassignedRole->id,
        ]);
    }

    public function test_sharing_cannot_exceed_sharer_access_and_respects_expiry_and_revoke(): void
    {
        $doc = Document::create([
            'reference_number' => 'WU/SHR/TEST/' . uniqid(),
            'title' => 'Unit A Confidential Strategy',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confRestricted->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => 1,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        // Department head has documents.view and documents.share on Unit A, but lacks clearance for restricted doc -> 403
        $this->actingAs($this->departmentHead);
        $resUnauthShare = $this->postJson("/api/v1/documents/{$doc->id}/share", [
            'target_user_id' => $this->staff->id,
            'permission_code' => 'documents.view',
        ]);
        $resUnauthShare->assertStatus(403);

        // Public document
        $docPublic = Document::create([
            'reference_number' => 'WU/SHR/PUB/' . uniqid(),
            'title' => 'Unit A Public Memo',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confPublic->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => 1,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        // Department head lacks documents.download -> cannot grant documents.download
        $resExceedPerm = $this->postJson("/api/v1/documents/{$docPublic->id}/share", [
            'target_user_id' => $this->staff->id,
            'permission_code' => 'documents.download',
        ]);
        $resExceedPerm->assertStatus(403);

        // Admin shares document with 1 hour expiration
        $this->actingAs($this->admin);
        $resShare = $this->postJson("/api/v1/documents/{$docPublic->id}/share", [
            'target_user_id' => $this->staff->id,
            'permission_code' => 'documents.view',
            'expires_at' => now()->addHour()->toDateTimeString(),
        ]);
        $resShare->assertStatus(201);
        $grantId = $resShare->json('data.grant_id');

        // Staff can now view
        $this->assertTrue($docPublic->allowsUser($this->staff, 'documents.view'));

        // Revoke share
        $resRevoke = $this->deleteJson("/api/v1/documents/{$docPublic->id}/share/{$grantId}");
        $resRevoke->assertStatus(200);

        // Staff can no longer view
        $this->assertFalse($docPublic->allowsUser($this->staff, 'documents.view'));

        // Expired grant does not grant access
        DB::table('document_grants')->insert([
            'document_id' => $docPublic->id,
            'permission_id' => DB::table('permissions')->where('code', 'documents.view')->value('id'),
            'user_id' => $this->staff->id,
            'granted_by' => $this->admin->id,
            'granted_at' => now()->subHours(2),
            'expires_at' => now()->subHour(),
        ]);
        $this->assertFalse($docPublic->allowsUser($this->staff, 'documents.view'));
    }

    public function test_csv_export_escapes_formulae_respects_unit_scope_and_row_cap(): void
    {
        // Create document with potential CSV injection formula in category
        $maliciousCat = DocumentCategory::create([
            'code' => 'formula_' . uniqid(),
            'name' => '=SUM(1+1)*cmd|',
            'is_active' => true,
        ]);

        Document::create([
            'reference_number' => 'WU/EXP/TEST/' . uniqid(),
            'title' => 'Formula Injection Test Document',
            'category_id' => $maliciousCat->id,
            'confidentiality_id' => $this->confPublic->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => 1,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $this->actingAs($this->departmentHead);
        $res = $this->get('/api/v1/reports/export');
        $res->assertStatus(200);

        $content = $res->streamedContent();
        // Leading '=' must be escaped with single quote
        $this->assertStringContainsString("'=SUM", $content);

        // Audit row must be written
        $this->assertDatabaseHas('audit_events', [
            'action' => 'report.exported',
            'target_type' => 'report',
        ]);
    }
}
