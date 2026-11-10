<?php

namespace Tests;

use App\Models\AuthorizationScope;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPermission;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    /**
     * Authenticate user with Sanctum stateful session cookies and transition test token.
     */
    protected function authenticateAs(User $user): string
    {
        $this->defaultCookies = [];
        $this->flushSession();
        \Illuminate\Support\Facades\Auth::forgetGuards();
        $this->actingAs($user, 'web');
        $this->withSession([
            'authz_version' => (int) $user->authz_version,
            'password_hash_web' => $user->getAuthPassword(),
        ]);

        $token = \Illuminate\Support\Str::random(60);
        \Illuminate\Support\Facades\DB::table('sessions')->insert([
            'id' => $token,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit/Test',
            'payload' => base64_encode(serialize(['user_id' => $user->id, 'authz_version' => $user->authz_version])),
            'last_activity' => time(),
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Origin' => 'http://localhost',
            'Referer' => 'http://localhost/',
            'Accept' => 'application/json',
        ]);

        return $token;
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
