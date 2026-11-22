<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Permission;

echo "=== CHECK 01: PERMISSION CODES CROSS-CHECK ===\n\n";

$seededPermissions = Permission::orderBy('code')->pluck('code')->toArray();
$seededSet = array_flip($seededPermissions);
echo "Seeded permissions in DB: " . count($seededPermissions) . "\n";

// 1. Scan routes
$routePermissions = [];
$router = app('router');
foreach ($router->getRoutes() as $route) {
    $middlewares = (array) $route->middleware();
    foreach ($middlewares as $mw) {
        if (str_starts_with($mw, 'permission:')) {
            $codeStr = substr($mw, strlen('permission:'));
            foreach (explode('|', $codeStr) as $c) {
                $c = trim($c);
                if ($c !== '') {
                    $routePermissions[$c][] = 'Route: ' . implode('|', $route->methods()) . ' ' . $route->uri();
                }
            }
        }
    }
}

// 2. Scan server app/
$serverPermissions = [];
$serverFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../app'));
foreach ($serverFiles as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getRealPath());
        $relPath = str_replace(realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR, '', $file->getRealPath());
        // Patterns for permission checking
        // hasPermission('code'), hasPermissionTo('code'), can('code'), permission:code
        if (preg_match_all("/(?:hasPermission|hasPermissionTo|can|authorize)\(\s*['\"]([a-zA-Z0-9_\-.:]+)['\"]/i", $content, $m)) {
            foreach ($m[1] as $c) {
                $serverPermissions[$c][] = $relPath;
            }
        }
        if (preg_match_all("/['\"]permission:([a-zA-Z0-9_\-.:|]+)['\"]/", $content, $m)) {
            foreach ($m[1] as $group) {
                foreach (explode('|', $group) as $c) {
                    $serverPermissions[$c][] = $relPath;
                }
            }
        }
    }
}

// 3. Scan client src/
$clientPermissions = [];
$clientSrcDir = realpath(__DIR__ . '/../../client/src');
if ($clientSrcDir && is_dir($clientSrcDir)) {
    $clientFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($clientSrcDir));
    foreach ($clientFiles as $file) {
        if ($file->isFile() && in_array($file->getExtension(), ['ts', 'vue', 'js'])) {
            $content = file_get_contents($file->getRealPath());
            $relPath = 'client/src/' . str_replace($clientSrcDir . DIRECTORY_SEPARATOR, '', $file->getRealPath());
            
            // match permission: 'code', permission: "code", permissions: ['code']
            if (preg_match_all("/(?:permission|requiredPermission|hasPermission)\s*(?::|\()\s*['\"]([a-zA-Z0-9_\-.:]+)['\"]/i", $content, $m)) {
                foreach ($m[1] as $c) {
                    $clientPermissions[$c][] = $relPath;
                }
            }
            if (preg_match_all("/v-permission=\"['\"]([a-zA-Z0-9_\-.:]+)['\"]/", $content, $m)) {
                foreach ($m[1] as $c) {
                    $clientPermissions[$c][] = $relPath;
                }
            }
            // match permissions: ['code1', 'code2']
            if (preg_match_all("/permissions:\s*\[([^\]]+)\]/", $content, $m)) {
                foreach ($m[1] as $arr) {
                    if (preg_match_all("/['\"]([a-zA-Z0-9_\-.:]+)['\"]/", $arr, $sub)) {
                        foreach ($sub[1] as $c) {
                            $clientPermissions[$c][] = $relPath;
                        }
                    }
                }
            }
        }
    }
}

echo "\n--- [A] PERMISSIONS IN SERVER ROUTES ---\n";
echo "Total distinct in routes: " . count($routePermissions) . "\n";
$routeMissing = [];
foreach ($routePermissions as $code => $usages) {
    if (!isset($seededSet[$code])) {
        $routeMissing[$code] = $usages;
    }
}
if (!empty($routeMissing)) {
    echo "[!] ROUTE PERMISSIONS NOT SEEDED IN DB:\n";
    foreach ($routeMissing as $c => $usages) {
        echo "  - $c (used in " . implode(', ', array_unique($usages)) . ")\n";
    }
} else {
    echo "All route permissions are present in seeded permissions.\n";
}

echo "\n--- [B] PERMISSIONS IN SERVER APP (Controllers/Policies/Services) ---\n";
$serverMissing = [];
foreach ($serverPermissions as $code => $usages) {
    if (!isset($seededSet[$code])) {
        // filter out common non-permission can() arguments if any like standard policy abilities
        $serverMissing[$code] = array_unique($usages);
    }
}
if (!empty($serverMissing)) {
    echo "[!] SERVER CODE PERMISSIONS NOT SEEDED IN DB (or Policy Abilities):\n";
    foreach ($serverMissing as $c => $usages) {
        echo "  - $c in: " . implode(', ', $usages) . "\n";
    }
} else {
    echo "All server permissions match seeded DB permissions.\n";
}

echo "\n--- [C] PERMISSIONS IN CLIENT SRC ---\n";
$clientMissing = [];
foreach ($clientPermissions as $code => $usages) {
    if (!isset($seededSet[$code])) {
        $clientMissing[$code] = array_unique($usages);
    }
}
if (!empty($clientMissing)) {
    echo "[!] CLIENT PERMISSIONS NOT SEEDED IN DB:\n";
    foreach ($clientMissing as $c => $usages) {
        echo "  - $c in: " . implode(', ', $usages) . "\n";
    }
} else {
    echo "All client permissions match seeded DB permissions.\n";
}

echo "\n--- [D] SEEDED BUT UNUSED ANYWHERE ---\n";
$unused = [];
foreach ($seededPermissions as $p) {
    $inRoutes = isset($routePermissions[$p]);
    $inServer = isset($serverPermissions[$p]);
    $inClient = isset($clientPermissions[$p]);
    if (!$inRoutes && !$inServer && !$inClient) {
        $unused[] = $p;
    }
}
echo "Seeded permissions not directly referenced: " . count($unused) . "\n";
foreach ($unused as $u) {
    echo "  - $u\n";
}

echo "\n--- [E] SERVER / CLIENT SPELLING MISMATCH ANALYSIS ---\n";
// Compare all referenced codes
$allReferenced = array_unique(array_merge(array_keys($routePermissions), array_keys($serverPermissions), array_keys($clientPermissions)));
foreach ($allReferenced as $ref) {
    if (!isset($seededSet[$ref])) {
        // find closest match in seededSet
        $closest = null;
        $minDist = 99;
        foreach ($seededPermissions as $sp) {
            $dist = levenshtein($ref, $sp);
            if ($dist < $minDist) {
                $minDist = $dist;
                $closest = $sp;
            }
        }
        if ($minDist <= 4) {
            echo "Possible typo: '$ref' might be intended as '$closest' (distance $minDist)\n";
        }
    }
}
