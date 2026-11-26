<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== CHECK 06: FILE SECURITY & STORAGE AUDIT ===\n\n";

$checks = [];

// 1. Check storage disks configuration
$disks = config('filesystems.disks');
echo "Configured storage disks:\n";
foreach ($disks as $name => $cfg) {
    $driver = $cfg['driver'] ?? 'unknown';
    $visibility = $cfg['visibility'] ?? 'default';
    $root = $cfg['root'] ?? '';
    echo "  - $name: driver=$driver, visibility=$visibility, root=$root\n";
    if ($visibility === 'public') {
        $checks[] = "[!] Storage disk '$name' has public visibility! Private documents must not be publicly served.";
    }
}

// 2. Check FileSecurityService capabilities and implementation
$fileSecurityPath = __DIR__ . '/../app/Services/FileSecurityService.php';
if (file_exists($fileSecurityPath)) {
    $content = file_get_contents($fileSecurityPath);
    echo "\nFileSecurityService checks:\n";
    if (str_contains($content, 'finfo_open') || str_contains($content, 'mime_content_type') || str_contains($content, 'getMimeType')) {
        echo "  [+] Content-based MIME detection present.\n";
    } else {
        $checks[] = "[!] FileSecurityService may lack content-based MIME inspection (magic bytes).";
    }
    
    if (str_contains($content, 'hash_file') || str_contains($content, 'sha256')) {
        echo "  [+] SHA-256 checksum calculation/verification present.\n";
    } else {
        $checks[] = "[!] FileSecurityService missing SHA-256 checksum verification.";
    }
    
    if (str_contains($content, '..') || str_contains($content, 'basename') || str_contains($content, 'Str::slug') || str_contains($content, 'Str::random')) {
        echo "  [+] Path traversal guards / safe stored path generation present.\n";
    } else {
        $checks[] = "[!] Potential path traversal risk in stored path generation.";
    }
}

// 3. Inspect downloadFile implementation in DocumentController
$docControllerPath = __DIR__ . '/../app/Http/Controllers/Api/V1/DocumentController.php';
if (file_exists($docControllerPath)) {
    $content = file_get_contents($docControllerPath);
    echo "\nDocumentController file operations:\n";
    
    // Check downloadFile method
    if (preg_match('/function downloadFile\s*\([^)]*\)\s*(?::\s*[^{]+)?\{([^}]+(?:\{[^}]*\}[^}]*)*)\}/s', $content, $m)) {
        $dlMethod = $m[1];
        if (str_contains($dlMethod, 'Content-Disposition')) {
            echo "  [+] Content-Disposition header specified in download response.\n";
        } else {
            $checks[] = "[!] DocumentController@downloadFile missing explicit Content-Disposition header.";
        }
        
        if (str_contains($dlMethod, 'sha256') || str_contains($dlMethod, 'verify') || str_contains($dlMethod, 'hash')) {
            echo "  [+] Checksum check / validation reference on download.\n";
        } else {
            $checks[] = "[!] DocumentController@downloadFile does not verify checksum on streaming.";
        }
    }
}

// 4. Check routes for file downloading without auth or download permission
$router = app('router');
foreach ($router->getRoutes() as $r) {
    if (str_contains($r->uri(), 'download') || str_contains($r->uri(), 'files/')) {
        $mw = (array) $r->middleware();
        if (!in_array('auth:sanctum', $mw)) {
            $checks[] = "[!] File route '{$r->uri()}' is missing auth:sanctum middleware!";
        }
    }
}

echo "\n--- SUMMARY OF FILE SECURITY CHECKS ---\n";
if (empty($checks)) {
    echo "No file security defects detected.\n";
} else {
    foreach ($checks as $c) {
        echo "$c\n";
    }
}
