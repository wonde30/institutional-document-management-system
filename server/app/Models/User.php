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
     * Active primary membership.
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
    public function correspondenceParticipations(): HasMany
    {
        return $this->hasMany(CorrespondenceParticipant::class, 'user_id');
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

        $query = $this->userRoles()
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereHas('role', fn ($q) => $q->whereIn('code', $codes)->where('active', 1));

        if ($unitId !== null) {
            $ancestorIds = [];
            $targetUnit = OrganizationUnit::find($unitId);
            if ($targetUnit) {
                $ancestorIds = $targetUnit->getAncestorIds();
            }
            $subtreeUnitIds = array_merge([$unitId], $ancestorIds);

            $query->whereHas('scope', function ($q) use ($unitId, $subtreeUnitIds) {
                $q->where('kind', 'global')
                  ->orWhere(fn ($sq) => $sq->where('kind', 'unit')->where('unit_id', $unitId))
                  ->orWhere(fn ($sq) => $sq->where('kind', 'subtree')->whereIn('unit_id', $subtreeUnitIds));
            });
        }

        return $query->exists();
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

        // 1. Direct permission in global scope or matching unit scope
        $directQuery = $this->userPermissions()
            ->whereHas('permission', fn ($q) => $q->where('code', $permissionCode)->where('active', 1))
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereHas('scope', function ($q) use ($unitId, $subtreeUnitIds) {
                $q->where('kind', 'global');
                if ($unitId !== null) {
                    $q->orWhere(fn ($sq) => $sq->where('kind', 'unit')->where('unit_id', $unitId))
                      ->orWhere(fn ($sq) => $sq->where('kind', 'subtree')->whereIn('unit_id', $subtreeUnitIds));
                }
            });

        if ($directQuery->exists()) {
            return true;
        }

        // 2. Role permission in global scope or matching unit scope
        $roleQuery = $this->userRoles()
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereHas('role', function ($rq) use ($permissionCode) {
                $rq->where('active', 1)
                   ->whereHas('permissions', fn ($pq) => $pq->where('code', $permissionCode)->where('active', 1));
            })
            ->whereHas('scope', function ($q) use ($unitId, $subtreeUnitIds) {
                $q->where('kind', 'global');
                if ($unitId !== null) {
                    $q->orWhere(fn ($sq) => $sq->where('kind', 'unit')->where('unit_id', $unitId))
                      ->orWhere(fn ($sq) => $sq->where('kind', 'subtree')->whereIn('unit_id', $subtreeUnitIds));
                }
            });

        return $roleQuery->exists();
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

        $targetUnit = OrganizationUnit::find($unitId);
        if (! $targetUnit) {
            return [$unitId];
        }

        return array_merge([$unitId], $targetUnit->getAncestorIds());
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
        $subtreeUnitIds = $this->resolveSubtreeUnitIds($unitId);

        // 1. Direct permissions
        $directQuery = $this->userPermissions()
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with(['permission', 'scope']);

        $directCodes = $directQuery->get()
            ->filter(function ($up) use ($unitId, $subtreeUnitIds) {
                if (! $up->permission || ! $up->permission->active) return false;
                return $this->scopeAppliesToUnit($up->scope, $unitId, $subtreeUnitIds);
            })
            ->map(fn ($up) => $up->permission->code);

        // 2. Role permissions
        $roleQuery = $this->userRoles()
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with(['role.permissions', 'scope']);

        $roleCodes = $roleQuery->get()
            ->filter(function ($ur) use ($unitId, $subtreeUnitIds) {
                if (! $ur->role || ! $ur->role->active) return false;
                return $this->scopeAppliesToUnit($ur->scope, $unitId, $subtreeUnitIds);
            })
            ->flatMap(fn ($ur) => $ur->role->permissions->where('active', 1)->pluck('code'));

        return $directCodes->toBase()->merge($roleCodes)->unique()->values()->all();
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
        $directCodes = $this->userPermissions()
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with('permission')
            ->get()
            ->filter(fn ($up) => $up->permission && $up->permission->active)
            ->map(fn ($up) => $up->permission->code);

        $roleCodes = $this->userRoles()
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with('role.permissions')
            ->get()
            ->filter(fn ($ur) => $ur->role && $ur->role->active)
            ->flatMap(fn ($ur) => $ur->role->permissions->where('active', 1)->pluck('code'));

        return $directCodes->toBase()->merge($roleCodes)->unique()->values()->all();
    }

    /**
     * Resolve the unit the user is currently operating in (primary membership).
     */
    public function getPrimaryUnitId(): ?int
    {
        $membership = $this->primaryMembershipRecord?->membership;

        return $membership?->unit_id !== null ? (int) $membership->unit_id : null;
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
        // 1. Direct permissions
        $directPerms = $this->userPermissions()
            ->whereHas('permission', fn ($q) => $q->where('code', $permissionCode)->where('active', 1))
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with('scope')
            ->get();

        // 2. Role permissions
        $rolePerms = $this->userRoles()
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereHas('role', function ($rq) use ($permissionCode) {
                $rq->where('active', 1)
                   ->whereHas('permissions', fn ($pq) => $pq->where('code', $permissionCode)->where('active', 1));
            })
            ->with('scope')
            ->get();

        $allScopes = $directPerms->pluck('scope')->merge($rolePerms->pluck('scope'))->filter();

        // If no active grant exists for this permission, return empty array
        if ($allScopes->isEmpty()) {
            return [];
        }

        // If any scope is global, permission is unconstrained across all units
        if ($allScopes->contains(fn ($s) => $s->kind === 'global')) {
            return null;
        }

        $unitIds = [];
        foreach ($allScopes as $scope) {
            if ($scope->kind === 'unit' && $scope->unit_id) {
                $unitIds[] = (int) $scope->unit_id;
            } elseif ($scope->kind === 'subtree' && $scope->unit_id) {
                $unitIds[] = (int) $scope->unit_id;
                $targetUnit = OrganizationUnit::find($scope->unit_id);
                if ($targetUnit) {
                    $unitIds = array_merge($unitIds, $targetUnit->getDescendantIds());
                }
            }
        }

        return array_values(array_unique($unitIds));
    }
}
