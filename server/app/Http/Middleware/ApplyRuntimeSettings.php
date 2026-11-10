<?php

namespace App\Http\Middleware;

use App\Services\Settings\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the runtime configuration to the request before the session starts.
 *
 * The session idle lifetime is read from the configuration catalogue so that
 * an administrator can change it without a deployment. The value is resolved
 * from the cache, which is invalidated whenever the setting changes.
 */
class ApplyRuntimeSettings
{
    public function __construct(
        protected SettingsService $settings,
    ) {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $lifetime = $this->settings->value('session.lifetime_minutes');

        if (is_numeric($lifetime) && (int) $lifetime > 0) {
            config(['session.lifetime' => (int) $lifetime]);
        }

        return $next($request);
    }
}
