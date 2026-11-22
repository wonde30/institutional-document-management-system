<?php

require __DIR__ . '/server/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/server/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\File;

// Find a recently signed PDF in storage
$signedFiles = glob(storage_path('app/documents/signed_*.pdf'));
if (empty($signedFiles)) {
    echo "No signed PDFs found. Run the test first.\n";
    exit(1);
}

$signedPath = $signedFiles[0];
echo "Testing with: $signedPath\n\n";

$signedBytes = File::get($signedPath);
$pdfLen = strlen($signedBytes);

// Extract ByteRange
preg_match('/\/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]/', $signedBytes, $m);
$offset0 = (int) $m[1];
$len1 = (int) $m[2];
$offset2 = (int) $m[3];
$len2 = (int) $m[4];

echo "PDF Structure:\n";
echo "  Total length: $pdfLen bytes\n";
echo "  Part 1 (signed): $offset0 to " . ($len1 - 1) . " (length: $len1)\n";
echo "  Signature section (NOT signed): $len1 to " . ($offset2 - 1) . " (length: " . ($offset2 - $len1) . ")\n";
echo "  Part 2 (signed): $offset2 to " . ($offset2 + $len2 - 1) . " (length: $len2)\n\n";

// Test different tamper positions
$testPositions = [
    'Part 1 - Near start' => 100,
    'Part 1 - Near end' => $len1 - 10,
    'Signature - Start' => $len1 + 5,
    'Signature - Middle' => (int)(($len1 + $offset2) / 2),
    'Signature - End' => $offset2 - 10,
    'Part 2 - Start' => $offset2 + 10,
    'Part 2 - Middle' => (int)($offset2 + $len2 / 2),
];

$verifier = app(\App\Services\Pki\PdfSignatureVerificationService::class);

foreach ($testPositions as $label => $pos) {
    echo "Testing tamper at position $pos ($label):\n";
    
    $tamperedBytes = $signedBytes;
    $tamperedBytes[$pos] = $tamperedBytes[$pos] === 'a' ? 'b' : 'a';
    
    $tempPath = storage_path('app/test_tamper_' . rand(1000, 9999) . '.pdf');
    File::put($tempPath, $tamperedBytes);
    
    $result = $verifier->verifyPdf($tempPath);
    
    echo "  Outcome: {$result['outcome']}\n";
    echo "  Message: {$result['message']}\n";
    echo "  Tamper detected: " . ($result['details']['tamper_detected'] ?? 'NOT SET') . "\n";
    echo "\n";
    
    @unlink($tempPath);
}
