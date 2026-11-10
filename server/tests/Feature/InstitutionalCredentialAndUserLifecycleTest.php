<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Database\Seeders\InstitutionalCredentialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstitutionalCredentialAndUserLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);
        $this->seed(InstitutionalCredentialSeeder::class);

        $this->admin = User::where('email', 'admin@wu.edu.et')->firstOrFail();
    }

    public function test_institutional_credentials_are_seeded_across_all_roles(): void
    {
        // 1. Super Admin
        $superAdmin = User::where('email', 'admin@wu.edu.et')->first();
        $this->assertNotNull($superAdmin);
        $this->assertEquals('active', $superAdmin->status);
        $this->assertTrue($superAdmin->hasRole('super_admin'));

        // 2. System Administrator (Admin)
        $sysAdmin = User::where('email', 'sysadmin@wu.edu.et')->first();
        $this->assertNotNull($sysAdmin);
        $this->assertEquals('active', $sysAdmin->status);
        $this->assertTrue($sysAdmin->hasRole('admin'));

        // 3. College Dean (Department Head in Subtree scope)
        $dean = User::where('email', 'dean.technology@wu.edu.et')->first();
        $this->assertNotNull($dean);
        $this->assertEquals('active', $dean->status);
        $this->assertTrue($dean->hasRole('department_head'));

        // 4. Department Head
        $deptHead = User::where('email', 'head.se@wu.edu.et')->first();
        $this->assertNotNull($deptHead);
        $this->assertTrue($deptHead->hasRole('department_head'));

        // 5. Central Registry (Records Officer)
        $registrar = User::where('email', 'registrar@wu.edu.et')->first();
        $this->assertNotNull($registrar);
        $this->assertTrue($registrar->hasRole('records_officer'));

        // 6. Lead Archivist
        $archivist = User::where('email', 'archivist@wu.edu.et')->first();
        $this->assertNotNull($archivist);
        $this->assertTrue($archivist->hasRole('archivist'));

        // 7. Quality Assurance Director (Document Controller)
        $qaDirector = User::where('email', 'qa.director@wu.edu.et')->first();
        $this->assertNotNull($qaDirector);
        $this->assertTrue($qaDirector->hasRole('document_controller'));

        // 8. Academic Staff
        $staff = User::where('email', 'staff.tech@wu.edu.et')->first();
        $this->assertNotNull($staff);
        $this->assertTrue($staff->hasRole('staff'));
    }

    public function test_single_user_direct_permission_guarantee_works_without_admin_role(): void
    {
        $auditor = User::where('email', 'auditor.direct@wu.edu.et')->firstOrFail();
        
        // Base role is staff
        $this->assertTrue($auditor->hasRole('staff'));
        $this->assertFalse($auditor->hasRole('admin'));
        $this->assertFalse($auditor->hasRole('super_admin'));

        // Standard staff cannot delete documents
        $normalStaff = User::where('email', 'staff.tech@wu.edu.et')->firstOrFail();
        $this->assertFalse($normalStaff->hasPermission('documents.delete'));

        // But auditor user has guaranteed direct permission override for documents.delete and audit.view
        $this->assertTrue($auditor->hasPermission('documents.delete'));
        $this->assertTrue($auditor->hasPermission('audit.view'));

        // Direct permissions are loaded and included in user resource
        $this->authenticateAs($this->admin);
        $res = $this->getJson("/api/v1/users/{$auditor->id}");
        $res->assertOk();
        $res->assertJsonFragment(['permission_code' => 'documents.delete']);
        $res->assertJsonFragment(['permission_code' => 'audit.view']);
    }

    public function test_inactive_and_locked_accounts_are_seeded(): void
    {
        $inactive = User::where('email', 'suspended.staff@wu.edu.et')->firstOrFail();
        $this->assertEquals('inactive', $inactive->status);
        $this->assertFalse($inactive->isActive());

        $locked = User::where('email', 'locked.account@wu.edu.et')->firstOrFail();
        $this->assertEquals('locked', $locked->status);
        $this->assertFalse($locked->isActive());
    }

    public function test_users_list_supports_server_pagination_and_filtering(): void
    {
        $this->authenticateAs($this->admin);

        // Test pagination metadata
        $response = $this->getJson('/api/v1/users?per_page=5&page=1');
        $response->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'status',
                        'roles',
                        'permissions',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                    'from',
                    'to',
                ],
            ]);

        // Test status filtering: inactive
        $inactiveRes = $this->getJson('/api/v1/users?status=inactive');
        $inactiveRes->assertOk();
        foreach ($inactiveRes->json('data') as $u) {
            $this->assertEquals('inactive', $u['status']);
        }

        // Test role filtering: records_officer
        $recordsRes = $this->getJson('/api/v1/users?role=records_officer');
        $recordsRes->assertOk();
        $this->assertGreaterThanOrEqual(1, count($recordsRes->json('data')));

        // Test search filtering by name or email
        $searchRes = $this->getJson('/api/v1/users?search=Solomon');
        $searchRes->assertOk();
        $this->assertCount(1, $searchRes->json('data'));
        $this->assertEquals('staff.tech@wu.edu.et', $searchRes->json('data.0.email'));
    }

    public function test_admin_can_activate_and_deactivate_user_account(): void
    {
        $this->authenticateAs($this->admin);

        $targetUser = User::where('email', 'staff.tech@wu.edu.et')->firstOrFail();
        $this->assertEquals('active', $targetUser->status);
        $initialAuthzVersion = $targetUser->authz_version;

        // Deactivate account
        $patchRes = $this->patchJson("/api/v1/users/{$targetUser->id}/status", [
            'status' => 'inactive',
        ]);

        $patchRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'inactive');

        $targetUser->refresh();
        $this->assertEquals('inactive', $targetUser->status);
        $this->assertEquals($initialAuthzVersion + 1, $targetUser->authz_version);

        // Confirm audit event recorded
        $this->assertDatabaseHas('audit_events', [
            'action' => 'auth.account_suspended',
            'actor_id' => $this->admin->id,
            'target_id' => (string) $targetUser->id,
        ]);

        // Re-activate account
        $activateRes = $this->patchJson("/api/v1/users/{$targetUser->id}/status", [
            'status' => 'active',
        ]);

        $activateRes->assertOk()
            ->assertJsonPath('data.status', 'active');

        $targetUser->refresh();
        $this->assertEquals('active', $targetUser->status);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'auth.account_activated',
            'actor_id' => $this->admin->id,
            'target_id' => (string) $targetUser->id,
        ]);
    }

    public function test_admin_cannot_deactivate_own_account(): void
    {
        $this->authenticateAs($this->admin);

        $response = $this->patchJson("/api/v1/users/{$this->admin->id}/status", [
            'status' => 'inactive',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->admin->refresh();
        $this->assertEquals('active', $this->admin->status);
    }

    public function test_direct_user_permission_grant_and_revoke_lifecycle(): void
    {
        $this->authenticateAs($this->admin);

        $targetUser = User::where('email', 'staff.tech@wu.edu.et')->firstOrFail();
        $permission = Permission::where('code', 'reports.export')->firstOrFail();

        $this->assertFalse($targetUser->hasPermission('reports.export'));

        // Grant direct user permission
        $grantRes = $this->postJson('/api/v1/user-permissions', [
            'user_id' => $targetUser->id,
            'permission_id' => $permission->id,
            'scope_kind' => 'global',
        ]);

        $grantRes->assertOk()
            ->assertJsonPath('success', true);

        $targetUser->refresh();
        $this->assertTrue($targetUser->hasPermission('reports.export'));

        // Revoke direct user permission
        $scopeId = \Illuminate\Support\Facades\DB::table('authorization_scopes')
            ->where('kind', 'global')
            ->value('id');

        $revokeRes = $this->deleteJson('/api/v1/user-permissions', [
            'user_id' => $targetUser->id,
            'permission_id' => $permission->id,
            'scope_id' => $scopeId,
        ]);

        $revokeRes->assertOk()
            ->assertJsonPath('success', true);

        $targetUser->refresh();
        $this->assertFalse($targetUser->hasPermission('reports.export'));
    }
}
