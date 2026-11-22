<?php

// Simulate a signed PDF structure
$len1 = 500;  // End of first signed section
$signatureLen = 8192 + 2;  // <hex> where hex is 8192 chars
$offset2 = $len1 + $signatureLen;  // Start of second signed section
$len2 = 300;  // Length of second signed section

$totalLen = $offset2 + $len2;
echo "Total PDF length: $totalLen bytes\n";
echo "Part 1 (signed): 0 to " . ($len1 - 1) . "\n";
echo "Signature (NOT signed): $len1 to " . ($offset2 - 1) . "\n";
echo "Part 2 (signed): $offset2 to " . ($offset2 + $len2 - 1) . "\n\n";

// Test position is middle of file
$testPos = (int)($totalLen / 2);
echo "Test tampers at position: $testPos\n";

if ($testPos < $len1) {
    echo "Result: IN PART 1 (signed) - SHOULD DETECT\n";
} elseif ($testPos < $offset2) {
    echo "Result: IN SIGNATURE SECTION (not signed) - WILL NOT DETECT\n";
} else {
    echo "Result: IN PART 2 (signed) - SHOULD DETECT\n";
}
