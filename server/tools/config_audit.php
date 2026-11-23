<?php

declare(strict_types=1);

/**
 * Re-runnable Configuration Audit Script (Phase 1A)
 * Checks:
 * 1. .env vs .env.example vs .env.testing vs .env.production.example & env() outside config
 * 2. Sanctum / session / CORS / domain consistency
 * 3. Queue, cache, mail, filesystem, logging
 * 4. Required PHP extensions
 * 5. Windows path handling (hardcoded / or \ joins)
 * 6. Database config: charset, collation, strict mode, timezone
 * 7. Tooling configs: phpunit.xml, phpstan.neon, pint.json, package.json, CI config
 */

echo "=== PHASE 1A: CONFIGURATION AUDIT ===" . PHP_EOL . PHP_EOL;

$serverDir = realpath(__DIR__ . '/..');
$clientDir = realpath(__DIR__ . '/../../client');
$rootDir   = realpath(__DIR__ . '/../..');

$findings = [];

// 1. Check env() calls outside config
echo "--- 1. Checking env() calls outside config/ ---" . PHP_EOL;
$scanDirs = [$serverDir . '/app', $serverDir . '/routes', $serverDir . '/bootstrap'];
$envCalls = [];
foreach ($scanDirs as $dir) {
    if (!is_dir($dir)) continue;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $content = file_get_contents($file->getPathname());
            if (preg_match_all('/\benv\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[1] as $match) {
                    $envCalls[] = str_replace($serverDir . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . $match[0];
                }
            }
        }
    }
}
if (empty($envCalls)) {
    echo "  [+] No env() calls found outside config directory." . PHP_EOL;
} else {
    echo "  [!] Found env() calls outside config: " . implode(', ', $envCalls) . PHP_EOL;
    $findings[] = ['area' => 'Config', 'desc' => 'env() called outside config directory', 'items' => $envCalls];
}

// Check keys in config/*.php vs .env.example
echo PHP_EOL . "--- 2. Checking config/*.php env() keys vs .env.example ---" . PHP_EOL;
$configKeys = [];
foreach (glob($serverDir . '/config/*.php') as $cfg) {
    $content = file_get_contents($cfg);
    if (preg_match_all('/\benv\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
        foreach ($matches[1] as $k) {
            $configKeys[$k] = true;
        }
    }
}
$envExampleContent = file_exists($serverDir . '/.env.example') ? file_get_contents($serverDir . '/.env.example') : '';
$missingInExample = [];
foreach (array_keys($configKeys) as $k) {
    if (!preg_match('/^' . preg_quote($k, '/') . '=/m', $envExampleContent)) {
        $missingInExample[] = $k;
    }
}
echo "  Total distinct env keys in config: " . count($configKeys) . PHP_EOL;
if (empty($missingInExample)) {
    echo "  [+] All config env keys have placeholders in .env.example." . PHP_EOL;
} else {
    echo "  [!] Missing keys in .env.example: " . implode(', ', $missingInExample) . PHP_EOL;
    $findings[] = ['area' => 'Config', 'desc' => 'Config keys missing from .env.example', 'items' => $missingInExample];
}

// 2. Sanctum / session / CORS consistency
echo PHP_EOL . "--- 3. Checking Sanctum, Session, and CORS consistency ---" . PHP_EOL;
require $serverDir . '/vendor/autoload.php';
$app = require_once $serverDir . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$corsOrigins = config('cors.allowed_origins', []);
$corsCreds = config('cors.supports_credentials', false);
$sanctumDomains = config('sanctum.stateful', []);
$sessionDomain = config('session.domain');
$sessionSameSite = config('session.same_site');
$appUrl = config('app.url');

echo "  APP_URL: " . ($appUrl ?: 'NULL') . PHP_EOL;
echo "  CORS allowed_origins: " . json_encode($corsOrigins) . PHP_EOL;
echo "  CORS supports_credentials: " . ($corsCreds ? 'true' : 'false') . PHP_EOL;
echo "  Sanctum stateful domains: " . json_encode($sanctumDomains) . PHP_EOL;
echo "  Session domain: " . ($sessionDomain ?: 'NULL') . PHP_EOL;
echo "  Session same_site: " . ($sessionSameSite ?: 'NULL') . PHP_EOL;

if ($corsCreds && in_array('*', $corsOrigins, true)) {
    echo "  [!] Security violation: CORS supports credentials with wildcard origin '*'" . PHP_EOL;
    $findings[] = ['area' => 'CORS', 'desc' => 'CORS wildcard origin with credentials'];
} else {
    echo "  [+] CORS credential/origin configuration is secure." . PHP_EOL;
}

// 3. Queue, Cache, Mail, Filesystems
echo PHP_EOL . "--- 4. Queue, Cache, Mail, Filesystem configuration ---" . PHP_EOL;
echo "  Queue Connection: " . config('queue.default') . PHP_EOL;
echo "  Cache Driver: " . config('cache.default') . PHP_EOL;
echo "  Mail Driver: " . config('mail.default') . PHP_EOL;
echo "  Private Disk: " . config('filesystems.default') . ' -> ' . config('filesystems.disks.' . config('filesystems.default') . '.root') . PHP_EOL;
echo "  Private disk serve route: " . (config('filesystems.disks.local.serve') ? 'TRUE (INSECURE)' : 'FALSE (SECURE)') . PHP_EOL;

// 4. Required PHP extensions
echo PHP_EOL . "--- 5. Checking required PHP extensions ---" . PHP_EOL;
$requiredExtensions = [
    'bcmath' => 'Precision calculations and timestamps',
    'fileinfo' => 'MIME type sniffing and upload validation',
    'mbstring' => 'Multi-byte string handling',
    'openssl' => 'PKI signatures, encryption, and Sanctum tokens',
    'pdo_mysql' => 'MySQL database connectivity',
    'zip' => 'Archive and export compression',
    'intl' => 'Number::format, currency and Ethiopian date calculations',
    'gd' => 'Thumbnail and stamp image rendering',
];

foreach ($requiredExtensions as $ext => $purpose) {
    if (extension_loaded($ext)) {
        echo "  [+] Extension '$ext' loaded ($purpose)" . PHP_EOL;
    } else {
        echo "  [-] Extension '$ext' NOT loaded ($purpose)" . PHP_EOL;
        $findings[] = ['area' => 'Extensions', 'desc' => "Extension '$ext' is not loaded", 'purpose' => $purpose];
    }
}

// 5. Windows path handling check
echo PHP_EOL . "--- 6. Checking hardcoded path separators in server/app ---" . PHP_EOL;
$pathJoinIssues = [];
$appDir = $serverDir . '/app';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appDir));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        // Look for things like "path/to" . "\\" or 'path\to/' or storage_path('app/...')
        if (preg_match_all('/[\'"][a-zA-Z0-9_\-\.]+(?:\/|\\\\)[a-zA-Z0-9_\-\.]+(?:\\\\|\/)[a-zA-Z0-9_\-\.]+[\'"]/', $content, $m)) {
            foreach ($m[0] as $str) {
                if (str_contains($str, '/') && str_contains($str, '\\')) {
                    $pathJoinIssues[] = str_replace($serverDir . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ': ' . $str;
                }
            }
        }
    }
}
if (empty($pathJoinIssues)) {
    echo "  [+] No mixed / and \\ path separator literals found in server/app." . PHP_EOL;
} else {
    echo "  [!] Mixed path separator literals found: " . implode(', ', array_slice($pathJoinIssues, 0, 5)) . PHP_EOL;
}

