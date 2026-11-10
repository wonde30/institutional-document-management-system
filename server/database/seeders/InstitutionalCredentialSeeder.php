<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class InstitutionalCredentialSeeder extends Seeder
{
    /**
     * Seed comprehensive, realistic institutional credentials covering all roles,
     * dynamic database-driven permissions, single-user direct permission overrides,
     * and both active and deactivated user accounts.
     */
    public function run(): void
    {
        $now = now()->format('Y-m-d H:i:s.u');
        $defaultPassword = Hash::make('Password123!');

        // 1. Resolve unit types
        $collegeTypeId = DB::table('unit_types')->where('code', 'college')->value('id') ?? 2;
        $departmentTypeId = DB::table('unit_types')->where('code', 'department')->value('id') ?? 4;
        $directorateTypeId = DB::table('unit_types')->where('code', 'directorate')->value('id') ?? 5;
        $officeTypeId = DB::table('unit_types')->where('code', 'office')->value('id') ?? 6;

        $rootUnit = DB::table('organization_units')->where('code', 'WU')->first();
        $rootUnitId = $rootUnit ? $rootUnit->id : 1;

        // 2. Provision Core Institutional Organization Units
        $units = [
            'COTE' => [
                'name' => 'College of Technology & Informatics',
                'type' => $collegeTypeId,
                'parent_id' => $rootUnitId,
            ],
            'SE' => [
                'name' => 'Department of Software Engineering',
                'type' => $departmentTypeId,
                'parent_code' => 'COTE',
            ],
            'EE' => [
                'name' => 'Department of Electrical & Computer Engineering',
                'type' => $departmentTypeId,
                'parent_code' => 'COTE',
            ],
            'REG' => [
                'name' => 'Central Registry & Document Intake Office',
                'type' => $officeTypeId,
                'parent_id' => $rootUnitId,
            ],
            'DISP' => [
                'name' => 'Central Institutional Dispatch Office',
                'type' => $officeTypeId,
                'parent_id' => $rootUnitId,
            ],
            'ARC' => [
                'name' => 'University Central Digital Archive',
                'type' => $officeTypeId,
                'parent_id' => $rootUnitId,
            ],
            'QA' => [
                'name' => 'Institutional Quality Assurance Directorate',
                'type' => $directorateTypeId,
                'parent_id' => $rootUnitId,
            ],
            'ICT' => [
                'name' => 'ICT Infrastructure & Systems Directorate',
                'type' => $directorateTypeId,
                'parent_id' => $rootUnitId,
            ],
        ];

        $unitIds = ['WU' => $rootUnitId];

        // First pass: colleges and direct child units of root
        foreach ($units as $code => $data) {
            $parentId = $data['parent_id'] ?? null;
            if ($parentId !== null) {
                $existing = DB::table('organization_units')->where('code', $code)->first();
                if (! $existing) {
                    $unitIds[$code] = DB::table('organization_units')->insertGetId([
                        'parent_id' => $parentId,
                        'unit_type_id' => $data['type'],
                        'code' => $code,
                        'name' => $data['name'],
                        'active' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } else {
                    $unitIds[$code] = $existing->id;
                }
            }
        }

        // Second pass: child departments
        foreach ($units as $code => $data) {
            if (isset($data['parent_code']) && isset($unitIds[$data['parent_code']])) {
                $parentId = $unitIds[$data['parent_code']];
                $existing = DB::table('organization_units')->where('code', $code)->first();
                if (! $existing) {
                    $unitIds[$code] = DB::table('organization_units')->insertGetId([
                        'parent_id' => $parentId,
                        'unit_type_id' => $data['type'],
                        'code' => $code,
                        'name' => $data['name'],
                        'active' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } else {
                    $unitIds[$code] = $existing->id;
                }
            }
        }

        // 3. Provision Authorization Scopes
        $scopeIds = [];

        // Global scope
        $globalScope = DB::table('authorization_scopes')->where('kind', 'global')->first();
        if (! $globalScope) {
            $scopeIds['global'] = DB::table('authorization_scopes')->insertGetId([
                'kind' => 'global',
                'unit_id' => null,
            ]);
        } else {
            $scopeIds['global'] = $globalScope->id;
        }

        // Unit scopes
        foreach ($unitIds as $code => $uid) {
            if ($code === 'WU') continue;
            $scope = DB::table('authorization_scopes')
                ->where('kind', 'unit')
                ->where('unit_id', $uid)
                ->first();

            if (! $scope) {
                $scopeIds["unit_{$code}"] = DB::table('authorization_scopes')->insertGetId([
                    'kind' => 'unit',
                    'unit_id' => $uid,
                ]);
            } else {
                $scopeIds["unit_{$code}"] = $scope->id;
            }
        }

        // Subtree scope for College of Technology
        if (isset($unitIds['COTE'])) {
            $coteSubtreeScope = DB::table('authorization_scopes')
                ->where('kind', 'subtree')
                ->where('unit_id', $unitIds['COTE'])
                ->first();

            if (! $coteSubtreeScope) {
                $scopeIds['subtree_COTE'] = DB::table('authorization_scopes')->insertGetId([
                    'kind' => 'subtree',
                    'unit_id' => $unitIds['COTE'],
                ]);
            } else {
                $scopeIds['subtree_COTE'] = $coteSubtreeScope->id;
            }
        }

        // 4. Resolve System Roles from Database
        $roles = DB::table('roles')->pluck('id', 'code')->toArray();

        // 5. System Administrator User Reference (for audit accountability)
        $adminUserId = DB::table('users')->where('email', 'admin@wu.edu.et')->value('id');
        if (! $adminUserId) {
            $adminUserId = DB::table('users')->insertGetId([
                'name' => 'Wollo University System Administrator',
                'email' => 'admin@wu.edu.et',
                'password' => $defaultPassword,
                'status' => 'active',
                'authz_version' => 1,
                'email_verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Assign root membership
            $membershipId = DB::table('organization_memberships')->insertGetId([
                'user_id' => $adminUserId,
                'unit_id' => $rootUnitId,
                'position_title' => 'Chief System Administrator',
                'starts_at' => $now,
                'ends_at' => null,
                'created_by' => $adminUserId,
                'created_at' => $now,
            ]);

            DB::table('primary_memberships')->updateOrInsert(
                ['user_id' => $adminUserId],
                ['membership_id' => $membershipId, 'selected_at' => $now]
            );

            if (isset($roles['super_admin'])) {
                DB::table('user_roles')->updateOrInsert([
                    'user_id' => $adminUserId,
                    'role_id' => $roles['super_admin'],
                    'scope_id' => $scopeIds['global'],
                ], [
                    'granted_by' => $adminUserId,
                    'granted_at' => $now,
                    'expires_at' => null,
                ]);
            }
        }

        // 6. Institutional Persona Credentials across All Roles
        $institutionalAccounts = [
            [
                'email' => 'sysadmin@wu.edu.et',
                'name' => 'Eng. Dawit Haile',
                'position' => 'Director of ICT Infrastructure',
                'unit_code' => 'ICT',
                'role_code' => 'admin',
                'scope_key' => 'global',
                'status' => 'active',
            ],
            [
                'email' => 'dean.technology@wu.edu.et',
                'name' => 'Dr. Mesfin Tadesse',
                'position' => 'Dean, College of Technology & Informatics',
                'unit_code' => 'COTE',
                'role_code' => 'department_head',
                'scope_key' => 'subtree_COTE',
                'status' => 'active',
            ],
            [
                'email' => 'head.se@wu.edu.et',
                'name' => 'Dr. Bethlehem Yohannes',
                'position' => 'Head, Department of Software Engineering',
                'unit_code' => 'SE',
                'role_code' => 'department_head',
                'scope_key' => 'unit_SE',
                'status' => 'active',
            ],
            [
                'email' => 'registrar@wu.edu.et',
                'name' => 'Almaz Bekele',
                'position' => 'Central Registry & Document Intake Lead',
                'unit_code' => 'REG',
                'role_code' => 'records_officer',
                'scope_key' => 'unit_REG',
                'status' => 'active',
            ],
            [
                'email' => 'archivist@wu.edu.et',
                'name' => 'Kassahun Gizaw',
                'position' => 'Chief Institutional Archivist',
                'unit_code' => 'ARC',
                'role_code' => 'archivist',
                'scope_key' => 'unit_ARC',
                'status' => 'active',
            ],
            [
                'email' => 'qa.director@wu.edu.et',
                'name' => 'Dr. Rahel Assefa',
                'position' => 'Director, Quality Assurance & Accreditation',
                'unit_code' => 'QA',
                'role_code' => 'document_controller',
                'scope_key' => 'unit_QA',
                'status' => 'active',
            ],
            [
                'email' => 'staff.tech@wu.edu.et',
                'name' => 'Solomon Kebede',
                'position' => 'Assistant Lecturer, Software Engineering',
                'unit_code' => 'SE',
                'role_code' => 'staff',
                'scope_key' => 'unit_SE',
                'status' => 'active',
            ],
            // 7. Single User with Direct Single-User Permissions Override
            [
                'email' => 'auditor.direct@wu.edu.et',
                'name' => 'Hanan Mohammed (Direct Permissions Auditor)',
                'position' => 'Senior Institutional Compliance Auditor',
                'unit_code' => 'QA',
                'role_code' => 'staff',
                'scope_key' => 'unit_QA',
                'status' => 'active',
                'direct_permissions' => [
                    ['code' => 'audit.view', 'scope' => 'global'],
                    ['code' => 'documents.delete', 'scope' => 'global'],
                    ['code' => 'accreditation.review_evidence', 'scope' => 'unit_QA'],
                ],
            ],
            // 8. Inactive (Suspended) User Account
            [
                'email' => 'suspended.staff@wu.edu.et',
                'name' => 'Yared Fikre (Deactivated Account)',
                'position' => 'Former Laboratory Technician',
                'unit_code' => 'SE',
                'role_code' => 'staff',
                'scope_key' => 'unit_SE',
                'status' => 'inactive',
            ],
            // 9. Locked User Account
            [
                'email' => 'locked.account@wu.edu.et',
                'name' => 'Tigist Alem (Security Locked)',
                'position' => 'Procurement Records Assistant',
                'unit_code' => 'REG',
                'role_code' => 'records_officer',
                'scope_key' => 'unit_REG',
                'status' => 'locked',
            ],
        ];

        // Resolve all permissions by code
        $permissionsByCode = DB::table('permissions')->pluck('id', 'code')->toArray();

        foreach ($institutionalAccounts as $acc) {
            $user = DB::table('users')->where('email', $acc['email'])->first();
            if (! $user) {
                $userId = DB::table('users')->insertGetId([
                    'name' => $acc['name'],
                    'email' => $acc['email'],
                    'password' => $defaultPassword,
                    'status' => $acc['status'],
                    'authz_version' => 1,
                    'email_verified_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $userId = $user->id;
                DB::table('users')->where('id', $userId)->update([
                    'name' => $acc['name'],
                    'status' => $acc['status'],
                    'updated_at' => $now,
                ]);
            }

            // Membership
            $targetUnitId = $unitIds[$acc['unit_code']] ?? $rootUnitId;
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
                DB::table('organization_memberships')->where('id', $membershipId)->update([
                    'position_title' => $acc['position'],
                ]);
            }

            DB::table('primary_memberships')->updateOrInsert(
                ['user_id' => $userId],
                ['membership_id' => $membershipId, 'selected_at' => $now]
            );

            // Scoped Role Assignment
            $roleId = $roles[$acc['role_code']] ?? null;
            $targetScopeId = $scopeIds[$acc['scope_key']] ?? $scopeIds['global'];

            if ($roleId && $targetScopeId) {
                DB::table('user_roles')->updateOrInsert(
                    [
                        'user_id' => $userId,
                        'role_id' => $roleId,
                        'scope_id' => $targetScopeId,
                    ],
                    [
                        'granted_by' => $adminUserId,
                        'granted_at' => $now,
                        'expires_at' => null,
                    ]
                );
            }

            // Direct User Permissions (Single-User Override Guaranteed)
            if (! empty($acc['direct_permissions'])) {
                foreach ($acc['direct_permissions'] as $dp) {
                    $pCode = $dp['code'];
                    $permId = $permissionsByCode[$pCode] ?? null;
                    $pScopeId = $scopeIds[$dp['scope']] ?? $scopeIds['global'];

                    if ($permId && $pScopeId) {
                        DB::table('user_permissions')->updateOrInsert(
                            [
                                'user_id' => $userId,
                                'permission_id' => $permId,
                                'scope_id' => $pScopeId,
                            ],
                            [
                                'granted_by' => $adminUserId,
                                'granted_at' => $now,
                                'expires_at' => null,
                            ]
                        );
                    }
                }
            }
        }
    }
}
