<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use CanResetPassword, HasApiTokens, HasFactory, MustVerifyEmailTrait, Notifiable;

    protected $table = 'users';
    protected $dateFormat = 'Y-m-d H:i:s.u';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'authz_version',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The unit of the user's primary membership, memoised for the instance.
     */
    protected ?int $cachedPrimaryUnitId = null;

    /**
     * Whether the primary membership has already been resolved.
     */
    protected bool $primaryUnitResolved = false;

    /**
     * Memoised ancestor unit identifiers, keyed by unit identifier.
     *
     * @var array<string, list<int>>
     */
    protected array $cachedSubtreeUnitIds = [];

    public function clearGrantsCache(): void
    {
        $this->cachedSubtreeUnitIds = [];
        static::clearUserGrantsCache($this->id);
    }

    public static function clearUserGrantsCache(?int $userId = null): void
    {
        if (app()->bound('request') && ($req = app('request')) && $req instanceof \Illuminate\Http\Request) {
            if ($userId !== null) {
                $req->attributes->remove('__user_grants_' . $userId);
                foreach (array_keys($req->attributes->all()) as $k) {
                    if (str_starts_with($k, '__user_permitted_units_' . $userId . '_')) {
                        $req->attributes->remove($k);
                    }
                }
            } else {
                foreach (array_keys($req->attributes->all()) as $k) {
                    if (str_starts_with($k, '__user_grants_') || str_starts_with($k, '__user_permitted_units_')) {
                        $req->attributes->remove($k);
                    }
                }
            }
        }
    }

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'authz_version' => 'integer',
        ];
    }

    /**
     * All memberships in organizational units.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class, 'user_id');
    }

    /**
     * Organization units the user is a member of.
     */
    public function organizationUnits(): BelongsToMany
    {
        return $this->belongsToMany(OrganizationUnit::class, 'organization_memberships', 'user_id', 'unit_id')
            ->withPivot(['position_title', 'starts_at', 'ends_at', 'created_by', 'created_at']);
    }

    /**
     * Active primary membership.
     */
    /**
     * @return HasOne<PrimaryMembership, $this>
     */
    public function primaryMembershipRecord(): HasOne
    {
        return $this->hasOne(PrimaryMembership::class, 'user_id');
    }

    /**
     * Scoped user roles.
     */
    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class, 'user_id');
    }

    /**
     * Direct user permissions.
     */
    public function userPermissions(): HasMany
    {
        return $this->hasMany(UserPermission::class, 'user_id');
    }

    /**
     * Roles assigned to the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot(['scope_id', 'granted_by', 'granted_at', 'expires_at']);
    }

    /**
     * Associated staff profile.
     */
    /**
     * @return HasOne<StaffProfile, $this>
     */
    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class, 'user_id');
    }

    /**
     * Documents registered/created by this user.
     */
    public function createdDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'created_by');
    }

    /**
     * Correspondence participation entries.
     */
    public function correspondenceParticipations(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            CorrespondenceParticipant::class,
            CommunicationParty::class,
            'user_id',
            'party_id'
        );
    }

    /**
     * Check if user is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Deliver the password recovery notification.
     *
     * The notification is delivered synchronously. Account recovery is a low
     * volume, high consequence operation, and a queued delivery would leave
     * the user waiting on a worker that a single-node institutional
     * deployment may not run. The delivery attempt is audited by the
     * notification transport.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notifyNow(new \Illuminate\Auth\Notifications\ResetPassword($token));
    }

    /**
     * Deliver the address verification notification synchronously for the same
     * reason as the password recovery notification.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notifyNow(new \Illuminate\Auth\Notifications\VerifyEmail);
    }

    /**
     * Check if user has an assigned active role (by code or array of codes), optionally scoped.
     * When $unitId is omitted, checks if the role is held in any scope.
     */
    public function hasRole(string|array $roleCodes, ?int $unitId = null): bool
    {
        $codes = is_array($roleCodes) ? $roleCodes : [$roleCodes];
        $subtreeUnitIds = $this->resolveSubtreeUnitIds($unitId);
        $grants = $this->loadActiveGrants();

        return $grants['roles']
            ->filter(fn ($ur) => $ur->role && in_array($ur->role->code, $codes, true) && $ur->role->active)
            ->contains(function ($ur) use ($unitId, $subtreeUnitIds) {
                if ($unitId === null) {
                    return true;
                }
                return $this->scopeAppliesToUnit($ur->scope, $unitId, $subtreeUnitIds);
            });
    }

    /**
     * Check if user has a permission in a given scope (or global).
     *
     * Scope resolution is delegated to scopeAppliesToUnit() so that this
     * method and getEffectivePermissionCodes() can never drift apart.
     */
    public function hasPermission(string $permissionCode, ?int $unitId = null): bool
    {
        $subtreeUnitIds = $this->resolveSubtreeUnitIds($unitId);
        $grants = $this->loadActiveGrants();

        // 1. Direct permission in global scope or matching unit scope
        $direct = $grants['direct']
            ->filter(fn ($up) => $up->permission?->code === $permissionCode)
            ->contains(fn ($up) => $this->scopeAppliesToUnit($up->scope, $unitId, $subtreeUnitIds));

        if ($direct) {
            return true;
        }

        // 2. Role permission in global scope or matching unit scope
        return $grants['roles']
            ->filter(fn ($ur) => $ur->role?->permissions->contains(
                fn ($p) => $p->code === $permissionCode && $p->active
            ))
            ->contains(fn ($ur) => $this->scopeAppliesToUnit($ur->scope, $unitId, $subtreeUnitIds));
    }

    /**
     * Determine whether an authorization scope grants access for a given unit.
     *
     * Scope semantics (identical to the resolution performed by hasPermission()):
     *  - 'global'  -> applies everywhere, including when no unit is targeted.
     *  - 'unit'    -> applies only to the exact unit.
     *  - 'subtree' -> applies to the anchor unit and every descendant unit.
     *
     * When $unitId is null the grant is evaluated in the global scope only, so a
     * unit- or subtree-scoped grant never satisfies an unscoped check.
     */
    protected function scopeAppliesToUnit(?AuthorizationScope $scope, ?int $unitId, array $subtreeUnitIds): bool
    {
        if (! $scope) {
            return false;
        }

        if ($scope->kind === 'global') {
            return true;
        }

        if ($unitId === null) {
            return false;
        }

        if ($scope->kind === 'unit') {
            return (int) $scope->unit_id === $unitId;
        }

        if ($scope->kind === 'subtree') {
            return in_array((int) $scope->unit_id, $subtreeUnitIds, true);
        }

        return false;
    }

    /**
     * Resolve the ancestor unit identifiers for the given unit.
     *
     * @return list<int>
     */
    protected function resolveSubtreeUnitIds(?int $unitId): array
    {
        if ($unitId === null) {
            return [];
        }

        $cacheKey = 'unit_'.$unitId;

        if (isset($this->cachedSubtreeUnitIds[$cacheKey])) {
            return $this->cachedSubtreeUnitIds[$cacheKey];
        }

        $targetUnit = OrganizationUnit::find($unitId);
        if (! $targetUnit) {
            return $this->cachedSubtreeUnitIds[$cacheKey] = [$unitId];
        }

        return $this->cachedSubtreeUnitIds[$cacheKey] = array_merge([$unitId], $targetUnit->getAncestorIds());
    }

    /**
     * Get all effective permission codes for the user in a given scope.
     *
     * The resolution is intentionally identical to hasPermission() so that the
     * capability set exposed to clients matches the server-side decision:
     *  - $unitId null  -> global scope grants only.
     *  - $unitId set   -> global grants, grants for that exact unit, and
     *                     subtree grants anchored on the unit or an ancestor.
     */
    public function getEffectivePermissionCodes(?int $unitId = null): array
    {
        return $this->getCapabilitySets($unitId)['active'];
    }

    /**
     * Get every permission code the user holds across every scope.
     *
     * Reserved for administrative surfaces (permission registry, scoped role
     * management) that must display the complete grant matrix. It must never
     * be used to decide whether an operation is permitted.
     */
    public function getAllPermissionCodesAcrossScopes(): array
    {
        return $this->getCapabilitySets()['all'];
    }

    /**
     * Resolve the unit the user is currently operating in (primary membership).
     */
    public function getPrimaryUnitId(): ?int
    {
        if ($this->primaryUnitResolved) {
            return $this->cachedPrimaryUnitId;
        }

        $membership = $this->primaryMembershipRecord?->membership;

        $this->primaryUnitResolved = true;

        return $this->cachedPrimaryUnitId = ($membership?->unit_id !== null ? (int) $membership->unit_id : null);
    }

    /**
     * Get all organization unit IDs where the user has the specified permission.
     * Returns null if the user has global permission (unrestricted).
     * Returns array of unit IDs (including expanded subtrees) if restricted.
     * Returns empty array [] if the user holds no active grant for this permission.
     *
     * @return list<int>|null
     */
    public function getPermittedUnitIds(string $permissionCode): ?array
    {
        $request = app()->bound('request') ? app('request') : null;
        $reqCacheKey = '__user_permitted_units_' . $this->id . '_' . $permissionCode;

        if ($request && $request instanceof \Illuminate\Http\Request && $request->attributes->has($reqCacheKey)) {
            return $request->attributes->get($reqCacheKey);
        }

        $grants = $this->loadActiveGrants();

        // 1. Direct permissions carrying the capability
        $directPerms = $grants['direct']->filter(
            fn ($up) => $up->permission && $up->permission->code === $permissionCode
        );

        // 2. Role grants whose role carries the capability
        $rolePerms = $grants['roles']->filter(
            fn ($ur) => $ur->role && $ur->role->permissions->contains(
                fn ($p) => $p->code === $permissionCode && $p->active
            )
        );

        $allScopes = $directPerms->pluck('scope')->merge($rolePerms->pluck('scope'))->filter();

        // If no active grant exists for this permission, return empty array
        if ($allScopes->isEmpty()) {
            if ($request && $request instanceof \Illuminate\Http\Request) {
                $request->attributes->set($reqCacheKey, []);
            }
            return [];
        }

        // If any scope is global, permission is unconstrained across all units
        if ($allScopes->contains(fn ($s) => $s->kind === 'global')) {
            if ($request && $request instanceof \Illuminate\Http\Request) {
                $request->attributes->set($reqCacheKey, null);
            }
            return null;
        }

        $unitIds = $this->expandScopesToUnitIds($allScopes);
        if ($request && $request instanceof \Illuminate\Http\Request) {
            $request->attributes->set($reqCacheKey, $unitIds);
        }
        return $unitIds;
    }

    /**
     * Expand a set of authorization scopes into the concrete unit identifiers
     * they cover.
     *
     * A 'unit' scope covers exactly its own unit. A 'subtree' scope covers
     * the anchor unit and every descendant unit. A 'global' scope is
     * unconstrained and therefore contributes no specific unit here; it is
     * handled by the caller.
     *
     * The subtree expansion is performed once for every distinct anchor unit,
     * and all anchors are expanded together, so the cost is one query per
     * level of the hierarchy rather than one walk per grant.
     *
     * @param  iterable<mixed>  $scopes
     * @return list<int>
     */
    protected function expandScopesToUnitIds(iterable $scopes): array
    {
        $unitIds = [];
        $subtreeAnchors = [];

        foreach ($scopes as $scope) {
            if (! $scope || ! $scope->unit_id) {
                continue;
            }

            $unitIds[] = (int) $scope->unit_id;

            if ($scope->kind === 'subtree') {
                $subtreeAnchors[(int) $scope->unit_id] = true;
            }
        }

        if (! empty($subtreeAnchors)) {
            $unitIds = array_merge($unitIds, $this->descendantIdsFor(array_keys($subtreeAnchors)));
        }

        return array_values(array_unique($unitIds));
    }

    /**
     * Resolve every descendant of the given anchor units.
     *
     * All anchors are expanded in a single breadth first walk, which costs one
     * query per level of the hierarchy regardless of how many anchors or
     * grants are involved.
     *
     * @param  list<int>  $anchorIds
     * @return list<int>
     */
    protected function descendantIdsFor(array $anchorIds): array
    {
        $descendants = [];
        $visited = [];
        $queue = array_values(array_unique($anchorIds));

        while (! empty($queue)) {
            $parentIds = array_values(array_diff($queue, $visited));

            if (empty($parentIds)) {
                break;
            }

            $visited = array_merge($visited, $parentIds);

            $childIds = OrganizationUnit::whereIn('parent_id', $parentIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            foreach ($childIds as $childId) {
                if (! in_array($childId, $descendants, true)) {
                    $descendants[] = $childId;
                }
            }

            $queue = $childIds;
        }

        return $descendants;
    }

    /**
     * The user's active role and direct permission grants.
     *
     * A grant is active when it has not expired and the role or permission it
     * carries is itself active. The grants are loaded on demand and are never
     * cached on the instance, so a grant that is added or revoked is always
     * observed by the next decision.
     *
     * @return array{roles: \Illuminate\Support\Collection<int, UserRole>, direct: \Illuminate\Support\Collection<int, UserPermission>}
     */
    public function loadActiveGrants(bool $forceRefresh = false): array
    {
        $request = app()->bound('request') ? app('request') : null;
        $reqCacheKey = '__user_grants_' . $this->id;

        if (! $forceRefresh && $request && $request instanceof \Illuminate\Http\Request && $request->attributes->has($reqCacheKey)) {
            return $request->attributes->get($reqCacheKey);
        }

        $roles = $this->userRoles()
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereHas('role', fn ($rq) => $rq->where('active', 1))
            ->with(['role.permissions', 'scope'])
            ->get();

        $direct = $this->userPermissions()
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereHas('permission', fn ($q) => $q->where('active', 1))
            ->with(['permission', 'scope'])
            ->get();

        $grants = ['roles' => $roles, 'direct' => $direct];

        if ($request && $request instanceof \Illuminate\Http\Request) {
            $request->attributes->set($reqCacheKey, $grants);
        }

        return $grants;
    }

    /**
     * Resolve every capability set of the user in a single pass over the
     * active grants.
     *
     * The three sets are derived from one load of the grants, so the published
     * capability set, the global capability set and the administrative listing
     * can never disagree with one another.
     *
     * @return array{active: list<string>, global: list<string>, all: list<string>}
     */
    public function getCapabilitySets(?int $unitId = null, ?array $grants = null): array
    {
        $subtreeUnitIds = $this->resolveSubtreeUnitIds($unitId);
        $grants = $grants ?? $this->loadActiveGrants();

        $active = [];
        $global = [];
        $all = [];

        foreach ($grants['direct'] as $directGrant) {
            if (! $directGrant->permission || ! $directGrant->permission->active) {
                continue;
            }

            $code = $directGrant->permission->code;
            $all[$code] = true;

            if ($directGrant->scope?->kind === 'global') {
                $global[$code] = true;
            }

            if ($this->scopeAppliesToUnit($directGrant->scope, $unitId, $subtreeUnitIds)) {
                $active[$code] = true;
            }
        }

        foreach ($grants['roles'] as $roleGrant) {
            if (! $roleGrant->role || ! $roleGrant->role->active) {
                continue;
            }

            foreach ($roleGrant->role->permissions->where('active', 1) as $permission) {
                $code = $permission->code;
                $all[$code] = true;

                if ($roleGrant->scope?->kind === 'global') {
                    $global[$code] = true;
                }

                if ($this->scopeAppliesToUnit($roleGrant->scope, $unitId, $subtreeUnitIds)) {
                    $active[$code] = true;
                }
            }
        }

        return [
            'active' => array_keys($active),
            'global' => array_keys($global),
            'all' => array_keys($all),
        ];
    }

    /**
     * The scopes carried by the user's active grants.
     *
     * @return \Illuminate\Support\Collection<int, AuthorizationScope>
     */
    protected function activeGrantScopes(?array $grants = null): \Illuminate\Support\Collection
    {
        $grants = $grants ?? $this->loadActiveGrants();

        return $grants['roles']->pluck('scope')
            ->merge($grants['direct']->pluck('scope'))
            ->filter();
    }

    /**
     * Every organizational unit covered by an active scoped grant, regardless
     * of the capability that the grant carries.
     *
     * Global grants are unconstrained and therefore contribute no specific
     * unit. Subtree grants contribute the anchor unit and every descendant.
     *
     * @return list<int>
     */
    public function getGrantScopedUnitIds(?array $grants = null): array
    {
        $grants = $grants ?? $this->loadActiveGrants();

        $scopes = $grants['roles']->pluck('scope')
            ->merge($grants['direct']->pluck('scope'))
            ->filter();

        return $this->expandScopesToUnitIds($scopes);
    }

    /**
     * The organizational units the user is an active member of.
     *
     * @return list<int>
     */
    public function getActiveMembershipUnitIds(): array
    {
        return $this->memberships()
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->pluck('unit_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Inspect and resolve complete permission lineage for this user.
     *
     * Provides full audit trace: User -> Role/Direct Grant -> Permission -> Scope,
     * evaluating whether each grant is effective, expired, out of scope, or deactivated.
     *
     * @param int|null $unitId Target unit to evaluate scope against (null for global evaluation)
     * @return array<string, mixed>
     */
    public function getPermissionLineage(?int $unitId = null): array
    {
        $targetUnit = $unitId !== null ? OrganizationUnit::find($unitId) : null;
        $subtreeUnitIds = $this->resolveSubtreeUnitIds($unitId);

        $userRoles = $this->userRoles()
            ->with(['role.permissions', 'scope.unit', 'grantedBy'])
            ->get();

        $userPerms = $this->userPermissions()
            ->with(['permission', 'scope.unit', 'grantor'])
            ->get();

        $lineage = [];
        $warnings = [];
        $effectiveCodes = [];
        $scopesHeld = [];

        // 1. Process Role Grants
        foreach ($userRoles as $ur) {
            $role = $ur->role;
            $scope = $ur->scope;
            $isRoleExpired = $ur->expires_at && $ur->expires_at->isPast();
            $isRoleActive = $role && $role->active;

            if ($isRoleExpired) {
                $warnings[] = "Role assignment '{$role?->name}' expired on {$ur->expires_at->toIso8601String()}.";
            }
            if ($role && ! $role->active) {
                $warnings[] = "Assigned role '{$role->name}' is deactivated in the catalog.";
            }

            $scopeApplies = $this->scopeAppliesToUnit($scope, $unitId, $subtreeUnitIds);

            if ($scope) {
                $scopeKey = "{$scope->kind}_{$scope->unit_id}";
                $scopesHeld[$scopeKey] = [
                    'id' => $scope->id,
                    'kind' => $scope->kind,
                    'unit_id' => $scope->unit_id,
                    'unit_name' => $scope->unit?->name ?? ($scope->kind === 'global' ? 'Global Scope' : null),
                ];
            }

            if ($role) {
                foreach ($role->permissions as $p) {
                    $isPermActive = (bool) $p->active;
                    $isEffective = (! $isRoleExpired) && $isRoleActive && $isPermActive && $scopeApplies;

                    if ($isEffective) {
                        $effectiveCodes[$p->code] = true;
                    }

                    $statusLabel = 'Effective';
                    if ($isRoleExpired) {
                        $statusLabel = 'Expired';
                    } elseif (! $isRoleActive) {
                        $statusLabel = 'Role Inactive';
                    } elseif (! $isPermActive) {
                        $statusLabel = 'Permission Inactive';
                    } elseif (! $scopeApplies) {
                        $statusLabel = 'Out of Scope';
                    }

                    $codeParts = explode('.', $p->code);
                    $module = count($codeParts) > 1 ? $codeParts[0] : 'general';

                    $lineage[] = [
                        'permission_id' => $p->id,
                        'permission_code' => $p->code,
                        'permission_name' => $p->name,
                        'permission_description' => $p->description,
                        'module' => $module,
                        'source_type' => 'role',
                        'source_id' => $role->id,
                        'source_code' => $role->code,
                        'source_name' => $role->name,
                        'scope_kind' => $scope?->kind ?? 'unknown',
                        'scope_unit_id' => $scope?->unit_id,
                        'scope_unit_name' => $scope?->unit?->name ?? ($scope?->kind === 'global' ? 'All Units (Global)' : null),
                        'granted_at' => $ur->granted_at?->toIso8601String(),
                        'expires_at' => $ur->expires_at?->toIso8601String(),
                        'is_expired' => $isRoleExpired,
                        'is_source_active' => $isRoleActive,
                        'is_permission_active' => $isPermActive,
                        'scope_applies' => $scopeApplies,
                        'is_effective' => $isEffective,
                        'status_label' => $statusLabel,
                    ];
                }
            }
        }

        // 2. Process Direct Permission Grants
        foreach ($userPerms as $up) {
            $perm = $up->permission;
            $scope = $up->scope;
            $isPermExpired = $up->expires_at && $up->expires_at->isPast();
            $isPermActive = $perm && $perm->active;

            if ($isPermExpired) {
                $warnings[] = "Direct grant '{$perm?->name}' expired on {$up->expires_at->toIso8601String()}.";
            }
            if ($perm && ! $perm->active) {
                $warnings[] = "Directly granted permission '{$perm->name}' is deactivated in the catalog.";
            }

            $scopeApplies = $this->scopeAppliesToUnit($scope, $unitId, $subtreeUnitIds);

            if ($scope) {
                $scopeKey = "{$scope->kind}_{$scope->unit_id}";
                $scopesHeld[$scopeKey] = [
                    'id' => $scope->id,
                    'kind' => $scope->kind,
                    'unit_id' => $scope->unit_id,
                    'unit_name' => $scope->unit?->name ?? ($scope->kind === 'global' ? 'Global Scope' : null),
                ];
            }

            if ($perm) {
                $isEffective = (! $isPermExpired) && $isPermActive && $scopeApplies;

                if ($isEffective) {
                    $effectiveCodes[$perm->code] = true;
                }

                $statusLabel = 'Effective';
                if ($isPermExpired) {
                    $statusLabel = 'Expired';
                } elseif (! $isPermActive) {
                    $statusLabel = 'Permission Inactive';
                } elseif (! $scopeApplies) {
                    $statusLabel = 'Out of Scope';
                }

                $codeParts = explode('.', $perm->code);
                $module = count($codeParts) > 1 ? $codeParts[0] : 'general';

                $lineage[] = [
                    'permission_id' => $perm->id,
                    'permission_code' => $perm->code,
                    'permission_name' => $perm->name,
                    'permission_description' => $perm->description,
                    'module' => $module,
                    'source_type' => 'direct',
                    'source_id' => $up->id,
                    'source_code' => 'direct_grant',
                    'source_name' => 'Direct User Grant',
                    'scope_kind' => $scope?->kind ?? 'unknown',
                    'scope_unit_id' => $scope?->unit_id,
                    'scope_unit_name' => $scope?->unit?->name ?? ($scope?->kind === 'global' ? 'All Units (Global)' : null),
                    'granted_at' => $up->granted_at?->toIso8601String(),
                    'expires_at' => $up->expires_at?->toIso8601String(),
                    'is_expired' => $isPermExpired,
                    'is_source_active' => true,
                    'is_permission_active' => $isPermActive,
                    'scope_applies' => $scopeApplies,
                    'is_effective' => $isEffective,
                    'status_label' => $statusLabel,
                ];
            }
        }

        // Sort lineage by module then permission code
        usort($lineage, function ($a, $b) {
            $cmp = strcmp($a['module'], $b['module']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp($a['permission_code'], $b['permission_code']);
        });

        return [
            'user_id' => $this->id,
            'user_name' => $this->name,
            'user_email' => $this->email,
            'target_unit_id' => $unitId,
            'target_unit_name' => $targetUnit?->name,
            'primary_unit_id' => $this->getPrimaryUnitId(),
            'effective_codes' => array_values(array_keys($effectiveCodes)),
            'effective_count' => count($effectiveCodes),
            'lineage' => $lineage,
            'warnings' => array_values(array_unique($warnings)),
            'scopes_held' => array_values($scopesHeld),
        ];
    }

    /**
     * Count active users who hold the protected system administrative capability ('system.configure') in global scope.
     *
     * @param int|null $excludingUserId Exclude this user from the count if testing whether revoking/suspending them causes lockout.
     */
    public static function countActiveSystemAdministrators(?int $excludingUserId = null): int
    {
        $permId = DB::table('permissions')->where('code', 'system.configure')->value('id');
        if (! $permId) {
            return 0;
        }

        $now = now();

        $roleUserIds = DB::table('user_roles')
            ->join('users', 'users.id', '=', 'user_roles.user_id')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'roles.id')
            ->join('authorization_scopes', 'authorization_scopes.id', '=', 'user_roles.scope_id')
            ->where('role_permissions.permission_id', $permId)
            ->where('roles.active', 1)
            ->where('users.status', 'active')
            ->where('authorization_scopes.kind', 'global')
            ->where(function ($q) use ($now) {
                $q->whereNull('user_roles.expires_at')->orWhere('user_roles.expires_at', '>', $now);
            })
            ->when($excludingUserId !== null, fn ($q) => $q->where('users.id', '!=', $excludingUserId))
            ->pluck('users.id')
            ->all();

        $directUserIds = DB::table('user_permissions')
            ->join('users', 'users.id', '=', 'user_permissions.user_id')
            ->join('authorization_scopes', 'authorization_scopes.id', '=', 'user_permissions.scope_id')
            ->where('user_permissions.permission_id', $permId)
            ->where('users.status', 'active')
            ->where('authorization_scopes.kind', 'global')
            ->where(function ($q) use ($now) {
                $q->whereNull('user_permissions.expires_at')->orWhere('user_permissions.expires_at', '>', $now);
            })
            ->when($excludingUserId !== null, fn ($q) => $q->where('users.id', '!=', $excludingUserId))
            ->pluck('users.id')
            ->all();

        return count(array_unique(array_merge($roleUserIds, $directUserIds)));
    }
}
