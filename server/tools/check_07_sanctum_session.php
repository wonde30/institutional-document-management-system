<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== CHECK 07: SANCTUM & SESSION CONFIGURATION AUDIT ===\n\n";

$issues = [];

// 1. Sanctum stateful domains
$stateful = config('sanctum.stateful', []);
echo "Sanctum stateful domains: " . implode(', ', (array) $stateful) . "\n";
if (empty($stateful)) {
    $issues[] = "[!] Sanctum stateful domains is empty.";
}

// 2. Session configuration
$lifetime = config('session.lifetime');
$secure = config('session.secure');
$httpOnly = config('session.http_only');
$sameSite = config('session.same_site');
$driver = config('session.driver');
echo "Session config: driver=$driver, lifetime={$lifetime}m, secure=" . var_export($secure, true) . ", http_only=" . var_export($httpOnly, true) . ", same_site=$sameSite\n";

if ($httpOnly !== true) {
    $issues[] = "[!] Session cookie http_only should be true!";
}
if ($sameSite === 'none' && !$secure) {
    $issues[] = "[!] Session same_site is 'none' but secure is false!";
}

// 3. CORS configuration
$corsPaths = config('cors.paths', []);
$corsOrigins = config('cors.allowed_origins', []);
$corsCredentials = config('cors.supports_credentials', false);
echo "CORS config: supports_credentials=" . var_export($corsCredentials, true) . ", allowed_origins=" . implode(', ', (array) $corsOrigins) . "\n";

if ($corsCredentials && in_array('*', (array) $corsOrigins, true)) {
    $issues[] = "[!] CRITICAL: CORS wildcard origin '*' is used with supports_credentials=true!";
}

// 4. EnsureAccountIsActive Middleware analysis
$middlewarePath = __DIR__ . '/../app/Http/Middleware/EnsureAccountIsActive.php';
if (file_exists($middlewarePath)) {
    $mwContent = file_get_contents($middlewarePath);
    echo "\nEnsureAccountIsActive middleware inspection:\n";
    if (str_contains($mwContent, "'active'") || str_contains($mwContent, "status !== 'active'")) {
        echo "  [+] Checks user active status.\n";
    } else {
        $issues[] = "[!] EnsureAccountIsActive does not properly check user active status!";
    }
}

// Check where EnsureAccountIsActive is applied
$bootstrapPath = __DIR__ . '/../bootstrap/app.php';
$bootstrapContent = file_get_contents($bootstrapPath);
echo "Middleware registration in bootstrap/app.php:\n";
if (str_contains($bootstrapContent, 'active_account') || str_contains($bootstrapContent, 'EnsureAccountIsActive')) {
    echo "  [+] EnsureAccountIsActive is registered.\n";
} else {
    $issues[] = "[!] EnsureAccountIsActive not registered in bootstrap/app.php!";
}

// 5. AuthController login inspection: throttling, session regenerate, status check
$authControllerPath = __DIR__ . '/../app/Http/Controllers/Api/V1/AuthController.php';
if (file_exists($authControllerPath)) {
    $acContent = file_get_contents($authControllerPath);
    echo "\nAuthController inspection:\n";
    if (str_contains($acContent, 'RateLimiter') || str_contains($acContent, 'throttle') || str_contains($acContent, 'tooManyAttempts')) {
        echo "  [+] Login throttling / RateLimiter is implemented.\n";
    } else {
        $issues[] = "[!] AuthController lacks login rate limiting / throttling!";
    }
    
    if (str_contains($acContent, 'regenerate')) {
        echo "  [+] Session regenerate on login is implemented.\n";
    } else {
        $issues[] = "[!] AuthController does not call \$request->session()->regenerate() on login!";
    }
    
    if (str_contains($acContent, 'invalidate') && str_contains($acContent, 'regenerateToken')) {
        echo "  [+] Session invalidate & regenerateToken on logout is implemented.\n";
    } else {
        $issues[] = "[!] AuthController logout does not invalidate session or regenerate token!";
    }
}

echo "\n--- SUMMARY OF SANCTUM / SESSION ISSUES ---\n";
if (empty($issues)) {
    echo "Sanctum and session security checks passed.\n";
} else {
    foreach ($issues as $iss) {
        echo "$iss\n";
    }
}
