<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('role_permissions')
    ->join('roles', 'roles.id', '=', 'role_permissions.role_id')
    ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
    ->select('roles.code as role', 'permissions.code as perm')
    ->get();

$map = [];
foreach ($rows as $r) {
    $map[$r->perm][] = $r->role;
}

$check = [
    'stamps.apply',
    'stamps.manage',
    'documents.stamp',
    'workflow.reassign',
    'workflow.configure',
    'workflow.manage',
    'correspondence.register',
    'correspondence.dispatch',
    'correspondence.manage',
    'system.configure',
    'settings.manage',
    'archive.view',
    'documents.share',
    'reports.export',
    'reports.view',
    'roles.delete',
    'research.submit',
    'research.review',
    'research.approve',
    'research.reject',
    'projects.submit',
    'projects.review',
    'projects.approve',
    'projects.reject',
    'documents.version',
    'documents.upload',
];

echo str_pad('Permission', 28) . ' | Roles in Seed' . PHP_EOL;
echo str_repeat('-', 28) . '-+-' . str_repeat('-', 60) . PHP_EOL;
foreach ($check as $p) {
    $roles = isset($map[$p]) ? implode(', ', $map[$p]) : 'NONE (not in seed vocabulary)';
    echo str_pad($p, 28) . ' | ' . $roles . PHP_EOL;
}
