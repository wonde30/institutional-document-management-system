<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuthorizationScope;
use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowState;
use App\Models\WorkflowTask;
use App\Models\WorkflowVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoleDeletionHardeningAuditTest extends TestCase
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

    public function test_system_role_deletion_blocked_by_is_system(): void
    {
        $systemRole = Role::where('is_system', true)->firstOrFail();

        $res = $this->actingAs($this->admin)->deleteJson("/api/v1/roles/{$systemRole->id}");
        $res->assertStatus(422);
        $this->assertDatabaseHas('roles', ['id' => $systemRole->id]);
    }

    public function test_user_roles_reference_blocks_deletion_with_422(): void
    {
        $role = Role::create([
            'code' => 'custom_user_role_' . uniqid(),
            'name' => 'Custom Role with User',
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

    public function test_routing_policies_reference_blocks_deletion_with_422(): void
    {
        $role = Role::create([
            'code' => 'custom_rp_role_' . uniqid(),
            'name' => 'Custom Role with Routing Policy',
            'is_system' => false,
            'active' => true,
        ]);

        $unitType = \App\Models\UnitType::first();
        $unit = OrganizationUnit::create([
            'code' => 'TEST_RP_UNIT_' . uniqid(),
            'name' => 'RP Unit',
            'unit_type_id' => $unitType->id,
            'active' => true,
        ]);

        DB::table('routing_policies')->insert([
            'code' => 'RP_' . uniqid(),
            'name' => 'Routing Policy with Role',
            'to_unit_id' => $unit->id,
            'default_assignee_role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $res = $this->actingAs($this->admin)->deleteJson("/api/v1/roles/{$role->id}");
        $res->assertStatus(422);
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_workflow_states_reference_blocks_deletion_with_422(): void
    {
        $role = Role::create([
            'code' => 'custom_ws_role_' . uniqid(),
            'name' => 'Custom Role with Workflow State',
            'is_system' => false,
            'active' => true,
        ]);

        $def = WorkflowDefinition::firstOrCreate(['code' => 'test_def_' . uniqid()], ['name' => 'Test Def', 'active' => true]);
        $ver = WorkflowVersion::firstOrCreate(['definition_id' => $def->id, 'version_number' => 999], ['created_by' => $this->admin->id, 'created_at' => now()]);

        DB::table('workflow_states')->insert([
            'workflow_version_id' => $ver->id,
            'code' => 'custom_state_' . uniqid(),
            'name' => 'State referencing role',
            'assigned_role_id' => $role->id,
            'is_initial' => false,
            'is_terminal' => false,
        ]);

        $res = $this->actingAs($this->admin)->deleteJson("/api/v1/roles/{$role->id}");
        $res->assertStatus(422);
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_workflow_tasks_reference_blocks_deletion_with_422(): void
    {
        $role = Role::create([
            'code' => 'custom_wt_role_' . uniqid(),
            'name' => 'Custom Role with Workflow Task',
            'is_system' => false,
            'active' => true,
        ]);

        $def = WorkflowDefinition::firstOrCreate(['code' => 'test_task_def_' . uniqid()], ['name' => 'Test Task Def', 'active' => true]);
        $ver = WorkflowVersion::firstOrCreate(['definition_id' => $def->id, 'version_number' => 888], ['created_by' => $this->admin->id, 'created_at' => now()]);
        $state = WorkflowState::create([
            'workflow_version_id' => $ver->id,
            'code' => 'st_' . uniqid(),
            'name' => 'Task State',
            'is_initial' => false,
            'is_terminal' => false,
        ]);

        $inst = WorkflowInstance::create([
            'workflow_version_id' => $ver->id,
            'current_state_id' => $state->id,
            'status' => 'active',
            'started_by' => $this->admin->id,
            'started_at' => now(),
        ]);

        WorkflowTask::create([
            'instance_id' => $inst->id,
            'state_id' => $state->id,
            'assigned_user_id' => $this->admin->id,
            'required_role_id' => $role->id,
            'created_at' => now(),
            'due_at' => now()->addDays(3),
        ]);

        $res = $this->actingAs($this->admin)->deleteJson("/api/v1/roles/{$role->id}");
        $res->assertStatus(422);
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_role_permissions_detached_on_deletion_with_200(): void
    {
        $role = Role::create([
            'code' => 'custom_perm_role_' . uniqid(),
            'name' => 'Custom Role with Permissions',
            'is_system' => false,
            'active' => true,
        ]);

        $perm = Permission::first();
        DB::table('role_permissions')->insert([
            'role_id' => $role->id,
            'permission_id' => $perm->id,
        ]);

        $res = $this->actingAs($this->admin)->deleteJson("/api/v1/roles/{$role->id}");
        $res->assertStatus(200);
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertDatabaseMissing('role_permissions', ['role_id' => $role->id]);
    }

    public function test_unreferenced_custom_role_deletion_succeeds_with_200(): void
    {
        $role = Role::create([
            'code' => 'custom_clean_role_' . uniqid(),
            'name' => 'Custom Clean Role',
            'is_system' => false,
            'active' => true,
        ]);

        $res = $this->actingAs($this->admin)->deleteJson("/api/v1/roles/{$role->id}");
        $res->assertStatus(200);
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }
}
