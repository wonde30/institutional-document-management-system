<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$d = new App\Models\Document();
try {
    $r = $d->category();
    echo "Class: " . get_class($r) . "\n";
    echo "Related: " . get_class($r->getRelated()) . "\n";
    echo "FK: " . $r->getForeignKeyName() . "\n";
    echo "OwnerKey: " . $r->getOwnerKeyName() . "\n";
} catch (Throwable $e) {
    echo 'ERR: ' . $e->getMessage() . "\n" . $e->getTraceAsString();
}
