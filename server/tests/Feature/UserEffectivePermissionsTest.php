<?php

namespace Tests\Feature;

use App\Models\AuthorizationScope;
use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use App\Models\UserPermission;
use App\Models\UserRole;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserEffectivePermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $targetUser;
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
            'code' => 'TEST_COLL_B',
            'name' => 'College of Business',
            'active' => true,
        ]);

        $this->targetUser = User::create([
            'name' => 'Abebe Kebede',
            'email' => 'abebe.kebede@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
    }

    public function test_admin_can_inspect_user_effective_permissions_and_lineage(): void
    {
        $this->authenticateAs($this->admin);

        // Assign a scoped role to targetUser
        $role = Role::where('code', 'records_officer')->first();
        $scopeUnitA = AuthorizationScope::create([
            'kind' => 'unit',
            'unit_id' => $this->unitA->id,
        ]);

        UserRole::create([
            'user_id' => $this->targetUser->id,
            'role_id' => $role->id,
            'scope_id' => $scopeUnitA->id,
            'granted_by' => $this->admin->id,
            'granted_at' => now(),
        ]);

        // 1. Query for unit A scope
        $responseUnitA = $this->getJson("/api/v1/users/{$this->targetUser->id}/effective-permissions?unit_id={$this->unitA->id}");
        $responseUnitA->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user_id', $this->targetUser->id)
            ->assertJsonPath('data.target_unit_id', $this->unitA->id);

        $dataA = $responseUnitA->json('data');
        $this->assertNotEmpty($dataA['effective_codes']);
        $this->assertGreaterThan(0, $dataA['effective_count']);
        $this->assertNotEmpty($dataA['lineage']);
        $this->assertEquals('records_officer', $dataA['lineage'][0]['source_code']);
        $this->assertTrue($dataA['lineage'][0]['is_effective']);
        $this->assertEquals('Effective', $dataA['lineage'][0]['status_label']);

        // 2. Query for unit B scope (unit A grant should NOT apply in unit B)
        $responseUnitB = $this->getJson("/api/v1/users/{$this->targetUser->id}/effective-permissions?unit_id={$this->unitB->id}");
        $responseUnitB->assertStatus(200);
        $dataB = $responseUnitB->json('data');
        $this->assertFalse($dataB['lineage'][0]['is_effective']);
        $this->assertEquals('Out of Scope', $dataB['lineage'][0]['status_label']);
    }

    public function test_lineage_detects_expired_assignments_and_generates_warnings(): void
    {
        $this->authenticateAs($this->admin);

        $role = Role::where('code', 'staff')->first();
        $globalScope = AuthorizationScope::firstOrCreate(['kind' => 'global', 'unit_id' => null]);

        // Assign an expired role
        UserRole::create([
            'user_id' => $this->targetUser->id,
            'role_id' => $role->id,
            'scope_id' => $globalScope->id,
            'granted_by' => $this->admin->id,
            'granted_at' => now()->subMonths(2),
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->getJson("/api/v1/users/{$this->targetUser->id}/effective-permissions");
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNotEmpty($data['warnings']);
        $this->assertStringContainsString('expired', $data['warnings'][0]);
        $this->assertTrue($data['lineage'][0]['is_expired']);
        $this->assertFalse($data['lineage'][0]['is_effective']);
        $this->assertEquals('Expired', $data['lineage'][0]['status_label']);
    }

    public function test_unauthorized_user_cannot_inspect_effective_permissions(): void
    {
        $unprivilegedUser = User::create([
            'name' => 'Unprivileged Student',
            'email' => 'student@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        $this->authenticateAs($unprivilegedUser);

        $response = $this->getJson("/api/v1/users/{$this->targetUser->id}/effective-permissions");
        $response->assertStatus(403);
    }
}
