<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Route;

echo "=== STEP 1b: ROUTES WITHOUT auth:sanctum ===\n\n";

$routes = Route::getRoutes()->getRoutes();
$unauthRoutes = [];

foreach ($routes as $r) {
    $middleware = $r->gatherMiddleware();
    $hasSanctum = false;
    foreach ($middleware as $m) {
        if ($m === 'auth:sanctum' || str_contains($m, 'Authenticate:sanctum') || str_contains($m, 'auth:sanctum')) {
            $hasSanctum = true;
            break;
        }
    }
    if (! $hasSanctum) {
        $unauthRoutes[] = [
            'method' => implode('|', array_filter($r->methods(), fn($m) => $m !== 'HEAD')),
            'uri' => $r->uri(),
            'action' => $r->getActionName(),
            'middleware' => $middleware,
        ];
    }
}

$justifications = [
    'sanctum/csrf-cookie' => 'Official Laravel Sanctum CSRF initialization endpoint; required for SPA authentication bootstrap.',
    'up' => 'Laravel default framework uptime / load-balancer health check endpoint.',
    'api/v1/health' => 'Unauthenticated public liveness probe for monitoring systems and container orchestrators.',
    'api/v1/auth/login' => 'Public credential authentication endpoint (rate-limited via RateLimiter 5/60s).',
    'api/v1/auth/forgot-password' => 'Public password reset request initiation endpoint.',
    'api/v1/auth/reset-password' => 'Public signed token password reset completion endpoint.',
    'api/v1/auth/verify-email/{id}/{hash}' => 'Signed URL email verification link handler (validated via signature).',
    'api/v1/settings/public' => 'Public system branding, institution name, and maintenance status configuration.',
    'api/v1/public/landing/summary' => 'Public landing portal aggregate statistics for unauthenticated landing page.',
    'api/v1/public/landing/announcements' => 'Public university announcements bulletin for landing portal.',
    'api/v1/public/landing/announcements/{id}' => 'Public announcement details view for university community.',
    'api/v1/public/landing/faq' => 'Public frequently asked questions for university document portal.',
    'api/v1/public/landing/contact' => 'Public registrar and archive contact directory.',
    'api/v1/public/landing/verify-document' => 'Public document verification tool for external validation of issued credentials.',
];

printf("%-10s | %-45s | %-30s | %s\n", 'Method', 'URI', 'Middleware', 'Justification');
echo str_repeat('-', 140) . "\n";

foreach ($unauthRoutes as $ur) {
    $uri = $ur['uri'];
    $method = $ur['method'];
    $mw = implode(', ', $ur['middleware']);
    $just = $justifications[$uri] ?? 'Public endpoint';
    printf("%-10s | %-45s | %-30s | %s\n", $method, $uri, substr($mw, 0, 30), $just);
}

echo "\nTotal routes without auth:sanctum: " . count($unauthRoutes) . "\n\n";

echo "=== DOWNLOAD & STORAGE ACCESS PROOF ===\n";

use Illuminate\Http\Request;

// 1. Direct storage route access (MUST be 404 - route does not exist)
$requestDirectStorage = Request::create('/storage/test.pdf', 'GET');
try {
    $response = $app->handle($requestDirectStorage);
    echo "1. Direct anonymous GET /storage/test.pdf status: " . $response->getStatusCode() . " (Expected: 404)\n";
} catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
    echo "1. Direct anonymous GET /storage/test.pdf: 404 NotFoundHttpException (Route removed)\n";
}

// 2. Anonymous file download attempt (MUST be 401 Unauthorized)
$requestAnonDownload = Request::create('/api/v1/files/1/download', 'GET');
$requestAnonDownload->headers->set('Accept', 'application/json');
$responseAnon = $app->handle($requestAnonDownload);
echo "2. Anonymous GET /api/v1/files/1/download status: " . $responseAnon->getStatusCode() . " (Expected: 401)\n";

// 3. Authenticated authorized file download
$user = \App\Models\User::where('email', 'admin@wu.edu.et')->first();
$fileObject = \App\Models\FileObject::first();

if ($user && $fileObject) {
    // Authenticate user in session
    auth()->login($user);
    $requestAuthDownload = Request::create("/api/v1/files/{$fileObject->id}/download", 'GET');
    $requestAuthDownload->headers->set('Accept', 'application/json');
    $requestAuthDownload->setUserResolver(fn() => $user);
    $responseAuth = $app->handle($requestAuthDownload);
    echo "3. Authenticated GET /api/v1/files/{$fileObject->id}/download status: " . $responseAuth->getStatusCode() . " (Expected: 200 or binary response)\n";
} else {
    echo "3. Note: Admin or FileObject record not found in database to execute authenticated test.\n";
}

