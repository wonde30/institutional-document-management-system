<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your institutional account is not active (status: '.$user->status.'). Please contact an administrator.',
            ], 403);
        }

        return $next($request);
    }
}
