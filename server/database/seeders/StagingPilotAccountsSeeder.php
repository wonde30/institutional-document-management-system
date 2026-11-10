<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StagingPilotAccountsSeeder extends Seeder
{
    /**
     * Seed isolated institutional pilot organizational units, scopes, and user accounts.
     */
    public function run(): void
    {
        $now = now()->format('Y-m-d H:i:s.u');
        $passwordHash = Hash::make('PilotPassword2026!');

        // 1. Resolve unit types
        $directorateTypeId = DB::table('unit_types')->where('code', 'directorate')->value('id') ?? 5;
        $departmentTypeId = DB::table('unit_types')->where('code', 'department')->value('id') ?? 4;
        $officeTypeId = DB::table('unit_types')->where('code', 'office')->value('id') ?? 6;

        $rootUnitId = DB::table('organization_units')->where('code', 'WU')->value('id') ?? 1;

        // 2. Provision Pilot Organization Units
        $pilotUnits = [
            'REG' => ['name' => 'Central Registry & Document Intake Office', 'type' => $officeTypeId],
            'EED' => ['name' => 'Department of Electrical & Computer Engineering', 'type' => $departmentTypeId],
            'DISP' => ['name' => 'Central Institutional Dispatch Office', 'type' => $officeTypeId],
            'REC' => ['name' => 'Office of Academic Records & Registration', 'type' => $officeTypeId],
            'ARC' => ['name' => 'University Central Digital Archive', 'type' => $officeTypeId],
            'RES' => ['name' => 'Research & Community Service Directorate', 'type' => $directorateTypeId],
            'QA' => ['name' => 'Institutional Quality Assurance Directorate', 'type' => $directorateTypeId],
            'ICT' => ['name' => 'ICT Infrastructure & Systems Directorate', 'type' => $directorateTypeId],
        ];

        $unitIds = [];
        foreach ($pilotUnits as $code => $data) {
            $unit = DB::table('organization_units')->where('code', $code)->first();
            if (! $unit) {
                $unitIds[$code] = DB::table('organization_units')->insertGetId([
                    'parent_id' => $rootUnitId,
                    'unit_type_id' => $data['type'],
                    'code' => $code,
                    'name' => $data['name'],
                    'active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $unitIds[$code] = $unit->id;
            }
        }

        // 3. Provision Authorization Scopes for Units
        $scopeIds = [];
        // Global scope
        $globalScope = DB::table('authorization_scopes')->where('kind', 'global')->first();
        $scopeIds['global'] = $globalScope ? $globalScope->id : 1;

        foreach ($unitIds as $code => $uid) {
            $scope = DB::table('authorization_scopes')
                ->where('kind', 'unit')
                ->where('unit_id', $uid)
                ->first();

            if (! $scope) {
                $scopeIds[$code] = DB::table('authorization_scopes')->insertGetId([
                    'kind' => 'unit',
                    'unit_id' => $uid,
                ]);
            } else {
                $scopeIds[$code] = $scope->id;
            }
        }

        // 4. Resolve Roles
        $roles = DB::table('roles')->pluck('id', 'code')->toArray();

        // 5. Define 9 Pilot Personas
        $pilotAccounts = [
            [
                'email' => 'pilot.admin@wu.edu.et',
                'name' => 'Staging Pilot Administrator',
                'position' => 'Chief Staging Systems Administrator',
                'unit_code' => 'ICT',
                'role_code' => 'admin',
                'scope_key' => 'global',
            ],
            [
                'email' => 'pilot.registry@wu.edu.et',
                'name' => 'Staging Central Registry Officer',
                'position' => 'Head of Document Intake & Registry',
                'unit_code' => 'REG',
                'role_code' => 'records_officer',
                'scope_key' => 'REG',
            ],
            [
                'email' => 'pilot.head.ee@wu.edu.et',
                'name' => 'Staging Electrical Eng Department Head',
                'position' => 'Department Head, Electrical Engineering',
                'unit_code' => 'EED',
                'role_code' => 'department_head',
                'scope_key' => 'EED',
            ],
            [
                'email' => 'pilot.staff@wu.edu.et',
                'name' => 'Staging Faculty Academic Staff',
                'position' => 'Assistant Professor & Academic Staff',
                'unit_code' => 'EED',
                'role_code' => 'staff',
                'scope_key' => 'EED',
            ],
            [
                'email' => 'pilot.dispatch@wu.edu.et',
                'name' => 'Staging University Dispatch Officer',
                'position' => 'Institutional Dispatch Coordinator',
                'unit_code' => 'DISP',
                'role_code' => 'records_officer',
                'scope_key' => 'DISP',
            ],
            [
                'email' => 'pilot.records@wu.edu.et',
                'name' => 'Staging Academic Records Controller',
                'position' => 'Senior Records Management Officer',
                'unit_code' => 'REC',
                'role_code' => 'document_controller',
                'scope_key' => 'REC',
            ],
            [
                'email' => 'pilot.archivist@wu.edu.et',
                'name' => 'Staging Institutional Archivist',
                'position' => 'Lead Digital & Physical Custody Archivist',
                'unit_code' => 'ARC',
                'role_code' => 'archivist',
                'scope_key' => 'ARC',
            ],
            [
                'email' => 'pilot.research@wu.edu.et',
                'name' => 'Staging Research Directorate Officer',
                'position' => 'Research Dissemination Officer',
                'unit_code' => 'RES',
                'role_code' => 'staff',
                'scope_key' => 'RES',
            ],
            [
                'email' => 'pilot.qa@wu.edu.et',
                'name' => 'Staging Quality Assurance Director',
                'position' => 'Institutional Accreditation Evaluator',
                'unit_code' => 'QA',
                'role_code' => 'document_controller',
                'scope_key' => 'QA',
            ],
        ];

        $adminUserId = DB::table('users')->where('email', 'admin@wu.edu.et')->value('id') ?? 1;

        foreach ($pilotAccounts as $acc) {
            $user = DB::table('users')->where('email', $acc['email'])->first();
            if (! $user) {
                $userId = DB::table('users')->insertGetId([
                    'name' => $acc['name'],
                    'email' => $acc['email'],
                    'password' => $passwordHash,
                    'status' => 'active',
                    'authz_version' => 1,
                    'email_verified_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $userId = $user->id;
            }

            // Membership
            $targetUnitId = $unitIds[$acc['unit_code']];
            $membership = DB::table('organization_memberships')
                ->where('user_id', $userId)
                ->where('unit_id', $targetUnitId)
                ->first();

            if (! $membership) {
                $membershipId = DB::table('organization_memberships')->insertGetId([
                    'user_id' => $userId,
                    'unit_id' => $targetUnitId,
                    'position_title' => $acc['position'],
                    'starts_at' => $now,
                    'ends_at' => null,
                    'created_by' => $adminUserId,
                    'created_at' => $now,
                ]);
            } else {
                $membershipId = $membership->id;
            }

            DB::table('primary_memberships')->updateOrInsert(
                ['user_id' => $userId],
                ['membership_id' => $membershipId, 'selected_at' => $now]
            );

            // Scoped Role Assignment
            $roleId = $roles[$acc['role_code']] ?? null;
            $targetScopeId = $scopeIds[$acc['scope_key']] ?? 1;

            if ($roleId && $targetScopeId) {
                $hasRole = DB::table('user_roles')
                    ->where('user_id', $userId)
                    ->where('role_id', $roleId)
                    ->where('scope_id', $targetScopeId)
                    ->exists();

                if (! $hasRole) {
                    DB::table('user_roles')->insert([
                        'user_id' => $userId,
                        'role_id' => $roleId,
                        'scope_id' => $targetScopeId,
                        'granted_by' => $adminUserId,
                        'granted_at' => $now,
                        'expires_at' => null,
                    ]);
                }
            }
        }
    }
}
