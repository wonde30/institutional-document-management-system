<?php

namespace Tests\Feature;

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

/**
 * The semantics of the permission resolution.
 *
 * The whole authorization architecture rests on four rules. They are asserted
 * here so that a change to one of them cannot silently alter the security
 * boundary:
 *
 *  1. A check without a target unit is satisfied only by a global grant.
 *  2. A unit grant covers exactly its own unit.
 *  3. A subtree grant covers the anchor unit and every descendant.
 *  4. The decision and the published capability set resolve identically.
 */
class AuthorizationResolutionRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected OrganizationUnit $university;
    protected OrganizationUnit $college;
    protected OrganizationUnit $department;
    protected OrganizationUnit $sibling;
    protected OrganizationUnit $otherCollege;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $collegeType = UnitType::firstOrCreate(['code' => 'college'], ['name' => 'College', 'active' => 1]);
        $departmentType = UnitType::firstOrCreate(['code' => 'department'], ['name' => 'Department', 'active' => 1]);

        $this->university = OrganizationUnit::first();

        $this->college = OrganizationUnit::create([
            'parent_id' => $this->university->id,
            'unit_type_id' => $collegeType->id,
            'code' => 'REG_COLLEGE',
            'name' => 'Regression College',
            'active' => 1,
        ]);

        $this->department = OrganizationUnit::create([
            'parent_id' => $this->college->id,
            'unit_type_id' => $departmentType->id,
            'code' => 'REG_DEPARTMENT',
            'name' => 'Regression Department',
            'active' => 1,
        ]);

        $this->sibling = OrganizationUnit::create([
            'parent_id' => $this->college->id,
            'unit_type_id' => $departmentType->id,
            'code' => 'REG_SIBLING',
            'name' => 'Regression Sibling Department',
            'active' => 1,
        ]);

        $this->otherCollege = OrganizationUnit::create([
            'parent_id' => $this->university->id,
            'unit_type_id' => $collegeType->id,
            'code' => 'REG_OTHER_COLLEGE',
            'name' => 'Regression Other College',
            'active' => 1,
        ]);
    }

    protected function makeUser(string $email): User
    {
        return User::create([
            'name' => 'Regression Officer',
            'email' => $email,
            'password' => bcrypt('StrongPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
    }

    protected function scopeId(string $kind, ?int $unitId): int
    {
        $existing = DB::table('authorization_scopes')
            ->where('kind', $kind)
            ->where(function ($query) use ($unitId) {
                $unitId === null
                    ? $query->whereNull('unit_id')
                    : $query->where('unit_id', $unitId);
            })
            ->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) DB::table('authorization_scopes')->insertGetId([
            'kind' => $kind,
            'unit_id' => $unitId,
        ]);
    }

    protected function grantDirect(User $user, string $code, string $kind, ?int $unitId): void
    {
        UserPermission::create([
            'user_id' => $user->id,
            'permission_id' => Permission::where('code', $code)->value('id'),
            'scope_id' => $this->scopeId($kind, $unitId),
            'granted_by' => $user->id,
            'granted_at' => now(),
        ]);
    }

    protected function grantRole(User $user, string $roleCode, string $kind, ?int $unitId): void
    {
        UserRole::create([
            'user_id' => $user->id,
            'role_id' => Role::where('code', $roleCode)->value('id'),
            'scope_id' => $this->scopeId($kind, $unitId),
            'granted_by' => $user->id,
            'granted_at' => now(),
        ]);
    }

    public function test_a_global_grant_satisfies_an_unscoped_check(): void
    {
        $user = $this->makeUser('reg.global@wu.edu.et');
        $this->grantDirect($user, 'documents.view', 'global', null);

        $this->assertTrue($user->hasPermission('documents.view', null));
        $this->assertTrue($user->hasPermission('documents.view', $this->college->id));
        $this->assertTrue($user->hasPermission('documents.view', $this->otherCollege->id));

        // A global grant is unrestricted.
        $this->assertNull($user->getPermittedUnitIds('documents.view'));
    }

    public function test_a_unit_grant_covers_exactly_its_own_unit(): void
    {
        $user = $this->makeUser('reg.unit@wu.edu.et');
        $this->grantDirect($user, 'documents.view', 'unit', $this->college->id);

        $this->assertTrue($user->hasPermission('documents.view', $this->college->id));
        $this->assertFalse($user->hasPermission('documents.view', null));
        $this->assertFalse($user->hasPermission('documents.view', $this->department->id));
        $this->assertFalse($user->hasPermission('documents.view', $this->otherCollege->id));

        $this->assertSame([$this->college->id], $user->getPermittedUnitIds('documents.view'));
    }

    public function test_a_subtree_grant_covers_the_anchor_and_its_descendants(): void
    {
        $user = $this->makeUser('reg.subtree@wu.edu.et');
        $this->grantDirect($user, 'documents.view', 'subtree', $this->college->id);

        // The anchor and both descendants.
        $this->assertTrue($user->hasPermission('documents.view', $this->college->id));
        $this->assertTrue($user->hasPermission('documents.view', $this->department->id));
        $this->assertTrue($user->hasPermission('documents.view', $this->sibling->id));

        // A sibling college is not a descendant.
        $this->assertFalse($user->hasPermission('documents.view', $this->otherCollege->id));
        $this->assertFalse($user->hasPermission('documents.view', null));

        $permitted = $user->getPermittedUnitIds('documents.view');
        $this->assertContains($this->college->id, $permitted);
        $this->assertContains($this->department->id, $permitted);
        $this->assertContains($this->sibling->id, $permitted);
        $this->assertNotContains($this->otherCollege->id, $permitted);
    }

    public function test_a_role_grant_and_a_direct_grant_resolve_identically(): void
    {
        $direct = $this->makeUser('reg.direct@wu.edu.et');
        $this->grantDirect($direct, 'documents.view', 'unit', $this->college->id);

        $viaRole = $this->makeUser('reg.role@wu.edu.et');
        $this->grantRole($viaRole, 'records_officer', 'unit', $this->college->id);

        // The role carries the capabilities of its role, the direct grant the
        // single capability it names. The scope resolution is identical.
        $this->assertContains('documents.view', $direct->getEffectivePermissionCodes($this->college->id));
        $this->assertContains('documents.view', $viaRole->getEffectivePermissionCodes($this->college->id));

        // A capability the role carries beyond the direct grant.
        $this->assertNotContains('documents.create', $direct->getEffectivePermissionCodes($this->college->id));
        $this->assertContains('documents.create', $viaRole->getEffectivePermissionCodes($this->college->id));

        // Neither is honoured outside the scope of the grant.
        $this->assertFalse($direct->hasPermission('documents.view', $this->otherCollege->id));
        $this->assertFalse($viaRole->hasPermission('documents.view', $this->otherCollege->id));
        $this->assertFalse($direct->hasPermission('documents.view', null));
        $this->assertFalse($viaRole->hasPermission('documents.view', null));
    }

    public function test_the_decision_and_the_published_capability_set_agree(): void
    {
        $user = $this->makeUser('reg.agreement@wu.edu.et');
        $this->grantDirect($user, 'documents.view', 'unit', $this->college->id);
        $this->grantDirect($user, 'audit.view', 'global', null);

        foreach ([null, $this->college->id, $this->otherCollege->id] as $unitId) {
            $published = $user->getEffectivePermissionCodes($unitId);

            foreach ($published as $code) {
                $this->assertTrue(
                    $user->hasPermission($code, $unitId),
                    "The published capability '{$code}' is not honoured by the decision for unit ".($unitId ?? 'global').'.'
                );
            }

            // Every capability the decision honours is published.
            foreach (['documents.view', 'audit.view'] as $code) {
                $this->assertSame(
                    $user->hasPermission($code, $unitId),
                    in_array($code, $published, true),
                    "The decision and the published capability set disagree for '{$code}'."
                );
            }
        }
    }

    public function test_a_capability_without_a_grant_resolves_to_nothing(): void
    {
        $user = $this->makeUser('reg.none@wu.edu.et');

        $this->assertSame([], $user->getPermittedUnitIds('documents.view'));
        $this->assertSame([], $user->getEffectivePermissionCodes(null));
        $this->assertFalse($user->hasPermission('documents.view', null));
    }

    public function test_an_expired_grant_is_not_honoured(): void
    {
        $user = $this->makeUser('reg.expired@wu.edu.et');

        UserPermission::create([
            'user_id' => $user->id,
            'permission_id' => Permission::where('code', 'documents.view')->value('id'),
            'scope_id' => $this->scopeId('global', null),
            'granted_by' => $user->id,
            'granted_at' => now()->subDays(30),
            'expires_at' => now()->subDay(),
        ]);

        $this->assertFalse($user->hasPermission('documents.view', null));
        $this->assertSame([], $user->getEffectivePermissionCodes(null));
    }

    public function test_the_active_organizational_unit_context_resolves_the_published_set(): void
    {
        $user = $this->makeUser('reg.context@wu.edu.et');
        $this->grantDirect($user, 'documents.view', 'unit', $this->college->id);

        $membership = DB::table('organization_memberships')->insertGetId([
            'user_id' => $user->id,
            'unit_id' => $this->college->id,
            'position_title' => 'Officer',
            'starts_at' => now()->subYear(),
            'created_by' => $user->id,
            'created_at' => now(),
        ]);

        DB::table('primary_memberships')->insert([
            'user_id' => $user->id,
            'membership_id' => $membership,
            'selected_at' => now(),
        ]);

        $context = app(\App\Services\Authorization\OrganizationalContextService::class);

        $this->authenticateAs($user);

        $activeUnitId = $context->resolveActiveUnitId($user, request());

        $this->assertSame($this->college->id, $activeUnitId);
        $this->assertContains('documents.view', $user->getEffectivePermissionCodes($activeUnitId));
    }
}
