<?php

declare(strict_types=1);

namespace App\Services\Authorization;

use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Resolves the active organizational context of the authenticated user.
 *
 * The context has two parts:
 *
 *  - the **available units**: the organizational units the user may select as
 *    their working context. The server is the only authority on this set.
 *  - the **active unit**: the unit the user is currently operating in. It is
 *    persisted in the session, so the client never supplies an authoritative
 *    unit identifier; it only asks the server to switch.
 *
 * The effective capability set is always calculated for the active unit using
 * the canonical scope resolution (User::getEffectivePermissionCodes()). A user
 * without an active unit receives only globally scoped capabilities, never the
 * union of every scoped grant.
 *
 * The active unit never widens a grant: selecting a different unit changes
 * which capabilities are published, and nothing else. Record level
 * authorization continues to evaluate the record's own owning unit.
 */
class OrganizationalContextService
{
    /**
     * Session key holding the active organizational unit identifier.
     */
    public const SESSION_KEY = 'active_unit_id';

    /**
     * The organizational units the user may select.
     *
     * A unit is available when the user either belongs to it, or holds an
     * active grant whose scope covers it. Global grants are unconstrained and
     * therefore do not widen the selectable set.
     *
     * @return list<int>
     */
    public function availableUnitIds(User $user, ?array $grants = null): array
    {
        $ids = array_merge(
            $user->getActiveMembershipUnitIds(),
            $user->getGrantScopedUnitIds($grants)
        );

        $ids = array_values(array_unique(array_filter($ids)));

        if (empty($ids)) {
            return [];
        }

        // An inactive unit is never offered and never accepted as a selection.
        $activeIds = OrganizationUnit::whereIn('id', $ids)
            ->where('active', 1)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_intersect($ids, $activeIds));
    }

    /**
     * The available units, enriched with the metadata the interface needs.
     *
     * @param  list<int>|null  $unitIds  Pre-computed available identifiers.
     * @return list<array<string, mixed>>
     */
    public function availableUnits(User $user, ?array $unitIds = null): array
    {
        $unitIds = $unitIds ?? $this->availableUnitIds($user);

        if (empty($unitIds)) {
            return [];
        }

        $units = OrganizationUnit::with('unitType')
            ->whereIn('id', $unitIds)
            ->where('active', 1)
            ->orderBy('name')
            ->get();

        $primaryUnitId = $user->getPrimaryUnitId();

        return $units->map(fn (OrganizationUnit $unit) => [
            'id' => (int) $unit->id,
            'code' => $unit->code,
            'name' => $unit->name,
            'unit_type' => $unit->unitType?->name,
            'parent_id' => $unit->parent_id !== null ? (int) $unit->parent_id : null,
            'is_primary' => (int) $unit->id === (int) $primaryUnitId,
        ])->values()->all();
    }

    /**
     * Resolve the effective active unit for the request.
     *
     * Resolution order:
     *  1. the unit recorded in the session, when it is still available;
     *  2. the user's primary membership, when it is available;
     *  3. the single available unit, when exactly one exists;
     *  4. no active unit.
     *
     * A session unit that is no longer available (for example after the grant
     * was revoked) is discarded rather than honoured.
     */
    public function resolveActiveUnitId(User $user, ?Request $request = null, ?array $availableUnitIds = null): ?int
    {
        $available = $availableUnitIds ?? $this->availableUnitIds($user);

        if (empty($available)) {
            if ($request) {
                $this->forgetActiveUnit($request);
            }

            return null;
        }

        $sessionUnitId = $request ? $this->sessionUnitId($request) : null;

        if ($sessionUnitId !== null && in_array($sessionUnitId, $available, true)) {
            return $sessionUnitId;
        }

        // The recorded unit is no longer available: fall back rather than
        // honouring a unit the user may not act in.
        if ($request && $sessionUnitId !== null) {
            $this->forgetActiveUnit($request);
        }

        $primaryUnitId = $user->getPrimaryUnitId();
        if ($primaryUnitId !== null && in_array($primaryUnitId, $available, true)) {
            if ($request) {
                $this->rememberActiveUnit($request, $primaryUnitId);
            }

            return $primaryUnitId;
        }

        if (count($available) === 1) {
            if ($request) {
                $this->rememberActiveUnit($request, $available[0]);
            }

            return $available[0];
        }

        return null;
    }

    /**
     * Validate a requested unit selection.
     *
     * Returns the unit identifier when the user may select it, otherwise null.
     */
    public function validateUnitSelection(User $user, ?int $unitId): ?int
    {
        if ($unitId === null) {
            return null;
        }

        $available = $this->availableUnitIds($user);

        if (! in_array($unitId, $available, true)) {
            return null;
        }

        return $unitId;
    }

    /**
     * The capability set for the active organizational context.
     *
     * The grants are loaded once and serve both the active unit resolution
     * and the capability resolution, so an endpoint that only needs the
     * capability set does not repeat the grant query.
     *
     * @return list<string>
     */
    public function capabilitiesForActiveContext(User $user, Request $request): array
    {
        $grants = $user->loadActiveGrants();

        $availableUnitIds = $this->availableUnitIds($user, $grants);
        $activeUnitId = $this->resolveActiveUnitId($user, $request, $availableUnitIds);

        return $user->getCapabilitySets($activeUnitId, $grants)['active'];
    }

    /**
     * Build the published organizational context.
     *
     * @return array<string, mixed>
     */
    public function buildContext(User $user, ?Request $request = null): array
    {
        // One load of the grants serves the available units and the capability
        // sets, so a context build never repeats the grant query.
        $grants = $user->loadActiveGrants();

        $availableUnitIds = $this->availableUnitIds($user, $grants);
        $activeUnitId = $this->resolveActiveUnitId($user, $request, $availableUnitIds);
        $available = $this->availableUnits($user, $availableUnitIds);

        $activeUnit = null;
        if ($activeUnitId !== null) {
            $activeUnit = collect($available)->firstWhere('id', $activeUnitId);
        }

        // The capability set is calculated for the active context only. With no
        // active unit the user receives globally scoped capabilities alone,
        // never the union of every scoped grant.
        $capabilities = $user->getCapabilitySets($activeUnitId, $grants);

        $scopedGrantUnitIds = $user->getGrantScopedUnitIds($grants);

        return [
            'active_unit' => $activeUnit,
            'active_unit_id' => $activeUnitId,
            'available_units' => $available,
            'unit_selection_required' => $activeUnitId === null && ! empty($scopedGrantUnitIds),
            'permissions' => $capabilities['active'],
            'permissions_global' => $capabilities['global'],
            'permissions_all_scopes' => $capabilities['all'],
        ];
    }

    /**
     * Record the active unit in the session.
     */
    public function rememberActiveUnit(Request $request, int $unitId): void
    {
        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, $unitId);
        }
    }

    /**
     * Discard the recorded active unit.
     */
    public function forgetActiveUnit(Request $request): void
    {
        if ($request->hasSession()) {
            $request->session()->forget(self::SESSION_KEY);
        }
    }

    /**
     * The recorded active unit identifier, without validation.
     */
    protected function sessionUnitId(Request $request): ?int
    {
        if (! $request->hasSession()) {
            return null;
        }

        $value = $request->session()->get(self::SESSION_KEY);

        return $value !== null ? (int) $value : null;
    }
}
