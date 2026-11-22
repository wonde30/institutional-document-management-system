<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The organizational context is resolved once per request. The scoped
        // binding lets the resource, the controller and the navigation
        // catalog share one computed set of available units instead of
        // repeating the resolution for each of them.
        $this->app->scoped(\App\Services\Authorization\OrganizationalContextService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Legacy Transition Token Support: Route legacy 60-char bearer tokens to `sessions` table
        // while preserving first-party cookie SPA authentication without requiring a `personal_access_tokens` table.
        \Laravel\Sanctum\Sanctum::usePersonalAccessTokenModel(\App\Models\LegacyTransitionToken::class);

        // Defensible Institutional Rate Limiting: Normalized Account + Source IP
        RateLimiter::for('login', function (Request $request) {
            $email = (string) Str::transliterate(Str::lower($request->input('email', '')));
            return [
                Limit::perMinute(5)->by($email),
                Limit::perMinute(15)->by($request->ip()),
            ];
        });

        // Account recovery is a sensitive, unauthenticated surface. It is
        // limited per normalized account and per source address so that a
        // password reset cannot be used to flood institutional mailboxes.
        RateLimiter::for('password-reset', function (Request $request) {
            $email = (string) Str::transliterate(Str::lower($request->input('email', '')));
            return [
                Limit::perMinute(3)->by('email:'.$email),
                Limit::perMinute(10)->by('ip:'.$request->ip()),
            ];
        });

        // Account recovery links point at the single page application, which
        // completes the recovery against the API.
        \Illuminate\Auth\Notifications\ResetPassword::createUrlUsing(function ($notifiable, string $token): string {
            $url = rtrim((string) config('app.frontend_url'), '/')
                .'/reset-password?token='.$token
                .'&email='.urlencode($notifiable->getEmailForPasswordReset());

            return $url;
        });

        \Illuminate\Auth\Notifications\VerifyEmail::createUrlUsing(function ($notifiable): string {
            $url = rtrim((string) config('app.frontend_url'), '/')
                .'/verify-email?id='.urlencode($notifiable->getKey())
                .'&hash='.urlencode(sha1($notifiable->getEmailForVerification()));

            return $url;
        });

        // Platform Security Invariant & Super Admin Authorization
        Gate::before(function ($user, $ability) {
            if ($user && $user->status !== 'active') {
                return false;
            }

            // Super Admin holds full operational capabilities
            if ($user && method_exists($user, 'hasPermission') && $user->hasPermission('system.configure')) {
                return true;
            }

            return null;
        });
    }
}
