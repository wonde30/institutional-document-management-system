<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class DemoAccountsListCommand extends Command
{
    protected $signature = 'dms:demo-accounts';

    protected $description = 'Display synthetic demonstration account names, organizational assignments, and roles without exposing credentials';

    public function handle(): int
    {
        $this->info('========================================================================');
        $this->info(' Wollo University DMS — Synthetic Demonstration Accounts Inventory     ');
        $this->info('========================================================================');
        $this->line('Notice: Passwords are redacted for security. See local storage/demo_credentials.json');
        $this->line('');

        $demoUsers = User::with([
            'primaryMembershipRecord.membership.unit',
            'userRoles.role',
            'userRoles.scope.unit',
            'userPermissions.permission',
        ])
        ->where('email', 'like', 'demo.%@wu.edu.et')
        ->orderBy('id')
        ->get();

        if ($demoUsers->isEmpty()) {
            $this->warn('No demonstration accounts found. Run "php artisan dms:demo-provision" first.');
            return 1;
        }

        $rows = [];
        foreach ($demoUsers as $u) {
            $pm = $u->primaryMembershipRecord?->membership;
            $unitStr = $pm ? ($pm->unit?->code . ' (' . $pm->unit?->name . ')') : 'N/A';
            $titleStr = $pm?->position_title ?? 'N/A';

            $rolesList = $u->userRoles->map(function ($ur) {
                $scopeTarget = $ur->scope?->unit?->code ? (':' . $ur->scope->unit->code) : '';
                return ($ur->role?->code ?? 'unknown') . ' [' . ($ur->scope?->kind ?? 'global') . $scopeTarget . ']';
            })->implode(', ');

            $directPermsCount = $u->userPermissions->count();
            $directPermsStr = $directPermsCount > 0 ? ($directPermsCount . ' direct') : 'none';

            $rows[] = [
                $u->id,
                $u->name,
                $u->email,
                $titleStr,
                $unitStr,
                $rolesList,
                $directPermsStr,
                $u->status,
            ];
        }

        $this->table(
            ['ID', 'Display Name', 'Email Address', 'Position Title', 'Unit', 'Role & Scope', 'Direct Perms', 'Status'],
            $rows
        );

        $this->line('');
        $this->info("Total Demonstration Accounts: {$demoUsers->count()}");
        $this->info('Credentials location: server/storage/demo_credentials.json (Git-ignored, restricted 0600)');

        return 0;
    }
}
