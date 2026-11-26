<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== CHECK 11: PUBLIC LANDING ENDPOINTS READ-ONLY AUDIT ===\n\n";

$router = app('router');
$publicLandingRoutes = [];

foreach ($router->getRoutes() as $r) {
    if (str_contains($r->uri(), 'public') || str_contains($r->getActionName(), 'PublicLandingController') || str_contains($r->getActionName(), 'Landing')) {
        $publicLandingRoutes[] = [
            'methods' => implode('|', array_diff($r->methods(), ['HEAD'])),
            'uri' => $r->uri(),
            'action' => $r->getActionName(),
            'middleware' => implode(', ', (array) $r->middleware()),
        ];
    }
}

echo "Public Landing Routes found:\n";
foreach ($publicLandingRoutes as $plr) {
    echo "  [{$plr['methods']}] {$plr['uri']}\n      Action: {$plr['action']}\n      Middleware: [{$plr['middleware']}]\n";
}

// Inspect PublicLandingController code (read-only)
$plcPath = __DIR__ . '/../app/Http/Controllers/Api/V1/PublicLandingController.php';
if (file_exists($plcPath)) {
    $plcContent = file_get_contents($plcPath);
    echo "\nPublicLandingController inspection:\n";
    
    // Check if it exposes internal models, user details, confidential counts, etc.
    if (str_contains($plcContent, 'password') || str_contains($plcContent, 'email') || str_contains($plcContent, 'User::') || str_contains($plcContent, 'confidential')) {
        echo "  [!] Notice: Controller references user/confidential models. Checking exposure...\n";
    }
    
    // Check for rate limiting
    $hasRateLimit = false;
    foreach ($publicLandingRoutes as $plr) {
        if (str_contains($plr['middleware'], 'throttle')) {
            $hasRateLimit = true;
        }
    }
    if ($hasRateLimit) {
        echo "  [+] Routes have throttle middleware.\n";
    } else {
        echo "  [!] Public landing routes do not explicitly show throttle middleware.\n";
    }
} else {
    echo "PublicLandingController not found at $plcPath\n";
}
