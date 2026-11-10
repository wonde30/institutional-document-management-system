<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Models\AuthorizationScope;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserPermission;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoRbacSeeder extends Seeder
{
    /**
     * Seed fine-grained direct user permissions for specialized demonstration roles.
     */
    public function run(): void
    {
        $now = Carbon::now();
        $globalScope = AuthorizationScope::where('kind', 'global')->first();
        $globalScopeId = $globalScope ? $globalScope->id : 1;

        $userSpecialGrants = [
            'demo.approver@example.test' => [
                'workflow.view', 'workflow.submit', 'workflow.review', 'workflow.approve', 'workflow.reject',
                'signatures.request', 'signatures.verify', 'documents.view', 'documents.download',
            ],
            'demo.auditor@example.test' => [
                'audit.view', 'reports.view', 'reports.export', 'documents.view', 'documents.download',
                'organization.view', 'roles.view', 'permissions.view',
            ],
            'demo.hr@example.test' => [
                'staff.view', 'staff.update', 'documents.view', 'documents.create', 'documents.upload', 'documents.download',
            ],
            'demo.qa@example.test' => [
                'accreditation.view', 'accreditation.manage', 'accreditation.submit_evidence', 'accreditation.review_evidence', 'accreditation.export',
                'documents.view', 'documents.download', 'reports.view',
            ],
            'demo.research@example.test' => [
                'research.view', 'research.create', 'research.update', 'research.publish', 'research.withdraw',
                'projects.view', 'projects.submit', 'documents.view', 'documents.create', 'documents.upload', 'documents.download',
            ],
        ];

        foreach ($userSpecialGrants as $email => $permCodes) {
            $user = User::where('email', $email)->first();
            if (! $user) {
                continue;
            }

            $permissions = Permission::whereIn('code', $permCodes)->get();
            foreach ($permissions as $perm) {
                UserPermission::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'permission_id' => $perm->id,
                    ],
                    [
                        'scope_id' => $globalScopeId,
                        'granted_by' => $user->id,
                        'granted_at' => $now,
                    ]
                );
            }
        }
    }
}
