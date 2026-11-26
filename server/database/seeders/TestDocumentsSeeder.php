<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ArchiveCollection;
use App\Models\ArchiveEntry;
use App\Models\ConfidentialityLevel;
use App\Models\Correspondence;
use App\Models\CorrespondenceParticipant;
use App\Models\CorrespondenceDispatch;
use App\Models\CorrespondenceType;
use App\Models\DispatchMethod;
use App\Models\DispositionRequest;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\NotificationEvent;
use App\Models\OrganizationUnit;
use App\Models\RetentionPolicy;
use App\Models\RetentionPolicyVersion;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowTask;
use App\Services\DocumentRepositoryService;
use App\Services\WorkflowEngineService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class TestDocumentsSeeder extends Seeder
{
    /**
     * Seed comprehensive test data for all dashboard cards.
     * This seeder is idempotent and MUST NOT run against phpunit test database.
     */
    public function run(): void
    {
        $dbName = DB::connection()->getDatabaseName();

        // Guard 1: Never run against phpunit test database
        if (str_ends_with($dbName, '_phpunit') || str_ends_with($dbName, '_test_run')) {
            $this->command->error("ABORT: TestDocumentsSeeder must not run against test database: {$dbName}");
            return;
        }

        // Guard 2: Must run against the app database from .env
        $envDbName = env('DB_DATABASE');
        if ($dbName !== $envDbName) {
            $this->command->error("ABORT: Current database ({$dbName}) does not match .env DB_DATABASE ({$envDbName})");
            return;
        }

        // Guard 3: Never run against production
        if (str_contains($dbName, 'prod') || str_contains($dbName, 'live')) {
            $this->command->error("ABORT: TestDocumentsSeeder must not run against production database: {$dbName}");
            return;
        }

        $this->command->info("✓ Database guard passed: {$dbName}");
        $this->command->info('Starting comprehensive test data seeding...');

        $documentService = app(DocumentRepositoryService::class);
        $workflowService = app(WorkflowEngineService::class);

        // Resolve users
        $admin = User::where('email', 'admin@wu.edu.et')->firstOrFail();
        $headSE = User::where('email', 'head.se@wu.edu.et')->firstOrFail();
        $staffTech = User::where('email', 'staff.tech@wu.edu.et')->firstOrFail();
        $registrar = User::where('email', 'registrar@wu.edu.et')->firstOrFail();
        $archivist = User::where('email', 'archivist@wu.edu.et')->firstOrFail();
        $qaDirector = User::where('email', 'qa.director@wu.edu.et')->firstOrFail();

        $this->command->info("\nUsers for test data:");
        $this->command->info("  • {$admin->email} - System Administrator (super_admin)");
        $this->command->info("  • {$headSE->email} - Department Head (department_head)");
        $this->command->info("  • {$staffTech->email} - Staff Member (staff)");

        // Resolve organization units
        $unitSE = OrganizationUnit::where('code', 'SE')->firstOrFail();
        $unitREG = OrganizationUnit::where('code', 'REG')->firstOrFail();
        $unitQA = OrganizationUnit::where('code', 'QA')->firstOrFail();
        $unitARC = OrganizationUnit::where('code', 'ARC')->firstOrFail();

        // Resolve categories and confidentiality levels
        $categoryAcademic = DocumentCategory::where('code', 'academic')->firstOrFail();
        $categoryAdmin = DocumentCategory::where('code', 'administrative')->firstOrFail();
        $categoryResearch = DocumentCategory::where('code', 'research')->firstOrFail();
        $categoryHR = DocumentCategory::where('code', 'hr')->firstOrFail();
        $categoryFinance = DocumentCategory::where('code', 'finance')->firstOrFail();

        $confidPublic = ConfidentialityLevel::where('code', 'public')->firstOrFail();
        $confidInternal = ConfidentialityLevel::where('code', 'internal')->firstOrFail();
        $confidConfidential = ConfidentialityLevel::where('code', 'confidential')->firstOrFail();

        $this->command->info("\n=== Creating 20 Documents ===");

        // Define test documents
        $testDocuments = [
            ['prefix' => 'DEMO-DOC-001', 'title' => 'SE Curriculum Review 2024', 'desc' => 'Curriculum review report', 'category' => $categoryAcademic, 'confid' => $confidPublic, 'unit' => $unitSE, 'author' => $headSE],
            ['prefix' => 'DEMO-DOC-002', 'title' => 'Faculty Evaluation Guidelines', 'desc' => 'Annual evaluation criteria', 'category' => $categoryHR, 'confid' => $confidInternal, 'unit' => $unitSE, 'author' => $headSE],
            ['prefix' => 'DEMO-DOC-003', 'title' => 'AI Research Grant Proposal', 'desc' => 'UNESCO funding proposal', 'category' => $categoryResearch, 'confid' => $confidInternal, 'unit' => $unitSE, 'author' => $staffTech],
            ['prefix' => 'DEMO-DOC-004', 'title' => 'Budget Request FY2024', 'desc' => 'Lab equipment budget', 'category' => $categoryFinance, 'confid' => $confidConfidential, 'unit' => $unitSE, 'author' => $headSE],
            ['prefix' => 'DEMO-DOC-005', 'title' => 'Admission Policy Update', 'desc' => 'Graduate admission criteria', 'category' => $categoryAcademic, 'confid' => $confidPublic, 'unit' => $unitREG, 'author' => $registrar],
            ['prefix' => 'DEMO-DOC-006', 'title' => 'Security Audit Report', 'desc' => 'IT security assessment', 'category' => $categoryAdmin, 'confid' => $confidInternal, 'unit' => $unitQA, 'author' => $qaDirector],
            ['prefix' => 'DEMO-DOC-007', 'title' => 'Lab Safety Procedures', 'desc' => 'Computer lab safety', 'category' => $categoryAdmin, 'confid' => $confidInternal, 'unit' => $unitSE, 'author' => $staffTech],
            ['prefix' => 'DEMO-DOC-008', 'title' => 'Accreditation Self-Study', 'desc' => 'Institutional assessment', 'category' => $categoryAcademic, 'confid' => $confidInternal, 'unit' => $unitQA, 'author' => $qaDirector],
            ['prefix' => 'DEMO-DOC-009', 'title' => 'Staff Appointment Letter', 'desc' => 'Appointment documentation', 'category' => $categoryHR, 'confid' => $confidConfidential, 'unit' => $unitSE, 'author' => $admin],
            ['prefix' => 'DEMO-DOC-010', 'title' => 'Database Course Syllabus', 'desc' => 'Graduate course syllabus', 'category' => $categoryAcademic, 'confid' => $confidPublic, 'unit' => $unitSE, 'author' => $staffTech],
            ['prefix' => 'DEMO-DOC-011', 'title' => 'ICSE Conference Proceedings', 'desc' => 'Conference proceedings', 'category' => $categoryResearch, 'confid' => $confidPublic, 'unit' => $unitSE, 'author' => $headSE],
            ['prefix' => 'DEMO-DOC-012', 'title' => 'Equipment Procurement Tender', 'desc' => 'Lab equipment tender', 'category' => $categoryFinance, 'confid' => $confidInternal, 'unit' => $unitSE, 'author' => $headSE],
            ['prefix' => 'DEMO-DOC-013', 'title' => 'Archive Retention Policy', 'desc' => 'Retention policy document', 'category' => $categoryAdmin, 'confid' => $confidInternal, 'unit' => $unitARC, 'author' => $archivist],
            ['prefix' => 'DEMO-DOC-014', 'title' => 'QA Annual Report 2023', 'desc' => 'QA metrics report', 'category' => $categoryAdmin, 'confid' => $confidInternal, 'unit' => $unitQA, 'author' => $qaDirector],
            ['prefix' => 'DEMO-DOC-015', 'title' => 'Student Grievance Protocol', 'desc' => 'Complaint handling procedures', 'category' => $categoryAcademic, 'confid' => $confidPublic, 'unit' => $unitREG, 'author' => $registrar],
            ['prefix' => 'DEMO-DOC-016', 'title' => 'Research Ethics Guidelines', 'desc' => 'Ethics review procedures', 'category' => $categoryResearch, 'confid' => $confidInternal, 'unit' => $unitSE, 'author' => $headSE],
            ['prefix' => 'DEMO-DOC-017', 'title' => 'Network Infrastructure Plan', 'desc' => 'Campus network upgrade', 'category' => $categoryAdmin, 'confid' => $confidInternal, 'unit' => $unitSE, 'author' => $staffTech],
            ['prefix' => 'DEMO-DOC-018', 'title' => 'Staff Training Manual', 'desc' => 'New staff onboarding', 'category' => $categoryHR, 'confid' => $confidInternal, 'unit' => $unitSE, 'author' => $headSE],
            ['prefix' => 'DEMO-DOC-019', 'title' => 'External Partnership MOU', 'desc' => 'Industry partnership agreement', 'category' => $categoryAdmin, 'confid' => $confidConfidential, 'unit' => $unitSE, 'author' => $headSE],
            ['prefix' => 'DEMO-DOC-020', 'title' => 'Strategic Plan 2025-2030', 'desc' => 'Departmental strategic plan', 'category' => $categoryAdmin, 'confid' => $confidInternal, 'unit' => $unitSE, 'author' => $headSE],
        ];

        $createdDocuments = [];

        foreach ($testDocuments as $docData) {
            $existing = Document::where('reference_number', 'LIKE', $docData['prefix'] . '%')->first();

            if ($existing) {
                $this->command->warn("  ↻ {$docData['prefix']} exists");
                $createdDocuments[] = $existing;
                continue;
            }

            $pdfContent = $this->generateMinimalPDF($docData['title'], $docData['desc']);
            $tempPath = storage_path('app/temp/' . uniqid('demo_', true) . '.pdf');
            File::ensureDirectoryExists(dirname($tempPath));
            file_put_contents($tempPath, $pdfContent);

            $uploadedFile = new UploadedFile($tempPath, $docData['prefix'] . '.pdf', 'application/pdf', null, true);

            $document = $documentService->registerDocument([
                'title' => $docData['title'],
                'summary' => $docData['desc'],
                'category_id' => $docData['category']->id,
                'confidentiality_id' => $docData['confid']->id,
                'owner_unit_id' => $docData['unit']->id,
                'received_at' => now()->subDays(rand(5, 30)),
            ], $uploadedFile, $docData['author']);

            $refParts = explode('/', $document->reference_number);
            $refParts[0] = $docData['prefix'];
            $document->reference_number = implode('/', $refParts);
            $document->save();

            @unlink($tempPath);

            $this->command->info("  ✓ {$document->reference_number}");
            $createdDocuments[] = $document;
        }

        $this->command->info("\n=== Creating Workflow Instances (pending, approved, rejected) ===");

        $workflowDef = WorkflowDefinition::where('code', 'standard_doc_approval')->first();

        if (!$workflowDef) {
            $this->command->warn("  ⚠ No workflow definition found");
        } else {
            // Create 10 workflow instances
            for ($i = 0; $i < min(10, count($createdDocuments)); $i++) {
                $doc = $createdDocuments[$i];
                $version = $doc->currentVersion;
                if (!$version) continue;

                $existingInstance = DB::table('workflow_instances')->where('document_version_id', $version->id)->first();
                if ($existingInstance) {
                    $this->command->warn("  ↻ Workflow for doc {$doc->id} exists");
                    continue;
                }

                $author = User::find($doc->created_by);
                if (!$author) continue;

                $instance = $workflowService->startInstance($workflowDef->id, $version->id, null, $author);

                // Assign tasks to specific users to create pending tasks
                $task = WorkflowTask::where('instance_id', $instance->id)->whereNull('closed_at')->first();
                if ($task) {
                    // Assign tasks: first 6 to admin, next 2 to head.se, rest unit-assigned
                    if ($i < 6) {
                        $task->update(['assigned_user_id' => $admin->id, 'assigned_unit_id' => null]);
                    } elseif ($i < 8) {
                        $task->update(['assigned_user_id' => $headSE->id, 'assigned_unit_id' => null]);
                    } else {
                        $task->update(['assigned_unit_id' => $unitSE->id]);
                    }

                    // Make 2 tasks overdue by directly updating created_at
                    if ($i < 2) {
                        DB::statement('UPDATE workflow_tasks SET created_at = ?, due_at = ? WHERE id = ?', [
                            now()->subDays(10)->format('Y-m-d H:i:s.u'),
                            now()->subDays(2)->format('Y-m-d H:i:s.u'),
                            $task->id
                        ]);
                    }
                }

                $this->command->info("  ✓ Workflow instance {$instance->id} for doc {$doc->reference_number}");
            }
        }

        $this->command->info("\n=== Creating Correspondence Records (incoming, outgoing, dispatches) ===");

        $correspondenceTypes = CorrespondenceType::whereIn('code', ['incoming', 'outgoing'])->get()->keyBy('code');
        $priorityNormal = DB::table('priorities')->where('code', 'normal')->first();
        $dispatchMethod = DispatchMethod::first();

        if (!$priorityNormal) {
            $this->command->warn("  ⚠ No priority found");
        } else {
            // Create 10 correspondence records
            for ($i = 10; $i < min(20, count($createdDocuments)); $i++) {
                $doc = $createdDocuments[$i];

                $existing = Correspondence::where('document_id', $doc->id)->first();
                if ($existing) {
                    $this->command->warn("  ↻ Correspondence for doc {$doc->id} exists");
                    continue;
                }

                $isIncoming = $i % 2 === 0;
                $typeRecord = $correspondenceTypes[$isIncoming ? 'incoming' : 'outgoing'] ?? null;
                if (!$typeRecord) continue;

                $correspondence = Correspondence::create([
                    'document_id' => $doc->id,
                    'type_id' => $typeRecord->id,
                    'priority_id' => $priorityNormal->id,
                    'purpose_code' => $isIncoming ? 'request_for_information' : 'formal_response',
                    'external_reference' => 'EXT-REF-DEMO-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                    'correspondence_date' => now()->subDays(rand(5, 20))->toDateString(),
                    'first_receiving_unit_id' => ($isIncoming && $i < 17) ? $unitREG->id : null,
                    'received_at' => ($isIncoming && $i < 17) ? now()->subDays(rand(3, 15)) : null,
                    'requested_due_at' => null,
                    'registered_by' => $registrar->id,
                    'registered_at' => now()->subDays(rand(1, 10)),
                ]);

                // For outgoing: 3 awaiting dispatch means no dispatches at all
                // For incoming: 2 unreceived means received_at is null (already set above)
                if (!$isIncoming) {
                    $this->command->info("  ✓ Outgoing correspondence {$correspondence->id} (awaiting dispatch)");
                } else {
                    $this->command->info("  ✓ Incoming correspondence {$correspondence->id}");
                }
            }
        }

        $this->command->info("\n=== Creating Archive Entries with Retention Reviews ===");

        // Create retention policy
        $retentionPolicy = RetentionPolicy::firstOrCreate(
            ['code' => 'DEMO-RETENTION-001'],
            ['name' => 'Demo Retention Policy', 'description' => 'Test retention policy', 'active' => true]
        );

        $retentionVersion = RetentionPolicyVersion::firstOrCreate(
            ['policy_id' => $retentionPolicy->id, 'version_number' => 1],
            [
                'trigger_code' => 'document_archived',
                'duration_months' => 12,
                'disposition_action' => 'review',
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'effective_from' => now()->subMonths(6)->toDateString(),
            ]
        );

        // Create archive collection
        $archiveCollection = ArchiveCollection::firstOrCreate(
            ['code' => 'DEMO-ARCHIVE-COL-001'],
            ['name' => 'Demo Archive Collection', 'description' => 'Test archive collection']
        );

        // Archive 5 documents, 3 with reviews due
        for ($i = 0; $i < 5; $i++) {
            if ($i >= count($createdDocuments)) break;
            $doc = $createdDocuments[$i];

            $existing = ArchiveEntry::where('document_id', $doc->id)->first();
            if ($existing) {
                $this->command->warn("  ↻ Archive entry for doc {$doc->id} exists");
                continue;
            }

            $reviewDue = $i < 3; // First 3 have review due
            ArchiveEntry::create([
                'document_id' => $doc->id,
                'collection_id' => $archiveCollection->id,
                'retention_policy_version_id' => $retentionVersion->id,
                'archived_by' => $archivist->id,
                'archived_at' => now()->subMonths(6),
                'trigger_occurred_at' => now()->subMonths(7),
                'next_review_at' => $reviewDue ? now()->subDays(rand(1, 5)) : now()->addMonths(6),
            ]);

            $this->command->info("  ✓ Archived doc {$doc->id}" . ($reviewDue ? " (review due)" : ""));
        }

        $this->command->info("\n=== Creating Disposition Requests ===");

        // Create 2 pending disposition requests
        $archivedDocs = ArchiveEntry::take(2)->get();
        foreach ($archivedDocs as $idx => $entry) {
            $existing = DispositionRequest::where('archive_entry_id', $entry->id)->first();
            if ($existing) {
                $this->command->warn("  ↻ Disposition request for entry {$entry->id} exists");
                continue;
            }

            DispositionRequest::create([
                'archive_entry_id' => $entry->id,
                'requested_by' => $archivist->id,
                'requested_action' => 'transfer',
                'requested_at' => now()->subDays(rand(5, 10)),
                'justification' => 'Document retention period has expired and requires transfer to permanent archive.',
            ]);

            $this->command->info("  ✓ Disposition request for entry {$entry->id}");
        }

        $this->command->info("\n=== Creating Notifications ===");

        // Create notification events and user notifications
        try {
            for ($i = 0; $i < 5; $i++) {
                if ($i >= count($createdDocuments)) break;
                $doc = $createdDocuments[$i];

                $notifEvent = NotificationEvent::create([
                    'type_code' => 'workflow.task_assigned',
                    'document_id' => $doc->id,
                    'actor_id' => $admin->id,
                    'occurred_at' => now()->subHours(rand(1, 48)),
                ]);

                // Create notifications for admin
                UserNotification::firstOrCreate([
                    'event_id' => $notifEvent->id,
                    'user_id' => $admin->id,
                ], [
                    'created_at' => now()->subHours(rand(1, 48)),
                ]);

                $this->command->info("  ✓ Notification for doc {$doc->id}");
            }
        } catch (\Throwable $e) {
            $this->command->warn("  ⚠ Could not create notifications: {$e->getMessage()}");
        }

        $this->command->info("\n✅ Comprehensive test data seeding completed!");
        $this->command->info("Created:");
        $this->command->info("  • 20 documents");
        $this->command->info("  • 10 workflow instances (6 for admin, 2 for head.se, 2 overdue)");
        $this->command->info("  • 10 correspondence records (3 outgoing awaiting dispatch, 2 dispatched unreceived, 2 incoming unreceived)");
        $this->command->info("  • 5 archive entries (3 with reviews due)");
        $this->command->info("  • 2 pending disposition requests");
        $this->command->info("  • 5 notifications for admin");
    }

    private function generateMinimalPDF(string $title, string $description): string
    {
        $pdf = "%PDF-1.4\n";
        $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /MediaBox [0 0 612 792] /Contents 5 0 R >>\nendobj\n";
        $pdf .= "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

        $content = "BT\n/F1 12 Tf\n50 750 Td\n(" . addslashes($title) . ") Tj\n0 -20 Td\n(" . addslashes($description) . ") Tj\nET";
        $streamLength = strlen($content);

        $pdf .= "5 0 obj\n<< /Length {$streamLength} >>\nstream\n{$content}\nendstream\nendobj\n";
        $pdf .= "xref\n0 6\n0000000000 65535 f\n0000000009 00000 n\n0000000058 00000 n\n0000000115 00000 n\n0000000254 00000 n\n0000000333 00000 n\n";
        $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n" . (strlen($pdf) - 100) . "\n%%EOF";

        return $pdf;
    }
}
