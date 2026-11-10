<?php

namespace Database\Seeders;

use App\Models\ArchiveRecord;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\IncomingCorrespondence;
use App\Models\Memo;
use App\Models\MemoRecipient;
use App\Models\OutgoingCorrespondence;
use App\Models\RetentionPolicy;
use App\Models\SecurityClassification;
use App\Models\Tag;
use App\Models\User;
use App\Models\WorkflowAction;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $institutionName = 'Wollo University';
        $acronym = 'WU';

        if (Document::where('reference_number', "{$acronym}-POL-2026-0001")->exists()) {
            return;
        }

        $admin = User::where('email', "admin@{$acronym}")->orWhere('email', 'like', 'admin@%')->first();
        $docController = User::where('email', 'like', 'doc.controller@%')->orWhere('email', 'like', '%controller%')->first() ?? $admin;
        $headAad = User::where('email', 'like', 'head.aad@%')->orWhere('email', 'like', '%director%')->first() ?? $admin;
        $recordsOfficer = User::where('email', 'like', 'records.officer@%')->orWhere('email', 'like', '%records%')->first() ?? $admin;
        $archivist = User::where('email', 'like', 'archivist@%')->first() ?? $admin;
        $staff = User::where('email', 'like', 'staff.ict@%')->first() ?? $admin;

        $aad = Department::where('code', 'AAD')->first();
        $op = Department::where('code', 'OP')->first();
        $ictc = Department::where('code', 'ICTC')->first();

        $policyType = DocumentType::where('code', 'POL')->first();
        $directiveType = DocumentType::where('code', 'DIR')->first();
        $sopType = DocumentType::where('code', 'SOP')->first();

        $acadCategory = DocumentCategory::where('code', 'ACAD')->first();
        $adminCategory = DocumentCategory::where('code', 'ADMIN')->first();

        $confClassification = SecurityClassification::where('code', 'CONF')->first();
        $intClassification = SecurityClassification::where('code', 'INT')->first();
        $pubClassification = SecurityClassification::where('code', 'PUB')->first();

        // Ensure storage directory exists and seed dummy sample files
        Storage::disk('local')->makeDirectory('documents');
        $samplePdfContent = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n4 0 obj\n<< /Length 53 >>\nstream\nBT /F1 12 Tf 72 712 Td ({$institutionName} Official Document) Tj ET\nendstream\nendobj\nxref\n0 5\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \n0000000196 00000 n \ntrailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n300\n%%EOF";
        
        $filePath1 = "documents/{$acronym}-POL-2026-0001_v1.pdf";
        $filePath2 = "documents/{$acronym}-DIR-2026-0002_v1.pdf";
        $filePath3 = "documents/{$acronym}-SOP-2020-0014_v1.pdf";
        
        Storage::disk('local')->put($filePath1, $samplePdfContent);
        Storage::disk('local')->put($filePath2, $samplePdfContent);
        Storage::disk('local')->put($filePath3, $samplePdfContent);

        // 1. Seed Published Document
        $doc1 = Document::create([
            'reference_number' => "{$acronym}-POL-2026-0001",
            'title' => "{$institutionName} Comprehensive Academic Integrity & Governance Policy",
            'description' => "Institutional guidelines and administrative procedures regarding academic standards, documentation compliance, and organizational accountability.",
            'document_type_id' => $policyType->id,
            'category_id' => $acadCategory->id,
            'security_classification_id' => $pubClassification->id,
            'department_id' => $aad->id,
            'created_by' => $docController->id,
            'owner_id' => $headAad->id,
            'status' => 'approved',
            'current_version' => 1,
            'published_at' => Carbon::now()->subDays(15),
        ]);

        DocumentVersion::create([
            'document_id' => $doc1->id,
            'version_number' => 1,
            'file_path' => $filePath1,
            'file_name' => 'WU-Academic-Integrity-Policy-2026.pdf',
            'file_mime_type' => 'application/pdf',
            'file_size' => strlen($samplePdfContent),
            'file_hash' => hash('sha256', $samplePdfContent),
            'change_summary' => 'Initial release approved by Academic Senate',
            'uploaded_by' => $docController->id,
            'created_at' => Carbon::now()->subDays(15),
        ]);

        $tags = Tag::whereIn('slug', ['accreditation', 'curriculum-reform'])->get();
        $doc1->tags()->sync($tags);

        WorkflowAction::create([
            'workflowable_type' => Document::class,
            'workflowable_id' => $doc1->id,
            'action' => 'Approve Document',
            'from_status' => 'under_review',
            'to_status' => 'approved',
            'performed_by' => $headAad->id,
            'comments' => 'Reviewed against National Higher Education proclamation and approved without reservations.',
            'performed_at' => Carbon::now()->subDays(15),
        ]);

        // 2. Seed Document Pending Review
        $doc2 = Document::create([
            'reference_number' => "{$acronym}-DIR-2026-0002",
            'title' => 'Executive Directive on Campus Digital Signature & Zero-Paper Initiative',
            'description' => 'Mandating digital endorsement protocols for inter-office memos and internal financial requisitions.',
            'document_type_id' => $directiveType->id,
            'category_id' => $adminCategory->id,
            'security_classification_id' => $intClassification->id,
            'department_id' => $op->id,
            'created_by' => $recordsOfficer->id,
            'owner_id' => $admin->id,
            'status' => 'pending_review',
            'current_version' => 1,
        ]);

        DocumentVersion::create([
            'document_id' => $doc2->id,
            'version_number' => 1,
            'file_path' => $filePath2,
            'file_name' => "{$acronym}-Directive-Digital-Signature.pdf",
            'file_mime_type' => 'application/pdf',
            'file_size' => strlen($samplePdfContent),
            'file_hash' => hash('sha256', $samplePdfContent),
            'change_summary' => 'Submitted draft awaiting executive director review',
            'uploaded_by' => $recordsOfficer->id,
            'created_at' => Carbon::now()->subDays(2),
        ]);

        // 3. Seed Incoming Correspondence
        $inc1 = IncomingCorrespondence::create([
            'reference_number' => "{$acronym}-INC-2026-0001",
            'sender_name' => 'Ministry of Education (FDRE)',
            'sender_organization' => 'Higher Education Governance Directorate',
            'sender_reference' => 'MoE/HEG/442/2026',
            'subject' => 'Call for Annual Quality Audit and Institutional Accreditation Data Submission',
            'description' => "Directing {$institutionName} to submit updated QA self-assessment and compliance ratios by the designated deadline.",
            'date_received' => Carbon::now()->subDays(5)->toDateString(),
            'time_received' => '09:30:00',
            'priority' => 'urgent',
            'receiving_department_id' => $op->id,
            'registered_by' => $recordsOfficer->id,
            'assigned_to' => $headAad->id,
            'assigned_department_id' => $aad->id,
            'due_date' => Carbon::now()->addDays(10)->toDateString(),
            'status' => 'in_progress',
            'notes' => 'Assigned to Academic VP with priority attention.',
        ]);

        // 4. Seed Outgoing Correspondence
        $out1 = OutgoingCorrespondence::create([
            'reference_number' => "{$acronym}-OUT-2026-0001",
            'recipient_name' => 'Dr. Kassa Tefera, Director General',
            'recipient_organization' => 'Ethiopian Higher Education Quality Agency (HERQA)',
            'recipient_address' => 'P.O. Box 1367, Addis Ababa, Ethiopia',
            'subject' => "Submission of {$institutionName} Institutional Accreditation Renewal Dossier",
            'description' => 'Official transmittal of curricular assessment and organizational documentation.',
            'sending_department_id' => $aad->id,
            'prepared_by' => $headAad->id,
            'approved_by' => $admin->id,
            'priority' => 'high',
            'status' => 'approved',
            'dispatch_date' => Carbon::now()->subDay()->toDateString(),
            'dispatch_method' => 'Registered Courier & Electronic Copy',
            'notes' => 'Dispatched with tracking no. ETH-POST-881920.',
        ]);

        // 5. Seed Inter-Office Memo
        $memo1 = Memo::create([
            'reference_number' => "{$acronym}-MEM-2026-0001",
            'subject' => 'Implementation of New Document Repository System & Staff Orientation Schedule',
            'body' => "To all Department Directors and Unit Heads:\n\nPlease be advised that the new {$institutionName} Integrated Document Repository and Digital Archive goes into official production this week.\n\nAll official documents, correspondence, and inter-office communications must now be registered through this portal. Mandatory training sessions will be held at the ICT auditorium on Wednesday.",
            'priority' => 'high',
            'confidentiality' => 'normal',
            'from_department_id' => $ictc->id,
            'from_user_id' => $admin->id,
            'status' => 'sent',
            'sent_at' => Carbon::now()->subDays(3),
        ]);

        MemoRecipient::create([
            'memo_id' => $memo1->id,
            'department_id' => $aad->id,
            'user_id' => $headAad->id,
            'recipient_type' => 'to',
            'is_read' => true,
            'read_at' => Carbon::now()->subDays(2),
            'is_acknowledged' => true,
            'acknowledged_at' => Carbon::now()->subDays(1),
            'acknowledged_by' => $headAad->id,
            'created_at' => Carbon::now()->subDays(3),
        ]);

        MemoRecipient::create([
            'memo_id' => $memo1->id,
            'department_id' => $op->id,
            'user_id' => $recordsOfficer->id,
            'recipient_type' => 'cc',
            'is_read' => true,
            'read_at' => Carbon::now()->subDays(2),
            'created_at' => Carbon::now()->subDays(3),
        ]);

        // 6. Seed Archived Record
        $retentionGen = RetentionPolicy::where('code', 'RET-GEN')->first();
        $docArchived = Document::create([
            'reference_number' => "{$acronym}-SOP-2020-0014",
            'title' => 'Emergency Remote Instruction Protocol (2020 Revision)',
            'description' => 'Superseded distance learning guidelines utilized during institutional containment.',
            'document_type_id' => $sopType->id,
            'category_id' => $acadCategory->id,
            'security_classification_id' => $intClassification->id,
            'department_id' => $aad->id,
            'created_by' => $docController->id,
            'owner_id' => $headAad->id,
            'status' => 'archived',
            'current_version' => 1,
            'published_at' => Carbon::now()->subYears(4),
        ]);

        DocumentVersion::create([
            'document_id' => $docArchived->id,
            'version_number' => 1,
            'file_path' => $filePath3,
            'file_name' => "{$acronym}-SOP-2020-0014-Archive.pdf",
            'file_mime_type' => 'application/pdf',
            'file_size' => strlen($samplePdfContent),
            'file_hash' => hash('sha256', $samplePdfContent),
            'change_summary' => 'Archival transfer upon policy deprecation',
            'uploaded_by' => $archivist->id,
            'created_at' => Carbon::now()->subMonths(6),
        ]);

        ArchiveRecord::create([
            'archive_reference' => "{$acronym}-ARC-2026-0001",
            'archivable_type' => Document::class,
            'archivable_id' => $docArchived->id,
            'retention_policy_id' => $retentionGen->id,
            'archived_by' => $archivist->id,
            'archive_date' => Carbon::now()->subMonths(6)->toDateString(),
            'retention_review_date' => Carbon::now()->addYears(4)->toDateString(),
            'disposal_eligible_date' => Carbon::now()->addYears(5)->toDateString(),
            'status' => 'active',
            'archival_notes' => 'Preserved for institutional historical precedent. Digital surrogate intact.',
            'original_metadata' => [
                'title' => $docArchived->title,
                'author' => 'Academic Affairs Directorate',
                'year' => 2020,
            ],
        ]);

        // 7. Initial Audit Log
        AuditLog::create([
            'user_id' => $admin->id,
            'event' => 'system.initialization',
            'auditable_type' => null,
            'auditable_id' => null,
            'old_values' => null,
            'new_values' => ['system' => "{$institutionName} DMS v1.0", 'environment' => 'production_ready'],
            'ip_address' => '127.0.0.1',
            'user_agent' => "{$institutionName} System Setup",
            'created_at' => Carbon::now()->subDays(30),
        ]);
    }
}
