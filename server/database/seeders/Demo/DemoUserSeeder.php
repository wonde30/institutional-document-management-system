<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Models\AuthorizationScope;
use App\Models\OrganizationMembership;
use App\Models\OrganizationUnit;
use App\Models\PrimaryMembership;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    /**
     * Seed 12 synthetic demonstration accounts with dynamic RBAC and organizational memberships.
     */
    public function run(): void
    {
        $now = Carbon::now();
        $passwordPlain = env('DMS_DEMO_SEED_PASSWORD', 'DemoSeedPassword2026!');
        $hashedPassword = Hash::make($passwordPlain);

        $demoAccounts = [
            [
                'email' => 'demo.admin@example.test',
                'name' => 'Abebe Bekele',
                'unit_code' => 'WU',
                'role_code' => 'super_admin',
                'scope_kind' => 'global',
                'position' => 'Chief Information Officer & System Administrator',
                'emp_number' => 'WU-EMP-0001',
                'bio' => 'System administrator for Wollo University enterprise computing systems.',
            ],
            [
                'email' => 'demo.records@example.test',
                'name' => 'Almaz Kebede',
                'unit_code' => 'CRO',
                'role_code' => 'records_officer',
                'scope_kind' => 'global',
                'position' => 'Senior Records and Correspondence Officer',
                'emp_number' => 'WU-EMP-0002',
                'bio' => 'Central registry officer managing incoming/outgoing letters and dispatch tracking.',
            ],
            [
                'email' => 'demo.head.cs@example.test',
                'name' => 'Dr. Dawit Tadesse',
                'unit_code' => 'CS',
                'role_code' => 'department_head',
                'scope_kind' => 'unit',
                'position' => 'Head, Department of Computer Science',
                'emp_number' => 'WU-EMP-0003',
                'bio' => 'Associate Professor and Head of Computer Science Department.',
            ],
            [
                'email' => 'demo.head.eng@example.test',
                'name' => 'Dr. Selamawit Hailu',
                'unit_code' => 'COTE',
                'role_code' => 'department_head',
                'scope_kind' => 'subtree',
                'position' => 'Dean, College of Technology and Engineering',
                'emp_number' => 'WU-EMP-0004',
                'bio' => 'Dean overseeing Kombolcha campus engineering faculties.',
            ],
            [
                'email' => 'demo.approver@example.test',
                'name' => 'Prof. Mengistu Haile',
                'unit_code' => 'VPAA',
                'role_code' => 'department_head',
                'scope_kind' => 'global',
                'position' => 'Vice President for Academic Affairs',
                'emp_number' => 'WU-EMP-0005',
                'bio' => 'Executive approval authority for academic regulations, curriculum, and degrees.',
            ],
            [
                'email' => 'demo.archivist@example.test',
                'name' => 'Tewodros Kassaye',
                'unit_code' => 'ARCH',
                'role_code' => 'archivist',
                'scope_kind' => 'global',
                'position' => 'Head University Archivist & Records Retention Lead',
                'emp_number' => 'WU-EMP-0006',
                'bio' => 'Specialist in archival accessioning, physical custody, and disposition workflows.',
            ],
            [
                'email' => 'demo.research@example.test',
                'name' => 'Dr. Bethelhem Girma',
                'unit_code' => 'RCSD',
                'role_code' => 'department_head',
                'scope_kind' => 'global',
                'position' => 'Director, Research & Community Services Directorate',
                'emp_number' => 'WU-EMP-0007',
                'bio' => 'Coordinates institutional research publications, patents, and community innovation.',
            ],
            [
                'email' => 'demo.qa@example.test',
                'name' => 'Mulugeta Alemu',
                'unit_code' => 'QAO',
                'role_code' => 'admin',
                'scope_kind' => 'global',
                'position' => 'Institutional Quality Assurance & Accreditation Director',
                'emp_number' => 'WU-EMP-0008',
                'bio' => 'Leads HERQA accreditation cycles and institutional compliance reviews.',
            ],
            [
                'email' => 'demo.hr@example.test',
                'name' => 'Hanna Worku',
                'unit_code' => 'HRMD',
                'role_code' => 'staff',
                'scope_kind' => 'global',
                'position' => 'Senior Human Resource Specialist',
                'emp_number' => 'WU-EMP-0009',
                'bio' => 'Responsible for academic personnel appointments, qualifications, and credentials.',
            ],
            [
                'email' => 'demo.finance@example.test',
                'name' => 'Yonas Berhanu',
                'unit_code' => 'FPD',
                'role_code' => 'staff',
                'scope_kind' => 'unit',
                'position' => 'Senior Finance & Procurement Analyst',
                'emp_number' => 'WU-EMP-0010',
                'bio' => 'Oversees capital budgets, financial audits, and procurement clearances.',
            ],
            [
                'email' => 'demo.auditor@example.test',
                'name' => 'Kalkidan Assefa',
                'unit_code' => 'IAO',
                'role_code' => 'staff',
                'scope_kind' => 'global',
                'position' => 'Chief Internal Auditor',
                'emp_number' => 'WU-EMP-0011',
                'bio' => 'Independent compliance and regulatory auditor for institutional operations.',
            ],
            [
                'email' => 'demo.staff@example.test',
                'name' => 'Ephrem Negash',
                'unit_code' => 'CS',
                'role_code' => 'staff',
                'scope_kind' => 'unit',
                'position' => 'Lecturer & Researcher, Computer Science Department',
                'emp_number' => 'WU-EMP-0012',
                'bio' => 'Faculty member teaching software engineering and distributed systems.',
            ],
        ];

        foreach ($demoAccounts as $acc) {
            // 1. Create or Update User
            $user = User::updateOrCreate(
                ['email' => $acc['email']],
                [
                    'name' => $acc['name'],
                    'password' => $hashedPassword,
                    'status' => 'active',
                    'email_verified_at' => $now,
                    'authz_version' => 1,
                ]
            );

            // 2. Resolve Unit
            $unit = OrganizationUnit::where('code', $acc['unit_code'])->first();
            $unitId = $unit ? $unit->id : 1;

            // 3. Create Organization Membership
            $membership = OrganizationMembership::firstOrCreate(
                ['user_id' => $user->id, 'unit_id' => $unitId],
                [
                    'position_title' => $acc['position'],
                    'starts_at' => $now->subMonths(6),
                    'created_by' => $user->id,
                    'created_at' => $now,
                ]
            );

            // 4. Set Primary Membership
            PrimaryMembership::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'membership_id' => $membership->id,
                    'selected_at' => $now,
                ]
            );

            // 5. Create Staff Profile
            StaffProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'employee_number' => $acc['emp_number'],
                    'biography' => $acc['bio'],
                ]
            );

            // 6. Assign Role with Scope
            $role = Role::where('code', $acc['role_code'])->first();
            if ($role) {
                // Resolve Scope
                $scope = match ($acc['scope_kind']) {
                    'global' => AuthorizationScope::where('kind', 'global')->first(),
                    'subtree' => AuthorizationScope::where('kind', 'subtree')->where('unit_id', $unitId)->first()
                        ?? AuthorizationScope::where('kind', 'global')->first(),
                    default => AuthorizationScope::where('kind', 'unit')->where('unit_id', $unitId)->first()
                        ?? AuthorizationScope::where('kind', 'global')->first(),
                };

                $scopeId = $scope ? $scope->id : 1;

                // Sync Role in user_roles table
                $user->roles()->syncWithoutDetaching([
                    $role->id => [
                        'scope_id' => $scopeId,
                        'granted_by' => $user->id,
                        'granted_at' => $now,
                    ],
                ]);
            }
        }
    }
}
