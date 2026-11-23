<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

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

// Every route whose middleware changed since baseline 768c161
$routes = [
    // RBAC-01
    [
        'route' => 'POST stamps/apply',
        'baseline' => 'documents.stamp (unseeded)',
        'current' => 'stamps.apply',
        'note' => 'RBAC-01 bug fix (unseeded to seeded)',
    ],
    // RBAC-02
    [
        'route' => 'POST workflows/tasks/{id}/reassign',
        'baseline' => 'workflow.manage (unseeded)',
        'current' => 'workflow.reassign',
        'note' => 'RBAC-02 bug fix (unseeded to seeded)',
    ],
    // RBAC-03
    [
        'route' => 'POST correspondences/inward',
        'baseline' => 'correspondence.manage (unseeded)',
        'current' => 'correspondence.register',
        'note' => 'RBAC-03 bug fix (unseeded to seeded)',
    ],
    [
        'route' => 'POST correspondences/{id}/dispatch',
        'baseline' => 'correspondence.manage (unseeded)',
        'current' => 'correspondence.dispatch',
        'note' => 'RBAC-03 bug fix (unseeded to seeded)',
    ],
    // RBAC-04
    [
        'route' => 'GET health',
        'baseline' => 'settings.manage (unseeded)',
        'current' => 'system.configure',
        'note' => 'RBAC-04 bug fix (unseeded to seeded)',
    ],
    // Signatures
    [
        'route' => 'POST signatures/{id}/execute',
        'baseline' => 'signatures.request',
        'current' => 'signatures.request',
        'note' => 'Reverted any pipe widening',
    ],
    [
        'route' => 'POST signatures/{id}/verify',
        'baseline' => 'signatures.request',
        'current' => 'signatures.request',
        'note' => 'Reverted any pipe widening',
    ],
    [
        'route' => 'POST signatures/verify-file',
        'baseline' => 'signatures.request',
        'current' => 'signatures.request',
        'note' => 'Reverted any pipe widening',
    ],
    // Backups
    [
        'route' => 'GET/POST admin/backups/*',
        'baseline' => 'system.configure',
        'current' => 'system.configure',
        'note' => 'Reverted any pipe widening',
    ],
    [
        'route' => 'GET/POST admin/dr-drills/*',
        'baseline' => 'system.configure',
        'current' => 'system.configure',
        'note' => 'Reverted any pipe widening',
    ],
    // documents.version
    [
        'route' => 'POST documents/{id}/versions',
        'baseline' => 'documents.version',
        'current' => 'documents.version',
        'note' => 'Reverted temporary documents.upload pipe to review/access-changes',
    ],
    // NEW ROUTES
    [
        'route' => 'GET reports/export',
        'baseline' => 'None (new route)',
        'current' => 'reports.export',
        'note' => 'NEW ROUTE',
    ],
    [
        'route' => 'POST documents/{id}/share',
        'baseline' => 'None (new route)',
        'current' => 'documents.share',
        'note' => 'NEW ROUTE',
    ],
    [
        'route' => 'DELETE documents/{id}/share/{grantId}',
        'baseline' => 'None (new route)',
        'current' => 'documents.share',
        'note' => 'NEW ROUTE',
    ],
    [
        'route' => 'DELETE roles/{id}',
        'baseline' => 'None (new route)',
        'current' => 'roles.delete',
        'note' => 'NEW ROUTE',
    ],
    [
        'route' => 'POST research/outputs/{id}/submit',
        'baseline' => 'None (new route)',
        'current' => 'research.submit',
        'note' => 'NEW ROUTE',
    ],
    [
        'route' => 'POST research/outputs/{id}/review',
        'baseline' => 'None (new route)',
        'current' => 'research.review',
        'note' => 'NEW ROUTE',
    ],
    [
        'route' => 'POST research/outputs/{id}/approve',
        'baseline' => 'None (new route)',
        'current' => 'research.approve',
        'note' => 'NEW ROUTE',
    ],
    [
        'route' => 'POST research/outputs/{id}/reject',
        'baseline' => 'None (new route)',
        'current' => 'research.reject',
        'note' => 'NEW ROUTE',
    ],
    [
        'route' => 'POST research/outputs/{id}/return',
        'baseline' => 'None (new route)',
        'current' => 'research.review|research.approve',
        'note' => 'NEW ROUTE',
    ],
    [
        'route' => 'POST projects/{id}/submit',
        'baseline' => 'None (new route)',
        'current' => 'projects.submit',
        'note' => 'NEW ROUTE',
    ],
    [
        'route' => 'POST projects/{id}/review',
        'baseline' => 'None (new route)',
        'current' => 'projects.review',
        'note' => 'NEW ROUTE',
    ],
    [
        'route' => 'POST projects/{id}/approve',
        'baseline' => 'None (new route)',
        'current' => 'projects.approve',
        'note' => 'NEW ROUTE',
    ],
    [
        'route' => 'POST projects/{id}/reject',
        'baseline' => 'None (new route)',
        'current' => 'projects.reject',
        'note' => 'NEW ROUTE',
    ],
    [
        'route' => 'POST projects/{id}/return',
        'baseline' => 'None (new route)',
        'current' => 'projects.review|projects.approve',
        'note' => 'NEW ROUTE',
    ],
];

echo "=== ROLE MAP & ACCESS CHANGE AUDIT TABLE (BASELINE 768c161 vs HEAD) ===\n\n";

printf("%-38s | %-28s | %-32s | %-35s | %-35s | %-20s | %-15s\n", 
    "Route", "Baseline Guard", "Current Guard", "Before Roles", "Now Roles", "Gains", "Losses");
echo str_repeat("-", 195) . "\n";

$auditTableRows = [];

foreach ($routes as $r) {
    $beforeRoles = ($r['baseline'] === 'None (new route)' || str_contains($r['baseline'], 'unseeded'))
        ? ($r['baseline'] === 'None (new route)' ? [] : ['super_admin'])
        : getRolesForExpr($r['baseline'], $roleMap);

    $afterRoles = getRolesForExpr($r['current'], $roleMap);

    $gains = array_diff($afterRoles, $beforeRoles);
    $losses = array_diff($beforeRoles, $afterRoles);

    $auditTableRows[] = [
        'route' => $r['route'],
        'baseline' => $r['baseline'],
        'current' => $r['current'],
        'before_roles' => $beforeRoles,
        'after_roles' => $afterRoles,
        'gains' => array_values($gains),
        'losses' => array_values($losses),
    ];

    printf("%-38s | %-28s | %-32s | %-35s | %-35s | %-20s | %-15s\n",
        substr($r['route'], 0, 38),
        substr($r['baseline'], 0, 28),
        substr($r['current'], 0, 32),
        empty($beforeRoles) ? 'none' : implode(',', $beforeRoles),
        empty($afterRoles) ? 'none' : implode(',', $afterRoles),
        empty($gains) ? 'none' : implode(',', $gains),
        empty($losses) ? 'none' : implode(',', $losses)
    );
}

echo "\n\n=== RAW ROLE-TO-PERMISSION MAPPINGS FROM DATABASE ===\n";
foreach ($roleMap as $role => $perms) {
    $isSystem = isset($rolesByCode[$role]) ? (int) $rolesByCode[$role]->is_system : 0;
    echo "Role [{$role}] (is_system: {$isSystem}, " . count($perms) . " permissions):\n";
    echo "  " . implode(', ', $perms) . "\n\n";
}
