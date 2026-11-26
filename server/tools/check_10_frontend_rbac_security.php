<?php

echo "=== CHECK 10: FRONTEND RBAC & SECURITY AUDIT ===\n\n";

$clientDir = realpath(__DIR__ . '/../../client/src');
if (!$clientDir || !is_dir($clientDir)) {
    die("client/src directory not found!\n");
}

$issues = [];

// 1. Inspect router/index.ts
$routerPath = $clientDir . '/router/index.ts';
if (file_exists($routerPath)) {
    $routerContent = file_get_contents($routerPath);
    echo "Router file: $routerPath\n";
    
    // Check for open redirect on returnUrl / redirect
    if (preg_match('/(?:redirect|returnUrl|next)\s*=\s*([^;\n]+)/i', $routerContent, $m)) {
        echo "  [+] Redirect param handling: {$m[0]}\n";
    }
    
    // Check if router guard sends 401 to login and 403 to forbidden
    if (str_contains($routerContent, 'forbidden') && str_contains($routerContent, 'login')) {
        echo "  [+] Router differentiates between forbidden (403) and login (401).\n";
    } else {
        $issues[] = "[!] Router navigation guard may not properly distinguish 401 vs 403 route handling.";
    }
}

// 2. Inspect axios / api client
$apiFiles = glob($clientDir . '/services/*.ts');
$apiFiles = array_merge($apiFiles, glob($clientDir . '/api/*.ts'));
$apiFiles = array_merge($apiFiles, [$clientDir . '/plugins/axios.ts', $clientDir . '/lib/axios.ts', $clientDir . '/http.ts']);

$foundAxios = false;
foreach ($apiFiles as $af) {
    if (file_exists($af)) {
        $content = file_get_contents($af);
        if (str_contains($content, 'interceptors.response')) {
            $foundAxios = true;
            echo "Axios interceptor found in $af:\n";
            if (str_contains($content, '401') && str_contains($content, '403')) {
                echo "  [+] 401 and 403 status codes explicitly handled.\n";
            } else {
                $issues[] = "[!] Axios response interceptor in $af does not handle both 401 and 403!";
            }
            if (str_contains($content, 'location.href') && !str_contains($content, 'startsWith(\'/\')')) {
                $issues[] = "[!] Possible open redirect via unvalidated return URL in $af!";
            }
        }
    }
}

// 3. Inspect localStorage usage across client/src
$allFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($clientDir));
$localStorageUsages = [];
$consoleLogUsages = [];

foreach ($allFiles as $file) {
    if ($file->isFile() && in_array($file->getExtension(), ['ts', 'vue', 'js'])) {
        $content = file_get_contents($file->getRealPath());
        $rel = str_replace($clientDir . DIRECTORY_SEPARATOR, '', $file->getRealPath());
        
        // Check localStorage
        if (preg_match_all('/localStorage\.(setItem|getItem)\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $m)) {
            foreach ($m[2] as $key) {
                $localStorageUsages[$key][] = $rel;
                if (in_array(strtolower($key), ['token', 'auth_token', 'password', 'secret', 'jwt', 'credentials'])) {
                    $issues[] = "[!] Sensitive key '$key' stored in localStorage in $rel! (Violates cookie-only Sanctum policy)";
                }
            }
        }
        
        // Check console.log of data
        if (preg_match_all('/console\.(log|debug|info)\s*\(\s*([^)]+)\)/', $content, $m)) {
            foreach ($m[0] as $logCall) {
                if (preg_match('/token|password|data|response|user|payload/i', $logCall)) {
                    $consoleLogUsages[] = "$rel: " . trim($logCall);
                }
            }
        }
    }
}

echo "\n--- LOCALSTORAGE USAGE ---\n";
foreach ($localStorageUsages as $k => $locs) {
    echo "  Key '$k' used in: " . implode(', ', array_unique($locs)) . "\n";
}

echo "\n--- CONSOLE.LOG OF DATA (" . count($consoleLogUsages) . " occurrences) ---\n";
foreach (array_slice($consoleLogUsages, 0, 10) as $clu) {
    echo "  $clu\n";
}
if (count($consoleLogUsages) > 10) {
    echo "  ... and " . (count($consoleLogUsages) - 10) . " more.\n";
}

echo "\n--- SUMMARY OF FRONTEND ISSUES ---\n";
if (empty($issues)) {
    echo "No high-severity frontend RBAC/storage defects detected.\n";
} else {
    foreach ($issues as $iss) {
        echo "$iss\n";
    }
}
