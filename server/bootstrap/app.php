<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        // The runtime configuration is applied before the session starts, so a
        // changed session lifetime takes effect on the next request.
        $middleware->append(\App\Http\Middleware\ApplyRuntimeSettings::class);

        $middleware->alias([
            'active_account' => \App\Http\Middleware\EnsureAccountIsActive::class,
            'authz_version' => \App\Http\Middleware\ValidateAuthorizationVersion::class,
            'permission' => \App\Http\Middleware\CheckBaselinePermission::class,
        ]);

        $middleware->priority([
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Illuminate\Auth\Middleware\Authenticate::class,
            \App\Http\Middleware\ValidateAuthorizationVersion::class,
            \App\Http\Middleware\EnsureAccountIsActive::class,
            \App\Http\Middleware\CheckBaselinePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Consistent API error envelope. The framework renders a validation
        // failure as {"message": ..., "errors": {...}}; the documented contract
        // additionally carries the "success" discriminator so that a client can
        // distinguish a failure without inspecting the status code. The same
        // envelope is applied to the HTTP exceptions raised by the controllers.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], $e->status);
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'The request could not be completed.',
            ], $e->getStatusCode());
        });
    })->create();
