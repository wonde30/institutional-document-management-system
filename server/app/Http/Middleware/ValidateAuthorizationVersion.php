<?php

namespace App\Http\Middleware;

use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ValidateAuthorizationVersion
{
    /**
     * Handle an incoming request and enforce immediate authorization revocation.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            // 1. Immediately block suspended, inactive, or locked accounts
            if ($user->status !== 'active') {
                AuditService::recordAuthEvent(
                    action: 'auth.account_suspended',
                    actorId: $user->id,
                    targetType: 'user',
                    targetId: (string) $user->id,
                    details: ['status' => $user->status],
                    request: $request
                );

                if ($request->hasSession()) {
                    Auth::guard('web')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Your institutional account is not active (status: '.$user->status.'). Please contact an administrator.',
                ], 403);
            }

            // 2. Enforce authorization version validation
            if ($request->hasSession()) {
                $sessionVersion = $request->session()->get('authz_version');

                // If session doesn't have an authz_version yet (e.g. established before check), initialize it
                if ($sessionVersion === null) {
                    $request->session()->put('authz_version', (int) $user->authz_version);
                } elseif ((int) $sessionVersion !== (int) $user->authz_version) {
                    // Authorization version mismatch: role revoked, transferred, or password changed remotely
                    AuditService::recordAuthEvent(
                        action: 'auth.authorization_version_changed',
                        actorId: $user->id,
                        targetType: 'user',
                        targetId: (string) $user->id,
                        details: [
                            'session_version' => (int) $sessionVersion,
                            'current_user_version' => (int) $user->authz_version,
                            'revocation_reason' => 'authorization_version_mismatch',
                        ],
                        request: $request
                    );

                    AuditService::recordAuthEvent(
                        action: 'auth.session_revoked',
                        actorId: $user->id,
                        targetType: 'user',
                        targetId: (string) $user->id,
                        details: ['reason' => 'forced_session_invalidation'],
                        request: $request
                    );

                    Auth::guard('web')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return response()->json([
                        'success' => false,
                        'message' => 'Your institutional authorization credentials or permissions have been updated. Please sign in again.',
                    ], 401);
                }
            }
        }

        return $next($request);
    }
}
