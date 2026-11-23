<?php

declare(strict_types=1);

require_once __DIR__ . '/../server/vendor/autoload.php';
$app = require_once __DIR__ . '/../server/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

require_once __DIR__ . '/../server/tools/role_map.php';
