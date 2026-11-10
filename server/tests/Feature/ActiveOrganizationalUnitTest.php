<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\OrganizationUnit;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Active organizational unit context and scope-aware authorization.
 *
 * The active unit determines the capability set the interface is allowed to
 * present. It never widens a grant: every record scoped request continues to
 * be evaluated against the owning unit or document of the record itself.
 *
 * The fixtures below mirror the institutional hierarchy that exists in the
 * database: a university root, colleges, and departments beneath a college.
 */
class ActiveOrganizationalUnitTest extends TestCase
{
    use RefreshDatabase;

    protected OrganizationUnit $university;
    protected OrganizationUnit $collegeA;
    protected OrganizationUnit $collegeB;
    protected OrganizationUnit $deptA1;
    protected OrganizationUnit $deptA2;
    protected OrganizationUnit $officeX;
    protected DocumentCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);
        Storage::fake('local');

        $collegeType = UnitType::firstOrCreate(['code' => 'college'], ['name' => 'College', 'active' => 1]);
        $departmentType = UnitType::firstOrCreate(['code' => 'department'], ['name' => 'Department', 'active' => 1]);
        $officeType = UnitType::firstOrCreate(['code' => 'office'], ['name' => 'Office', 'active' => 1]);

        $this->university = OrganizationUnit::first();
        $this->collegeA = $this->makeUnit('CTX_COLLEGE_A', 'Context College A', $collegeType->id, $this->university->id);
        $this->collegeB = $this->makeUnit('CTX_COLLEGE_B', 'Context College B', $collegeType->id, $this->university->id);
        $this->deptA1 = $this->makeUnit('CTX_DEPT_A1', 'Context Department A1', $departmentType->id, $this->collegeA->id);
        $this->deptA2 = $this->makeUnit('CTX_DEPT_A2', 'Context Department A2', $departmentType->id, $this->collegeA->id);
        $this->officeX = $this->makeUnit('CTX_OFFICE_X', 'Context Office X', $officeType->id, $this->university->id);

        $this->category = DocumentCategory::first();
    }

    protected function makeUnit(string $code, string $name, int $typeId, int $parentId): OrganizationUnit
    {
        return OrganizationUnit::create([
            'parent_id' => $parentId,
            'unit_type_id' => $typeId,
            'code' => $code,
            'name' => $name,
            'active' => 1,
        ]);
    }

    protected function makeUser(string $email): User
    {
        return User::create([
            'name' => 'Context Officer',
            'email' => $email,
            'password' => bcrypt('StrongPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
    }

    protected function establishMembership(User $user, OrganizationUnit $unit, bool $primary = true): void
    {
        $membership = DB::table('organization_memberships')->insertGetId([
            'user_id' => $user->id,
            'unit_id' => $unit->id,
            'position_title' => 'Records Officer',
            'starts_at' => now()->subMonths(6),
            'created_by' => $user->id,
            'created_at' => now(),
        ]);

        if ($primary) {
            DB::table('primary_memberships')->insert([
                'user_id' => $user->id,
                'membership_id' => $membership,
                'selected_at' => now(),
            ]);
        }
    }

    protected function createDocument(OrganizationUnit $unit, User $creator): Document
    {
        $confidentiality = \App\Models\ConfidentialityLevel::where('code', 'internal')->first()
            ?? \App\Models\ConfidentialityLevel::first();

        $doc = Document::create([
            'reference_number' => 'REF-CTX-'.uniqid(),
            'title' => 'Context Document for '.$unit->code,
            'category_id' => $this->category->id,
            'confidentiality_id' => $confidentiality?->id,
            'owner_unit_id' => $unit->id,
            'created_by' => $creator->id,
            'lifecycle' => 'active',
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
        ]);

        DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $creator->id,
            'comment' => 'Initial version',
        ]);

        return $doc;
    }

    /**
     * The identifier of the user the test is currently authenticated as.
     */
    protected ?int $authenticatedUserId = null;

    /**
     * Authenticate the user once per test.
     *
     * The helper deliberately reuses the existing session, because the active
     * organizational unit lives in the session and re-authenticating would
     * discard the selection under test.
     */
    protected function authenticate(User $user): void
    {
        if ($this->authenticatedUserId === $user->id) {
            return;
        }

        $this->authenticateAs($user);
        $this->authenticatedUserId = $user->id;
    }

    /**
     * Read the published context from the session bootstrap.
     *
     * @return array<string, mixed>
     */
    protected function contextFor(User $user): array
    {
        $this->authenticate($user);

        $response = $this->getJson('/api/v1/auth/me');
        $response->assertStatus(200);

        return $response->json('user');
    }

    /**
     * Switch the active organizational unit.
     */
    protected function switchTo(User $user, int $unitId): \Illuminate\Testing\TestResponse
    {
        $this->authenticate($user);

        return $this->postJson('/api/v1/auth/active-unit', ['unit_id' => $unitId]);
    }

    /**
     * 1. A global grant is available under the intended global semantics and
     *    does not widen the selectable unit set.
     */
    public function test_global_grant_applies_regardless_of_the_active_unit(): void
    {
        $user = $this->makeUser('ctx.global@wu.edu.et');
        $this->establishMembership($user, $this->university);
        $this->assignRoleToUser($user, 'admin', 'global', null);

        $context = $this->contextFor($user);

        // The global role grants documents.view in every unit.
        $this->assertContains('documents.view', $context['permissions']);
        $this->assertContains('documents.view', $context['permissions_global']);

        // A global grant does not widen the selectable unit set: only the
        // units the user belongs to are offered.
        $availableIds = array_column($context['available_units'], 'id');
        $this->assertContains($this->university->id, $availableIds);
        $this->assertNotContains($this->collegeA->id, $availableIds);

        // The capability survives in an unrelated unit context.
        $this->assertTrue($user->fresh()->hasPermission('documents.view', $this->collegeA->id));
        $this->assertTrue($user->fresh()->hasPermission('documents.view', null));
    }

    /**
     * 2. A role based unit grant is available only in the applicable scope.
     */
    public function test_role_based_unit_grant_is_limited_to_its_scope(): void
    {
        $user = $this->makeUser('ctx.role.unit@wu.edu.et');
        $this->establishMembership($user, $this->collegeA);
        $this->assignRoleToUser($user, 'records_officer', 'unit', $this->collegeA->id);

        $context = $this->contextFor($user);

        $this->assertContains('documents.view', $context['permissions']);
        $this->assertSame($this->collegeA->id, $context['active_unit_id']);

        // The scoped grant does not travel to an unrelated unit.
        $this->assertNotContains('documents.view', $context['permissions_global']);
        $this->assertFalse($user->fresh()->hasPermission('documents.view', $this->collegeB->id));
        $this->assertFalse($user->fresh()->hasPermission('documents.view', null));

        // Only the granted unit is selectable.
        $availableIds = array_column($context['available_units'], 'id');
        $this->assertSame([$this->collegeA->id], $availableIds);
    }

    /**
     * 3. A direct user permission follows the same scoping rules as a role
     *    grant.
     */
    public function test_direct_user_unit_grant_is_limited_to_its_scope(): void
    {
        $user = $this->makeUser('ctx.direct.unit@wu.edu.et');
        $this->establishMembership($user, $this->collegeA);
        $this->grantPermissionToUser($user, 'documents.view', 'unit', $this->collegeA->id);
        $this->grantPermissionToUser($user, 'documents.update', 'unit', $this->collegeA->id);

        $context = $this->contextFor($user);

        $this->assertContains('documents.view', $context['permissions']);
        $this->assertContains('documents.update', $context['permissions']);
        $this->assertNotContains('documents.view', $context['permissions_global']);

        $this->assertFalse($user->fresh()->hasPermission('documents.view', $this->collegeB->id));
    }

    /**
     * 4. A college subtree grant applies to the college and its valid
     *    descendants, and to nothing else.
     */
    public function test_college_subtree_grant_applies_to_the_college_and_its_descendants(): void
    {
        $user = $this->makeUser('ctx.subtree@wu.edu.et');
        $this->establishMembership($user, $this->collegeA);
        $this->assignRoleToUser($user, 'department_head', 'subtree', $this->collegeA->id);

        $context = $this->contextFor($user);

        // The college and both departments are selectable.
        $availableIds = array_column($context['available_units'], 'id');
        $this->assertContains($this->collegeA->id, $availableIds);
        $this->assertContains($this->deptA1->id, $availableIds);
        $this->assertContains($this->deptA2->id, $availableIds);

        // A sibling college is not covered.
        $this->assertNotContains($this->collegeB->id, $availableIds);

        // The capability holds in the anchor unit and in every descendant.
        $this->assertTrue($user->fresh()->hasPermission('documents.view', $this->collegeA->id));
        $this->assertTrue($user->fresh()->hasPermission('documents.view', $this->deptA1->id));
        $this->assertTrue($user->fresh()->hasPermission('documents.view', $this->deptA2->id));

        // It does not hold in a sibling unit.
        $this->assertFalse($user->fresh()->hasPermission('documents.view', $this->collegeB->id));

        // Switching into a descendant keeps the capability.
        $this->switchTo($user, $this->deptA1->id)->assertStatus(200);
        $context = $this->contextFor($user);
        $this->assertSame($this->deptA1->id, $context['active_unit_id']);
        $this->assertContains('documents.view', $context['permissions']);
    }

    /**
     * 5. A department grant does not grant access to an unrelated department,
     *    and a sibling department inherits nothing.
     */
    public function test_department_grant_does_not_extend_to_sibling_departments(): void
    {
        $user = $this->makeUser('ctx.dept@wu.edu.et');
        $this->establishMembership($user, $this->deptA1);
        $this->assignRoleToUser($user, 'records_officer', 'unit', $this->deptA1->id);

        $context = $this->contextFor($user);

        $availableIds = array_column($context['available_units'], 'id');
        $this->assertSame([$this->deptA1->id], $availableIds);

        $this->assertFalse($user->fresh()->hasPermission('documents.view', $this->deptA2->id));
        $this->assertFalse($user->fresh()->hasPermission('documents.view', $this->collegeA->id));
        $this->assertFalse($user->fresh()->hasPermission('documents.view', null));

        // The sibling department refuses the selection outright.
        $this->switchTo($user, $this->deptA2->id)->assertStatus(403);
    }

    /**
     * 6. A user without an active unit never receives the union of every
     *    scoped capability.
     */
    public function test_user_without_an_active_unit_receives_no_union_of_scoped_capabilities(): void
    {
        $user = $this->makeUser('ctx.no.unit@wu.edu.et');

        // Scoped grants in two units, but no organizational membership, so
        // there is no default selection.
        $this->grantPermissionToUser($user, 'documents.view', 'unit', $this->collegeA->id);
        $this->grantPermissionToUser($user, 'roles.view', 'unit', $this->collegeB->id);

        $context = $this->contextFor($user);

        $this->assertNull($context['active_unit_id']);
        $this->assertTrue($context['unit_selection_required']);

        // No scoped capability is published while no unit is selected.
        $this->assertNotContains('documents.view', $context['permissions']);
        $this->assertNotContains('roles.view', $context['permissions']);
        $this->assertSame([], $context['permissions']);
    }

    /**
     * 7. Selecting a unit the user may not select is rejected by the server.
     */
    public function test_unauthorized_unit_selection_is_rejected(): void
    {
        $user = $this->makeUser('ctx.reject@wu.edu.et');
        $this->establishMembership($user, $this->collegeA);
        $this->assignRoleToUser($user, 'records_officer', 'unit', $this->collegeA->id);

        $this->switchTo($user, $this->collegeB->id)->assertStatus(403);
        $this->switchTo($user, $this->officeX->id)->assertStatus(403);
        $this->switchTo($user, 999999)->assertStatus(403);

        // The context is unchanged by the rejected attempts.
        $context = $this->contextFor($user);
        $this->assertSame($this->collegeA->id, $context['active_unit_id']);
        $this->assertContains('documents.view', $context['permissions']);
    }

    /**
     * 8. Switching the unit recalculates the effective capability set, and no
     *    stale capability survives the switch.
     *
     *    A 'unit' scope covers exactly its own unit and is never expanded by
     *    the selection, so moving into a descendant of the granted unit does
     *    not carry the grant with it. Only a 'subtree' scope covers
     *    descendants.
     */
    public function test_switching_the_unit_recalculates_the_capability_set(): void
    {
        $user = $this->makeUser('ctx.switch@wu.edu.et');
        $this->establishMembership($user, $this->collegeA);
        $this->grantPermissionToUser($user, 'documents.view', 'unit', $this->collegeA->id);
        $this->grantPermissionToUser($user, 'documents.update', 'unit', $this->deptA1->id);

        $context = $this->contextFor($user);
        $this->assertSame($this->collegeA->id, $context['active_unit_id']);
        $this->assertContains('documents.view', $context['permissions']);
        $this->assertNotContains('documents.update', $context['permissions']);

        // Moving into the department that carries the second grant publishes
        // it, and does not carry the college scoped grant with it.
        $this->switchTo($user, $this->deptA1->id)->assertStatus(200);
        $context = $this->contextFor($user);
        $this->assertSame($this->deptA1->id, $context['active_unit_id']);
        $this->assertContains('documents.update', $context['permissions']);
        $this->assertNotContains('documents.view', $context['permissions']);

        // Moving back removes it again: the switch never leaves a stale
        // capability behind.
        $this->switchTo($user, $this->collegeA->id)->assertStatus(200);
        $context = $this->contextFor($user);
        $this->assertContains('documents.view', $context['permissions']);
        $this->assertNotContains('documents.update', $context['permissions']);
    }

    /**
     * 9. A revoked permission is no longer advertised.
     */
    public function test_revoked_permission_is_no_longer_advertised(): void
    {
        $user = $this->makeUser('ctx.revoke@wu.edu.et');
        $this->establishMembership($user, $this->collegeA);
        $this->grantPermissionToUser($user, 'documents.view', 'unit', $this->collegeA->id);
        $this->grantPermissionToUser($user, 'documents.update', 'unit', $this->collegeA->id);

        $context = $this->contextFor($user);
        $this->assertContains('documents.update', $context['permissions']);

        // Revoke the grant.
        DB::table('user_permissions')
            ->where('user_id', $user->id)
            ->whereIn('permission_id', function ($query) {
                $query->select('id')->from('permissions')->where('code', 'documents.update');
            })
            ->delete();

        $context = $this->contextFor($user);
        $this->assertNotContains('documents.update', $context['permissions']);
        $this->assertContains('documents.view', $context['permissions']);
    }

    /**
     * 10. The active unit never replaces record level authorization. A record
     *     owned by another unit stays inaccessible whatever the context.
     */
    public function test_record_level_authorization_is_enforced_regardless_of_the_active_unit(): void
    {
        $owner = $this->makeUser('ctx.owner@wu.edu.et');
        $this->establishMembership($owner, $this->collegeA);

        $docA = $this->createDocument($this->collegeA, $owner);
        $docB = $this->createDocument($this->collegeB, $owner);

        $user = $this->makeUser('ctx.cross@wu.edu.et');
        $this->establishMembership($user, $this->collegeA);
        $this->assignRoleToUser($user, 'records_officer', 'unit', $this->collegeA->id);

        $this->authenticateAs($user);

        // The own-unit record is readable.
        $this->getJson("/api/v1/documents/{$docA->id}")->assertStatus(200);

        // The cross-unit record is refused on read and on update.
        $this->getJson("/api/v1/documents/{$docB->id}")->assertStatus(403);
        $this->putJson("/api/v1/documents/{$docB->id}", ['title' => 'Tampered'])->assertStatus(403);

        // The refused update left the record untouched.
        $this->assertNotSame('Tampered', $docB->fresh()->title);

        // No deletion endpoint is exposed for documents, so no cross unit
        // deletion path exists at all.
        $this->deleteJson("/api/v1/documents/{$docB->id}")->assertStatus(405);
        $this->assertDatabaseHas('documents', ['id' => $docB->id]);

        // Creating a document in another unit is refused.
        $this->postJson('/api/v1/documents', [
            'title' => 'Cross unit creation',
            'category_id' => $this->category->id,
            'owner_unit_id' => $this->collegeB->id,
        ])->assertStatus(403);
    }

    /**
     * 11. The active unit does not widen a grant: a capability published in
     *     the active context is still refused outside its scope.
     */
    public function test_published_capability_is_still_refused_outside_its_scope(): void
    {
        $owner = $this->makeUser('ctx.scope.owner@wu.edu.et');
        $this->establishMembership($owner, $this->collegeA);
        $docB = $this->createDocument($this->collegeB, $owner);

        $user = $this->makeUser('ctx.scope@wu.edu.et');
        $this->establishMembership($user, $this->collegeA);
        $this->assignRoleToUser($user, 'records_officer', 'unit', $this->collegeA->id);

        $context = $this->contextFor($user);
        $this->assertContains('documents.view', $context['permissions']);

        // The published capability does not grant access to another unit.
        $this->authenticateAs($user);
        $this->getJson("/api/v1/documents/{$docB->id}")->assertStatus(403);
    }

    /**
     * 12. The navigation catalog follows the active context: before, during
     *     and after a context switch.
     */
    public function test_navigation_catalog_follows_the_active_context(): void
    {
        $user = $this->makeUser('ctx.nav@wu.edu.et');
        $this->grantPermissionToUser($user, 'documents.view', 'unit', $this->collegeA->id);
        $this->grantPermissionToUser($user, 'roles.view', 'unit', $this->collegeB->id);

        $this->authenticateAs($user);

        // Before a selection no scoped capability is published, so no
        // permission gated navigation item is offered.
        $routes = $this->navigationRoutes();
        $this->assertNotContains('/documents', $routes);
        $this->assertNotContains('/admin/roles', $routes);

        // After selecting the first unit its navigation appears.
        $this->postJson('/api/v1/auth/active-unit', ['unit_id' => $this->collegeA->id])->assertStatus(200);
        $routes = $this->navigationRoutes();
        $this->assertContains('/documents', $routes);
        $this->assertNotContains('/admin/roles', $routes);

        // After switching, the previous context's navigation disappears.
        $this->postJson('/api/v1/auth/active-unit', ['unit_id' => $this->collegeB->id])->assertStatus(200);
        $routes = $this->navigationRoutes();
        $this->assertNotContains('/documents', $routes);
        $this->assertContains('/admin/roles', $routes);
    }

    /**
     * 13. A global grant takes precedence over a scoped grant of a different
     *     capability when the scope is evaluated.
     */
    public function test_global_grant_and_scoped_grant_are_both_respected(): void
    {
        $user = $this->makeUser('ctx.precedence@wu.edu.et');
        $this->establishMembership($user, $this->collegeA);
        $this->grantPermissionToUser($user, 'documents.view', 'global', null);
        $this->grantPermissionToUser($user, 'documents.update', 'unit', $this->collegeA->id);

        $context = $this->contextFor($user);

        // The global capability is published everywhere.
        $this->assertContains('documents.view', $context['permissions']);
        $this->assertContains('documents.view', $context['permissions_global']);

        // The scoped capability is published only in its unit.
        $this->assertContains('documents.update', $context['permissions']);
        $this->assertNotContains('documents.update', $context['permissions_global']);

        $this->assertTrue($user->fresh()->hasPermission('documents.view', null));
        $this->assertTrue($user->fresh()->hasPermission('documents.view', $this->collegeB->id));
        $this->assertFalse($user->fresh()->hasPermission('documents.update', $this->collegeB->id));
    }

    /**
     * 14. A user with a single available unit receives it as the default.
     */
    public function test_single_available_unit_is_selected_by_default(): void
    {
        $user = $this->makeUser('ctx.single@wu.edu.et');
        $this->grantPermissionToUser($user, 'documents.view', 'unit', $this->officeX->id);

        $context = $this->contextFor($user);

        $this->assertSame($this->officeX->id, $context['active_unit_id']);
        $this->assertFalse($context['unit_selection_required']);
        $this->assertContains('documents.view', $context['permissions']);
    }

    /**
     * 15. A user with no grants and no membership has no active unit and no
     *     published capability.
     */
    public function test_user_without_any_grant_has_no_capability(): void
    {
        $user = $this->makeUser('ctx.none@wu.edu.et');
        $this->establishMembership($user, $this->officeX);

        $context = $this->contextFor($user);

        $this->assertSame($this->officeX->id, $context['active_unit_id']);
        $this->assertSame([], $context['permissions']);
        $this->assertFalse($context['unit_selection_required']);
    }

    /**
     * 16. Switching the unit is refused when the unit is inactive.
     */
    public function test_inactive_unit_cannot_be_selected(): void
    {
        $user = $this->makeUser('ctx.inactive@wu.edu.et');
        $this->establishMembership($user, $this->collegeA);
        $this->assignRoleToUser($user, 'records_officer', 'unit', $this->collegeA->id);

        $this->collegeB->update(['active' => 0]);
        $this->grantPermissionToUser($user, 'documents.view', 'unit', $this->collegeB->id);

        $this->switchTo($user, $this->collegeB->id)->assertStatus(403);
    }

    /**
     * Read the navigation routes offered to the authenticated user.
     *
     * @return list<string>
     */
    protected function navigationRoutes(): array
    {
        $response = $this->getJson('/api/v1/navigation');
        $response->assertStatus(200);

        return collect($response->json('data'))
            ->flatMap(fn ($section) => $section['items'])
            ->pluck('to')
            ->all();
    }
}
