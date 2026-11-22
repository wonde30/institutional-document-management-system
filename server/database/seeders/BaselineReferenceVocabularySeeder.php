<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BaselineReferenceVocabularySeeder extends Seeder
{
    /**
     * Seed pure baseline vocabulary in strict foreign key order.
     */
    public function run(): void
    {
        $now = now()->format('Y-m-d H:i:s.u');

        // 1. Unit Types
        $unitTypes = [
            ['code' => 'university', 'name' => 'University', 'description' => 'Top-level university administration', 'active' => 1],
            ['code' => 'college', 'name' => 'College', 'description' => 'Academic College or Faculty', 'active' => 1],
            ['code' => 'institute', 'name' => 'Institute', 'description' => 'Research or specialized institute', 'active' => 1],
            ['code' => 'department', 'name' => 'Department', 'description' => 'Academic or administrative department', 'active' => 1],
            ['code' => 'directorate', 'name' => 'Directorate', 'description' => 'Administrative directorate', 'active' => 1],
            ['code' => 'office', 'name' => 'Office', 'description' => 'Executive or specialized office', 'active' => 1],
        ];
        foreach ($unitTypes as $ut) {
            DB::table('unit_types')->updateOrInsert(['code' => $ut['code']], $ut);
        }

        // 2. Baseline Permissions
        $permissions = [
            ['code' => 'users.view', 'name' => 'Users view', 'active' => 1],
            ['code' => 'users.create', 'name' => 'Users create', 'active' => 1],
            ['code' => 'users.update', 'name' => 'Users update', 'active' => 1],
            ['code' => 'users.deactivate', 'name' => 'Users deactivate', 'active' => 1],
            ['code' => 'roles.view', 'name' => 'Roles view', 'active' => 1],
            ['code' => 'roles.create', 'name' => 'Roles create', 'active' => 1],
            ['code' => 'roles.update', 'name' => 'Roles update', 'active' => 1],
            ['code' => 'roles.delete', 'name' => 'Roles delete', 'active' => 1],
            ['code' => 'roles.assign', 'name' => 'Roles assign', 'active' => 1],
            ['code' => 'permissions.view', 'name' => 'Permissions view', 'active' => 1],
            ['code' => 'permissions.assign', 'name' => 'Permissions assign', 'active' => 1],
            ['code' => 'organization.view', 'name' => 'Organization view', 'active' => 1],
            ['code' => 'organization.manage', 'name' => 'Organization manage', 'active' => 1],
            ['code' => 'documents.view', 'name' => 'Documents view', 'active' => 1],
            ['code' => 'documents.create', 'name' => 'Documents create', 'active' => 1],
            ['code' => 'documents.update', 'name' => 'Documents update', 'active' => 1],
            ['code' => 'documents.delete', 'name' => 'Documents delete', 'active' => 1],
            ['code' => 'documents.upload', 'name' => 'Documents upload', 'active' => 1],
            ['code' => 'documents.download', 'name' => 'Documents download', 'active' => 1],
            ['code' => 'documents.version', 'name' => 'Documents version', 'active' => 1],
            ['code' => 'documents.share', 'name' => 'Documents share', 'active' => 1],
            ['code' => 'correspondence.view', 'name' => 'Correspondence view', 'active' => 1],
            ['code' => 'correspondence.register', 'name' => 'Correspondence register', 'active' => 1],
            ['code' => 'correspondence.send', 'name' => 'Correspondence send', 'active' => 1],
            ['code' => 'correspondence.dispatch', 'name' => 'Correspondence dispatch', 'active' => 1],
            ['code' => 'correspondence.issue_direct', 'name' => 'Correspondence issue direct', 'active' => 1],
            ['code' => 'correspondence.acknowledge', 'name' => 'Correspondence acknowledge', 'active' => 1],
            ['code' => 'correspondence.assign', 'name' => 'Correspondence assign', 'active' => 1],
            ['code' => 'correspondence.forward', 'name' => 'Correspondence forward', 'active' => 1],
            ['code' => 'workflow.view', 'name' => 'Workflow view', 'active' => 1],
            ['code' => 'workflow.configure', 'name' => 'Workflow configure', 'active' => 1],
            ['code' => 'workflow.submit', 'name' => 'Workflow submit', 'active' => 1],
            ['code' => 'workflow.review', 'name' => 'Workflow review', 'active' => 1],
            ['code' => 'workflow.approve', 'name' => 'Workflow approve', 'active' => 1],
            ['code' => 'workflow.reject', 'name' => 'Workflow reject', 'active' => 1],
            ['code' => 'workflow.reassign', 'name' => 'Workflow reassign', 'active' => 1],
            ['code' => 'workflow.cancel', 'name' => 'Workflow cancel', 'active' => 1],
            ['code' => 'archive.view', 'name' => 'Archive view', 'active' => 1],
            ['code' => 'archive.transfer', 'name' => 'Archive transfer', 'active' => 1],
            ['code' => 'archive.restore', 'name' => 'Archive restore', 'active' => 1],
            ['code' => 'archive.manage_retention', 'name' => 'Archive manage retention', 'active' => 1],
            ['code' => 'archive.request_disposal', 'name' => 'Archive request disposal', 'active' => 1],
            ['code' => 'archive.approve_disposal', 'name' => 'Archive approve disposal', 'active' => 1],
            ['code' => 'archive.execute_disposal', 'name' => 'Archive execute disposal', 'active' => 1],
            ['code' => 'staff.view', 'name' => 'Staff view', 'active' => 1],
            ['code' => 'staff.update', 'name' => 'Staff update', 'active' => 1],
            ['code' => 'staff.view_sensitive', 'name' => 'Staff view sensitive', 'active' => 1],
            ['code' => 'research.view', 'name' => 'Research view', 'active' => 1],
            ['code' => 'research.create', 'name' => 'Research create', 'active' => 1],
            ['code' => 'research.submit', 'name' => 'Research submit', 'active' => 1],
            ['code' => 'research.update', 'name' => 'Research update', 'active' => 1],
            ['code' => 'research.review', 'name' => 'Research review', 'active' => 1],
            ['code' => 'research.approve', 'name' => 'Research approve', 'active' => 1],
            ['code' => 'research.publish', 'name' => 'Research publish', 'active' => 1],
            ['code' => 'research.reject', 'name' => 'Research reject', 'active' => 1],
            ['code' => 'research.withdraw', 'name' => 'Research withdraw', 'active' => 1],
            ['code' => 'projects.view', 'name' => 'Projects view', 'active' => 1],
            ['code' => 'projects.submit', 'name' => 'Projects submit', 'active' => 1],
            ['code' => 'projects.review', 'name' => 'Projects review', 'active' => 1],
            ['code' => 'projects.approve', 'name' => 'Projects approve', 'active' => 1],
            ['code' => 'projects.reject', 'name' => 'Projects reject', 'active' => 1],
            ['code' => 'accreditation.view', 'name' => 'Accreditation view', 'active' => 1],
            ['code' => 'accreditation.manage', 'name' => 'Accreditation manage', 'active' => 1],
            ['code' => 'accreditation.submit_evidence', 'name' => 'Accreditation submit evidence', 'active' => 1],
            ['code' => 'accreditation.review_evidence', 'name' => 'Accreditation review evidence', 'active' => 1],
            ['code' => 'accreditation.export', 'name' => 'Accreditation export', 'active' => 1],
            ['code' => 'signatures.request', 'name' => 'Signatures request', 'active' => 1],
            ['code' => 'signatures.sign', 'name' => 'Signatures sign', 'active' => 1],
            ['code' => 'signatures.verify', 'name' => 'Signatures verify', 'active' => 1],
            ['code' => 'stamps.manage', 'name' => 'Stamps manage', 'active' => 1],
            ['code' => 'stamps.apply', 'name' => 'Stamps apply', 'active' => 1],
            ['code' => 'reports.view', 'name' => 'Reports view', 'active' => 1],
            ['code' => 'reports.export', 'name' => 'Reports export', 'active' => 1],
            ['code' => 'data.import', 'name' => 'Dataset import', 'active' => 1],
            ['code' => 'data.export', 'name' => 'Dataset export', 'active' => 1],
            ['code' => 'audit.view', 'name' => 'Audit view', 'active' => 1],
            ['code' => 'system.configure', 'name' => 'System configure', 'active' => 1],
            ['code' => 'backups.manage', 'name' => 'Backups manage', 'active' => 1],
            ['code' => 'backups.restore', 'name' => 'Backups restore', 'active' => 1],
        ];
        foreach ($permissions as $p) {
            DB::table('permissions')->updateOrInsert(['code' => $p['code']], $p);
        }

        // 3. System Roles
        $roles = [
            ['code' => 'super_admin', 'name' => 'Super Administrator', 'description' => 'Full institutional system access', 'is_system' => 1, 'active' => 1],
            ['code' => 'admin', 'name' => 'System Administrator', 'description' => 'System configuration and operations', 'is_system' => 1, 'active' => 1],
            ['code' => 'department_head', 'name' => 'Department Head', 'description' => 'Head of academic/administrative unit', 'is_system' => 1, 'active' => 1],
            ['code' => 'document_controller', 'name' => 'Document Controller', 'description' => 'Document cataloging and release', 'is_system' => 1, 'active' => 1],
            ['code' => 'records_officer', 'name' => 'Records Officer', 'description' => 'Central registry and dispatch', 'is_system' => 1, 'active' => 1],
            ['code' => 'archivist', 'name' => 'Archivist', 'description' => 'Institutional archival management', 'is_system' => 1, 'active' => 1],
            ['code' => 'staff', 'name' => 'Staff Member', 'description' => 'General university employee', 'is_system' => 1, 'active' => 1],
        ];
        foreach ($roles as $r) {
            $existing = DB::table('roles')->where('code', $r['code'])->first();
            if ($existing) {
                DB::table('roles')->where('code', $r['code'])->update([
                    'name' => $r['name'],
                    'description' => $r['description'],
                    'active' => $r['active'],
                    'is_system' => $r['is_system'],
                    'updated_at' => $now,
                ]);
            } else {
                $r['created_at'] = $now;
                $r['updated_at'] = $now;
                DB::table('roles')->insert($r);
            }
        }

        // 3b. Assign permissions to roles
        $superAdminRoleId = DB::table('roles')->where('code', 'super_admin')->value('id');
        if ($superAdminRoleId) {
            $allPermIds = DB::table('permissions')->pluck('id');
            foreach ($allPermIds as $pId) {
                DB::table('role_permissions')->updateOrInsert([
                    'role_id' => $superAdminRoleId,
                    'permission_id' => $pId,
                ], []);
            }
        }

        $rolePermissionMap = [
            'admin' => [
                'users.view', 'users.create', 'users.update', 'users.deactivate',
                'roles.view', 'roles.create', 'roles.update', 'roles.delete', 'roles.assign',
                'permissions.view', 'permissions.assign',
                'organization.view', 'organization.manage',
                'documents.view', 'documents.create', 'documents.update', 'documents.delete', 'documents.upload', 'documents.download', 'documents.version', 'documents.share',
                'reports.view', 'reports.export', 'audit.view', 'system.configure', 'backups.manage', 'backups.restore',
                'accreditation.view', 'accreditation.manage', 'accreditation.review_evidence', 'accreditation.export',
                'data.import', 'data.export',
            ],
            'document_controller' => [
                'documents.view', 'documents.create', 'documents.update', 'documents.upload', 'documents.download', 'documents.version', 'documents.share',
                'workflow.view', 'workflow.submit', 'workflow.review',
                'accreditation.view', 'accreditation.submit_evidence', 'accreditation.review_evidence',
                'signatures.request', 'signatures.verify', 'stamps.apply',
            ],
            'records_officer' => [
                'documents.view', 'documents.create', 'documents.upload', 'documents.download', 'documents.version',
                'correspondence.view', 'correspondence.register', 'correspondence.send', 'correspondence.dispatch',
                'correspondence.acknowledge', 'correspondence.assign', 'correspondence.forward',
                'workflow.view', 'workflow.submit',
            ],
            'archivist' => [
                'documents.view', 'documents.download',
                'archive.view', 'archive.transfer', 'archive.restore', 'archive.manage_retention',
                'archive.request_disposal', 'archive.approve_disposal', 'archive.execute_disposal',
            ],
            'department_head' => [
                'documents.view', 'documents.create', 'documents.update', 'documents.upload', 'documents.download', 'documents.version', 'documents.share',
                'correspondence.view', 'correspondence.register', 'correspondence.send', 'correspondence.dispatch', 'correspondence.acknowledge', 'correspondence.assign', 'correspondence.forward',
                'workflow.view', 'workflow.submit', 'workflow.review', 'workflow.approve', 'workflow.reject',
                'research.view', 'research.create', 'research.submit', 'research.update', 'research.review', 'research.approve', 'research.publish', 'research.reject', 'research.withdraw',
                'staff.view', 'projects.view', 'projects.submit', 'projects.review', 'projects.approve', 'projects.reject', 'signatures.request',
                'accreditation.view', 'accreditation.submit_evidence', 'accreditation.review_evidence',
            ],
            'staff' => [
                'documents.view', 'documents.create', 'documents.upload', 'documents.download',
                'correspondence.view', 'correspondence.acknowledge',
                'workflow.view', 'workflow.submit',
                'research.view', 'research.create', 'research.submit', 'staff.view',
                'accreditation.view', 'accreditation.submit_evidence',
            ],
        ];

        foreach ($rolePermissionMap as $roleCode => $permCodes) {
            $roleId = DB::table('roles')->where('code', $roleCode)->value('id');
            if ($roleId) {
                $permIds = DB::table('permissions')->whereIn('code', $permCodes)->pluck('id');
                foreach ($permIds as $pId) {
                    DB::table('role_permissions')->updateOrInsert([
                        'role_id' => $roleId,
                        'permission_id' => $pId,
                    ], []);
                }
            }
        }

        // 4. Confidentiality Levels
        $confidentialities = [
            ['code' => 'public', 'name' => 'Public', 'description' => 'Freely accessible', 'active' => 1],
            ['code' => 'internal', 'name' => 'Internal', 'description' => 'University staff only', 'active' => 1],
            ['code' => 'confidential', 'name' => 'Confidential', 'description' => 'Departmental authorized personnel', 'active' => 1],
            ['code' => 'restricted', 'name' => 'Restricted', 'description' => 'Strict executive authorization required', 'active' => 1],
        ];
        foreach ($confidentialities as $c) {
            DB::table('confidentiality_levels')->updateOrInsert(['code' => $c['code']], $c);
        }

        // 5. Document Categories
        $categories = [
            ['code' => 'academic', 'name' => 'Academic Affairs', 'description' => 'Curriculum, grades, and faculty records', 'active' => 1],
            ['code' => 'administrative', 'name' => 'Administrative', 'description' => 'University operations and memos', 'active' => 1],
            ['code' => 'finance', 'name' => 'Finance & Procurement', 'description' => 'Budgets, audits, and purchase orders', 'active' => 1],
            ['code' => 'hr', 'name' => 'Human Resources', 'description' => 'Staff profiles, contracts, and appointments', 'active' => 1],
            ['code' => 'research', 'name' => 'Research & Publications', 'description' => 'Grants, papers, and proposals', 'active' => 1],
            ['code' => 'legal', 'name' => 'Legal & Directives', 'description' => 'Policies, charters, and legal notices', 'active' => 1],
        ];
        foreach ($categories as $cat) {
            DB::table('document_categories')->updateOrInsert(['code' => $cat['code']], $cat);
        }

        // 6. Correspondence Types
        $correspondenceTypes = [
            ['code' => 'incoming', 'name' => 'Incoming Letter', 'description' => 'Official incoming correspondence', 'active' => 1],
            ['code' => 'outgoing', 'name' => 'Outgoing Letter', 'description' => 'Official dispatched letter', 'active' => 1],
            ['code' => 'internal_memo', 'name' => 'Internal Memorandum', 'description' => 'Internal memorandum between offices', 'active' => 1],
        ];
        foreach ($correspondenceTypes as $ct) {
            DB::table('correspondence_types')->updateOrInsert(['code' => $ct['code']], $ct);
        }

        // 7. Document Relation Types
        $relationTypes = [
            ['code' => 'responds_to', 'name' => 'Responds to', 'description' => 'Official response to a source document', 'active' => 1],
            ['code' => 'supports', 'name' => 'Supporting record', 'description' => 'Attachment or supporting evidence', 'active' => 1],
            ['code' => 'supersedes', 'name' => 'Supersedes', 'description' => 'Replaces previous policy or directive', 'active' => 1],
        ];
        foreach ($relationTypes as $rt) {
            DB::table('document_relation_types')->updateOrInsert(['code' => $rt['code']], $rt);
        }

        // 8. Priorities
        $priorities = [
            ['code' => 'normal', 'name' => 'Normal Priority', 'description' => 'Standard handling SLA', 'active' => 1],
            ['code' => 'urgent', 'name' => 'Urgent Priority', 'description' => 'Expedited handling SLA', 'active' => 1],
            ['code' => 'critical', 'name' => 'Critical Priority', 'description' => 'Immediate presidential/executive handling', 'active' => 1],
        ];
        foreach ($priorities as $p) {
            DB::table('priorities')->updateOrInsert(['code' => $p['code']], $p);
        }

        // 9. Dispatch Methods
        $dispatchMethods = [
            ['code' => 'hand_delivery', 'name' => 'Hand Delivery', 'description' => 'Physical courier or hand delivery', 'active' => 1],
            ['code' => 'post', 'name' => 'Postal Mail', 'description' => 'Ethiopian postal service', 'active' => 1],
            ['code' => 'email', 'name' => 'Email Dispatch', 'description' => 'Electronic email transmission', 'active' => 1],
            ['code' => 'internal', 'name' => 'Internal Routing', 'description' => 'Intra-university dispatch', 'active' => 1],
        ];
        foreach ($dispatchMethods as $dm) {
            DB::table('dispatch_methods')->updateOrInsert(['code' => $dm['code']], $dm);
        }

        // 10. Institutions
        $institutions = [
            ['code' => 'WU', 'name' => 'Wollo University', 'description' => 'Main institutional body', 'active' => 1],
            ['code' => 'MOE', 'name' => 'Ministry of Education', 'description' => 'Federal Ministry of Education Ethiopia', 'active' => 1],
        ];
        foreach ($institutions as $inst) {
            DB::table('institutions')->updateOrInsert(['code' => $inst['code']], $inst);
        }

        // 11. Qualification Types
        $qualifications = [
            ['code' => 'bsc', 'name' => 'Bachelor of Science / Arts', 'description' => 'First undergraduate degree', 'active' => 1],
            ['code' => 'msc', 'name' => 'Master of Science / Arts', 'description' => 'Second graduate degree', 'active' => 1],
            ['code' => 'phd', 'name' => 'Doctor of Philosophy', 'description' => 'Terminal doctoral degree', 'active' => 1],
            ['code' => 'diploma', 'name' => 'Technical Diploma', 'description' => 'Vocational or technical qualification', 'active' => 1],
        ];
        foreach ($qualifications as $q) {
            DB::table('qualification_types')->updateOrInsert(['code' => $q['code']], $q);
        }

        // 12. HR Record Types
        $hrRecordTypes = [
            ['code' => 'appointment_letter', 'name' => 'Appointment Letter', 'description' => 'Official employment letter', 'active' => 1],
            ['code' => 'contract', 'name' => 'Employment Contract', 'description' => 'Signed contract of employment', 'active' => 1],
            ['code' => 'promotion', 'name' => 'Academic Promotion', 'description' => 'Rank promotion approval record', 'active' => 1],
            ['code' => 'disciplinary', 'name' => 'Disciplinary Record', 'description' => 'Official disciplinary inquiry outcome', 'active' => 1],
        ];
        foreach ($hrRecordTypes as $hrt) {
            DB::table('hr_record_types')->updateOrInsert(['code' => $hrt['code']], $hrt);
        }

        // 13. Project Types
        $projectTypes = [
            ['code' => 'research', 'name' => 'Academic Research Project', 'description' => 'Faculty research grant', 'active' => 1],
            ['code' => 'community_service', 'name' => 'Community Service Project', 'description' => 'University outreach initiative', 'active' => 1],
            ['code' => 'infrastructure', 'name' => 'Campus Infrastructure', 'description' => 'Campus physical development project', 'active' => 1],
        ];
        foreach ($projectTypes as $pt) {
            DB::table('project_types')->updateOrInsert(['code' => $pt['code']], $pt);
        }

        // 14. Default Global Authorization Scope
        $globalScope = DB::table('authorization_scopes')->where('kind', 'global')->first();
        if (! $globalScope) {
            $scopeId = DB::table('authorization_scopes')->insertGetId([
                'kind' => 'global',
                'unit_id' => null,
            ]);
        } else {
            $scopeId = $globalScope->id;
        }

        // 15. Default Institutional Archive Collection
        $archiveCollectionExists = DB::table('archive_collections')->where('code', 'institutional_archive')->exists();
        if (! $archiveCollectionExists) {
            DB::table('archive_collections')->insert([
                'code' => 'institutional_archive',
                'name' => 'Wollo University Central Institutional Archive',
                'description' => 'Central repository for permanent institutional records',
            ]);
        }

        // 15b. Default Retention Policy & Version
        $permPolicy = DB::table('retention_policies')->where('code', 'INST_PERM')->first();
        if (! $permPolicy) {
            $permPolicyId = DB::table('retention_policies')->insertGetId([
                'code' => 'INST_PERM',
                'name' => 'Permanent Institutional Record Policy',
                'description' => 'Records of permanent historical significance',
                'active' => 1,
            ]);
            DB::table('retention_policy_versions')->insert([
                'policy_id' => $permPolicyId,
                'version_number' => 1,
                'trigger_code' => 'permanent',
                'duration_months' => 1200,
                'disposition_action' => 'retain',
                'approved_by' => null,
                'approved_at' => null,
                'effective_from' => '2026-01-01',
            ]);
        }

        // 16. Root Organization Unit
        $universityTypeId = DB::table('unit_types')->where('code', 'university')->value('id');
        $rootUnit = DB::table('organization_units')->where('code', 'WU')->first();
        if (! $rootUnit) {
            $rootUnitId = DB::table('organization_units')->insertGetId([
                'parent_id' => null,
                'unit_type_id' => $universityTypeId,
                'code' => 'WU',
                'name' => 'Wollo University',
                'active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $rootUnitId = $rootUnit->id;
        }

        // 17. Initial Super Administrator User
        $adminUser = DB::table('users')->where('email', 'admin@wu.edu.et')->first();
        if (! $adminUser) {
            $adminUserId = DB::table('users')->insertGetId([
                'name' => 'System Administrator',
                'email' => 'admin@wu.edu.et',
                'password' => Hash::make('Password123!'),
                'status' => 'active',
                'authz_version' => 1,
                'email_verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $adminUserId = $adminUser->id;
        }

        // 18. Primary Membership for Admin at Root Unit
        $existingMembership = DB::table('organization_memberships')
            ->where('user_id', $adminUserId)
            ->where('unit_id', $rootUnitId)
            ->first();

        if (! $existingMembership) {
            $membershipId = DB::table('organization_memberships')->insertGetId([
                'user_id' => $adminUserId,
                'unit_id' => $rootUnitId,
                'position_title' => 'Chief System Administrator',
                'starts_at' => $now,
                'ends_at' => null,
                'created_by' => $adminUserId,
                'created_at' => $now,
            ]);
        } else {
            $membershipId = $existingMembership->id;
        }

        DB::table('primary_memberships')->updateOrInsert(
            ['user_id' => $adminUserId],
            ['membership_id' => $membershipId, 'selected_at' => $now]
        );

        // 19. Assign super_admin role in global scope to admin
        if ($superAdminRoleId && $scopeId) {
            $hasRole = DB::table('user_roles')
                ->where('user_id', $adminUserId)
                ->where('role_id', $superAdminRoleId)
                ->where('scope_id', $scopeId)
                ->exists();

            if (! $hasRole) {
                DB::table('user_roles')->insert([
                    'user_id' => $adminUserId,
                    'role_id' => $superAdminRoleId,
                    'scope_id' => $scopeId,
                    'granted_by' => $adminUserId,
                    'granted_at' => $now,
                    'expires_at' => null,
                ]);
            }
        }

        // 20. Baseline Workflow Definition
        DB::table('workflow_definitions')->updateOrInsert(
            ['code' => 'standard_doc_approval'],
            [
                'name' => 'Standard Document Review & Approval',
                'active' => 1,
            ]
        );

        // 21. Official HERQA Accreditation Standards Reference Data
        $herqaFrameworkId = DB::table('accreditation_frameworks')->where('code', 'HERQA')->value('id');
        if (! $herqaFrameworkId) {
            $herqaFrameworkId = DB::table('accreditation_frameworks')->insertGetId([
                'code' => 'HERQA',
                'name' => 'Higher Education Relevance and Quality Agency',
                'description' => 'Federal Democratic Republic of Ethiopia Institutional Quality Audit and Accreditation Framework',
                'active' => 1,
            ]);
        }

        $herqaVersionId = DB::table('accreditation_framework_versions')
            ->where('framework_id', $herqaFrameworkId)
            ->where('version_label', 'HERQA-2022-V2')
            ->value('id');
        if (! $herqaVersionId) {
            $herqaVersionId = DB::table('accreditation_framework_versions')->insertGetId([
                'framework_id' => $herqaFrameworkId,
                'version_label' => 'HERQA-2022-V2',
                'effective_from' => '2022-01-01',
            ]);
        }

        $officialStandards = [
            [
                'code' => 'HERQA_STD_01',
                'title' => 'Governance, Leadership and Institutional Autonomy',
                'description' => 'Institutional leadership structure, statutory governance organs, institutional autonomy, and leadership decision transparency.',
            ],
            [
                'code' => 'HERQA_STD_02',
                'title' => 'Academic Programs and Curricula Relevance',
                'description' => 'Relevance of curricula to national development priorities, stakeholder participation, and regular curriculum review cycles.',
            ],
            [
                'code' => 'HERQA_STD_03',
                'title' => 'Teaching, Learning and Assessment',
                'description' => 'Student-centered pedagogy, continuous assessment frameworks, examination security, and grading moderation.',
            ],
            [
                'code' => 'HERQA_STD_04',
                'title' => 'Student Admission, Progression and Support Services',
                'description' => 'Equitable student admission, registrar records management, guidance/counseling, and academic progression tracking.',
            ],
            [
                'code' => 'HERQA_STD_05',
                'title' => 'Human Resources and Academic Staff Profile',
                'description' => 'Academic rank distribution, staff qualification ratios, professional development, and objective appraisal systems.',
            ],
            [
                'code' => 'HERQA_STD_06',
                'title' => 'Research, Publication and Community Engagement',
                'description' => 'University research policy, thematic research funding, peer-reviewed journals, and community technology transfer.',
            ],
            [
                'code' => 'HERQA_STD_07',
                'title' => 'Physical Infrastructure and Learning Facilities',
                'description' => 'Lecture halls, teaching laboratories, clinical skills facilities, occupational safety, and physical accessibility.',
            ],
            [
                'code' => 'HERQA_STD_08',
                'title' => 'Educational Resources, Library and Information Infrastructure',
                'description' => 'Library holdings, digital database subscriptions, institutional repository, campus LAN/WAN, and ICT support.',
            ],
            [
                'code' => 'HERQA_STD_09',
                'title' => 'Internal Quality Assurance System',
                'description' => 'Quality Assurance Directorate mandate, departmental QA committees, self-evaluation reports, and audit follow-up.',
            ],
            [
                'code' => 'HERQA_STD_10',
                'title' => 'Public Information, Transparency and Institutional Accountability',
                'description' => 'Public disclosure of programs, accredited status, graduate statistics, academic calendar, and fiscal accountability.',
            ],
        ];

        foreach ($officialStandards as $std) {
            DB::table('accreditation_standards')->updateOrInsert(
                [
                    'framework_version_id' => $herqaVersionId,
                    'code' => $std['code'],
                ],
                [
                    'title' => $std['title'],
                    'description' => $std['description'],
                    'parent_id' => null,
                ]
            );
        }
    }
}
