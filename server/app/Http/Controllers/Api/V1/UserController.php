<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\OrganizationMembership;
use App\Models\PrimaryMembership;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * List users.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with([
            'primaryMembershipRecord.membership.unit',
            'userRoles.role',
            'userRoles.scope.unit',
            'userPermissions.permission',
            'userPermissions.scope.unit',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('unit_id')) {
            $unitId = $request->query('unit_id');
            $query->whereHas('memberships', fn ($q) => $q->where('unit_id', $unitId)->whereNull('ends_at'));
        }

        if ($request->filled('role')) {
            $roleParam = $request->query('role');
            $query->whereHas('userRoles.role', function ($q) use ($roleParam) {
                if (is_numeric($roleParam)) {
                    $q->where('id', (int) $roleParam);
                } else {
                    $q->where('code', $roleParam);
                }
            });
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 25)));
        $users = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => UserResource::collection($users),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ],
        ]);
    }

    /**
     * Store new user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:160',
            'email' => 'required|email|max:254|unique:users,email',
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'locked'])],
            'unit_id' => 'nullable|exists:organization_units,id',
            'position_title' => 'nullable|string|max:160',
        ]);

        $actor = $request->user();
        if (! empty($validated['unit_id']) && $actor && ! $actor->hasPermission('system.configure') && ! $actor->hasPermission('users.create', (int) $validated['unit_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have users.create authority for the target organizational unit.',
            ], 403);
        }

        $now = now()->format('Y-m-d H:i:s.u');

        $user = DB::transaction(function () use ($validated, $actor, $now) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'status' => $validated['status'] ?? 'active',
                'authz_version' => 1,
            ]);

            if (! empty($validated['unit_id'])) {
                $membership = OrganizationMembership::create([
                    'user_id' => $user->id,
                    'unit_id' => $validated['unit_id'],
                    'position_title' => $validated['position_title'] ?? null,
                    'starts_at' => $now,
                    'ends_at' => null,
                    'created_by' => $actor->id,
                    'created_at' => $now,
                ]);

                PrimaryMembership::create([
                    'user_id' => $user->id,
                    'membership_id' => $membership->id,
                    'selected_at' => $now,
                ]);
            }

            return $user;
        });

        $user->load(['primaryMembershipRecord.membership.unit', 'userRoles.role']);

        return response()->json([
            'success' => true,
            'message' => 'User account created successfully.',
            'data' => new UserResource($user),
        ], 201);
    }

    /**
     * Show single user.
     */
    public function show(int $id): JsonResponse
    {
        $user = User::with([
            'memberships.unit',
            'primaryMembershipRecord.membership.unit',
            'userRoles.role.permissions',
            'userRoles.scope.unit',
            'userPermissions.permission',
            'staffProfile',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Update user.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $actor = $request->user();

        $targetIsAdmin = $user->hasPermission('system.configure')
            || $user->userRoles()->whereHas('role', fn ($q) => $q->where('code', 'super_admin'))->exists();

        if ($targetIsAdmin && $actor && ! $actor->hasPermission('system.configure')) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Only an authorized System Administrator can modify a privileged administrator account.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:160',
            'email' => ['sometimes', 'required', 'email', 'max:254', Rule::unique('users', 'email')->ignore($user->id)],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive', 'locked'])],
            'password' => ['nullable', 'string', Password::min(8)->letters()->numbers()],
        ]);

        if (isset($validated['status']) && $validated['status'] !== $user->status) {
            if ($actor && ! $actor->hasPermission('system.configure') && ! $actor->hasPermission('users.deactivate')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: Changing user account status requires users.deactivate permission.',
                ], 403);
            }

            if ($actor && $user->id === $actor->id && in_array($validated['status'], ['inactive', 'locked'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Security restriction: You cannot suspend or lock your own active session account.',
                ], 422);
            }
        }

        if (isset($validated['email']) && strtolower($validated['email']) !== strtolower($user->email)) {
            $validated['email_verified_at'] = null;
        }

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
            $validated['authz_version'] = $user->authz_version + 1;
        } else {
            unset($validated['password']);
        }

        if (isset($validated['status']) && $validated['status'] !== $user->status) {
            if (in_array($validated['status'], ['inactive', 'locked'], true)) {
                if ($user->hasPermission('system.configure') && User::countActiveSystemAdministrators($user->id) === 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Security restriction: Cannot suspend or lock the last active System Administrator in the institution.',
                    ], 422);
                }
            }

            $validated['authz_version'] = ($validated['authz_version'] ?? $user->authz_version) + 1;
            if (in_array($validated['status'], ['inactive', 'locked'], true)) {
                AuditService::recordAuthEvent(
                    action: 'auth.account_suspended',
                    actorId: $actor?->id,
                    targetType: 'user',
                    targetId: (string) $user->id,
                    details: ['status' => $validated['status']],
                    request: $request
                );
            }
        }

        $user->update($validated);
        $user->load(['primaryMembershipRecord.membership.unit', 'userRoles.role']);

        return response()->json([
            'success' => true,
            'message' => 'User account updated successfully.',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Assign unit membership to user.
     */
    public function assignMembership(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'unit_id' => 'required|exists:organization_units,id',
            'position_title' => 'nullable|string|max:160',
            'is_primary' => 'nullable|boolean',
        ]);

        $actor = $request->user();
        $targetIsAdmin = $user->hasPermission('system.configure')
            || $user->userRoles()->whereHas('role', fn ($q) => $q->where('code', 'super_admin'))->exists();

        if ($targetIsAdmin && $actor && ! $actor->hasPermission('system.configure')) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Only an authorized System Administrator can modify memberships of a privileged administrator account.',
            ], 403);
        }

        if ($actor && ! $actor->hasPermission('system.configure') && ! $actor->hasPermission('users.update', (int) $validated['unit_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have users.update authority for the target organizational unit.',
            ], 403);
        }

        $now = now()->format('Y-m-d H:i:s.u');

        $membership = DB::transaction(function () use ($user, $validated, $actor, $now) {
            $membership = OrganizationMembership::create([
                'user_id' => $user->id,
                'unit_id' => $validated['unit_id'],
                'position_title' => $validated['position_title'] ?? null,
                'starts_at' => $now,
                'ends_at' => null,
                'created_by' => $actor->id,
                'created_at' => $now,
            ]);

            if (! empty($validated['is_primary'])) {
                PrimaryMembership::updateOrInsert(
                    ['user_id' => $user->id],
                    ['membership_id' => $membership->id, 'selected_at' => $now]
                );
            }

            // Invalidate existing sessions for transferred staff
            $user->increment('authz_version');

            return $membership;
        });

        AuditService::recordAuthEvent(
            action: 'auth.authorization_version_changed',
            actorId: $actor->id,
            targetType: 'user',
            targetId: (string) $user->id,
            details: [
                'reason' => 'unit_membership_update',
                'unit_id' => $validated['unit_id'],
                'is_primary' => ! empty($validated['is_primary']),
            ],
            request: $request
        );

        return response()->json([
            'success' => true,
            'message' => 'Unit membership assigned successfully.',
            'data' => $membership->load('unit'),
        ], 201);
    }

    /**
     * Update user account status (active, inactive, locked) with audit trail.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        $targetUser = User::findOrFail($id);

        $targetIsAdmin = $targetUser->hasPermission('system.configure')
            || $targetUser->userRoles()->whereHas('role', fn ($q) => $q->where('code', 'super_admin'))->exists();

        if ($targetIsAdmin && $actor && ! $actor->hasPermission('system.configure')) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Only an authorized System Administrator can change the status of a privileged administrator account.',
            ], 403);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive', 'locked'])],
        ]);

        if ($actor->id === $targetUser->id && in_array($validated['status'], ['inactive', 'locked'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Security restriction: Administrators cannot suspend or lock their own authenticated account.',
            ], 422);
        }

        if (in_array($validated['status'], ['inactive', 'locked'], true)) {
            if ($targetUser->hasPermission('system.configure') && User::countActiveSystemAdministrators($targetUser->id) === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Security restriction: Cannot suspend or lock the last active System Administrator in the institution.',
                ], 422);
            }
        }

        $oldStatus = $targetUser->status;
        $newStatus = $validated['status'];

        $action = in_array($newStatus, ['inactive', 'locked'], true)
            ? 'auth.account_suspended'
            : 'auth.account_activated';

        DB::transaction(function () use ($targetUser, $newStatus, $oldStatus, $action, $actor, $request) {
            $targetUser->update([
                'status' => $newStatus,
                'authz_version' => $targetUser->authz_version + 1,
            ]);

            AuditService::recordAuthEvent(
                action: $action,
                actorId: $actor->id,
                targetType: 'user',
                targetId: (string) $targetUser->id,
                details: [
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ],
                request: $request
            );
        });

        $targetUser->load([
            'primaryMembershipRecord.membership.unit',
            'userRoles.role',
            'userRoles.scope.unit',
            'userPermissions.permission',
            'userPermissions.scope.unit',
        ]);

        return response()->json([
            'success' => true,
            'message' => "User account successfully updated to {$newStatus}.",
            'data' => new UserResource($targetUser),
        ]);
    }

    /**
     * Inspect effective permissions and full lineage for a specific user.
     */
    public function effectivePermissions(Request $request, int $id): JsonResponse
    {
        $targetUser = User::findOrFail($id);
        $unitId = $request->filled('unit_id') ? (int) $request->query('unit_id') : null;

        $lineageData = $targetUser->getPermissionLineage($unitId);

        return response()->json([
            'success' => true,
            'data' => $lineageData,
        ]);
    }
}
