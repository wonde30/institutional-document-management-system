<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\AuthorizationScope;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RolePermissionController extends Controller
{
    /**
     * List all system and custom roles.
     */
    public function roles(Request $request): JsonResponse
    {
        $roles = Role::withCount('permissions')
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => RoleResource::collection($roles),
        ]);
    }

    /**
     * Store a new custom role.
     */
    public function storeRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:64|unique:roles,code',
            'name' => 'required|string|max:160',
            'description' => 'nullable|string',
            'active' => 'nullable|boolean',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $actor = $request->user();
        if (! empty($validated['permission_ids']) && $actor && ! $actor->hasPermission('system.configure')) {
            $hasProtectedPerm = Permission::whereIn('id', $validated['permission_ids'])
                ->get()
                ->contains(fn (Permission $p) => (bool) $p->is_protected);

            if ($hasProtectedPerm) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: Creating a role with protected administrative capabilities requires system.configure authority.',
                ], 403);
            }
        }

        $role = Role::create([
            'code' => strtolower($validated['code']),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'active' => $validated['active'] ?? true,
            'is_system' => false,
        ]);

        if (! empty($validated['permission_ids'])) {
            $role->permissions()->sync($validated['permission_ids']);
        }

        $role->load('permissions');

        AuditService::recordAuthEvent(
            action: 'auth.role_created',
            actorId: $actor?->id,
            targetType: 'role',
            targetId: (string) $role->id,
            details: ['code' => $role->code, 'permission_ids' => $validated['permission_ids'] ?? []],
            request: $request
        );

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully.',
            'data' => new RoleResource($role),
        ], 201);
    }

    /**
     * Show role details with attached permissions.
     */
    public function showRole(int $id): JsonResponse
    {
        $role = Role::with('permissions')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new RoleResource($role),
        ]);
    }

    /**
     * List all users assigned to a specific role.
     */
    public function roleUsers(int $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        $userRoles = UserRole::where('role_id', $role->id)
            ->with(['user.primaryMembershipRecord.membership.unit', 'scope.unit', 'grantedBy'])
            ->get();

        $data = $userRoles->map(function (UserRole $ur): array {
            $isExpired = $ur->expires_at && $ur->expires_at->isPast();
            $scope = $ur->scope;
            $unit = $scope?->unit;
            return [
                'user_id' => $ur->user_id,
                'name' => $ur->user?->name,
                'email' => $ur->user?->email,
                'status' => $ur->user?->status,
                'primary_unit' => $ur->user?->primaryMembershipRecord?->membership?->unit?->name,
                'scope_id' => $ur->scope_id,
                'scope_kind' => $scope?->kind,
                'scope_unit_id' => $scope?->unit_id,
                'scope_unit_name' => $unit ? $unit->name : ($scope?->kind === 'global' ? 'Global Scope' : null),
                'granted_by_name' => $ur->grantedBy?->name,
                'granted_at' => $ur->granted_at?->toIso8601String(),
                'expires_at' => $ur->expires_at?->toIso8601String(),
                'is_expired' => $isExpired,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'role_id' => $role->id,
                'role_code' => $role->code,
                'role_name' => $role->name,
                'total_users' => $data->count(),
                'users' => $data,
            ],
        ]);
    }

    /**
     * Update role.
     */
    public function updateRole(Request $request, int $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:160',
            'description' => 'nullable|string',
            'active' => 'sometimes|boolean',
        ]);

        if (isset($validated['active']) && ! $validated['active']) {
            if ($role->is_system || $role->code === 'super_admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Security restriction: Protected system roles cannot be deactivated.',
                ], 422);
            }

            $carriesAdminCapability = $role->permissions()->where('code', 'system.configure')->exists();
            if ($carriesAdminCapability) {
                // Check if deactivating this role would leave zero active system administrators
                $permId = DB::table('permissions')->where('code', 'system.configure')->value('id');
                $otherActiveAdminCount = DB::table('user_roles')
                    ->join('users', 'users.id', '=', 'user_roles.user_id')
                    ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                    ->join('role_permissions', 'role_permissions.role_id', '=', 'roles.id')
                    ->join('authorization_scopes', 'authorization_scopes.id', '=', 'user_roles.scope_id')
                    ->where('role_permissions.permission_id', $permId)
                    ->where('roles.id', '!=', $role->id)
                    ->where('roles.active', 1)
                    ->where('users.status', 'active')
                    ->where('authorization_scopes.kind', 'global')
                    ->where(function ($q) {
                        $q->whereNull('user_roles.expires_at')->orWhere('user_roles.expires_at', '>', now());
                    })
                    ->count()
                    + DB::table('user_permissions')
                        ->join('users', 'users.id', '=', 'user_permissions.user_id')
                        ->join('authorization_scopes', 'authorization_scopes.id', '=', 'user_permissions.scope_id')
                        ->where('user_permissions.permission_id', $permId)
                        ->where('users.status', 'active')
                        ->where('authorization_scopes.kind', 'global')
                        ->where(function ($q) {
                            $q->whereNull('user_permissions.expires_at')->orWhere('user_permissions.expires_at', '>', now());
                        })
                        ->count();

                if ($otherActiveAdminCount === 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Security restriction: Cannot deactivate a role carrying system administrative capability when no other active administrator exists.',
                    ], 422);
                }
            }
        }

        $role->update($validated);

        if (array_key_exists('active', $validated)) {
            User::whereHas('userRoles', fn ($q) => $q->where('role_id', $role->id))->increment('authz_version');
        }

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully.',
            'data' => new RoleResource($role),
        ]);
    }

    /**
     * Delete role.
     */
    public function destroyRole(Request $request, int $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        if ($role->is_system) {
            AuditService::log(
                action: 'role.deletion_rejected',
                targetType: 'role',
                targetId: (string) $role->id,
                details: ['reason' => 'system_role_protected', 'code' => $role->code],
                actorId: $request->user()?->id,
                request: $request
            );

            return response()->json([
                'success' => false,
                'message' => 'Security restriction: Protected system roles cannot be deleted.',
            ], 422);
        }

        // Query information_schema for EVERY foreign key referencing roles.id
        $dbName = DB::connection()->getDatabaseName();
        $referencingFks = DB::select("
            SELECT TABLE_NAME, COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ?
              AND REFERENCED_TABLE_NAME = 'roles'
              AND REFERENCED_COLUMN_NAME = 'id'
        ", [$dbName]);

        foreach ($referencingFks as $fk) {
            $table = $fk->TABLE_NAME;
            // role_permissions rows belong to the role; detach them inside transaction instead of blocking
            if ($table === 'role_permissions') {
                continue;
            }

            $column = $fk->COLUMN_NAME;
            $count = DB::table($table)->where($column, $role->id)->count();

            if ($count > 0) {
                AuditService::log(
                    action: 'role.deletion_rejected',
                    targetType: 'role',
                    targetId: (string) $role->id,
                    details: [
                        'reason' => 'foreign_key_referenced',
                        'table' => $table,
                        'column' => $column,
                        'count' => $count,
                        'code' => $role->code,
                    ],
                    actorId: $request->user()?->id,
                    request: $request
                );

                return response()->json([
                    'success' => false,
                    'message' => "Role cannot be deleted because it is referenced in {$table} ({$count} record(s)).",
                ], 422);
            }
        }

        try {
            DB::transaction(function () use ($role, $request) {
                // Detach role_permissions rows inside the transaction
                DB::table('role_permissions')->where('role_id', $role->id)->delete();

                $role->delete();

                AuditService::log(
                    action: 'role.deleted',
                    targetType: 'role',
                    targetId: (string) $role->id,
                    details: ['code' => $role->code, 'name' => $role->name],
                    actorId: $request->user()?->id,
                    request: $request
                );
            });
        } catch (\Illuminate\Database\QueryException $e) {
            AuditService::log(
                action: 'role.deletion_rejected',
                targetType: 'role',
                targetId: (string) $role->id,
                details: ['reason' => 'foreign_key_exception', 'error' => $e->getMessage()],
                actorId: $request->user()?->id,
                request: $request
            );

            return response()->json([
                'success' => false,
                'message' => 'Role cannot be deleted due to referencing records.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully.',
        ]);
    }

    /**
     * Sync permissions to role.
     */
    public function syncRolePermissions(Request $request, int $id): JsonResponse
    {
        $role = Role::with('permissions')->findOrFail($id);

        $validated = $request->validate([
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $actor = $request->user();
        if ($actor && ! $actor->hasPermission('system.configure')) {
            if ($role->is_system || $role->code === 'super_admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: Modifying permissions of a system role requires system.configure authority.',
                ], 403);
            }

            $existingHasProtected = $role->permissions->contains(fn (Permission $p) => (bool) $p->is_protected);
            $incomingHasProtected = Permission::whereIn('id', $validated['permission_ids'])
                ->get()
                ->contains(fn (Permission $p) => (bool) $p->is_protected);

            if ($existingHasProtected || $incomingHasProtected) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: Assigning or removing protected capabilities on a role requires system.configure authority.',
                ], 403);
            }
        }

        $role->permissions()->sync($validated['permission_ids']);
        $role->load('permissions');

        // Increment authz_version and clear request grants cache for all users holding this role
        $affectedUserIds = UserRole::where('role_id', $role->id)->pluck('user_id')->unique()->all();
        if (! empty($affectedUserIds)) {
            User::whereIn('id', $affectedUserIds)->increment('authz_version');
            foreach ($affectedUserIds as $uid) {
                User::clearUserGrantsCache((int) $uid);
            }
        }

        AuditService::recordAuthEvent(
            action: 'auth.role_permissions_synced',
            actorId: $actor?->id,
            targetType: 'role',
            targetId: (string) $role->id,
            details: ['code' => $role->code, 'permission_ids' => $validated['permission_ids']],
            request: $request
        );

        return response()->json([
            'success' => true,
            'message' => 'Role permissions synchronized successfully.',
            'data' => new RoleResource($role),
        ]);
    }

    /**
     * List all permissions.
     */
    public function permissions(Request $request): JsonResponse
    {
        $query = Permission::orderBy('code');
        if (! $request->boolean('all', false) && ! $request->user()?->hasPermission('system.configure')) {
            $query->where('active', 1);
        }

        $permissions = $query->get();

        return response()->json([
            'success' => true,
            'data' => $permissions,
        ]);
    }

    /**
     * Update permission active status or description.
     */
    public function updatePermission(Request $request, int $id): JsonResponse
    {
        $permission = Permission::findOrFail($id);

        $validated = $request->validate([
            'active' => 'sometimes|boolean',
            'description' => 'nullable|string|max:500',
        ]);

        if (isset($validated['active']) && ! $validated['active'] && $permission->is_protected) {
            return response()->json([
                'success' => false,
                'message' => "Security restriction: Protected capability '{$permission->code}' cannot be deactivated.",
            ], 422);
        }

        $oldActive = $permission->active;
        $permission->update($validated);

        if ((bool) $oldActive !== (bool) $permission->active) {
            $roleUserIds = DB::table('user_roles')
                ->join('role_permissions', 'role_permissions.role_id', '=', 'user_roles.role_id')
                ->where('role_permissions.permission_id', $permission->id)
                ->pluck('user_roles.user_id')
                ->all();
            $directUserIds = DB::table('user_permissions')
                ->where('permission_id', $permission->id)
                ->pluck('user_id')
                ->all();
            $affectedUserIds = array_values(array_unique(array_merge($roleUserIds, $directUserIds)));
            if (! empty($affectedUserIds)) {
                User::whereIn('id', $affectedUserIds)->increment('authz_version');
                foreach ($affectedUserIds as $uid) {
                    User::clearUserGrantsCache((int) $uid);
                }
            }
        }

        AuditService::recordAuthEvent(
            action: 'auth.permission_status_changed',
            actorId: $request->user()->id,
            targetType: 'permission',
            targetId: (string) $permission->id,
            details: [
                'code' => $permission->code,
                'old_active' => $oldActive,
                'new_active' => $permission->active,
            ],
            request: $request
        );

        return response()->json([
            'success' => true,
            'message' => "Permission capability '{$permission->code}' updated successfully.",
            'data' => $permission,
        ]);
    }

    /**
     * List authorization scopes.
     */
    public function scopes(): JsonResponse
    {
        $scopes = AuthorizationScope::with('unit')->get();

        return response()->json([
            'success' => true,
            'data' => $scopes,
        ]);
    }

    /**
     * Assign role to user in a specific scope (global, unit, or subtree).
     */
    public function assignUserRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id',
            'scope_kind' => 'required|in:global,unit,subtree',
            'unit_id' => 'nullable|required_if:scope_kind,unit,subtree|exists:organization_units,id',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $actor = $request->user();
        $now = now()->format('Y-m-d H:i:s.u');

        // Privilege escalation barrier: only an authorized administrator (system.configure) can assign roles containing protected permissions
        $targetRole = Role::with('permissions')->findOrFail($validated['role_id']);
        if ($targetRole->code === 'super_admin' && ! $actor->hasPermission('system.configure')) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Only an existing Super Administrator can assign the super_admin role.',
            ], 403);
        }
        $hasProtectedPerm = $targetRole->is_system || $targetRole->permissions->contains(fn (Permission $p) => in_array($p->code, ['system.configure', 'roles.assign', 'roles.create'], true) || (bool) $p->is_protected);
        if ($hasProtectedPerm && ! $actor->hasPermission('system.configure')) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Only an authorized Super Administrator can assign roles containing protected administrative capabilities.',
            ], 403);
        }

        // Resolve or create authorization scope
        $scopeUnitId = ($validated['scope_kind'] === 'global') ? null : (int) $validated['unit_id'];

        if (! $actor->hasPermission('system.configure')) {
            if ($validated['scope_kind'] === 'global' && ! $actor->hasPermission('roles.assign', null)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: Assigning global-scoped roles requires global roles.assign authority.',
                ], 403);
            }
            if ($scopeUnitId !== null && ! $actor->hasPermission('roles.assign', $scopeUnitId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: You do not have roles.assign authority for the target organizational unit.',
                ], 403);
            }
        }
        
        $scope = AuthorizationScope::firstOrCreate([
            'kind' => $validated['scope_kind'],
            'unit_id' => $scopeUnitId,
        ]);

        // Grant role
        DB::table('user_roles')->updateOrInsert(
            [
                'user_id' => $validated['user_id'],
                'role_id' => $validated['role_id'],
                'scope_id' => $scope->id,
            ],
            [
                'granted_by' => $actor->id,
                'granted_at' => $now,
                'expires_at' => $validated['expires_at'] ?? null,
            ]
        );

        // Bump authz_version on target user and clear request-level cache
        User::where('id', $validated['user_id'])->increment('authz_version');
        User::clearUserGrantsCache((int) $validated['user_id']);

        AuditService::recordAuthEvent(
            action: 'auth.authorization_version_changed',
            actorId: $actor->id,
            targetType: 'user',
            targetId: (string) $validated['user_id'],
            details: [
                'reason' => 'role_assigned',
                'role_id' => $validated['role_id'],
                'scope_id' => $scope->id,
            ],
            request: $request
        );

        return response()->json([
            'success' => true,
            'message' => 'Role successfully assigned to user in scope.',
        ]);
    }

    /**
     * Revoke role from user in a scope.
     */
    public function revokeUserRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id',
            'scope_id' => 'required|exists:authorization_scopes,id',
        ]);

        $targetRole = Role::with('permissions')->findOrFail($validated['role_id']);
        $carriesAdminCapability = $targetRole->is_system
            || $targetRole->code === 'super_admin'
            || $targetRole->permissions->contains(fn (Permission $p) => $p->code === 'system.configure');

        if ($carriesAdminCapability) {
            if (! $request->user()->hasPermission('system.configure')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: Only an authorized Super Administrator can revoke administrative roles.',
                ], 403);
            }

            // Protect last active administrator carrying super_admin or system.configure
            $otherSuperAdminCount = DB::table('user_roles')
                ->join('users', 'users.id', '=', 'user_roles.user_id')
                ->where('user_roles.role_id', $targetRole->id)
                ->where('users.status', 'active')
                ->where('users.id', '!=', $validated['user_id'])
                ->where(function ($q) {
                    $q->whereNull('user_roles.expires_at')->orWhere('user_roles.expires_at', '>', now());
                })
                ->count();

            if (($targetRole->code === 'super_admin' && $otherSuperAdminCount === 0)
                || User::countActiveSystemAdministrators($validated['user_id']) === 0) {
                // Check if target user has direct system.configure permission that remains
                $targetUser = User::find($validated['user_id']);
                $hasDirectAdmin = $targetUser && $targetUser->userPermissions()
                    ->whereHas('permission', fn ($p) => $p->where('code', 'system.configure'))
                    ->whereHas('scope', fn ($s) => $s->where('kind', 'global'))
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    })
                    ->exists();

                if (! $hasDirectAdmin) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Security restriction: Cannot revoke administrative capability from the last active System Administrator in the institution.',
                    ], 422);
                }
            }
        }

        $scope = AuthorizationScope::findOrFail($validated['scope_id']);
        if (! $request->user()->hasPermission('system.configure')) {
            if ($scope->kind === 'global' && ! $request->user()->hasPermission('roles.assign', null)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: Revoking global-scoped roles requires global roles.assign authority.',
                ], 403);
            }
            if ($scope->unit_id !== null && ! $request->user()->hasPermission('roles.assign', (int) $scope->unit_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: You do not have roles.assign authority for the target organizational unit.',
                ], 403);
            }
        }

        DB::table('user_roles')
            ->where('user_id', $validated['user_id'])
            ->where('role_id', $validated['role_id'])
            ->where('scope_id', $validated['scope_id'])
            ->delete();

        User::where('id', $validated['user_id'])->increment('authz_version');
        User::clearUserGrantsCache((int) $validated['user_id']);

        AuditService::recordAuthEvent(
            action: 'auth.authorization_version_changed',
            actorId: $request->user()->id,
            targetType: 'user',
            targetId: (string) $validated['user_id'],
            details: [
                'reason' => 'role_revoked',
                'role_id' => $validated['role_id'],
                'scope_id' => $validated['scope_id'],
            ],
            request: $request
        );

        return response()->json([
            'success' => true,
            'message' => 'Role revoked successfully.',
        ]);
    }

    /**
     * Assign a direct single-user permission in a specific scope (global, unit, or subtree).
     */
    public function assignUserPermission(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'permission_id' => 'required|exists:permissions,id',
            'scope_kind' => 'required|in:global,unit,subtree',
            'unit_id' => 'nullable|required_if:scope_kind,unit,subtree|exists:organization_units,id',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $actor = $request->user();
        $targetPerm = Permission::findOrFail($validated['permission_id']);

        // Privilege escalation barrier: only an authorized Super Admin can grant protected capabilities
        if ($targetPerm->is_protected && ! $actor->hasPermission('system.configure')) {
            return response()->json([
                'success' => false,
                'message' => "Forbidden: Directly granting protected capability '{$targetPerm->code}' requires system.configure authority.",
            ], 403);
        }

        $now = now()->format('Y-m-d H:i:s.u');

        // Resolve or create authorization scope
        $scopeUnitId = ($validated['scope_kind'] === 'global') ? null : (int) $validated['unit_id'];

        if (! $actor->hasPermission('system.configure')) {
            if ($validated['scope_kind'] === 'global' && ! $actor->hasPermission('permissions.assign', null)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: Granting global-scoped direct permissions requires global permissions.assign authority.',
                ], 403);
            }
            if ($scopeUnitId !== null && ! $actor->hasPermission('permissions.assign', $scopeUnitId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: You do not have permissions.assign authority for the target organizational unit.',
                ], 403);
            }
        }

        $scope = AuthorizationScope::firstOrCreate([
            'kind' => $validated['scope_kind'],
            'unit_id' => $scopeUnitId,
        ]);

        // Grant single-user direct permission
        DB::table('user_permissions')->updateOrInsert(
            [
                'user_id' => $validated['user_id'],
                'permission_id' => $validated['permission_id'],
                'scope_id' => $scope->id,
            ],
            [
                'granted_by' => $actor->id,
                'granted_at' => $now,
                'expires_at' => $validated['expires_at'] ?? null,
            ]
        );

        // Bump authz_version on target user and clear request-level cache
        User::where('id', $validated['user_id'])->increment('authz_version');
        User::clearUserGrantsCache((int) $validated['user_id']);

        AuditService::recordAuthEvent(
            action: 'auth.authorization_version_changed',
            actorId: $actor->id,
            targetType: 'user',
            targetId: (string) $validated['user_id'],
            details: [
                'reason' => 'user_permission_assigned',
                'permission_id' => $validated['permission_id'],
                'scope_id' => $scope->id,
            ],
            request: $request
        );

        return response()->json([
            'success' => true,
            'message' => 'Direct user permission successfully granted in scope.',
        ]);
    }

    /**
     * Revoke direct permission from user in a scope.
     */
    public function revokeUserPermission(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'permission_id' => 'required|exists:permissions,id',
            'scope_id' => 'required|exists:authorization_scopes,id',
        ]);

        $targetPerm = Permission::findOrFail($validated['permission_id']);
        if ($targetPerm->is_protected && ! $request->user()->hasPermission('system.configure')) {
            return response()->json([
                'success' => false,
                'message' => "Forbidden: Revoking protected capability '{$targetPerm->code}' requires system.configure authority.",
            ], 403);
        }

        if ($targetPerm->code === 'system.configure') {
            if (User::countActiveSystemAdministrators($validated['user_id']) === 0) {
                // Check if target user has active role carrying system.configure
                $targetUser = User::find($validated['user_id']);
                $hasRoleAdmin = $targetUser && $targetUser->userRoles()
                    ->whereHas('role', function ($rq) {
                        $rq->where('active', 1)->whereHas('permissions', fn ($pq) => $pq->where('code', 'system.configure'));
                    })
                    ->whereHas('scope', fn ($s) => $s->where('kind', 'global'))
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    })
                    ->exists();

                if (! $hasRoleAdmin) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Security restriction: Cannot revoke system.configure from the last active System Administrator in the institution.',
                    ], 422);
                }
            }
        }

        $scope = AuthorizationScope::findOrFail($validated['scope_id']);
        if (! $request->user()->hasPermission('system.configure')) {
            if ($scope->kind === 'global' && ! $request->user()->hasPermission('permissions.assign', null)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: Revoking global-scoped direct permissions requires global permissions.assign authority.',
                ], 403);
            }
            if ($scope->unit_id !== null && ! $request->user()->hasPermission('permissions.assign', (int) $scope->unit_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: You do not have permissions.assign authority for the target organizational unit.',
                ], 403);
            }
        }

        DB::table('user_permissions')
            ->where('user_id', $validated['user_id'])
            ->where('permission_id', $validated['permission_id'])
            ->where('scope_id', $validated['scope_id'])
            ->delete();

        User::where('id', $validated['user_id'])->increment('authz_version');
        User::clearUserGrantsCache((int) $validated['user_id']);

        AuditService::recordAuthEvent(
            action: 'auth.authorization_version_changed',
            actorId: $request->user()->id,
            targetType: 'user',
            targetId: (string) $validated['user_id'],
            details: [
                'reason' => 'user_permission_revoked',
                'permission_id' => $validated['permission_id'],
                'scope_id' => $validated['scope_id'],
            ],
            request: $request
        );

        return response()->json([
            'success' => true,
            'message' => 'Direct user permission revoked successfully.',
        ]);
    }
}
