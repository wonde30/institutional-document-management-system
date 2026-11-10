<?php

namespace Database\Seeders;

use App\Models\DocumentCategory;
use App\Models\DocumentType;
use App\Models\RetentionPolicy;
use App\Models\SecurityClassification;
use App\Models\SystemSetting;
use App\Models\Tag;
use App\Models\WorkflowStep;
use App\Models\WorkflowTemplate;
use Illuminate\Database\Seeder;

class ControlledReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Security Classifications
        SecurityClassification::firstOrCreate(
            ['code' => 'PUB'],
            [
                'name' => 'Public',
                'description' => 'Freely accessible institutional materials, public press releases, newsletters',
                'level' => 1,
            ]
        );

        SecurityClassification::firstOrCreate(
            ['code' => 'INT'],
            [
                'name' => 'Internal',
                'description' => 'General university working documents, internal guidelines, committee notices',
                'level' => 2,
            ]
        );

        SecurityClassification::firstOrCreate(
            ['code' => 'CONF'],
            [
                'name' => 'Confidential',
                'description' => 'Restricted departmental budgets, personnel evaluations, internal audit memos',
                'level' => 3,
            ]
        );

        SecurityClassification::firstOrCreate(
            ['code' => 'REST'],
            [
                'name' => 'Restricted',
                'description' => 'Executive council minutes, sealed disciplinary investigations, national security correspondence',
                'level' => 4,
            ]
        );

        // 2. Document Categories
        $academic = DocumentCategory::firstOrCreate(
            ['code' => 'ACAD'],
            [
                'name' => 'Academic & Curricular',
                'description' => 'Curriculum guidelines, syllabi, grading policies, academic regulations',
                'sort_order' => 1,
            ]
        );

        DocumentCategory::firstOrCreate(
            ['code' => 'ADMIN'],
            [
                'name' => 'Administrative & Governance',
                'description' => 'Statutes, senate decisions, executive directives, council minutes',
                'sort_order' => 2,
            ]
        );

        DocumentCategory::firstOrCreate(
            ['code' => 'FIN'],
            [
                'name' => 'Financial & Audit',
                'description' => 'Annual budgets, quarterly reports, internal/external audit findings',
                'sort_order' => 3,
            ]
        );

        DocumentCategory::firstOrCreate(
            ['code' => 'HR'],
            [
                'name' => 'Human Resources & Personnel',
                'description' => 'Staff handbook, employment contracts, promotions, retirement dossiers',
                'sort_order' => 4,
            ]
        );

        DocumentCategory::firstOrCreate(
            ['code' => 'RES'],
            [
                'name' => 'Research & Community Service',
                'description' => 'Research grant agreements, conference proceedings, patent applications',
                'sort_order' => 5,
            ]
        );

        DocumentCategory::firstOrCreate(
            ['code' => 'STUD'],
            [
                'name' => 'Student Affairs & Admissions',
                'description' => 'Student conduct codes, admissions criteria, scholarship awards',
                'sort_order' => 6,
            ]
        );

        $acronym = 'WU';

        // 3. Document Types
        DocumentType::firstOrCreate(
            ['code' => 'POL'],
            [
                'name' => 'Institutional Policy',
                'description' => 'Officially approved institutional policies and governance guidelines',
                'reference_prefix' => "{$acronym}-POL",
                'requires_approval' => true,
            ]
        );

        DocumentType::firstOrCreate(
            ['code' => 'DIR'],
            [
                'name' => 'Executive Directive',
                'description' => 'Directives and orders issued by the Executive Leadership or Directors',
                'reference_prefix' => "{$acronym}-DIR",
                'requires_approval' => true,
            ]
        );

        DocumentType::firstOrCreate(
            ['code' => 'RES'],
            [
                'name' => 'Governing Board Resolution',
                'description' => 'Formal resolutions, meeting minutes, and decisions of the Board/Senate',
                'reference_prefix' => "{$acronym}-RES",
                'requires_approval' => true,
            ]
        );

        DocumentType::firstOrCreate(
            ['code' => 'SOP'],
            [
                'name' => 'Standard Operating Procedure (SOP)',
                'description' => 'Detailed step-by-step procedures for operational units',
                'reference_prefix' => "{$acronym}-SOP",
                'requires_approval' => true,
            ]
        );

        DocumentType::firstOrCreate(
            ['code' => 'RPT'],
            [
                'name' => 'Quarterly / Annual Report',
                'description' => 'Departmental, branch, and organization-wide operational reports',
                'reference_prefix' => "{$acronym}-RPT",
                'requires_approval' => false,
            ]
        );

        DocumentType::firstOrCreate(
            ['code' => 'MEM'],
            [
                'name' => 'Official Letter / Memorandum',
                'description' => 'Formal internal and inter-departmental letters and notifications',
                'reference_prefix' => "{$acronym}-MEM",
                'requires_approval' => false,
            ]
        );

        // 4. Tags
        $tagNames = ['Accreditation', 'Strategic Plan 2026', 'Curriculum Reform', 'Budget FY2026', 'Health Sciences', 'Research Grant', 'Audit Finding', 'Senate Action'];
        foreach ($tagNames as $name) {
            Tag::firstOrCreate(
                ['slug' => strtolower(str_replace(' ', '-', $name))],
                ['name' => $name]
            );
        }

        // 5. Retention Policies
        RetentionPolicy::firstOrCreate(
            ['code' => 'RET-GEN'],
            [
                'name' => 'General Administrative Records',
                'description' => 'Standard inter-office memos, operational correspondence, routine announcements',
                'retention_years' => 5,
                'requires_approval_for_disposal' => true,
            ]
        );

        RetentionPolicy::firstOrCreate(
            ['code' => 'RET-FIN'],
            [
                'name' => 'Financial & Procurement Records',
                'description' => 'Financial ledgers, payment vouchers, audit queries, procurement tenders',
                'retention_years' => 10,
                'requires_approval_for_disposal' => true,
            ]
        );

        RetentionPolicy::firstOrCreate(
            ['code' => 'RET-ACAD-PERM'],
            [
                'name' => 'Academic Transcripts & Degree Records',
                'description' => 'Permanent retention for student grades, graduation registries, diploma copies',
                'retention_years' => 99,
                'requires_approval_for_disposal' => true,
            ]
        );

        RetentionPolicy::firstOrCreate(
            ['code' => 'RET-HR'],
            [
                'name' => 'Personnel & Employment Dossiers',
                'description' => 'Staff personal records, retained 7 years following official separation/retirement',
                'retention_years' => 7,
                'requires_approval_for_disposal' => true,
            ]
        );

        RetentionPolicy::firstOrCreate(
            ['code' => 'RET-RES'],
            [
                'name' => 'Research Publications & Patents',
                'description' => 'Research output, intellectual property, university journal archives',
                'retention_years' => 20,
                'requires_approval_for_disposal' => true,
            ]
        );

        // 6. Workflow Templates and Steps
        $docWorkflow = WorkflowTemplate::firstOrCreate(
            ['name' => 'Standard Document Approval Workflow'],
            [
                'entity_type' => 'document',
                'description' => 'Standard multi-stage review and approval cycle for institutional documents',
                'is_active' => true,
            ]
        );

        WorkflowStep::firstOrCreate(
            [
                'workflow_template_id' => $docWorkflow->id,
                'from_status' => 'draft',
                'to_status' => 'pending_review',
            ],
            [
                'action_name' => 'Submit for Review',
                'required_permission' => 'documents.create',
                'description' => 'Submit draft document for departmental or controller review',
                'sort_order' => 1,
            ]
        );

        WorkflowStep::firstOrCreate(
            [
                'workflow_template_id' => $docWorkflow->id,
                'from_status' => 'pending_review',
                'to_status' => 'under_review',
            ],
            [
                'action_name' => 'Begin Review',
                'required_permission' => 'workflow.transition',
                'description' => 'Reviewer opens document and conducts editorial and compliance checks',
                'sort_order' => 2,
            ]
        );

        WorkflowStep::firstOrCreate(
            [
                'workflow_template_id' => $docWorkflow->id,
                'from_status' => 'under_review',
                'to_status' => 'approved',
            ],
            [
                'action_name' => 'Approve Document',
                'required_permission' => 'workflow.transition',
                'description' => 'Approver grants formal approval and publishes document to repository',
                'sort_order' => 3,
            ]
        );

        WorkflowStep::firstOrCreate(
            [
                'workflow_template_id' => $docWorkflow->id,
                'from_status' => 'under_review',
                'to_status' => 'rejected',
            ],
            [
                'action_name' => 'Reject / Request Changes',
                'required_permission' => 'workflow.transition',
                'description' => 'Send document back to author with correction comments',
                'sort_order' => 4,
            ]
        );

        WorkflowStep::firstOrCreate(
            [
                'workflow_template_id' => $docWorkflow->id,
                'from_status' => 'approved',
                'to_status' => 'archived',
            ],
            [
                'action_name' => 'Archive Document',
                'required_permission' => 'archive.transfer',
                'description' => 'Transfer superseded or historical document to digital archive',
                'sort_order' => 5,
            ]
        );

        // 7. System Settings
        $instName = 'Wollo University';

        $settings = [
            ['group' => 'institution', 'key' => 'university_name', 'value' => $instName, 'type' => 'string', 'description' => 'Official name of the institution'],
            ['group' => 'institution', 'key' => 'system_title', 'value' => 'Integrated Document Repository, Digital Archive & Inter-office Communication', 'type' => 'string', 'description' => 'Portal banner title'],
            ['group' => 'storage', 'key' => 'max_upload_size_mb', 'value' => '50', 'type' => 'integer', 'description' => 'Maximum allowed file upload size in megabytes'],
            ['group' => 'storage', 'key' => 'allowed_extensions', 'value' => json_encode(['pdf', 'docx', 'xlsx', 'pptx', 'txt', 'png', 'jpg']), 'type' => 'json', 'description' => 'Permissible document upload extensions'],
            ['group' => 'workflow', 'key' => 'auto_due_days_incoming', 'value' => '7', 'type' => 'integer', 'description' => 'Default days until incoming correspondence is marked due'],
            ['group' => 'archive', 'key' => 'require_dual_approval_disposal', 'value' => 'true', 'type' => 'boolean', 'description' => 'Require administrative head concurrence before physical/digital disposal'],
        ];

        foreach ($settings as $setting) {
            SystemSetting::firstOrCreate(
                ['group' => $setting['group'], 'key' => $setting['key']],
                $setting
            );
        }
    }
}
