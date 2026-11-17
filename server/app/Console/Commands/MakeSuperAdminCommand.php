<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuthorizationScope;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MakeSuperAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:make-super-admin 
                            {email : The institutional email address of the user to promote} 
                            {--force : Bypass interactive safety confirmation}';

    /**
     * Alternative signatures / aliases for this command.
     *
     * @var array<int, string>
     */
    protected $aliases = ['auth:recover-admin'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Emergency recovery command: Assign Super Admin role, activate account, invalidate stale sessions, and log immutable audit record';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Enforce CLI-only runtime check
        if (php_sapi_name() !== 'cli' && ! app()->runningInConsole()) {
            $this->error('Access Denied: This command may only be executed from an authorized server terminal.');
            return Command::FAILURE;
        }

        $email = trim((string) $this->argument('email'));

        // 1. Exact email format validation
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Invalid email address format: '{$email}'.");
            return Command::FAILURE;
        }

        // 2. Exact user lookup
        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->error("User with email [{$email}] not found in database.");
            return Command::FAILURE;
        }

        // 3. Resolve native super_admin role
        $superAdminRole = Role::where('code', 'super_admin')->first();
        if (! $superAdminRole) {
            $this->error("Institutional role [super_admin] does not exist in the database.");
            return Command::FAILURE;
        }

        // 4. Safe operator confirmation
        $environment = app()->environment();
        if (! $this->option('force')) {
            $confirmed = $this->confirm(
                "CRITICAL: Elevate user [{$user->name}] ({$email}) to Super Admin in [{$environment}] environment?",
                false
            );

            if (! $confirmed) {
                $this->warn('Emergency promotion aborted by operator.');
                return Command::FAILURE;
            }
        }

        // 5. Atomic database transaction
        DB::transaction(function () use ($user, $superAdminRole, $email): void {
            // Ensure global authorization scope exists
            $globalScope = AuthorizationScope::firstOrCreate([
                'kind' => 'global',
                'unit_id' => null,
            ]);

            // Ensure role has system.configure capability
            $sysConfigPerm = Permission::where('code', 'system.configure')->first();
            if ($sysConfigPerm && ! $superAdminRole->permissions()->where('permissions.id', $sysConfigPerm->id)->exists()) {
                $superAdminRole->permissions()->attach($sysConfigPerm->id);
            }

            // Attach role in global scope if not already granted
            $hasRole = DB::table('user_roles')
                ->where('user_id', $user->id)
                ->where('role_id', $superAdminRole->id)
                ->where('scope_id', $globalScope->id)
                ->exists();

            if (! $hasRole) {
                DB::table('user_roles')->updateOrInsert(
                    [
                        'user_id' => $user->id,
                        'role_id' => $superAdminRole->id,
                        'scope_id' => $globalScope->id,
                    ],
                    [
                        'granted_by' => $user->id,
                        'granted_at' => Carbon::now(),
                        'expires_at' => null,
                    ]
                );
            }

            // Ensure account status is active
            $user->status = 'active';

            // Invalidate existing sessions immediately across all client browsers
            $user->authz_version = ($user->authz_version ?? 0) + 1;
            $user->save();

            // Clear any existing session records
            if (DB::getSchemaBuilder()->hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }

            // Record immutable institutional audit log
            AuditService::log(
                action: 'user.emergency_admin_recovered',
                targetType: User::class,
                targetId: (string) $user->id,
                details: [
                    'promoted_by' => 'cli_artisan_console',
                    'email' => $email,
                    'user_id' => $user->id,
                    'authz_version' => $user->authz_version,
                    'scope' => 'global',
                    'capability' => 'system.configure',
                    'timestamp' => Carbon::now()->toIso8601String(),
                ],
                actorId: $user->id
            );
        });

        $this->info("SUCCESS: User '{$user->name}' ({$email}) activated and elevated to Super Admin.");
        $this->info("Authorization version incremented to {$user->authz_version} (all prior browser sessions terminated).");
        $this->info("Institutional audit event recorded in audit_events ledger.");

        return Command::SUCCESS;
    }
}
