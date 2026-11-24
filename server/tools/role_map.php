<?php

declare(strict_types=1);

if (! isset($app) || ! is_object($app)) {
    require_once __DIR__ . '/../vendor/autoload.php';
    $app = require __DIR__ . '/../bootstrap/app.php';
    $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
}

use Illuminate\Support\Facades\DB;

// Fetch all roles and permissions directly from DB
$roles = DB::table('roles')->orderBy('id')->get();
$rolesByCode = [];
foreach ($roles as $r) {
    $rolesByCode[$r->code] = $r;
}

$rolePermRows = DB::table('role_permissions')
    ->join('roles', 'role_permissions.role_id', '=', 'roles.id')
    ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
    ->select('roles.code as role_code', 'permissions.code as perm_code')
    ->get();

$roleMap = [];
foreach ($roles as $r) {
    $roleMap[$r->code] = [];
}
foreach ($rolePermRows as $row) {
    $roleMap[$row->role_code][] = $row->perm_code;
}

// Function to resolve which roles satisfy a permission expression (pipe separated)
function getRolesForExpr(string $expr, array $roleMap): array
{
    $perms = explode('|', $expr);
    $matchingRoles = [];

    foreach ($roleMap as $role => $assignedPerms) {
        // Super admin has wildcard access across all permissions
        if ($role === 'super_admin') {
            $matchingRoles[] = $role;
            continue;
        }

        foreach ($perms as $p) {
            $p = trim($p);
            if (in_array($p, $assignedPerms, true)) {
                $matchingRoles[] = $role;
                break;
            }
        }
    }

    sort($matchingRoles);
    return $matchingRoles;
}

