<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== CHECK 02: ROUTE PROTECTION AUDIT ===\n\n";

$router = app('router');
$routes = $router->getRoutes();

$publicUriPrefixes = [
    'sanctum/csrf-cookie',
    'api/v1/public',
    'api/v1/auth/login',
    'api/v1/auth/forgot-password',
    'api/v1/auth/reset-password',
    'up',
    '_debugbar',
];

$unprotected = [];
$total = 0;

foreach ($routes as $route) {
    $uri = $route->uri();
    $methods = implode('|', array_diff($route->methods(), ['HEAD']));
    $action = $route->getActionName();
    $middlewares = (array) $route->middleware();
    
    $total++;
    
    // Check if intentionally public
    $isPublic = false;
    foreach ($publicUriPrefixes as $prefix) {
        if ($uri === $prefix || str_starts_with($uri, $prefix . '/')) {
            $isPublic = true;
            break;
        }
    }
    
    $hasSanctum = in_array('auth:sanctum', $middlewares);
    $hasPermission = false;
    foreach ($middlewares as $mw) {
        if (str_starts_with($mw, 'permission:') || str_starts_with($mw, 'can:')) {
            $hasPermission = true;
            break;
        }
    }
    
    if (!$isPublic && (!$hasSanctum || !$hasPermission)) {
        $unprotected[] = [
            'methods' => $methods,
            'uri' => $uri,
            'action' => $action,
            'middlewares' => implode(', ', $middlewares),
            'missing_sanctum' => !$hasSanctum,
            'missing_permission' => !$hasPermission,
        ];
    }
}

echo "Total routes checked: $total\n";
echo "Flagged routes lacking auth:sanctum or permission check/policy: " . count($unprotected) . "\n\n";

foreach ($unprotected as $r) {
    echo sprintf("[%s] %-40s\n  Action: %s\n  Middlewares: [%s]\n  Missing: %s%s\n\n",
        $r['methods'],
        $r['uri'],
        $r['action'],
        $r['middlewares'],
        $r['missing_sanctum'] ? 'auth:sanctum ' : '',
        $r['missing_permission'] ? 'permission/can' : ''
    );
}
