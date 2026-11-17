<?php

namespace Tests\Feature;

use App\Models\AuthorizationScope;
use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminControlAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $staffUser;
    protected OrganizationUnit $deptComputerScience;
    protected OrganizationUnit $deptEconomics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $this->superAdmin = User::where('email', 'admin@wu.edu.et')->first();

        $collegeType = UnitType::where('code', 'college')->first();
        $departmentType = UnitType::where('code', 'department')->first() ?? $collegeType;

        $parentCollege = OrganizationUnit::first();
        $this->deptComputerScience = OrganizationUnit::create([
            'parent_id' => $parentCollege->id,
            'unit_type_id' => $departmentType->id,
            'code' => 'DEPT_CS',
            'name' => 'Department of Computer Science',
            'active' => true,
        ]);

        $this->deptEconomics = OrganizationUnit::create([
            'parent_id' => $parentCollege->id,
            'unit_type_id' => $departmentType->id,
            'code' => 'DEPT_ECON',
            'name' => 'Department of Economics',
            'active' => true,
        ]);

        $this->staffUser = User::create([
            'name' => 'Almaz Ayana',
            'email' => 'almaz.ayana@wu.edu.et',
            'password' => bcrypt('SecurePassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
    }

    public function test_complete_super_admin_dynamic_control_lifecycle(): void
    {
        // 1. Sign in as authorized Super Admin
        $this->authenticateAs($this->superAdmin);

        // 2 & 3. Create new role "Department Records Officer"
        $createRoleRes = $this->postJson('/api/v1/roles', [
            'code' => 'dept_records_officer',
            'name' => 'Department Records Officer',
            'description' => 'Responsible for departmental document records and official correspondence.',
            'active' => true,
        ]);

        $createRoleRes->assertStatus(201)
            ->assertJsonPath('data.code', 'dept_records_officer')
            ->assertJsonPath('data.name', 'Department Records Officer');

        $roleId = $createRoleRes->json('data.id');

        // 4 & 5. Inspect Permission catalog
        $permsRes = $this->getJson('/api/v1/permissions?all=true');
        $permsRes->assertStatus(200);

        $docView = Permission::where('code', 'documents.view')->firstOrFail();
        $docCreate = Permission::where('code', 'documents.create')->firstOrFail();
        $corrReg = Permission::where('code', 'correspondence.register')->firstOrFail();
        $corrView = Permission::where('code', 'correspondence.view')->firstOrFail();

        // 6. Assign document and correspondence permissions to the new role
        $syncRes = $this->postJson("/api/v1/roles/{$roleId}/permissions", [
            'permission_ids' => [$docView->id, $docCreate->id, $corrReg->id, $corrView->id],
        ]);
        $syncRes->assertStatus(200);

        // 7. Verify role assigned users is currently empty
        $roleUsersRes1 = $this->getJson("/api/v1/roles/{$roleId}/users");
        $roleUsersRes1->assertStatus(200)
            ->assertJsonPath('data.total_users', 0);

        // 8. Assign that role to the staff user scoped to Department of Computer Science
        $assignRes = $this->postJson('/api/v1/user-roles', [
            'user_id' => $this->staffUser->id,
            'role_id' => $roleId,
            'scope_kind' => 'unit',
            'unit_id' => $this->deptComputerScience->id,
        ]);
        $assignRes->assertStatus(200);

        // Verify role users now shows the assigned staff member with correct unit scope
        $roleUsersRes2 = $this->getJson("/api/v1/roles/{$roleId}/users");
        $roleUsersRes2->assertStatus(200)
            ->assertJsonPath('data.total_users', 1)
            ->assertJsonPath('data.users.0.user_id', $this->staffUser->id)
            ->assertJsonPath('data.users.0.scope_kind', 'unit')
            ->assertJsonPath('data.users.0.scope_unit_id', $this->deptComputerScience->id);

        // 9 & 10. Open Effective Permissions Lineage Inspector
        $lineageResCS = $this->getJson("/api/v1/users/{$this->staffUser->id}/effective-permissions?unit_id={$this->deptComputerScience->id}");
        $lineageResCS->assertStatus(200);

        $lineageDataCS = $lineageResCS->json('data');
        $this->assertContains('documents.create', $lineageDataCS['effective_codes']);
        $this->assertContains('correspondence.register', $lineageDataCS['effective_codes']);
        $this->assertEquals('Department Records Officer', $lineageDataCS['lineage'][0]['source_name']);
        $this->assertTrue($lineageDataCS['lineage'][0]['is_effective']);
        $this->assertEquals('Effective', $lineageDataCS['lineage'][0]['status_label']);

        // Inspect in Economics department context -> should be Out of Scope
        $lineageResEcon = $this->getJson("/api/v1/users/{$this->staffUser->id}/effective-permissions?unit_id={$this->deptEconomics->id}");
        $lineageResEcon->assertStatus(200);
        $lineageDataEcon = $lineageResEcon->json('data');
        $this->assertNotContains('documents.create', $lineageDataEcon['effective_codes']);
        $this->assertFalse($lineageDataEcon['lineage'][0]['is_effective']);
        $this->assertEquals('Out of Scope', $lineageDataEcon['lineage'][0]['status_label']);

        // 11. Sign in as the assigned staff user
        $this->authenticateAs($this->staffUser);

        // The user possesses documents.create in CS unit
        $this->assertTrue($this->staffUser->fresh()->hasPermission('documents.create', $this->deptComputerScience->id));

        // 12. Attempt an unauthorized API operation (e.g. system administration or creating roles)
        $unauthorizedRes = $this->postJson('/api/v1/roles', [
            'code' => 'hacked_role',
            'name' => 'Privilege Escalation Role',
        ]);
        $unauthorizedRes->assertStatus(403);

        // 13. Super Admin revokes one permission (documents.create) from the role
        $this->authenticateAs($this->superAdmin);
        $updatePermsRes = $this->postJson("/api/v1/roles/{$roleId}/permissions", [
            'permission_ids' => [$docView->id, $corrReg->id, $corrView->id], // documents.create removed
        ]);
        $updatePermsRes->assertStatus(200);

        // The staff user's subsequent check immediately loses documents.create without re-login
        $this->assertFalse($this->staffUser->fresh()->hasPermission('documents.create', $this->deptComputerScience->id));
        $this->assertTrue($this->staffUser->fresh()->hasPermission('correspondence.register', $this->deptComputerScience->id));
    }

    public function test_super_admin_safeguards_prevent_privilege_escalation_and_lockout(): void
    {
        // 1. Regular staff cannot assign super_admin or roles containing system.configure
        $this->authenticateAs($this->staffUser);

        $superAdminRole = Role::where('code', 'super_admin')->firstOrFail();
        $escalateRes = $this->postJson('/api/v1/user-roles', [
            'user_id' => $this->staffUser->id,
            'role_id' => $superAdminRole->id,
            'scope_kind' => 'global',
        ]);
        $escalateRes->assertStatus(403);

        // 2. Super admin cannot deactivate the super_admin role
        $this->authenticateAs($this->superAdmin);
        $deactivateRes = $this->putJson("/api/v1/roles/{$superAdminRole->id}", [
            'active' => false,
        ]);
        $deactivateRes->assertStatus(422)
            ->assertJsonPath('success', false);

        // 3. Super admin cannot deactivate protected capabilities
        $sysConfigPerm = Permission::where('code', 'system.configure')->firstOrFail();
        $deactPermRes = $this->patchJson("/api/v1/permissions/{$sysConfigPerm->id}", [
            'active' => false,
        ]);
        $deactPermRes->assertStatus(422)
            ->assertJsonPath('success', false);

        // 4. Cannot revoke the last active Super Admin
        $scope = AuthorizationScope::where('kind', 'global')->firstOrFail();
        $revokeLastRes = $this->deleteJson('/api/v1/user-roles', [
            'user_id' => $this->superAdmin->id,
            'role_id' => $superAdminRole->id,
            'scope_id' => $scope->id,
        ]);
        $revokeLastRes->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
