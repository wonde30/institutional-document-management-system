<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Permission;
use Illuminate\Support\Facades\DB;

$perms = [
    'backups.manage',
    'backups.restore',
    'correspondence.issue_direct',
    'documents.share',
    'documents.upload',
    'projects.approve',
    'projects.reject',
    'projects.review',
    'reports.export',
    'research.approve',
    'research.reject',
    'research.review',
    'research.submit',
    'roles.delete',
    'signatures.sign',
    'signatures.verify',
    'workflow.approve',
    'workflow.cancel',
    'workflow.reject',
];

echo "=== STEP 1c: AUDIT OF THE 19 SEEDED PERMISSIONS ===\n\n";

foreach ($perms as $p) {
    $dbPerm = DB::table('permissions')->where('code', $p)->first();
    echo "--- Permission: $p (DB ID: " . ($dbPerm?->id ?? 'NOT FOUND') . ") ---\n";
    echo "Name: " . ($dbPerm?->name ?? 'N/A') . " | Category: " . ($dbPerm?->category ?? 'N/A') . "\n";
    
    // Check roles assigned to this permission
    $roles = DB::table('role_permissions')
        ->join('roles', 'roles.id', '=', 'role_permissions.role_id')
        ->where('role_permissions.permission_id', $dbPerm?->id)
        ->pluck('roles.code')
        ->toArray();
    echo "Assigned Roles: " . (empty($roles) ? 'NONE' : implode(', ', $roles)) . "\n";

    // Grep in server/app
    $serverRefs = shell_exec("git grep -n \"$p\" server/app/");
    echo "Server refs:\n" . ($serverRefs ?: "  None\n");

    // Grep in server/routes
    $routeRefs = shell_exec("git grep -n \"$p\" server/routes/");
    echo "Route refs:\n" . ($routeRefs ?: "  None\n");

    // Grep in client/src
    $clientRefs = shell_exec("git grep -n \"$p\" client/src/");
    echo "Client refs:\n" . ($clientRefs ?: "  None\n");

    echo "\n";
}
