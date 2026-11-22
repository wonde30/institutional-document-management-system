<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Authorization\OrganizationalContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Authenticate user via Sanctum stateful session cookies.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $normalizedEmail = strtolower(trim($validated['email']));
        $throttleKey = Str::transliterate($normalizedEmail.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = max(1, RateLimiter::availableIn($throttleKey));

            AuditService::recordAuthEvent(
                action: 'auth.login_throttled',
                actorId: null,
                targetType: 'user',
                targetId: 'unknown',
                details: [
                    'attempted_email' => $normalizedEmail,
                    'available_in_seconds' => $seconds,
                ],
                request: $request
            );

            return response()->json([
                'success' => false,
                'message' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ], 429, [
                'Retry-After' => (string) $seconds,
            ]);
        }

        $user = User::where('email', $normalizedEmail)->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            AuditService::recordAuthEvent(
                action: 'auth.login_failed',
                actorId: $user?->id,
                targetType: 'user',
                targetId: $user ? (string) $user->id : 'unknown',
                details: [
                    'attempted_email' => $normalizedEmail,
                    'reason' => 'invalid_credentials',
                ],
                request: $request
            );

            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our institutional records.'],
            ]);
        }

        RateLimiter::clear($throttleKey);

        if ($user->status !== 'active') {
            AuditService::recordAuthEvent(
                action: 'auth.account_suspended',
                actorId: $user->id,
                targetType: 'user',
                targetId: (string) $user->id,
                details: ['status' => $user->status],
                request: $request
            );

            return response()->json([
                'success' => false,
                'message' => "Your institutional account is not active (status: {$user->status}). Please contact an administrator.",
            ], 403);
        }

        // 1. Stateful SPA cookie session authentication
        Auth::guard('web')->login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
            // Bind current authorization version to active session
            $request->session()->put('authz_version', (int) $user->authz_version);
        }

        // 2. Record successful authentication audit event
        AuditService::recordAuthEvent(
            action: 'auth.login_success',
            actorId: $user->id,
            targetType: 'user',
            targetId: (string) $user->id,
            details: ['authz_version' => (int) $user->authz_version],
            request: $request
        );

        $user->load([
            'primaryMembershipRecord.membership.unit',
            'userRoles.role.permissions',
            'userRoles.scope.unit',
            'userPermissions.permission',
            'userPermissions.scope',
        ]);

        return response()->json([
            'success' => true,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Get current authenticated user profile and roles.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load([
            'primaryMembershipRecord.membership.unit',
            'userRoles.role.permissions',
            'userRoles.scope.unit',
            'userPermissions.permission',
            'userPermissions.scope',
        ]);

        return response()->json([
            'success' => true,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Terminate current session.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            AuditService::recordAuthEvent(
                action: 'auth.logout',
                actorId: $user->id,
                targetType: 'user',
                targetId: (string) $user->id,
                details: ['session_id' => $request->hasSession() ? $request->session()->getId() : 'stateless'],
                request: $request
            );
        }

        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }

        $logoutAll = $request->boolean('all_devices', false);

        if ($logoutAll && $user) {
            \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $user->id)->delete();
        } elseif ($request->hasSession()) {
            $currentSessionId = $request->session()->getId();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($currentSessionId) {
                \Illuminate\Support\Facades\DB::table('sessions')->where('id', $currentSessionId)->delete();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out.',
        ]);
    }

    /**
     * Switch the active organizational unit.
     *
     * The requested unit is validated against the units the server has
     * determined the caller may select. A client supplied unit identifier is
     * never treated as proof of authorization: an unknown or unavailable unit
     * is rejected.
     */
    public function setActiveUnit(
        Request $request,
        OrganizationalContextService $contextService
    ): JsonResponse {
        $validated = $request->validate([
            'unit_id' => 'required|integer',
        ]);

        $user = $request->user();

        $unitId = $contextService->validateUnitSelection($user, (int) $validated['unit_id']);

        if ($unitId === null) {
            AuditService::recordAuthEvent(
                action: 'auth.active_unit_rejected',
                actorId: $user->id,
                targetType: 'organization_unit',
                targetId: (string) $validated['unit_id'],
                details: ['reason' => 'unit_not_available'],
                request: $request
            );

            return response()->json([
                'success' => false,
                'message' => 'Forbidden: You are not authorized to operate in the requested organizational unit.',
            ], 403);
        }

        $contextService->rememberActiveUnit($request, $unitId);

        AuditService::recordAuthEvent(
            action: 'auth.active_unit_changed',
            actorId: $user->id,
            targetType: 'organization_unit',
            targetId: (string) $unitId,
            details: [],
            request: $request
        );

        return response()->json([
            'success' => true,
            'message' => 'Active organizational unit updated.',
            'context' => $contextService->buildContext($user, $request),
        ]);
    }

    /**
     * Change current user password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|different:current_password',
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided current password is incorrect.'],
            ]);
        }

        $newAuthzVersion = $user->authz_version + 1;
        $user->update([
            'password' => Hash::make($validated['new_password']),
            'authz_version' => $newAuthzVersion,
        ]);

        // Retain the current device's session by syncing its authorization version
        if ($request->hasSession()) {
            $request->session()->put('authz_version', $newAuthzVersion);
        }

        AuditService::recordAuthEvent(
            action: 'auth.password_changed',
            actorId: $user->id,
            targetType: 'user',
            targetId: (string) $user->id,
            details: ['new_authz_version' => $newAuthzVersion],
            request: $request
        );

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully.',
        ]);
    }

    /**
     * Issue an account recovery link.
     *
     * The response is deliberately identical for a known and an unknown
     * address so that the endpoint cannot be used to enumerate institutional
     * accounts. The delivery attempt itself is audited.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:254',
        ]);

        $normalizedEmail = strtolower(trim($validated['email']));
        $user = User::where('email', $normalizedEmail)->first();

        if ($user && $user->status === 'active') {
            $status = Password::sendResetLink(['email' => $normalizedEmail]);

            AuditService::recordAuthEvent(
                action: 'auth.password_reset_requested',
                actorId: $user->id,
                targetType: 'user',
                targetId: (string) $user->id,
                details: ['delivery_status' => $status],
                request: $request
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'If the address belongs to an active institutional account, recovery instructions have been dispatched.',
        ], 202);
    }

    /**
     * Complete an account recovery.
     *
     * A successful reset invalidates every other session and raises the
     * authorization version, so a password recovered after a suspected
     * compromise cannot be used from a session established before it.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email|max:254',
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $normalizedEmail = strtolower(trim($validated['email']));

        $status = Password::reset(
            [
                'email' => $normalizedEmail,
                'password' => $validated['password'],
                'password_confirmation' => $validated['password_confirmation'] ?? $validated['password'],
                'token' => $validated['token'],
            ],
            function (User $user, string $password) {
                $newAuthzVersion = $user->authz_version + 1;

                $user->forceFill([
                    'password' => Hash::make($password),
                    'authz_version' => $newAuthzVersion,
                    'remember_token' => \Illuminate\Support\Str::random(60),
                ])->save();

                // Terminate every session established with the previous credential.
                \Illuminate\Support\Facades\DB::table('sessions')
                    ->where('user_id', $user->id)
                    ->delete();

                AuditService::recordAuthEvent(
                    action: 'auth.password_reset_completed',
                    actorId: $user->id,
                    targetType: 'user',
                    targetId: (string) $user->id,
                    details: ['new_authz_version' => $newAuthzVersion],
                );
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            AuditService::recordAuthEvent(
                action: 'auth.password_reset_rejected',
                actorId: null,
                targetType: 'user',
                targetId: 'unknown',
                details: ['status' => $status, 'attempted_email' => $normalizedEmail],
                request: $request
            );

            throw ValidationException::withMessages([
                'email' => ['The recovery link is invalid or has expired. Please request a new one.'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Your password has been reset. You may now sign in with the new credential.',
        ]);
    }

    /**
     * Dispatch an address verification notice to the authenticated account.
     */
    public function sendVerificationEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'This address has already been verified.',
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'message' => 'A verification link has been dispatched to your institutional address.',
        ], 202);
    }

    /**
     * Confirm an institutional address.
     *
     * The confirmation link carries the user identifier and a hash of the
     * address that was verified at the moment the notice was generated. A
     * changed address invalidates the link.
     */
    public function verifyEmail(Request $request, int $id, string $hash): JsonResponse
    {
        $user = User::find($id);

        if (! $user || ! hash_equals((string) $user->getKey(), (string) $id)) {
            return response()->json([
                'success' => false,
                'message' => 'The verification link is invalid.',
            ], 404);
        }

        if (! hash_equals((string) sha1($user->getEmailForVerification()), $hash)) {
            return response()->json([
                'success' => false,
                'message' => 'The verification link is invalid.',
            ], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'This address has already been verified.',
            ]);
        }

        $user->markEmailAsVerified();

        AuditService::recordAuthEvent(
            action: 'auth.email_verified',
            actorId: $user->id,
            targetType: 'user',
            targetId: (string) $user->id,
            details: [],
            request: $request
        );

        return response()->json([
            'success' => true,
            'message' => 'Your institutional address has been verified.',
        ]);
    }
}
