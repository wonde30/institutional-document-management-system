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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    /**
     * Automatically seed baseline reference data when RefreshDatabase is used.
     */
    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        if (Schema::hasTable('documents') && Schema::hasTable('assignment_participants')) {
            $docCount = DB::table('documents')->count();
            $partCount = DB::table('assignment_participants')->count();
            $testName = static::class . '::' . ($this->name() ?? '');
            @file_put_contents('C:/tmp/order.log', "{$testName} documents_rows={$docCount} participants={$partCount}\n", FILE_APPEND);
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
     * Helper to grant a scoped role to a user.
     */
    protected function assignRoleToUser(User $user, string $roleCode, string $scopeKind = 'global', ?int $unitId = null): void
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $scope = AuthorizationScope::firstOrCreate([
            'kind' => $scopeKind,
            'unit_id' => $unitId,
        ]);

        UserRole::firstOrCreate([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_id' => $scope->id,
            'granted_by' => $user->id,
            'granted_at' => now(),
        ]);
    }

    /**
     * Helper to grant a scoped permission to a user directly.
     */
    protected function grantPermissionToUser(User $user, string $permissionCode, string $scopeKind = 'global', ?int $unitId = null): void
    {
        $permission = Permission::where('code', $permissionCode)->firstOrFail();
        $scope = AuthorizationScope::firstOrCreate([
            'kind' => $scopeKind,
            'unit_id' => $unitId,
        ]);

        UserPermission::firstOrCreate([
            'user_id' => $user->id,
            'permission_id' => $permission->id,
            'scope_id' => $scope->id,
            'granted_by' => $user->id,
            'granted_at' => now(),
        ]);
    }
}
