<?php

namespace Tests\Feature;

use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicRbacTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;
    protected OrganizationUnit $unitA;
    protected OrganizationUnit $unitB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $this->admin = User::where('email', 'admin@wu.edu.et')->first();

        $collegeType = UnitType::where('code', 'college')->first();
        $this->unitA = OrganizationUnit::first();
        $this->unitB = OrganizationUnit::create([
            'parent_id' => $this->unitA->id,
            'unit_type_id' => $collegeType->id,
            'code' => 'TEST_COLLEGE_B',
            'name' => 'College of Business and Economics',
            'active' => true,
        ]);

        $this->regularUser = User::create([
            'name' => 'Dr. Candidate Evaluator',
            'email' => 'evaluator@wu.edu.et',
            'password' => bcrypt('Secret123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->regularUser, 'staff');
    }

    public function test_admin_can_create_dynamic_role_and_assign_to_user_in_scope(): void
    {
        $this->authenticateAs($this->admin);

        $perm1 = Permission::where('code', 'documents.view')->first();
        $perm2 = Permission::where('code', 'documents.share')->first();

        // 1. Create dynamic role
        $createResponse = $this->postJson('/api/v1/roles', [
            'code' => 'senate_secretary',
            'name' => 'Senate Secretariat Officer',
            'description' => 'Secretary of university academic senate',
            'active' => true,
            'permission_ids' => [$perm1->id, $perm2->id],
        ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('data.code', 'senate_secretary');

        $roleId = $createResponse->json('data.id');

        // 2. Assign dynamic role to regular user in Unit A scope
        $assignResponse = $this->postJson('/api/v1/user-roles', [
            'user_id' => $this->regularUser->id,
            'role_id' => $roleId,
            'scope_kind' => 'unit',
            'unit_id' => $this->unitA->id,
        ]);

        $assignResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        // 3. Verify effective scoped authorization
        $user = $this->regularUser->fresh();
        $this->assertTrue($user->hasPermission('documents.share', $this->unitA->id));
        // Scoped isolation: User does NOT have permission in Unit B!
        $this->assertFalse($user->hasPermission('documents.share', $this->unitB->id));
        // User does not have unauthorized global administrative capabilities
        $this->assertFalse($user->hasPermission('organization.manage'));
    }

    public function test_updating_role_permissions_immediately_updates_effective_rights(): void
    {
        $this->authenticateAs($this->admin);

        $role = Role::where('code', 'records_officer')->first();
        $permView = Permission::where('code', 'documents.view')->first();
        $permDelete = Permission::where('code', 'documents.delete')->first();

        // Assign records_officer to regular user globally
        $this->assignRoleToUser($this->regularUser, 'records_officer', 'global');

        // Initially records_officer does NOT have documents.delete
        $this->assertFalse($this->regularUser->fresh()->hasPermission('documents.delete'));

        // Admin synchronizes permissions to add documents.delete
        $syncRes = $this->postJson("/api/v1/roles/{$role->id}/permissions", [
            'permission_ids' => [$permView->id, $permDelete->id],
        ]);

        $syncRes->assertStatus(200);

        // Target user now has documents.delete
        $this->assertTrue($this->regularUser->fresh()->hasPermission('documents.delete'));

        // Admin removes documents.delete
        $removeRes = $this->postJson("/api/v1/roles/{$role->id}/permissions", [
            'permission_ids' => [$permView->id],
        ]);

        $removeRes->assertStatus(200);

        // Target user immediately loses documents.delete
        $this->assertFalse($this->regularUser->fresh()->hasPermission('documents.delete'));
    }

    public function test_revoking_role_immediately_removes_user_permissions(): void
    {
        $this->authenticateAs($this->admin);

        $role = Role::where('code', 'archivist')->first();
        $this->assignRoleToUser($this->regularUser, 'archivist', 'global');

        $this->assertTrue($this->regularUser->fresh()->hasPermission('archive.transfer'));

        // Find user_role record
        $userRole = \App\Models\UserRole::where('user_id', $this->regularUser->id)
            ->where('role_id', $role->id)
            ->first();

        $revokeRes = $this->deleteJson('/api/v1/user-roles', [
            'user_id' => $this->regularUser->id,
            'role_id' => $role->id,
            'scope_id' => $userRole->scope_id,
        ]);

        $revokeRes->assertStatus(200);

        $this->assertFalse($this->regularUser->fresh()->hasPermission('archive.transfer'));
    }

    public function test_unauthorized_user_cannot_assign_roles_or_mutate_rbac(): void
    {
        // Authenticate as regular staff member
        $this->authenticateAs($this->regularUser);

        $adminRole = Role::where('code', 'super_admin')->first();

        // Attempt to assign super_admin to self
        $response = $this->postJson('/api/v1/user-roles', [
            'user_id' => $this->regularUser->id,
            'role_id' => $adminRole->id,
            'scope_kind' => 'global',
        ]);

        $response->assertStatus(403);
    }

    public function test_syncing_role_permissions_increments_authz_version_for_role_holders(): void
    {
        $this->authenticateAs($this->admin);

        $role = Role::where('code', 'staff')->first();
        $initialAuthzVersion = (int) $this->regularUser->authz_version;

        $perm = Permission::where('code', 'documents.view')->first();

        // Sync permissions on role
        $res = $this->postJson("/api/v1/roles/{$role->id}/permissions", [
            'permission_ids' => [$perm->id],
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('success', true);

        // Regular user holding this role must have bumped authz_version
        $this->assertGreaterThan($initialAuthzVersion, (int) $this->regularUser->fresh()->authz_version);
    }
}
