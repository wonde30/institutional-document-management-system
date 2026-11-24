<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuthorizationScope;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoleDeletionDetachesPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\BaselineReferenceVocabularySeeder::class);
        $this->seed(\Database\Seeders\InstitutionalCredentialSeeder::class);

        $this->admin = User::factory()->create(['status' => 'active', 'authz_version' => 1]);
        $this->grantPermissionToUser($this->admin, 'system.configure', 'global', null);
        $this->grantPermissionToUser($this->admin, 'roles.delete', 'global', null);
    }

    /**
     * Test custom role with permissions and no assignments deletes OK and its role_permissions rows are gone.
     */
    public function test_custom_role_with_permissions_and_no_assignments_deletes_ok_and_role_permissions_gone(): void
    {
        $role = Role::create([
            'code' => 'custom_role_with_perms_' . uniqid(),
            'name' => 'Custom Role with Permissions',
            'is_system' => false,
            'active' => true,
        ]);

        $perm1 = Permission::where('code', 'documents.view')->firstOrFail();
        $perm2 = Permission::where('code', 'documents.create')->firstOrFail();

        DB::table('role_permissions')->insert([
            ['role_id' => $role->id, 'permission_id' => $perm1->id],
            ['role_id' => $role->id, 'permission_id' => $perm2->id],
        ]);

        $this->assertEquals(2, DB::table('role_permissions')->where('role_id', $role->id)->count());

        $res = $this->actingAs($this->admin)->deleteJson("/api/v1/roles/{$role->id}");
        $res->assertStatus(200);

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertEquals(0, DB::table('role_permissions')->where('role_id', $role->id)->count(), 'role_permissions rows must be detached upon role deletion');
    }

    /**
     * Test assigned role returns 422.
     */
    public function test_assigned_role_returns_422(): void
    {
        $role = Role::create([
            'code' => 'assigned_role_' . uniqid(),
            'name' => 'Assigned Role',
            'is_system' => false,
            'active' => true,
        ]);

        $user = User::factory()->create();
        $scope = AuthorizationScope::first() ?? AuthorizationScope::create(['kind' => 'global']);

        DB::table('user_roles')->insert([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_id' => $scope->id,
            'granted_by' => $this->admin->id,
            'granted_at' => now(),
        ]);

        $res = $this->actingAs($this->admin)->deleteJson("/api/v1/roles/{$role->id}");
        $res->assertStatus(422);
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    /**
     * Test a foreign key failure never returns 500.
     */
    public function test_foreign_key_failure_never_returns_500(): void
    {
        $role = Role::create([
            'code' => 'fk_failure_role_' . uniqid(),
            'name' => 'FK Failure Role',
            'is_system' => false,
            'active' => true,
        ]);

        $user = User::factory()->create();
        $scope = AuthorizationScope::first() ?? AuthorizationScope::create(['kind' => 'global']);

        DB::table('user_roles')->insert([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_id' => $scope->id,
            'granted_by' => $this->admin->id,
            'granted_at' => now(),
        ]);

        $res = $this->actingAs($this->admin)->deleteJson("/api/v1/roles/{$role->id}");
        $this->assertNotEquals(500, $res->status());
        $res->assertStatus(422);
    }
}
