<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ArchiveCollection;
use App\Models\ArchiveEntry;
use App\Models\AuthorizationScope;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentTemplate;
use App\Models\DocumentTextExtraction;
use App\Models\DocumentVersion;
use App\Models\FileObject;
use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\VersionFile;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowState;
use App\Models\WorkflowTask;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoProvisionCommand extends Command
{
    protected $signature = 'dms:demo-provision 
                            {--reset-passwords : Force regenerate demonstration account passwords}';

    protected $description = 'Safely provision realistic university demonstration accounts, organizational hierarchy, and scenarios in staging';

    public function handle(): int
    {
        $dbName = (string) config('database.connections.mysql.database');
        if (! app()->environment('testing') && $dbName !== 'wu_dms_demo') {
            $this->error("CRITICAL SAFETY VIOLATION: Current database is [{$dbName}]. Demo provisioning is strictly restricted to 'wu_dms_demo'.");
            return 1;
        }

        $this->info('========================================================================');
        $this->info(' Wollo University DMS — Safe Demonstration Provisioning Engine          ');
        $this->info('========================================================================');

        // 1. Sync Role Permissions (including document_controller)
        $this->syncRolePermissions();

        // 2. Provision Organizational Units Hierarchy
        $units = $this->provisionOrganizationalHierarchy();

        // 3. Provision 20 Demonstration Accounts
        $credentials = $this->provisionDemoUsers($units);

        // 4. Save Credentials File with restricted access
        $this->saveCredentialsFile($credentials);

        // 5. Provision 12 Realistic Demonstration Document Scenarios
        $this->provisionDemonstrationScenarios($units);

        $this->info('');
        $this->info('SUCCESS: Demonstration provisioning completed safely.');
        $this->info('Execute "php artisan dms:demo-accounts" to view all accounts and role assignments.');
        $this->info('Credentials saved securely to local Git-ignored file: storage/demo_credentials.json');

        return 0;
    }

    private function syncRolePermissions(): void
    {
        $rolePermissionMap = [
            'document_controller' => [
                'documents.view', 'documents.create', 'documents.update', 'documents.upload', 'documents.download', 'documents.version', 'documents.share',
                'workflow.view', 'workflow.submit', 'workflow.review',
                'accreditation.view', 'accreditation.submit_evidence', 'accreditation.review_evidence',
                'signatures.request', 'signatures.verify', 'stamps.apply',
            ],
        ];

        foreach ($rolePermissionMap as $roleCode => $perms) {
            $roleId = DB::table('roles')->where('code', $roleCode)->value('id');
            if ($roleId) {
                $permIds = DB::table('permissions')->whereIn('code', $perms)->pluck('id');
                foreach ($permIds as $pId) {
                    DB::table('role_permissions')->updateOrInsert(
                        ['role_id' => $roleId, 'permission_id' => $pId],
                        []
                    );
                }
            }
        }
    }

    private function provisionOrganizationalHierarchy(): array
    {
        $this->line('Provisioning Organizational Units...');

        $typeMap = DB::table('unit_types')->pluck('id', 'code')->toArray();
        $univTypeId = $typeMap['university'] ?? 1;
        $collegeTypeId = $typeMap['college'] ?? 2;
        $deptTypeId = $typeMap['department'] ?? 4;
        $dirTypeId = $typeMap['directorate'] ?? 5;
        $officeTypeId = $typeMap['office'] ?? 6;

        $root = OrganizationUnit::firstOrCreate(
            ['code' => 'WU'],
            ['name' => 'Wollo University', 'unit_type_id' => $univTypeId, 'active' => 1]
        );

        $units = ['WU' => $root];

        $unitDefs = [
            // Colleges
            'COI' => ['name' => 'College of Informatics', 'parent_id' => $root->id, 'unit_type_id' => $collegeTypeId],
            'COTE' => ['name' => 'College of Technology & Engineering', 'parent_id' => $root->id, 'unit_type_id' => $collegeTypeId],

            // Departments under College of Informatics
            'ITD' => ['name' => 'Department of Information Technology', 'parent_code' => 'COI', 'unit_type_id' => $deptTypeId],
            'CSD' => ['name' => 'Department of Computer Science', 'parent_code' => 'COI', 'unit_type_id' => $deptTypeId],
            'SED' => ['name' => 'Department of Software Engineering', 'parent_code' => 'COI', 'unit_type_id' => $deptTypeId],

            // Department under College of Technology
            'EED' => ['name' => 'Department of Electrical & Computer Engineering', 'parent_code' => 'COTE', 'unit_type_id' => $deptTypeId],

            // Directorates & Administrative Offices
            'REG' => ['name' => 'Central Registry & Document Intake Office', 'parent_id' => $root->id, 'unit_type_id' => $officeTypeId],
            'DISP' => ['name' => 'Central Institutional Dispatch Office', 'parent_id' => $root->id, 'unit_type_id' => $officeTypeId],
            'REC' => ['name' => 'Office of Academic Records & Registration', 'parent_id' => $root->id, 'unit_type_id' => $officeTypeId],
            'ARC' => ['name' => 'University Central Digital Archive', 'parent_id' => $root->id, 'unit_type_id' => $officeTypeId],
            'RES' => ['name' => 'Research & Community Service Directorate', 'parent_id' => $root->id, 'unit_type_id' => $dirTypeId],
            'QA' => ['name' => 'Institutional Quality Assurance Directorate', 'parent_id' => $root->id, 'unit_type_id' => $dirTypeId],
            'HRMD' => ['name' => 'Human Resource Management Directorate', 'parent_id' => $root->id, 'unit_type_id' => $dirTypeId],
            'VPAA' => ['name' => 'Office of the Vice President for Academic Affairs', 'parent_id' => $root->id, 'unit_type_id' => $officeTypeId],
            'ICT' => ['name' => 'ICT Infrastructure & Systems Directorate', 'parent_id' => $root->id, 'unit_type_id' => $dirTypeId],
        ];

        foreach ($unitDefs as $code => $data) {
            $parentId = $data['parent_id'] ?? ($units[$data['parent_code'] ?? 'WU']->id ?? $root->id);
            $u = OrganizationUnit::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $data['name'],
                    'parent_id' => $parentId,
                    'unit_type_id' => $data['unit_type_id'],
                    'active' => 1,
                ]
            );
            $units[$code] = $u;
        }

        return $units;
    }

    private function provisionDemoUsers(array $units): array
    {
        $this->line('Provisioning 20 Demonstration Personas...');

        $demoUsers = [
            [
                'category' => 'System Administrator',
                'name' => 'Ato Dawit SysAdmin',
                'email' => 'demo.sysadmin@wu.edu.et',
                'unit' => 'ICT',
                'title' => 'Chief Systems Administrator',
                'role' => 'super_admin',
                'scope' => 'global',
                'direct_perms' => [],
            ],
            [
                'category' => 'University Administrator',
                'name' => 'W/ro Rahel UniAdmin',
                'email' => 'demo.uniadmin@wu.edu.et',
                'unit' => 'WU',
                'title' => 'University Operations Officer',
                'role' => 'admin',
                'scope' => 'global',
                'direct_perms' => [],
            ],
            [
                'category' => 'Records and Registry Administrator',
                'name' => 'Ato Yohannes RecordsAdmin',
                'email' => 'demo.records.admin@wu.edu.et',
                'unit' => 'REC',
                'title' => 'Head of Academic Records & Registration',
                'role' => 'document_controller',
                'scope' => 'unit',
                'direct_perms' => ['documents.delete', 'reports.view'],
            ],
            [
                'category' => 'Document Registration Officer',
                'name' => 'W/t Bethlehem DocRegistrar',
                'email' => 'demo.doc.registrar@wu.edu.et',
                'unit' => 'REG',
                'title' => 'Document Intake & Registration Officer',
                'role' => 'records_officer',
                'scope' => 'unit',
                'direct_perms' => ['documents.upload', 'documents.version'],
            ],
            [
                'category' => 'Central Registry Officer',
                'name' => 'Ato Samuel CentralRegistry',
                'email' => 'demo.central.registry@wu.edu.et',
                'unit' => 'REG',
                'title' => 'Central Registry Coordinator',
                'role' => 'records_officer',
                'scope' => 'global',
                'direct_perms' => [],
            ],
            [
                'category' => 'College Dean',
                'name' => 'Dr. Solomon DeanInformatics',
                'email' => 'demo.dean.informatics@wu.edu.et',
                'unit' => 'COI',
                'title' => 'Dean, College of Informatics',
                'role' => 'department_head',
                'scope' => 'subtree',
                'direct_perms' => ['workflow.approve', 'signatures.sign', 'signatures.request', 'accreditation.review_evidence'],
            ],
            [
                'category' => 'Department Head',
                'name' => 'Dr. Martha HeadIT',
                'email' => 'demo.head.it@wu.edu.et',
                'unit' => 'ITD',
                'title' => 'Head, Department of Information Technology',
                'role' => 'department_head',
                'scope' => 'unit',
                'direct_perms' => [],
            ],
            [
                'category' => 'Department Secretary',
                'name' => 'W/ro Tigist SecIT',
                'email' => 'demo.sec.it@wu.edu.et',
                'unit' => 'ITD',
                'title' => 'Secretary, Department of IT',
                'role' => 'staff',
                'scope' => 'unit',
                'direct_perms' => ['correspondence.register', 'correspondence.send', 'documents.create', 'documents.upload'],
            ],
            [
                'category' => 'Office Administrator',
                'name' => 'Ato Berhanu OfficeAdmin',
                'email' => 'demo.office.admin@wu.edu.et',
                'unit' => 'VPAA',
                'title' => 'Executive Office Administrator',
                'role' => 'staff',
                'scope' => 'unit',
                'direct_perms' => ['correspondence.view', 'correspondence.assign', 'workflow.view', 'workflow.submit'],
            ],
            [
                'category' => 'Lecturer or Academic Staff',
                'name' => 'Instructor Eyob LecturerIT',
                'email' => 'demo.lecturer.it@wu.edu.et',
                'unit' => 'ITD',
                'title' => 'Lecturer in Information Technology',
                'role' => 'staff',
                'scope' => 'unit',
                'direct_perms' => [],
            ],
            [
                'category' => 'Document Author',
                'name' => 'Dr. Mulugeta DocAuthor',
                'email' => 'demo.doc.author@wu.edu.et',
                'unit' => 'CSD',
                'title' => 'Curriculum Lead & Assistant Professor',
                'role' => 'staff',
                'scope' => 'unit',
                'direct_perms' => ['documents.create', 'documents.version', 'workflow.submit'],
            ],
            [
                'category' => 'Document Reviewer',
                'name' => 'Dr. Senait DocReviewer',
                'email' => 'demo.doc.reviewer@wu.edu.et',
                'unit' => 'ITD',
                'title' => 'Academic Committee Reviewer',
                'role' => 'staff',
                'scope' => 'unit',
                'direct_perms' => ['workflow.review', 'workflow.view', 'documents.view'],
            ],
            [
                'category' => 'Document Approver',
                'name' => 'Prof. Worku DocApprover',
                'email' => 'demo.doc.approver@wu.edu.et',
                'unit' => 'VPAA',
                'title' => 'Vice President for Academic Affairs',
                'role' => 'department_head',
                'scope' => 'global',
                'direct_perms' => ['workflow.approve', 'workflow.reject', 'signatures.sign', 'stamps.apply'],
            ],
            [
                'category' => 'Researcher',
                'name' => 'Dr. Daniel Researcher',
                'email' => 'demo.researcher@wu.edu.et',
                'unit' => 'RES',
                'title' => 'Principal Investigator, AI Lab',
                'role' => 'staff',
                'scope' => 'unit',
                'direct_perms' => ['research.create', 'research.update', 'projects.submit'],
            ],
            [
                'category' => 'Research Coordinator',
                'name' => 'Dr. Hiwot ResearchCoord',
                'email' => 'demo.research.coord@wu.edu.et',
                'unit' => 'COI',
                'title' => 'College Research Coordinator',
                'role' => 'staff',
                'scope' => 'subtree',
                'direct_perms' => ['research.view', 'projects.view', 'projects.review', 'workflow.review'],
            ],
            [
                'category' => 'Research Directorate Reviewer',
                'name' => 'Dr. Tadesse ResearchDir',
                'email' => 'demo.research.dir@wu.edu.et',
                'unit' => 'RES',
                'title' => 'Director, Research & Community Services',
                'role' => 'department_head',
                'scope' => 'unit',
                'direct_perms' => [],
            ],
            [
                'category' => 'HR Officer',
                'name' => 'W/ro Genet HROfficer',
                'email' => 'demo.hr.officer@wu.edu.et',
                'unit' => 'HRMD',
                'title' => 'Human Resources Personnel Specialist',
                'role' => 'staff',
                'scope' => 'unit',
                'direct_perms' => ['staff.view', 'staff.update', 'documents.create', 'documents.upload'],
            ],
            [
                'category' => 'Archive Officer',
                'name' => 'Ato Negash ArchiveOfficer',
                'email' => 'demo.archive.officer@wu.edu.et',
                'unit' => 'ARC',
                'title' => 'Institutional Archival Records Officer',
                'role' => 'archivist',
                'scope' => 'unit',
                'direct_perms' => [],
            ],
            [
                'category' => 'Physical Records Custodian',
                'name' => 'Ato Kassahun Custodian',
                'email' => 'demo.custodian@wu.edu.et',
                'unit' => 'ARC',
                'title' => 'Physical Warehouse & Records Custodian',
                'role' => 'archivist',
                'scope' => 'unit',
                'direct_perms' => ['documents.view', 'correspondence.view', 'correspondence.acknowledge'],
            ],
            [
                'category' => 'General Staff Member',
                'name' => 'Ato Tamrat GeneralStaff',
                'email' => 'demo.general.staff@wu.edu.et',
                'unit' => 'ITD',
                'title' => 'IT Support Specialist',
                'role' => 'staff',
                'scope' => 'unit',
                'direct_perms' => [],
            ],
        ];

        $credentials = [];
        $adminId = DB::table('users')->where('id', 1)->value('id') ?? 1;

        foreach ($demoUsers as $spec) {
            $unit = $units[$spec['unit']] ?? $units['WU'];
            $defaultPassword = 'WU-Demo-2026!' . Str::studly(Str::slug($spec['role']));

            $user = User::where('email', $spec['email'])->first();
            if (! $user) {
                $user = User::create([
                    'name' => $spec['name'],
                    'email' => $spec['email'],
                    'password' => Hash::make($defaultPassword),
                    'status' => 'active',
                    'authz_version' => 1,
                    'email_verified_at' => now(),
                ]);
                $activePassword = $defaultPassword;
            } else {
                $user->update([
                    'name' => $spec['name'],
                    'status' => 'active',
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);
                if ($this->option('reset-passwords')) {
                    $user->update(['password' => Hash::make($defaultPassword)]);
                    $activePassword = $defaultPassword;
                } else {
                    $activePassword = '[Retained Existing Secure Password]';
                }
            }

            // Membership & Primary Membership
            $membership = DB::table('organization_memberships')->where('user_id', $user->id)->first();
            if (! $membership) {
                $mId = DB::table('organization_memberships')->insertGetId([
                    'user_id' => $user->id,
                    'unit_id' => $unit->id,
                    'position_title' => $spec['title'],
                    'starts_at' => now()->subYears(2),
                    'created_by' => $adminId,
                    'created_at' => now(),
                ]);
            } else {
                $mId = $membership->id;
                DB::table('organization_memberships')->where('id', $mId)->update([
                    'unit_id' => $unit->id,
                    'position_title' => $spec['title'],
                ]);
            }

            DB::table('primary_memberships')->updateOrInsert(
                ['user_id' => $user->id],
                ['membership_id' => $mId, 'selected_at' => now()]
            );

            // Scopes & Roles
            $scopeKind = $spec['scope'];
            $scopeUnitId = ($scopeKind === 'global') ? null : $unit->id;

            $scope = AuthorizationScope::firstOrCreate([
                'kind' => $scopeKind,
                'unit_id' => $scopeUnitId,
            ]);

            $role = Role::where('code', $spec['role'])->first();
            if ($role) {
                DB::table('user_roles')->updateOrInsert(
                    ['user_id' => $user->id, 'role_id' => $role->id, 'scope_id' => $scope->id],
                    ['granted_by' => $adminId, 'granted_at' => now()]
                );
            }

            // Direct User Permissions
            if (! empty($spec['direct_perms'])) {
                foreach ($spec['direct_perms'] as $pCode) {
                    $permId = DB::table('permissions')->where('code', $pCode)->value('id');
                    if ($permId) {
                        DB::table('user_permissions')->updateOrInsert(
                            ['user_id' => $user->id, 'permission_id' => $permId, 'scope_id' => $scope->id],
                            ['granted_by' => $adminId, 'granted_at' => now()]
                        );
                    }
                }
            }

            $credentials[] = [
                'category' => $spec['category'],
                'name' => $spec['name'],
                'email' => $spec['email'],
                'password' => $activePassword,
                'unit_code' => $unit->code,
                'unit_name' => $unit->name,
                'position_title' => $spec['title'],
                'role' => $spec['role'],
                'scope' => $spec['scope'],
            ];
        }

        return $credentials;
    }

    private function saveCredentialsFile(array $credentials): void
    {
        $payload = [
            'system' => 'Wollo University Administrative and Document Management System (WU-DMS)',
            'environment' => 'staging',
            'generated_at' => now()->toIso8601String(),
            'notice' => 'CONFIDENTIAL: Local synthetic demonstration credentials. Ignored from Git version control.',
            'accounts' => $credentials,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $serverPath = storage_path('demo_credentials.json');
        file_put_contents($serverPath, $json);
        @chmod($serverPath, 0600);

        $rootPath = base_path('../.demo_credentials.json');
        if (is_dir(dirname($rootPath))) {
            file_put_contents($rootPath, $json);
            @chmod($rootPath, 0600);
        }
    }

    private function provisionDemonstrationScenarios(array $units): void
    {
        $this->line('Provisioning 12 University Demonstration Document Scenarios...');

        $adminId = DB::table('users')->where('id', 1)->value('id') ?? 1;
        $catAcademic = DocumentCategory::where('code', 'academic')->value('id') ?? 1;
        $catAdmin = DocumentCategory::where('code', 'administrative')->value('id') ?? 2;
        $catResearch = DocumentCategory::where('code', 'research')->value('id') ?? 5;
        $catHr = DocumentCategory::where('code', 'hr')->value('id') ?? 4;
        $catLegal = DocumentCategory::where('code', 'legal')->value('id') ?? 6;

        $confInternal = DB::table('confidentiality_levels')->where('code', 'internal')->value('id') ?? 2;
        $confConfidential = DB::table('confidentiality_levels')->where('code', 'confidential')->value('id') ?? 3;
        $confRestricted = DB::table('confidentiality_levels')->where('code', 'restricted')->value('id') ?? 4;

        $authorUser = User::where('email', 'demo.doc.author@wu.edu.et')->first();
        $authorId = $authorUser ? $authorUser->id : $adminId;

        // 1. Incoming official correspondence
        $this->createSyntheticDocument(
            ref: 'IN-WU-2026-0042',
            title: 'HERQA Institutional Re-Accreditation Schedule Notification (Academic Year 2026/27)',
            categoryId: $catAdmin,
            confId: $confInternal,
            unitId: $units['REG']->id,
            authorId: $authorId,
            filename: 'herqa_schedule_notification_2026.pdf',
            lifecycle: 'active',
            isCorrespondence: true,
            corrType: 'incoming',
            corrPurpose: 'directive',
            externalRef: 'HERQA/DIR/2026/184'
        );

        // 2. Internal office memorandum
        $this->createSyntheticDocument(
            ref: 'MEMO-COI-2026-018',
            title: 'Advisory on Undergraduate Curriculum Benchmarking & Capstone Review',
            categoryId: $catAcademic,
            confId: $confInternal,
            unitId: $units['COI']->id,
            authorId: $authorId,
            filename: 'undergraduate_curriculum_advisory.pdf',
            lifecycle: 'active',
            isCorrespondence: true,
            corrType: 'internal_memo',
            corrPurpose: 'guidance',
            externalRef: null
        );

        // 3. Outgoing official letter
        $this->createSyntheticDocument(
            ref: 'OUT-WU-2026-0089',
            title: 'Formal Endorsement of National AI Consortium Collaborative Research Agreement',
            categoryId: $catAdmin,
            confId: $confInternal,
            unitId: $units['VPAA']->id,
            authorId: $authorId,
            filename: 'mint_consortium_endorsement.pdf',
            lifecycle: 'active',
            isCorrespondence: true,
            corrType: 'outgoing',
            corrPurpose: 'endorsement',
            externalRef: 'MInT/NAT/AI/892'
        );

        // 4. Meeting minutes
        $this->createSyntheticDocument(
            ref: 'MIN-CSD-2026-007',
            title: 'Minutes of the Department of Computer Science Academic Council Meeting 07/2026',
            categoryId: $catAcademic,
            confId: $confInternal,
            unitId: $units['CSD']->id,
            authorId: $authorId,
            filename: 'cs_academic_council_minutes_07.pdf',
            lifecycle: 'active'
        );

        // 5. Research proposal
        $researcherUser = User::where('email', 'demo.researcher@wu.edu.et')->first();
        $this->createSyntheticDocument(
            ref: 'RES-PROP-2026-0031',
            title: 'Investigation of NLP for Ethiopic Healthcare Document Translation and Retrieval',
            categoryId: $catResearch,
            confId: $confInternal,
            unitId: $units['RES']->id,
            authorId: $researcherUser ? $researcherUser->id : $authorId,
            filename: 'ethiopic_nlp_healthcare_proposal.pdf',
            lifecycle: 'active'
        );

        // 6. HR-related document
        $this->createSyntheticDocument(
            ref: 'HR-DOC-2026-0112',
            title: 'Staff Employment Contract & Intellectual Property Undertaking - Senior Lecturer in Data Science',
            categoryId: $catHr,
            confId: $confConfidential,
            unitId: $units['HRMD']->id,
            authorId: $authorId,
            filename: 'senior_lecturer_ip_contract.pdf',
            lifecycle: 'active'
        );

        // 7. Scanned document without a template (with Bilingual OCR record)
        $scannedDoc = $this->createSyntheticDocument(
            ref: 'SCAN-REG-2026-0095',
            title: 'Physical Watermarked Directive from Ethiopian National Accreditation Board',
            categoryId: $catAdmin,
            confId: $confInternal,
            unitId: $units['REG']->id,
            authorId: $authorId,
            filename: 'watermarked_physical_scan.pdf',
            lifecycle: 'active'
        );
        $scannedVer = $scannedDoc->versions()->first();
        $scannedFile = $scannedVer?->versionFiles()->first()?->fileObject;
        if ($scannedVer && $scannedFile) {
            DocumentTextExtraction::updateOrCreate(
                ['document_version_id' => $scannedVer->id, 'file_object_id' => $scannedFile->id],
                [
                    'ocr_engine' => 'Tesseract 5.x',
                    'language_detected' => 'amh+eng',
                    'processing_status' => 'completed',
                    'confidence_score' => 96.50,
                    'raw_text' => "የኢትዮጵያ ከፍተኛ ትምህርት ጥራትና አግባብነት ኤጀንሲ\nETHIOPIAN HIGHER EDUCATION RELEVANCE AND QUALITY AGENCY\nInstitutional Accreditation Directive 2026",
                    'verified_text' => "የኢትዮጵያ ከፍተኛ ትምህርት ጥራትና አግባብነት ኤጀንሲ\nETHIOPIAN HIGHER EDUCATION RELEVANCE AND QUALITY AGENCY\nInstitutional Accreditation Directive 2026",
                    'searchable_text' => "የኢትዮጵያ ከፍተኛ ትምህርት ጥራትና አግባብነት ኤጀንሲ institutional accreditation directive 2026",
                    'verified_by' => $authorId,
                    'verified_at' => now(),
                    'extracted_at' => now(),
                ]
            );
        }

        // 8. Template-generated document
        $template = DocumentTemplate::firstOrCreate(
            ['code' => 'TPL-ACAD-REC-01'],
            [
                'name' => 'Student Academic Recommendation Template',
                'description' => 'Official institutional academic recommendation for graduate studies',
                'category_id' => $catAcademic,
                'owner_unit_id' => $units['COI']->id,
                'visibility_scope' => 'unit_restricted',
                'is_active' => true,
                'created_by' => $authorId,
            ]
        );

        $this->createSyntheticDocument(
            ref: 'TPL-GEN-2026-0054',
            title: 'Official Student Academic Recommendation Letter (Master of Science in Software Engineering)',
            categoryId: $catAcademic,
            confId: $confInternal,
            unitId: $units['SED']->id,
            authorId: $authorId,
            filename: 'student_recommendation_template_gen.pdf',
            lifecycle: 'active',
            templateId: $template->id
        );

        // 9. Document requiring multiple sequential approvals
        $docSeq = $this->createSyntheticDocument(
            ref: 'SEQ-APP-2026-0023',
            title: 'Revised Information Technology Department Laboratory Safety and Access Policy',
            categoryId: $catAcademic,
            confId: $confInternal,
            unitId: $units['ITD']->id,
            authorId: $authorId,
            filename: 'it_lab_safety_access_policy.pdf',
            lifecycle: 'active'
        );
        $this->createSequentialWorkflowScenario($docSeq, $units);

        // 10. Document requiring parallel committee review
        $docPar = $this->createSyntheticDocument(
            ref: 'PAR-REV-2026-0017',
            title: 'Inter-Disciplinary Joint AI and Health Informatics Degree Curriculum Proposal',
            categoryId: $catAcademic,
            confId: $confInternal,
            unitId: $units['COI']->id,
            authorId: $authorId,
            filename: 'joint_ai_health_informatics_proposal.pdf',
            lifecycle: 'active'
        );
        $this->createParallelWorkflowScenario($docPar, $units);

        // 11. Archived document
        $docArch = $this->createSyntheticDocument(
            ref: 'ARCH-HIST-2021-0004',
            title: 'Historical Wollo University Academic Calendar and Policy Compendium (2020-2021)',
            categoryId: $catAdmin,
            confId: $confInternal,
            unitId: $units['ARC']->id,
            authorId: $authorId,
            filename: 'historical_calendar_2020_2021.pdf',
            lifecycle: 'retired'
        );
        $this->createArchivalScenario($docArch, $units);

        // 12. Document with physical-original custody information
        $docPhys = $this->createSyntheticDocument(
            ref: 'PHYS-CUST-2026-0068',
            title: 'Deed of Institutional Land Grant and Campus Boundary Certification (Kombolcha Campus)',
            categoryId: $catLegal,
            confId: $confRestricted,
            unitId: $units['ARC']->id,
            authorId: $authorId,
            filename: 'kombolcha_campus_land_deed.pdf',
            lifecycle: 'active'
        );
        $this->createPhysicalCustodyScenario($docPhys, $units);
    }

    private function createSyntheticDocument(
        string $ref,
        string $title,
        int $categoryId,
        int $confId,
        int $unitId,
        int $authorId,
        string $filename,
        string $lifecycle = 'active',
        bool $isCorrespondence = false,
        ?string $corrType = null,
        ?string $corrPurpose = null,
        ?string $externalRef = null,
        ?int $templateId = null
    ): Document {
        $doc = Document::firstOrCreate(
            ['reference_number' => $ref],
            [
                'title' => $title,
                'category_id' => $categoryId,
                'confidentiality_id' => $confId,
                'owner_unit_id' => $unitId,
                'created_by' => $authorId,
                'lifecycle' => $lifecycle,
                'document_date' => now()->toDateString(),
                'registered_at' => now(),
            ]
        );

        $ver = $doc->versions()->first();
        if (! $ver) {
            $ver = DocumentVersion::create([
                'document_id' => $doc->id,
                'version_number' => 1,
                'created_by' => $authorId,
                'comment' => 'Initial demonstration release',
            ]);
        }

        // File Object and local disk persistence
        if (! $ver->versionFiles()->exists()) {
            $storageKey = 'documents/' . date('Y') . '/' . Str::slug($ref) . '-' . Str::random(8) . '.pdf';
            $content = "%PDF-1.4\n%WU-DMS Synthetic Demonstration Document: [{$ref}] {$title}\n%Author: {$authorId}\n%%EOF";
            Storage::disk('local')->put($storageKey, $content);

            $fileObj = FileObject::create([
                'storage_disk' => 'local',
                'storage_key' => $storageKey,
                'original_filename' => $filename,
                'detected_mime' => 'application/pdf',
                'byte_size' => strlen($content),
                'sha256' => hash('sha256', $content),
                'uploaded_by' => $authorId,
                'uploaded_at' => now(),
                'scan_status' => 'clean',
                'storage_status' => 'ready',
            ]);

            VersionFile::create([
                'document_version_id' => $ver->id,
                'file_object_id' => $fileObj->id,
                'purpose' => 'primary',
                'display_order' => 1,
            ]);
        }

        // Correspondence registration
        if ($isCorrespondence && $corrType) {
            $typeId = DB::table('correspondence_types')->where('code', $corrType)->value('id') ?? 1;
            $priorityId = DB::table('priorities')->where('code', 'normal')->value('id') ?? 1;

            DB::table('correspondences')->updateOrInsert(
                ['document_id' => $doc->id],
                [
                    'type_id' => $typeId,
                    'priority_id' => $priorityId,
                    'purpose_code' => $corrPurpose ?? 'routine',
                    'external_reference' => $externalRef,
                    'correspondence_date' => now()->toDateString(),
                    'first_receiving_unit_id' => $unitId,
                    'received_at' => now(),
                    'registered_by' => $authorId,
                    'registered_at' => now(),
                ]
            );
        }

        return $doc;
    }

    private function createSequentialWorkflowScenario(Document $doc, array $units): void
    {
        $ver = $doc->versions()->first();
        if (! $ver) return;

        $def = WorkflowDefinition::firstOrCreate(
            ['code' => 'WF-POLICY-SEQ-DEMO'],
            ['name' => 'Academic Policy Multi-Level Sequential Approval', 'active' => true]
        );

        $wfVersion = WorkflowVersion::firstOrCreate(
            ['definition_id' => $def->id, 'version_number' => 1],
            ['created_by' => 1, 'created_at' => now()]
        );

        $stReview = WorkflowState::firstOrCreate(
            ['workflow_version_id' => $wfVersion->id, 'code' => 'dept_review'],
            ['name' => 'Department Review', 'is_initial' => 1, 'is_terminal' => 0]
        );
        $stDean = WorkflowState::firstOrCreate(
            ['workflow_version_id' => $wfVersion->id, 'code' => 'dean_approval'],
            ['name' => 'College Dean Approval', 'is_initial' => 0, 'is_terminal' => 0]
        );
        $stFinal = WorkflowState::firstOrCreate(
            ['workflow_version_id' => $wfVersion->id, 'code' => 'executive_final'],
            ['name' => 'Executive Final Endorsement', 'is_initial' => 0, 'is_terminal' => 1]
        );

        $permId = DB::table('permissions')->where('code', 'workflow.approve')->value('id') ?? 1;

        WorkflowTransition::firstOrCreate(
            ['from_state_id' => $stReview->id, 'to_state_id' => $stDean->id, 'action_code' => 'endorse'],
            ['permission_id' => $permId, 'approval_policy' => 'single']
        );
        WorkflowTransition::firstOrCreate(
            ['from_state_id' => $stDean->id, 'to_state_id' => $stFinal->id, 'action_code' => 'approve'],
            ['permission_id' => $permId, 'approval_policy' => 'single']
        );

        $deanUser = User::where('email', 'demo.dean.informatics@wu.edu.et')->first();
        $authorUser = User::where('email', 'demo.doc.author@wu.edu.et')->first();

        $instance = WorkflowInstance::firstOrCreate(
            ['document_version_id' => $ver->id, 'workflow_version_id' => $wfVersion->id],
            ['started_by' => $authorUser ? $authorUser->id : 1, 'started_at' => now()]
        );

        WorkflowTask::firstOrCreate(
            ['instance_id' => $instance->id, 'state_id' => $stDean->id],
            ['assigned_user_id' => $deanUser ? $deanUser->id : 1, 'created_at' => now()]
        );
    }

    private function createParallelWorkflowScenario(Document $doc, array $units): void
    {
        $ver = $doc->versions()->first();
        if (! $ver) return;

        $def = WorkflowDefinition::firstOrCreate(
            ['code' => 'WF-CURR-PARALLEL-DEMO'],
            ['name' => 'Inter-Disciplinary Curriculum Tri-Branch Parallel Review', 'active' => true]
        );

        $wfVersion = WorkflowVersion::firstOrCreate(
            ['definition_id' => $def->id, 'version_number' => 1],
            ['created_by' => 1, 'created_at' => now()]
        );

        $stInit = WorkflowState::firstOrCreate(['workflow_version_id' => $wfVersion->id, 'code' => 'init_proposal'], ['name' => 'Initial Proposal', 'is_initial' => 1, 'is_terminal' => 0]);
        $stBranch1 = WorkflowState::firstOrCreate(['workflow_version_id' => $wfVersion->id, 'code' => 'curr_comm'], ['name' => 'Curriculum Committee Review', 'is_initial' => 0, 'is_terminal' => 0]);
        $stBranch2 = WorkflowState::firstOrCreate(['workflow_version_id' => $wfVersion->id, 'code' => 'fin_comm'], ['name' => 'Finance Committee Review', 'is_initial' => 0, 'is_terminal' => 0]);
        $stBranch3 = WorkflowState::firstOrCreate(['workflow_version_id' => $wfVersion->id, 'code' => 'qa_comm'], ['name' => 'QA Committee Review', 'is_initial' => 0, 'is_terminal' => 0]);
        $stFinal = WorkflowState::firstOrCreate(['workflow_version_id' => $wfVersion->id, 'code' => 'senate_approved'], ['name' => 'Senate Approved', 'is_initial' => 0, 'is_terminal' => 1]);

        $permId = DB::table('permissions')->where('code', 'workflow.review')->value('id') ?? 1;

        WorkflowTransition::firstOrCreate(
            ['from_state_id' => $stInit->id, 'to_state_id' => $stBranch1->id, 'action_code' => 'fork_parallel'],
            [
                'permission_id' => $permId,
                'approval_policy' => 'single',
                'fork_state_ids' => [$stBranch1->id, $stBranch2->id, $stBranch3->id],
            ]
        );

        WorkflowTransition::firstOrCreate(
            ['from_state_id' => $stBranch1->id, 'to_state_id' => $stFinal->id, 'action_code' => 'approve'],
            [
                'permission_id' => $permId,
                'approval_policy' => 'single',
                'join_policy' => 'all_branches',
            ]
        );

        $authorUser = User::where('email', 'demo.doc.author@wu.edu.et')->first();
        $reviewerUser = User::where('email', 'demo.doc.reviewer@wu.edu.et')->first();
        $coordUser = User::where('email', 'demo.research.coord@wu.edu.et')->first();
        $qaUser = User::where('email', 'pilot.qa@wu.edu.et')->first() ?? $reviewerUser;

        $instance = WorkflowInstance::firstOrCreate(
            ['document_version_id' => $ver->id, 'workflow_version_id' => $wfVersion->id],
            ['started_by' => $authorUser ? $authorUser->id : 1, 'started_at' => now()]
        );

        WorkflowTask::firstOrCreate(
            ['instance_id' => $instance->id, 'state_id' => $stBranch1->id, 'branch_key' => 'branch_curr'],
            ['assigned_user_id' => $reviewerUser ? $reviewerUser->id : 1, 'created_at' => now()]
        );
        WorkflowTask::firstOrCreate(
            ['instance_id' => $instance->id, 'state_id' => $stBranch2->id, 'branch_key' => 'branch_fin'],
            ['assigned_user_id' => $coordUser ? $coordUser->id : 1, 'created_at' => now()]
        );
        WorkflowTask::firstOrCreate(
            ['instance_id' => $instance->id, 'state_id' => $stBranch3->id, 'branch_key' => 'branch_qa'],
            ['assigned_user_id' => $qaUser ? $qaUser->id : 1, 'created_at' => now()]
        );
    }

    private function createArchivalScenario(Document $doc, array $units): void
    {
        $collection = ArchiveCollection::firstOrCreate(
            ['code' => 'COL-HIST-ACAD'],
            ['name' => 'Historical Academic Catalogues & Compendiums', 'description' => 'Permanent preservation series']
        );

        $archiveOfficer = User::where('email', 'demo.archive.officer@wu.edu.et')->first();
        $officerId = $archiveOfficer ? $archiveOfficer->id : 1;

        $entry = ArchiveEntry::firstOrCreate(
            ['document_id' => $doc->id],
            [
                'collection_id' => $collection->id,
                'retention_policy_version_id' => null,
                'archived_by' => $officerId,
                'archived_at' => now()->subMonths(6),
                'trigger_occurred_at' => now()->subMonths(6),
                'next_review_at' => now()->addYears(10),
            ]
        );

        DB::table('archive_actions')->updateOrInsert(
            ['archive_entry_id' => $entry->id, 'action' => 'transfer_in'],
            [
                'actor_id' => $officerId,
                'occurred_at' => now()->subMonths(6),
                'reason' => 'Scheduled institutional archive transfer pursuant to Records Management Policy',
            ]
        );
    }

    private function createPhysicalCustodyScenario(Document $doc, array $units): void
    {
        $location = DB::table('physical_storage_locations')->where('code', 'LOC-VAULT-02')->first();
        if (! $location) {
            $locId = DB::table('physical_storage_locations')->insertGetId([
                'unit_id' => $units['ARC']->id,
                'code' => 'LOC-VAULT-02',
                'name' => 'Central Archives Security Vault — Cabinet 02, Safe Drawer A',
                'active' => 1,
            ]);
        } else {
            $locId = $location->id;
        }

        $custodian = User::where('email', 'demo.custodian@wu.edu.et')->first();
        $custodianId = $custodian ? $custodian->id : 1;

        $physicalOriginal = DB::table('physical_originals')->where('document_id', $doc->id)->first();
        if (! $physicalOriginal) {
            $origId = DB::table('physical_originals')->insertGetId([
                'document_id' => $doc->id,
                'physical_reference' => 'PHYS-DEED-KOMB-2026-01',
                'retention_requirement' => 'required',
                'created_by' => $custodianId,
                'created_at' => now(),
            ]);
        } else {
            $origId = $physicalOriginal->id;
        }

        DB::table('physical_custody_events')->updateOrInsert(
            ['physical_original_id' => $origId, 'action' => 'register'],
            [
                'location_id' => $locId,
                'custodian_id' => $custodianId,
                'actor_id' => $custodianId,
                'occurred_at' => now(),
                'reason' => 'Secured physical deposit of certified land grant original by Legal Custodian',
            ]
        );
    }
}
