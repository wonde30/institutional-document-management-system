<?php

echo "=== CHECK 04: SWALLOWED EXCEPTIONS & TRY/CATCH AUDIT ===\n\n";

$appDir = realpath(__DIR__ . '/../app');
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appDir));

$swallowedFindings = [];

foreach ($files as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    
    $content = file_get_contents($file->getRealPath());
    $relPath = 'app/' . str_replace($appDir . DIRECTORY_SEPARATOR, '', $file->getRealPath());
    
    // Look for catch blocks: catch (\Throwable $e) { ... }
    // Tokenize or regex match
    $tokens = token_get_all($content);
    $count = count($tokens);
    
    for ($i = 0; $i < $count; $i++) {
        if (is_array($tokens[$i]) && $tokens[$i][0] === T_CATCH) {
            $line = $tokens[$i][2];
            // find the body of the catch block
            // skip until '{'
            $braceDepth = 0;
            $catchBody = '';
            $inBody = false;
            
            for ($j = $i + 1; $j < $count; $j++) {
                $tokenStr = is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
                if ($tokenStr === '{') {
                    $braceDepth++;
                    $inBody = true;
                    continue;
                }
                if ($tokenStr === '}') {
                    $braceDepth--;
                    if ($braceDepth <= 0 && $inBody) {
                        break;
                    }
                }
                if ($inBody) {
                    $catchBody .= $tokenStr;
                }
            }
            
            $trimmedBody = trim($catchBody);
            // Check if empty or only comments
            $strippedBody = preg_replace('/\/\*.*?\*\/|\/\/.*?(\r?\n|$)/s', '', $trimmedBody);
            $strippedBody = trim($strippedBody);
            
            $isSwallowed = false;
            $reason = '';
            
            if ($strippedBody === '' || $strippedBody === ';') {
                $isSwallowed = true;
                $reason = 'Empty catch block (completely swallowed)';
            } elseif (!str_contains($catchBody, 'throw') && !str_contains($catchBody, 'Log::') && !str_contains($catchBody, 'logger') && !str_contains($catchBody, 'report(') && !str_contains($catchBody, 'return response()->json')) {
                // If it doesn't log, report, rethrow, or return an error response
                if (!str_contains($catchBody, 'return false') && !str_contains($catchBody, 'return null')) {
                    $isSwallowed = true;
                    $reason = 'Exception caught without log, rethrow, or error return';
                }
            }
            
            // Special check around AuditService or audit
            if (str_contains($content, 'AuditService') || str_contains($content, 'audit')) {
                if (str_contains($catchBody, 'audit') || $isSwallowed) {
                    $reason .= ' (Audit-related context)';
                }
            }
            
            if ($isSwallowed) {
                $swallowedFindings[] = [
                    'file' => $relPath,
                    'line' => $line,
                    'reason' => $reason,
                    'body' => substr($trimmedBody, 0, 120),
                ];
            }
        }
    }
}

echo "Total swallowed / suspicious catch blocks found: " . count($swallowedFindings) . "\n\n";
foreach ($swallowedFindings as $f) {
    echo "  [!] {$f['file']}:{$f['line']}\n      Reason: {$f['reason']}\n      Body: {$f['body']}\n\n";
}
