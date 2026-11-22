<?php

namespace Tests;

use App\Models\AuthorizationScope;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPermission;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    /**
     * Automatically seed baseline reference data when RefreshDatabase is used.
     */
    protected bool $seed = true;

    /**
     * Override artisan to protect the test database schema from being dropped or rolled back mid-suite.
     */
    public function artisan($command, $parameters = [])
    {
        if (in_array($command, ['migrate:fresh', 'migrate:rollback'], true)) {
            return 0;
        }

        return parent::artisan($command, $parameters);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $uses = array_flip(class_uses_recursive(static::class));

        $excludeTransactions = [
            \Tests\Feature\ConcurrencyAndDeadlockTest::class,
            \Tests\Feature\DisasterRecoveryRestorationTest::class,
        ];

        if (! in_array(static::class, $excludeTransactions, true)
            && ! isset($uses[\Illuminate\Foundation\Testing\RefreshDatabase::class])
            && ! isset($uses[\Illuminate\Foundation\Testing\DatabaseTransactions::class])
            && ! isset($uses[\Illuminate\Foundation\Testing\DatabaseMigrations::class])) {
            DB::beginTransaction();
        }

        if (isset($uses[\Illuminate\Foundation\Testing\DatabaseMigrations::class])) {
            $this->cleanUpAtomicityArtifacts();
        }
    }

    protected function tearDown(): void
    {
        $uses = array_flip(class_uses_recursive(static::class));

        $excludeTransactions = [
            \Tests\Feature\ConcurrencyAndDeadlockTest::class,
            \Tests\Feature\DisasterRecoveryRestorationTest::class,
        ];

        if (! in_array(static::class, $excludeTransactions, true)
            && ! isset($uses[\Illuminate\Foundation\Testing\RefreshDatabase::class])
            && ! isset($uses[\Illuminate\Foundation\Testing\DatabaseTransactions::class])
            && ! isset($uses[\Illuminate\Foundation\Testing\DatabaseMigrations::class])) {
            try {
                DB::rollBack();
            } catch (\Throwable) {
            }
        }

        if (isset($uses[\Illuminate\Foundation\Testing\DatabaseMigrations::class])) {
            $this->cleanUpAtomicityArtifacts();
            \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = true;
        }

        parent::tearDown();
    }

    protected function cleanUpAtomicityArtifacts(): void
    {
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');
            DB::statement('SET @dms_allow_version_mutation = 1');
            DB::unprepared('DROP TRIGGER IF EXISTS ctx_assignment_audit_failure');

            $atomicityDocs = DB::table('documents')->where('title', 'Atomicity document')->pluck('id');
            if ($atomicityDocs->isNotEmpty()) {
                $assignmentIds = DB::table('document_assignments')->whereIn('document_id', $atomicityDocs)->pluck('id');
                if ($assignmentIds->isNotEmpty()) {
                    DB::table('assignment_participants')->whereIn('assignment_id', $assignmentIds)->delete();
                    DB::table('document_assignments')->whereIn('id', $assignmentIds)->delete();
                }
                DB::table('document_versions')->whereIn('document_id', $atomicityDocs)->delete();
                DB::table('documents')->whereIn('id', $atomicityDocs)->delete();
            }

            $atomicityUsers = DB::table('users')->whereIn('email', [
                'assignment.atomicity.officer@wu.edu.et',
                'assignment.atomicity.participant@wu.edu.et',
            ])->pluck('id');
            if ($atomicityUsers->isNotEmpty()) {
                DB::table('user_roles')->whereIn('user_id', $atomicityUsers)->delete();
                DB::table('user_permissions')->whereIn('user_id', $atomicityUsers)->delete();
                DB::table('users')->whereIn('id', $atomicityUsers)->delete();
            }

            $atomicityUnit = DB::table('organization_units')->where('code', 'ATOMICITY_UNIT_B')->first();
            if ($atomicityUnit) {
                DB::table('authorization_scopes')->where('unit_id', $atomicityUnit->id)->delete();
                DB::table('organization_units')->where('id', $atomicityUnit->id)->delete();
            }

            DB::statement('SET @dms_allow_version_mutation = 0');
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        } catch (\Throwable) {
        }
    }
    /**
     * Authenticate user with Sanctum stateful session and bypass CSRF for JSON API tests.
     *
     * Sanctum stateful requests require a matching XSRF-TOKEN cookie / X-XSRF-TOKEN header.
     * During automated testing we bypass VerifyCsrfToken by injecting a known token into
     * both the session and the request header so CSRF validation passes without a real browser.
     */
    protected function authenticateAs(User $user): string
    {
        $this->defaultCookies = [];
        $this->flushSession();
        Auth::forgetGuards();
        $this->actingAs($user, 'web');

        // Generate a stable CSRF token for this test request.
        $csrfToken = Str::random(40);

        $this->withSession([
            'authz_version' => (int) $user->authz_version,
            'password_hash_web' => $user->getAuthPassword(),
            '_token' => $csrfToken,
        ]);

        $this->withHeaders([
            'Origin' => 'http://localhost',
            'Referer' => 'http://localhost/',
            'Accept' => 'application/json',
            'X-XSRF-TOKEN' => $csrfToken,
        ]);

        return $csrfToken;
    }

    /**
     * Assign scoped role to a user.
     */
    protected function assignRoleToUser(User $user, string $roleCode, string $scopeKind = 'global', ?int $unitId = null): void
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $scope = AuthorizationScope::firstOrCreate([
            'kind' => $scopeKind,
            'unit_id' => $unitId,
        ]);

        $adminId = DB::table('users')->where('email', 'admin@wu.edu.et')->value('id') ?? $user->id;

        UserRole::firstOrCreate([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_id' => $scope->id,
        ], [
            'granted_by' => $adminId,
            'granted_at' => now(),
        ]);
    }

    /**
     * Grant direct scoped permission to a user.
     */
    protected function grantPermissionToUser(User $user, string $permissionCode, string $scopeKind = 'global', ?int $unitId = null): void
    {
        $permission = Permission::where('code', $permissionCode)->firstOrFail();
        $scope = AuthorizationScope::firstOrCreate([
            'kind' => $scopeKind,
            'unit_id' => $unitId,
        ]);

        $adminId = DB::table('users')->where('email', 'admin@wu.edu.et')->value('id') ?? $user->id;

        UserPermission::firstOrCreate([
            'user_id' => $user->id,
            'permission_id' => $permission->id,
            'scope_id' => $scope->id,
        ], [
            'granted_by' => $adminId,
            'granted_at' => now(),
        ]);
    }
}
