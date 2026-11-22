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
