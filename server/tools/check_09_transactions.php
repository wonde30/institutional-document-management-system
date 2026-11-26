<?php

echo "=== CHECK 09: MULTI-STEP WRITE TRANSACTION BOUNDARY AUDIT ===\n\n";

$controllersDir = realpath(__DIR__ . '/../app/Http/Controllers/Api/V1');
$files = glob($controllersDir . '/*.php');

$transactionAudit = [];

foreach ($files as $f) {
    $content = file_get_contents($f);
    $base = basename($f);
    
    // Look for methods that perform multiple writes
    // We search for functions
    if (preg_match_all('/public function\s+([a-zA-Z0-9_]+)\s*\([^)]*\)\s*(?::\s*[^{]+)?\{/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
        for ($i = 0; $i < count($matches[0]); $i++) {
            $methodName = $matches[1][$i][0];
            $startOffset = $matches[0][$i][1];
            
            // find method body
            $braceDepth = 0;
            $len = strlen($content);
            $body = '';
            $started = false;
            
            for ($k = $startOffset; $k < $len; $k++) {
                if ($content[$k] === '{') {
                    $braceDepth++;
                    $started = true;
                    continue;
                }
                if ($content[$k] === '}') {
                    $braceDepth--;
                    if ($braceDepth <= 0 && $started) {
                        break;
                    }
                }
                if ($started) {
                    $body .= $content[$k];
                }
            }
            
            // Count write calls: create, update, delete, save, insert
            $writeCount = preg_match_all('/->(create|update|delete|save)\(|::(create|insert|update|delete)\(/', $body);
            $hasTransaction = str_contains($body, 'DB::transaction') || str_contains($body, 'beginTransaction');
            
            if ($writeCount >= 2 && !$hasTransaction) {
                // If it calls a service method, check if service uses transaction
                $callsService = preg_match('/->[a-zA-Z0-9_]+Service->|app\([a-zA-Z0-9_]+Service::class\)/', $body);
                $transactionAudit[] = [
                    'controller' => $base,
                    'method' => $methodName,
                    'write_count' => $writeCount,
                    'has_transaction' => false,
                    'calls_service' => (bool)$callsService,
                ];
            }
        }
    }
}

echo "Methods with 2+ write operations without direct DB::transaction:\n";
foreach ($transactionAudit as $ta) {
    echo "  [!] {$ta['controller']}::{$ta['method']} (writes: {$ta['write_count']}, calls_service: " . ($ta['calls_service'] ? 'yes' : 'no') . ")\n";
}
