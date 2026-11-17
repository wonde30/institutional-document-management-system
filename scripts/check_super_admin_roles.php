<?php
require __DIR__ . '/../server/vendor/autoload.php';
$app = require_once __DIR__ . '/../server/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Role;
use App\Models\User;

echo "=== ROLES HOLDING system.configure ===\n";
$roles = DB::select("
    SELECT r.id, r.code, r.name, r.is_system, r.is_protected 
    FROM roles r 
    JOIN role_permissions rp ON rp.role_id = r.id 
    JOIN permissions p ON p.id = rp.permission_id 
    WHERE p.code = 'system.configure'
");
foreach ($roles as $r) {
    echo "- Role ID {$r->id}: {$r->code} ({$r->name}) | system={$r->is_system}, protected={$r->is_protected}\n";
}

echo "\n=== USERS WITH super_admin ROLE ===\n";
$superUsers = DB::select("
    SELECT u.id, u.email, u.name, s.kind 
    FROM users u 
    JOIN user_roles ur ON ur.user_id = u.id 
    JOIN roles r ON r.id = ur.role_id 
    JOIN authorization_scopes s ON s.id = ur.scope_id 
    WHERE r.code = 'super_admin'
");
foreach ($superUsers as $u) {
    echo "- User ID {$u->id}: {$u->email} ({$u->name}) [Scope: {$u->kind}]\n";
}

echo "\n=== CHECK: DOES ANY OTHER ROLE HOLD system.configure? ===\n";
$nonSuperAdminConfigRoles = DB::select("
    SELECT r.id, r.code, r.name 
    FROM roles r 
    JOIN role_permissions rp ON rp.role_id = r.id 
    JOIN permissions p ON p.id = rp.permission_id 
    WHERE p.code = 'system.configure' AND r.code != 'super_admin'
");
echo "Count: " . count($nonSuperAdminConfigRoles) . "\n";
foreach ($nonSuperAdminConfigRoles as $r) {
    echo "- Non-super_admin role with system.configure: {$r->code}\n";
}