// Every route whose middleware changed or was added since baseline 768c161
$routes = [
    // RBAC-01
    [
        'route' => 'POST stamps/apply',
        'baseline' => 'documents.stamp (unseeded)',
        'current' => 'stamps.apply',
        'is_new' => false,
        'note' => 'RBAC-01 bug fix (unseeded to seeded)',
    ],
    // RBAC-02
    [
        'route' => 'POST workflows/tasks/{id}/reassign',
        'baseline' => 'workflow.manage (unseeded)',
        'current' => 'workflow.reassign',
        'is_new' => false,
        'note' => 'RBAC-02 bug fix (unseeded to seeded)',
    ],
    // RBAC-03
    [
        'route' => 'POST correspondences/inward',
        'baseline' => 'correspondence.manage (unseeded)',
        'current' => 'correspondence.register',
        'is_new' => false,
        'note' => 'RBAC-03 bug fix (unseeded to seeded)',
    ],
    [
        'route' => 'POST correspondences/{id}/dispatch',
        'baseline' => 'correspondence.manage (unseeded)',
        'current' => 'correspondence.dispatch',
        'is_new' => false,
        'note' => 'RBAC-03 bug fix (unseeded to seeded)',
    ],
    // RBAC-04
    [
        'route' => 'GET health',
        'baseline' => 'settings.manage (unseeded)',
        'current' => 'system.configure',
        'is_new' => false,
        'note' => 'RBAC-04 bug fix (unseeded to seeded)',
    ],
    // Signatures (verified guards)
    [
        'route' => 'POST signatures/{id}/execute',
        'baseline' => 'signatures.request',
        'current' => 'signatures.request',
        'is_new' => false,
        'note' => 'Reverted any pipe widening',
    ],
    [
        'route' => 'POST signatures/{id}/verify',
        'baseline' => 'signatures.request',
        'current' => 'signatures.request',
        'is_new' => false,
        'note' => 'Reverted any pipe widening',
    ],
    [
        'route' => 'POST signatures/verify-file',
        'baseline' => 'signatures.request',
        'current' => 'signatures.request',
        'is_new' => false,
        'note' => 'Reverted any pipe widening',
    ],
    // Backups (verified guards)
    [
        'route' => 'GET/POST admin/backups/*',
        'baseline' => 'system.configure',
        'current' => 'system.configure',
        'is_new' => false,
        'note' => 'Reverted any pipe widening',
    ],
    [
        'route' => 'GET/POST admin/dr-drills/*',
        'baseline' => 'system.configure',
        'current' => 'system.configure',
        'is_new' => false,
        'note' => 'Reverted any pipe widening',
    ],
    // documents.version
    [
        'route' => 'POST documents/{id}/versions',
        'baseline' => 'documents.version',
        'current' => 'documents.version',
        'is_new' => false,
        'note' => 'Reverted temporary documents.upload pipe to review/access-changes',
    ],
    // NEW ROUTES (No before - brand new since 768c161)
    [
        'route' => 'GET reports/export',
        'baseline' => 'None (new route)',
        'current' => 'reports.export',
        'is_new' => true,
        'note' => 'NEW ROUTE (No before)',
    ],
    [
        'route' => 'POST documents/{id}/share',
        'baseline' => 'None (new route)',
        'current' => 'documents.share',
        'is_new' => true,
        'note' => 'NEW ROUTE (No before)',
    ],
    [
        'route' => 'DELETE documents/{id}/share/{grantId}',
        'baseline' => 'None (new route)',
        'current' => 'documents.share',
        'is_new' => true,
        'note' => 'NEW ROUTE (No before)',
    ],
    [
        'route' => 'DELETE roles/{id}',
        'baseline' => 'None (new route)',
        'current' => 'roles.delete',
        'is_new' => true,
        'note' => 'NEW ROUTE (No before)',
    ],
    [
        'route' => 'POST research/outputs/{id}/submit',
        'baseline' => 'None (new route)',
        'current' => 'research.submit',
        'is_new' => true,
        'note' => 'NEW ROUTE (No before)',
    ],
    [
        'route' => 'POST research/outputs/{id}/review',
        'baseline' => 'None (new route)',
        'current' => 'research.review',
        'is_new' => true,
        'note' => 'NEW ROUTE (No before)',
    ],
    [
        'route' => 'POST research/outputs/{id}/approve',
        'baseline' => 'None (new route)',
        'current' => 'research.approve',
        'is_new' => true,
        'note' => 'NEW ROUTE (No before)',
    ],
    [
        'route' => 'POST research/outputs/{id}/reject',
        'baseline' => 'None (new route)',
        'current' => 'research.reject',
        'is_new' => true,
        'note' => 'NEW ROUTE (No before)',
    ],
    [
        'route' => 'POST research/outputs/{id}/return',
        'baseline' => 'None (new route)',
        'current' => 'research.review|research.approve',
        'is_new' => true,
        'note' => 'NEW ROUTE (No before)',
    ],
    [
        'route' => 'POST projects/{id}/submit',
        'baseline' => 'None (new route)',
        'current' => 'projects.submit',
        'is_new' => true,
        'note' => 'NEW ROUTE (No before)',
    ],
    [
        'route' => 'POST projects/{id}/review',
        'baseline' => 'None (new route)',
        'current' => 'projects.review',
        'is_new' => true,
        'note' => 'NEW ROUTE (No before)',
    ],
    [
        'route' => 'POST projects/{id}/approve',
        'baseline' => 'None (new route)',
        'current' => 'projects.approve',
        'is_new' => true,
        'note' => 'NEW ROUTE (No before)',
    ],
    [
        'route' => 'POST projects/{id}/reject',
        'baseline' => 'None (new route)',
        'current' => 'projects.reject',
        'is_new' => true,
        'note' => 'NEW ROUTE (No before)',
    ],
    [
        'route' => 'POST projects/{id}/return',
        'baseline' => 'None (new route)',
        'current' => 'projects.review|projects.approve',
        'is_new' => true,
        'note' => 'NEW ROUTE (No before)',
    ],
];

ob_start();

echo "========================================================================================================================\n";
echo "=== ROLE MAP & ACCESS CHANGE AUDIT TABLE (BASELINE 768c161 vs HEAD) ===\n";
echo "========================================================================================================================\n\n";

// Table headers - full width, no truncation ("...")
printf("| %-42s | %-28s | %-32s | %-42s | %-42s | %-24s | %-16s |\n",
    "Route", "Baseline Guard", "Current Guard", "Before Roles", "Now Roles", "Gains", "Losses");
