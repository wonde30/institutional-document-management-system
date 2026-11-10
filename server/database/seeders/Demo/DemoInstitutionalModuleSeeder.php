<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Models\AccreditationCycle;
use App\Models\AccreditationEvidence;
use App\Models\AccreditationFramework;
use App\Models\AccreditationFrameworkVersion;
use App\Models\AccreditationReview;
use App\Models\AccreditationStandard;
use App\Models\AuditEvent;
use App\Models\BackupRun;
use App\Models\DigitalSignature;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Institution;
use App\Models\NotificationEvent;
use App\Models\OrganizationUnit;
use App\Models\Project;
use App\Models\ProjectProposal;
use App\Models\ProjectType;
use App\Models\Publication;
use App\Models\QualificationType;
use App\Models\ResearchAuthor;
use App\Models\ResearchRecord;
use App\Models\RestoreDrill;
use App\Models\SignatureRequest;
use App\Models\SigningIdentity;
use App\Models\StaffEmploymentRecord;
use App\Models\StaffProfile;
use App\Models\StaffQualification;
use App\Models\StampDefinition;
use App\Models\StampVersion;
use App\Models\UnitType;
use App\Models\User;
use App\Models\UserNotification;
use Carbon\CarbonImmutable as Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoInstitutionalModuleSeeder extends Seeder
{
    /**
     * Seed institutional modules: Research, Staff, Accreditation, Projects, Signatures, Notifications, Audit Ledger.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $users = User::pluck('id', 'email')->toArray();
        $units = OrganizationUnit::pluck('id', 'code')->toArray();

        $adminId = $users['demo.admin@example.test'] ?? 1;
        $headCsId = $users['demo.head.cs@example.test'] ?? $adminId;
        $deanEngId = $users['demo.head.eng@example.test'] ?? $adminId;
        $approverId = $users['demo.approver@example.test'] ?? $adminId;
        $staffId = $users['demo.staff@example.test'] ?? $adminId;
        $researchId = $users['demo.research@example.test'] ?? $adminId;
        $qaId = $users['demo.qa@example.test'] ?? $adminId;

        $unitCs = $units['CS'] ?? 1;
        $unitCote = $units['COTE'] ?? 1;
        $unitWu = $units['WU'] ?? 1;

        // ====================================================================
        // 1. Research Records & Authors
        // ====================================================================
        $resDocs = [
            'WU-DOC-2026-0041' => ['type' => 'applied', 'days_ago' => 15, 'ref' => 'RES-AGRI-AI-2026-01'],
            'WU-DOC-2026-0042' => ['type' => 'compendium', 'days_ago' => 28, 'ref' => 'WUJST-VOL8-ISS2'],
            'WU-DOC-2026-0045' => ['type' => 'patent', 'days_ago' => 72, 'ref' => 'EIPA-PAT-2026-0091'],
            'WU-DOC-2026-0046' => ['type' => 'technology_transfer', 'days_ago' => 92, 'ref' => 'COMM-WEREBABO-2026'],
        ];

        foreach ($resDocs as $refNumber => $rData) {
            $doc = Document::where('reference_number', $refNumber)->first();
            if (! $doc) {
                continue;
            }

            $record = ResearchRecord::firstOrCreate(
                ['document_id' => $doc->id],
                [
                    'research_type' => $rData['type'],
                    'completed_on' => $now->subDays($rData['days_ago'])->toDateString(),
                    'external_selection_reference' => $rData['ref'],
                    'recorded_by' => $researchId,
                    'recorded_at' => $now->subDays($rData['days_ago']),
                ]
            );

            // Add Authors
            $staffProfileCs = StaffProfile::where('user_id', $headCsId)->first();
            if ($staffProfileCs) {
                ResearchAuthor::firstOrCreate(
                    ['research_id' => $record->id, 'author_order' => 1],
                    [
                        'staff_id' => $staffProfileCs->id,
                        'external_name' => null,
                        'contribution' => 'Principal Investigator & Lead Author',
                    ]
                );
            }

            ResearchAuthor::firstOrCreate(
                ['research_id' => $record->id, 'author_order' => 2],
                [
                    'staff_id' => null,
                    'external_name' => 'Dr. Michael Brown (External Collaborator)',
                    'contribution' => 'Co-Investigator & Dataset Validation',
                ]
            );
        }

        // ====================================================================
        // 2. Staff Profiles, Employment History & Qualifications
        // ====================================================================
        $docPromo = Document::where('reference_number', 'WU-DOC-2026-0021')->first();
        $evidenceDocId = $docPromo ? $docPromo->id : Document::first()?->id ?? 1;

        $instWu = Institution::where('code', 'WU')->first()?->id ?? 1;
        $qualBsc = QualificationType::where('code', 'bsc')->first()?->id ?? 1;
        $qualMsc = QualificationType::where('code', 'msc')->first()?->id ?? 2;
        $qualPhd = QualificationType::where('code', 'phd')->first()?->id ?? 3;

        // Staff profile for Dr. Dawit Tadesse (Head CS)
        $profileCs = StaffProfile::where('user_id', $headCsId)->first();
        if ($profileCs) {
            StaffEmploymentRecord::firstOrCreate(
                [
                    'staff_id' => $profileCs->id,
                    'position_title' => 'Associate Professor of Computer Science',
                ],
                [
                    'unit_id' => $unitCs,
                    'starts_on' => '2023-09-01',
                    'ends_on' => null,
                    'evidence_document_id' => $evidenceDocId,
                ]
            );

            StaffQualification::firstOrCreate(
                [
                    'staff_id' => $profileCs->id,
                    'qualification_type_id' => $qualPhd,
                    'field_of_study' => 'Computer Science (Distributed Systems)',
                ],
                [
                    'institution_id' => $instWu,
                    'awarded_on' => '2019-07-15',
                    'evidence_document_id' => $evidenceDocId,
                ]
            );

            StaffQualification::firstOrCreate(
                [
                    'staff_id' => $profileCs->id,
                    'qualification_type_id' => $qualMsc,
                    'field_of_study' => 'Software Engineering',
                ],
                [
                    'institution_id' => $instWu,
                    'awarded_on' => '2014-06-30',
                    'evidence_document_id' => $evidenceDocId,
                ]
            );
        }

        // Staff profile for Ephrem Negash (Staff)
        $profileStaff = StaffProfile::where('user_id', $staffId)->first();
        if ($profileStaff) {
            StaffEmploymentRecord::firstOrCreate(
                [
                    'staff_id' => $profileStaff->id,
                    'position_title' => 'Lecturer in Computer Science',
                ],
                [
                    'unit_id' => $unitCs,
                    'starts_on' => '2022-10-01',
                    'ends_on' => null,
                    'evidence_document_id' => $evidenceDocId,
                ]
            );

            StaffQualification::firstOrCreate(
                [
                    'staff_id' => $profileStaff->id,
                    'qualification_type_id' => $qualMsc,
                    'field_of_study' => 'Information Technology',
                ],
                [
                    'institution_id' => $instWu,
                    'awarded_on' => '2021-08-20',
                    'evidence_document_id' => $evidenceDocId,
                ]
            );
        }

        // ====================================================================
        // 3. HERQA Accreditation Cycles & Evidence Reviews
        // ====================================================================
        $framework = AccreditationFramework::firstOrCreate(['code' => 'HERQA_ET'], ['name' => 'Higher Education Relevance and Quality Agency (Ethiopia)', 'active' => 1]);
        $fwVersion = AccreditationFrameworkVersion::firstOrCreate(['framework_id' => $framework->id, 'version_label' => '2025/2026 National Institutional Standard'], ['active' => 1]);

        $standards = [
            ['code' => 'STD-1', 'title' => 'Institutional Vision, Mission, and Governance'],
            ['code' => 'STD-2', 'title' => 'Academic Programs and Curriculum Quality'],
            ['code' => 'STD-3', 'title' => 'Teaching, Learning, and Assessment Systems'],
            ['code' => 'STD-4', 'title' => 'Research, Innovation, and Community Engagement'],
            ['code' => 'STD-5', 'title' => 'Student Support Services and Learning Resources'],
        ];

        $stdIds = [];
        foreach ($standards as $s) {
            $std = AccreditationStandard::firstOrCreate(
                ['framework_version_id' => $fwVersion->id, 'code' => $s['code']],
                ['title' => $s['title'], 'description' => "Official evaluation criteria for {$s['title']}."]
            );
            $stdIds[$s['code']] = $std->id;
        }

        $cycle = AccreditationCycle::firstOrCreate(
            ['framework_version_id' => $fwVersion->id, 'name' => 'Institutional Re-Accreditation Cycle 2024–2026'],
            [
                'unit_id' => $unitWu,
                'starts_on' => '2024-09-01',
                'ends_on' => '2026-08-31',
            ]
        );

        // Submit evidence from document WU-DOC-2026-0013 (Accreditation Self-Evaluation)
        $docAcc = Document::where('reference_number', 'WU-DOC-2026-0013')->first();
        if ($docAcc) {
            $vAcc = DocumentVersion::where('document_id', $docAcc->id)->first();
            if ($vAcc) {
                $evidence = AccreditationEvidence::firstOrCreate(
                    [
                        'cycle_id' => $cycle->id,
                        'standard_id' => $stdIds['STD-2'] ?? 1,
                        'document_version_id' => $vAcc->id,
                    ],
                    [
                        'submitted_by' => $qaId,
                        'submitted_at' => $now->subDays(30),
                        'note' => 'Engineering curriculum mapping and external stakeholder advisory committee minutes submitted as verification evidence.',
                    ]
                );

                AccreditationReview::firstOrCreate(
                    ['evidence_id' => $evidence->id],
                    [
                        'reviewer_id' => $approverId,
                        'decision' => 'accepted',
                        'comment' => 'Standard criteria fully verified. Syllabus modular credit allocation matches HERQA guidelines.',
                        'reviewed_at' => $now->subDays(20),
                    ]
                );
            }
        }

        // ====================================================================
        // 4. Projects & Innovation Grants
        // ====================================================================
        $projType = ProjectType::firstOrCreate(['code' => 'technology_transfer'], ['name' => 'Technology Transfer & Innovation', 'active' => 1]);

        $docProj = Document::where('reference_number', 'WU-DOC-2026-0043')->first();
        if ($docProj) {
            $proposal = ProjectProposal::firstOrCreate(
                ['document_id' => $docProj->id],
                [
                    'project_type_id' => $projType->id,
                    'recorded_by' => $researchId,
                    'recorded_at' => $now->subDays(33),
                ]
            );

            Project::firstOrCreate(
                ['proposal_id' => $proposal->id],
                [
                    'code' => 'PRJ-2026-SOLAR-001',
                    'starts_on' => '2026-01-01',
                    'ends_on' => '2027-12-31',
                ]
            );
        }

        // ====================================================================
        // 5. Digital Signatures & Stamps (TEST-ONLY)
        // ====================================================================
        $signingIdentity = SigningIdentity::firstOrCreate(
            [
                'user_id' => $approverId,
                'provider_code' => 'wu_internal_pki_test',
            ],
            [
                'provider_identity_reference' => 'DEMO-PKI-CERT-VPAA-2026',
                'valid_from' => $now->subMonths(6),
                'valid_until' => $now->addMonths(18),
                'public_certificate_file_id' => null,
            ]
        );

        $docSign = Document::where('reference_number', 'WU-DOC-2026-0003')->first();
        if ($docSign) {
            $vSign = DocumentVersion::where('document_id', $docSign->id)->first();
            $fileSign = $vSign?->fileObjects()->first();

            if ($fileSign) {
                $sigReq = SignatureRequest::firstOrCreate(
                    ['document_version_id' => $vSign->id],
                    [
                        'requested_by' => $adminId,
                        'signer_id' => $approverId,
                        'purpose' => 'Ratified academic policy digital signature certification.',
                        'requested_at' => $now->subDays(12),
                        'expires_at' => $now->addDays(30),
                    ]
                );

                if (! DigitalSignature::where('request_id', $sigReq->id)->exists()) {
                    $signedFile = DemoFileGenerator::createPdf(
                        originalFilename: 'WU-DOC-2026-0003_v1_digitally_signed.pdf',
                        title: 'Academic Policy (Digitally Signed Certificate)',
                        refNumber: 'WU-DOC-2026-0003/SIGNED',
                        body: 'Cryptographically signed institutional policy document with embedded PKI timestamp verification.',
                        uploadedBy: $approverId,
                        year: '2026'
                    );

                    DigitalSignature::create([
                        'request_id' => $sigReq->id,
                        'signing_identity_id' => $signingIdentity->id,
                        'source_file_id' => $fileSign->id,
                        'signed_file_id' => $signedFile->id,
                        'signature_format' => 'PAdES-B-LT',
                        'signed_at' => $now->subDays(11),
                        'provider_transaction_reference' => 'TEST-TXN-PKI-89230192',
                    ]);
                }
            }
        }

        // ====================================================================
        // 6. User Notifications (Actionable & Read)
        // ====================================================================
        $doc1 = Document::where('reference_number', 'WU-DOC-2026-0011')->first();
        $doc1Id = $doc1 ? $doc1->id : 1;

        $notifEvents = [
            [
                'user_id' => $headCsId,
                'type' => 'workflow.task_assigned',
                'doc_id' => $doc1Id,
                'actor' => $staffId,
                'hours_ago' => 4,
                'read' => false, // UNREAD
            ],
            [
                'user_id' => $approverId,
                'type' => 'workflow.approval_requested',
                'doc_id' => $doc1Id,
                'actor' => $headCsId,
                'hours_ago' => 6,
                'read' => false, // UNREAD
            ],
            [
                'user_id' => $headCsId,
                'type' => 'correspondence.assigned',
                'doc_id' => $doc1Id,
                'actor' => $adminId,
                'hours_ago' => 24,
                'read' => true, // READ
            ],
            [
                'user_id' => $staffId,
                'type' => 'document.version_approved',
                'doc_id' => $doc1Id,
                'actor' => $approverId,
                'hours_ago' => 48,
                'read' => true, // READ
            ],
        ];

        if (UserNotification::count() === 0) {
            foreach ($notifEvents as $ne) {
                $event = NotificationEvent::create([
                    'type_code' => $ne['type'],
                    'document_id' => $ne['doc_id'],
                    'actor_id' => $ne['actor'],
                    'occurred_at' => $now->subHours($ne['hours_ago']),
                ]);

                UserNotification::create([
                    'event_id' => $event->id,
                    'user_id' => $ne['user_id'],
                    'read_at' => $ne['read'] ? $now->subHours($ne['hours_ago'] - 1) : null,
                    'created_at' => $now->subHours($ne['hours_ago']),
                ]);
            }
        }

        // ====================================================================
        // 7. Operations Telemetry & Audit Events
        // ====================================================================
        // Backup run in telemetry
        $backupRun = BackupRun::firstOrCreate(
            ['provider_job_reference' => 'BACKUP-JOB-2026-11-06-001'],
            [
                'started_at' => $now->subDays(1),
                'completed_at' => $now->subDays(1)->addMinutes(4),
                'status' => 'completed',
                'manifest_reference' => 'manifests/backup_20261106_001.json',
                'verification_status' => 'verified',
            ]
        );

        // Restore drill in telemetry
        RestoreDrill::firstOrCreate(
            ['backup_run_id' => $backupRun->id],
            [
                'started_at' => $now->subHours(20),
                'completed_at' => $now->subHours(20)->addMinutes(2),
                'status' => 'completed',
                'notes' => 'Automated disaster recovery drill verified against staging sandbox. All 119 tables and referential constraints verified.',
            ]
        );

        // Audit Event
        if (AuditEvent::where('action', 'DEMO_SEED.initial_provisioning')->count() === 0) {
            AuditEvent::create([
                'actor_id' => $adminId,
                'action' => 'DEMO_SEED.initial_provisioning',
                'target_type' => 'Database\\Seeders\\Demo\\DemoMasterSeeder',
                'target_id' => '1',
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Wollo-DMS-Seeder/1.0',
                'details_json' => [
                    'environment' => 'demo',
                    'target_database' => 'wu_dms_demo',
                    'status' => 'synthetic_demonstration_dataset_active',
                ],
            ]);
        }
    }
}
