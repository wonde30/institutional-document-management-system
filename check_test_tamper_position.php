<?php

require __DIR__ . '/server/vendor/autoload.php';

$app = require_once __DIR__ . '/server/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\File;

$signedFiles = glob(storage_path('app/documents/signed_*.pdf'));
if (empty($signedFiles)) {
    echo "No signed PDFs found.\n";
    exit(1);
}

$signedPath = $signedFiles[0];
$signedBytes = File::get($signedPath);
$pdfLen = strlen($signedBytes);

// This is what the test does
$pos = (int)($pdfLen / 2);

echo "PDF length: $pdfLen\n";
echo "Test tampers at: $pos\n";

// Extract ByteRange
preg_match('/\/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]/', $signedBytes, $m);
$len1 = (int) $m[2];
$offset2 = (int) $m[3];

echo "ByteRange: [0, $len1, $offset2, " . $m[4] . "]\n";

if ($pos < $len1) {
    echo "Position is in PART 1 (signed data) - SHOULD DETECT\n";
} elseif ($pos < $offset2) {
    $distanceFromStart = $pos - $len1;
    $signatureSectionLen = $offset2 - $len1;
    $percentIntoSignature = ($distanceFromStart / $signatureSectionLen) * 100;
    echo "Position is in SIGNATURE SECTION at " . round($percentIntoSignature, 1) . "% through the hex\n";
    
    // Extract the contents section
    $contentsSection = substr($signedBytes, $len1, $offset2 - $len1);
    if (preg_match('/<([0-9A-Fa-f\s]+)>/', $contentsSection, $cMatch)) {
        $rawHex = preg_replace('/\s+/', '', $cMatch[1]);
        
        // Determine DER length
        $tag = hexdec(substr($rawHex, 0, 2));
        $lenByte = hexdec(substr($rawHex, 2, 2));
        
        $derTotalLen = 0;
        if ($tag === 0x30) {
            if ($lenByte < 0x80) {
                $derTotalLen = 2 + $lenByte;
            } elseif ($lenByte === 0x81) {
                $derTotalLen = 3 + hexdec(substr($rawHex, 4, 2));
            } elseif ($lenByte === 0x82) {
                $derTotalLen = 4 + hexdec(substr($rawHex, 4, 4));
            }
        }
        
        $derHexLen = $derTotalLen * 2;
        $paddingLen = strlen($rawHex) - $derHexLen;
        
        echo "  Signature hex total length: " . strlen($rawHex) . " chars\n";
        echo "  Actual DER length: $derHexLen chars\n";
        echo "  Padding length: $paddingLen chars\n";
        
        $byteInHex = $pos - $len1 - 1; // -1 for the '<'
        if ($byteInHex < $derHexLen) {
            echo "  Byte is in ACTIVE DER DATA - should invalidate signature\n";
        } else {
            echo "  Byte is in PADDING ZEROS - will be IGNORED!\n";
        }
    }
} else {
    echo "Position is in PART 2 (signed data) - SHOULD DETECT\n";
}
