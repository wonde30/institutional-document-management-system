<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Role-to-permission mapping from seed
$rows = DB::table('role_permissions')
    ->join('roles', 'roles.id', '=', 'role_permissions.role_id')
    ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
    ->select('roles.code as role', 'permissions.code as permission')
    ->get();

$roleMap = [];
foreach ($rows as $r) {
    $roleMap[$r->permission][] = $r->role;
}

$apiContent = file_get_contents(__DIR__ . '/../routes/api.php');
$lines = explode("\n", $apiContent);

$results = [];
$curRoute = '';
foreach ($lines as $i => $line) {
    if (preg_match('/Route::(get|post|put|patch|delete)\([\'"]([^\'"]+)[\'"]/i', $line, $m)) {
        $curRoute = strtoupper($m[1]) . ' ' . $m[2];
    }
    if (preg_match('/middleware\([\'"]permission:([^)]+)[\'"]\)/', $line, $m)) {
        if (str_contains($m[1], '|')) {
            $perms = explode('|', $m[1]);
            $allRoles = [];
            foreach ($perms as $p) {
                $allRoles = array_merge($allRoles, $roleMap[$p] ?? []);
            }
            $allRoles = array_unique($allRoles);
            sort($allRoles);
            
            $results[] = [
                'line' => $i + 1,
                'route' => $curRoute,
                'middleware' => $m[1],
                'roles' => implode(', ', $allRoles),
            ];
        }
    }
}

echo "| Route | Line | Middleware | Authorized Roles (from seed map) |\n";
echo "|---|---|---|---|\n";
foreach ($results as $r) {
    echo "| `{$r['route']}` | {$r['line']} | `{$r['middleware']}` | {$r['roles']} |\n";
}