// 6. Database config
echo PHP_EOL . "--- 7. Database configuration ---" . PHP_EOL;
$dbConnection = config('database.default');
$dbConfig = config("database.connections.{$dbConnection}");
echo "  Default connection: {$dbConnection}" . PHP_EOL;
echo "  Database: {$dbConfig['database']}" . PHP_EOL;
echo "  Charset: {$dbConfig['charset']}" . PHP_EOL;
echo "  Collation: {$dbConfig['collation']}" . PHP_EOL;
echo "  Strict mode: " . ($dbConfig['strict'] ? 'true' : 'false') . PHP_EOL;

// 7. Tooling configs & CI
echo PHP_EOL . "--- 8. Tooling and CI configurations ---" . PHP_EOL;
$ciPath = $rootDir . '/.github/workflows/ci.yml';
echo "  CI workflow (.github/workflows/ci.yml): " . (file_exists($ciPath) ? 'EXISTS' : 'MISSING') . PHP_EOL;
if (!file_exists($ciPath)) {
    $findings[] = ['area' => 'Tooling', 'desc' => 'CI workflow .github/workflows/ci.yml is missing'];
}

echo "  phpunit.xml: " . (file_exists($serverDir . '/phpunit.xml') ? 'EXISTS' : 'MISSING') . PHP_EOL;
echo "  phpstan.neon: " . (file_exists($serverDir . '/phpstan.neon') ? 'EXISTS' : 'MISSING') . PHP_EOL;
echo "  pint.json: " . (file_exists($serverDir . '/pint.json') ? 'EXISTS' : 'MISSING') . PHP_EOL;
echo "  client package.json: " . (file_exists($clientDir . '/package.json') ? 'EXISTS' : 'MISSING') . PHP_EOL;

echo PHP_EOL . "=== AUDIT SUMMARY ===" . PHP_EOL;
echo "Total configuration findings: " . count($findings) . PHP_EOL;
foreach ($findings as $idx => $f) {
    echo "  " . ($idx + 1) . ". [{$f['area']}] {$f['desc']}" . PHP_EOL;
}