echo "|" . str_repeat("-", 44) . "|" . str_repeat("-", 30) . "|" . str_repeat("-", 34) . "|" . str_repeat("-", 44) . "|" . str_repeat("-", 44) . "|" . str_repeat("-", 26) . "|" . str_repeat("-", 18) . "|\n";

foreach ($routes as $r) {
    if ($r['is_new']) {
        $beforeRolesStr = 'None (new route, no before)';
        $beforeRoles = [];
    } elseif (str_contains($r['baseline'], 'unseeded')) {
        $beforeRolesStr = 'super_admin';
        $beforeRoles = ['super_admin'];
    } else {
        $beforeRoles = getRolesForExpr($r['baseline'], $roleMap);
        $beforeRolesStr = empty($beforeRoles) ? 'none' : implode(', ', $beforeRoles);
    }

    $afterRoles = getRolesForExpr($r['current'], $roleMap);
    $afterRolesStr = empty($afterRoles) ? 'none' : implode(', ', $afterRoles);

    if ($r['is_new']) {
        $gainsStr = 'n/a (new route)';
        $lossesStr = 'none';
    } else {
        $gains = array_diff($afterRoles, $beforeRoles);
        $losses = array_diff($beforeRoles, $afterRoles);
        $gainsStr = empty($gains) ? 'none' : implode(', ', $gains);
        $lossesStr = empty($losses) ? 'none' : implode(', ', $losses);
    }

    printf("| %-42s | %-28s | %-32s | %-42s | %-42s | %-24s | %-16s |\n",
        $r['route'],
        $r['baseline'],
        $r['current'],
        $beforeRolesStr,
        $afterRolesStr,
        $gainsStr,
        $lossesStr
    );
}

echo "\n\n========================================================================================================================\n";
echo "=== AUDIT OF 6 SPECIFIC PERMISSION CODES FROM DATABASE ===\n";
echo "========================================================================================================================\n";

$targetCodes = [
    'backups.manage',
    'backups.restore',
    'signatures.sign',
    'signatures.verify',
    'reports.export',
    'correspondence.issue_direct',
];

$perms = DB::table('permissions')->whereIn('code', $targetCodes)->orderBy('id')->get();
printf("| %-5s | %-30s | %-12s | %-50s |\n", "ID", "Permission Code", "Exists in DB", "Roles Holding Permission");
echo "|-------|--------------------------------|--------------|----------------------------------------------------|\n";
foreach ($targetCodes as $tCode) {
    $permRow = $perms->firstWhere('code', $tCode);
    if ($permRow) {
        $rolesHolding = DB::table('role_permissions')
            ->join('roles', 'role_permissions.role_id', '=', 'roles.id')
            ->where('role_permissions.permission_id', $permRow->id)
            ->pluck('roles.code')
            ->all();
        $rolesStr = empty($rolesHolding) ? 'NONE' : implode(', ', $rolesHolding);
        printf("| %-5d | %-30s | %-12s | %-50s |\n", $permRow->id, $permRow->code, "YES", $rolesStr);
    } else {
        printf("| %-5s | %-30s | %-12s | %-50s |\n", "-", $tCode, "NO", "NONE");
    }
}

echo "\n\n========================================================================================================================\n";
echo "=== RAW ROLE-TO-PERMISSION MAPPINGS FROM DATABASE (NO TRUNCATION) ===\n";
echo "========================================================================================================================\n\n";
foreach ($roleMap as $role => $assignedPerms) {
    $isSystem = isset($rolesByCode[$role]) ? (int) $rolesByCode[$role]->is_system : 0;
    $roleName = isset($rolesByCode[$role]) ? $rolesByCode[$role]->name : $role;
    echo "Role: [{$role}] - {$roleName} (is_system: {$isSystem}, Total Permissions: " . count($assignedPerms) . ")\n";
    echo "Assigned Permissions:\n";
    sort($assignedPerms);
    foreach (array_chunk($assignedPerms, 6) as $chunk) {
        echo "  " . implode(', ', $chunk) . "\n";
    }
    echo "\n";
}

$output = ob_get_clean();

// Print to STDOUT
echo $output;

// Write complete output to server/storage/logs/role_map.txt
$logDir = __DIR__ . '/../storage/logs';
if (! is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
file_put_contents($logDir . '/role_map.txt', $output);
