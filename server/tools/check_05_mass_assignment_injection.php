<?php

echo "=== CHECK 05: MASS ASSIGNMENT & SQL INJECTION AUDIT ===\n\n";

$appDir = realpath(__DIR__ . '/../app');
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appDir));

$guardedIssues = [];
$rawSqlIssues = [];
$requestAllIssues = [];

foreach ($files as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    
    $content = file_get_contents($file->getRealPath());
    $relPath = 'app/' . str_replace($appDir . DIRECTORY_SEPARATOR, '', $file->getRealPath());
    $lines = explode("\n", $content);
    
    foreach ($lines as $lineNum => $line) {
        $ln = $lineNum + 1;
        
        // 1. $guarded = [] or $guarded = ['id']
        if (preg_match('/\$guarded\s*=\s*\[\s*\]/', $line)) {
            $guardedIssues[] = [
                'file' => $relPath,
                'line' => $ln,
                'issue' => '$guarded = [] (completely un-guarded model!)',
            ];
        }
        
        // 2. Sensitive fields in $fillable
        // role_id, permission_id, is_admin, owner_unit_id, user_id, active, approved, verified
        if (str_contains($relPath, 'Models') && preg_match('/\$fillable\s*=/', $line)) {
            // grab the array
            $fillableBlock = $line;
            for ($k = $lineNum + 1; $k < count($lines) && $k < $lineNum + 30; $k++) {
                $fillableBlock .= "\n" . $lines[$k];
                if (str_contains($lines[$k], '];')) {
                    break;
                }
            }
            $sensitive = ['is_admin', 'role_id', 'is_active', 'status', 'approved_at', 'verified_at'];
            foreach ($sensitive as $s) {
                if (preg_match("/['\"]" . preg_quote($s) . "['\"]/", $fillableBlock)) {
                    $guardedIssues[] = [
                        'file' => $relPath,
                        'line' => $ln,
                        'issue' => "Sensitive field '$s' in \$fillable",
                    ];
                }
            }
        }
        
        // 3. Raw SQL with potential user input: DB::raw, whereRaw, selectRaw, orderByRaw, havingRaw
        if (preg_match('/(whereRaw|selectRaw|orderByRaw|havingRaw|DB::raw)\s*\(\s*([^;]+)/', $line, $m)) {
            $rawExp = $m[2];
            // Check if variable or string interpolation is used instead of bindings
            if (preg_match('/\$request|\$input|\$validated|\$_GET|\$_POST|\$order|\$sort|\$dir|\$column|\$search/', $rawExp) || preg_match('/"[^"]*\$[^"]*"/', $rawExp) || str_contains($rawExp, ' . $')) {
                // Check if there are bindings: second argument array
                $hasBindings = preg_match('/,\s*\[.*\]/', $rawExp);
                if (!$hasBindings || preg_match('/orderByRaw|ORDER BY/i', $line)) {
                    $rawSqlIssues[] = [
                        'file' => $relPath,
                        'line' => $ln,
                        'expression' => trim($line),
                    ];
                }
            }
        }
        
        // 4. $request->all() into create/update/fill
        if (preg_match('/(create|update|fill)\s*\(\s*\$request->all\(\)/', $line) || preg_match('/(create|update|fill)\s*\(\s*\$request->input\(\)/', $line)) {
            $requestAllIssues[] = [
                'file' => $relPath,
                'line' => $ln,
                'issue' => '$request->all() passed directly to create/update/fill',
            ];
        }
    }
}

echo "--- 1. GUARDED / FILLABLE ISSUES (" . count($guardedIssues) . ") ---\n";
foreach ($guardedIssues as $g) {
    echo "  [!] {$g['file']}:{$g['line']} - {$g['issue']}\n";
}

echo "\n--- 2. RAW SQL ISSUES (" . count($rawSqlIssues) . ") ---\n";
foreach ($rawSqlIssues as $r) {
    echo "  [!] {$r['file']}:{$r['line']} - {$r['expression']}\n";
}

echo "\n--- 3. REQUEST->ALL() IN CREATE/UPDATE (" . count($requestAllIssues) . ") ---\n";
foreach ($requestAllIssues as $ra) {
    echo "  [!] {$ra['file']}:{$ra['line']} - {$ra['issue']}\n";
}
