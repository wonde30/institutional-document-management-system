<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AccreditationCycle;
use App\Models\AccreditationEvidence;
use App\Models\AccreditationReview;
use App\Models\AccreditationStandard;
use App\Models\CommunicationParty;
use App\Models\ConfidentialityLevel;
use App\Models\Correspondence;
use App\Models\CorrespondenceType;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentMovement;
use App\Models\DocumentVersion;
use App\Models\FileObject;
use App\Models\MovementReceipt;
use App\Models\OrganizationUnit;
use App\Models\Priority;
use App\Models\User;
use App\Models\VersionFile;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LandingPageDemoSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $admin = User::first() ?? User::create([
            'name' => 'System Administrator',
            'email' => 'admin@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'email_verified_at' => $now,
            'authz_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $qaDirector = User::where('email', 'qa.director@wu.edu.et')->first() ?? $admin;
        $dean = User::where('email', 'dean.technology@wu.edu.et')->first() ?? $admin;
        $registrar = User::where('email', 'registrar@wu.edu.et')->first() ?? $admin;

        // Ensure units have communication parties
        $units = OrganizationUnit::all();
        foreach ($units as $unit) {
            CommunicationParty::firstOrCreate(
                ['unit_id' => $unit->id],
                ['user_id' => null, 'external_party_id' => null]
            );
        }

        $categories = DocumentCategory::pluck('id', 'code')->toArray();
        $confLevels = ConfidentialityLevel::pluck('id', 'code')->toArray();
        $unitWu = OrganizationUnit::where('code', 'WU')->value('id') ?? 1;
        $unitCote = OrganizationUnit::where('code', 'COTE')->value('id') ?? $unitWu;
        $unitReg = OrganizationUnit::where('code', 'REG')->value('id') ?? $unitWu;
        $unitQa = OrganizationUnit::where('code', 'QA')->value('id') ?? $unitWu;
        $unitIct = OrganizationUnit::where('code', 'ICT')->value('id') ?? $unitWu;
        $unitSe = OrganizationUnit::where('code', 'SE')->value('id') ?? $unitWu;

        $priorityNormal = Priority::where('code', 'normal')->value('id') ?? 1;
        $priorityUrgent = Priority::where('code', 'urgent')->value('id') ?? 1;
        $corrIncoming = CorrespondenceType::where('code', 'incoming')->value('id') ?? 1;
        $corrOutgoing = CorrespondenceType::where('code', 'outgoing')->value('id') ?? 2;
        $corrMemo = CorrespondenceType::where('code', 'internal_memo')->value('id') ?? 3;

        // 1. Seed Documents if count < 10
        if (Document::count() < 10) {
            $sampleDocs = [
                [
                    'title' => 'Wollo University Five-Year Strategic Plan (2026-2030)',
                    'summary' => 'Comprehensive strategic institutional blueprint detailing academic excellence, research expansion, and digital transformation across campuses.',
                    'cat' => 'administrative',
                    'conf' => 'public',
                    'unit' => $unitWu,
                ],
                [
                    'title' => 'Senate Legislation on Academic Standards & Grading Policy',
                    'summary' => 'Approved Senate statutory provisions for undergraduate and postgraduate assessment criteria, academic progress, and degree award qualifications.',
                    'cat' => 'academic',
                    'conf' => 'internal',
                    'unit' => $unitWu,
                ],
                [
                    'title' => 'B.Sc. Software Engineering Revised Modular Curriculum 2026',
                    'summary' => 'Revised curriculum aligned with national higher education modernization directive and outcome-based engineering education frameworks.',
                    'cat' => 'academic',
                    'conf' => 'public',
                    'unit' => $unitSe,
                ],
                [
                    'title' => 'Institutional Research Grant Allocation & Ethical Review Protocol',
                    'summary' => 'Guidelines for faculty competitive grant submissions, institutional review board (IRB) ethical clearances, and publication incentives.',
                    'cat' => 'research',
                    'conf' => 'internal',
                    'unit' => $unitWu,
                ],
                [
                    'title' => 'Kombolcha Institute of Technology (KIoT) Quality Assurance Manual',
                    'summary' => 'Internal quality audit procedures, course evaluation methodologies, and departmental compliance review cycles for engineering faculties.',
                    'cat' => 'academic',
                    'conf' => 'internal',
                    'unit' => $unitCote,
                ],
                [
                    'title' => 'Administrative Directive on Digital Document Retention & Preservation',
                    'summary' => 'Mandatory schedules for digital filing, archival transfers, legal hold authorizations, and cryptographic document stamping.',
                    'cat' => 'legal',
                    'conf' => 'internal',
                    'unit' => $unitWu,
                ],
                [
                    'title' => 'Annual Institutional Performance and Audit Report 2025/2026',
                    'summary' => 'Consolidated evaluation of academic, financial, infrastructural, and community outreach performance metrics.',
                    'cat' => 'administrative',
                    'conf' => 'public',
                    'unit' => $unitWu,
                ],
                [
                    'title' => 'Staff Development & Postgraduate Scholarship Fellowship Framework',
                    'summary' => 'Policy for academic staff study leaves, institutional sponsorship bonds, and international academic exchange programs.',
                    'cat' => 'hr',
                    'conf' => 'internal',
                    'unit' => $unitWu,
                ],
                [
                    'title' => 'Campus ICT Infrastructure Security & Acceptable Use Policy',
                    'summary' => 'Institutional network security guidelines, role-based resource access, data privacy protections, and audit log integrity mandates.',
                    'cat' => 'administrative',
                    'conf' => 'internal',
                    'unit' => $unitIct,
                ],
                [
                    'title' => 'Inter-Institutional Collaboration MoU with National Research Institute',
                    'summary' => 'Formal agreement covering joint doctoral supervision, research infrastructure sharing, and technology transfer initiatives.',
                    'cat' => 'legal',
                    'conf' => 'public',
                    'unit' => $unitWu,
                ],
                [
                    'title' => 'Departmental Annual Course Portfolio Compilation: Software Engineering',
                    'summary' => 'Complete compilation of course outlines, assessment rubrics, student performance analyses, and advisory committee feedback.',
                    'cat' => 'academic',
                    'conf' => 'internal',
                    'unit' => $unitSe,
                ],
                [
                    'title' => 'Central Registry Incoming Dispatch Protocol & Chain-of-Custody Standard',
                    'summary' => 'Standard operating procedures for logging incoming federal ministry correspondence, physical stamp verification, and automated routing.',
                    'cat' => 'administrative',
                    'conf' => 'internal',
                    'unit' => $unitReg,
                ],
            ];

            $seq = 1;
            foreach ($sampleDocs as $docData) {
                $refNo = sprintf('WU/DOC/2026/%04d', $seq++);
                $catId = $categories[$docData['cat']] ?? 1;
                $confId = $confLevels[$docData['conf']] ?? 1;

                $doc = Document::create([
                    'reference_number' => $refNo,
                    'title' => $docData['title'],
                    'summary' => $docData['summary'],
                    'category_id' => $catId,
                    'confidentiality_id' => $confId,
                    'owner_unit_id' => $docData['unit'],
                    'custodian_id' => $admin->id,
                    'created_by' => $admin->id,
                    'document_date' => $now->toDateString(),
                    'registered_at' => $now->subMinutes(rand(100, 10000)),
                    'lifecycle' => 'active',
                    'lock_version' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // Create Version
                $version = DocumentVersion::create([
                    'document_id' => $doc->id,
                    'version_number' => 1,
                    'created_by' => $admin->id,
                    'change_note' => 'Initial approved release',
                    'created_at' => $now,
                ]);

                // Create File Object
                $fileSha = hash('sha256', $doc->title . $refNo);
                $file = FileObject::create([
                    'storage_disk' => 'local',
                    'storage_key' => 'documents/2026/' . Str::slug($doc->title) . '.pdf',
                    'original_filename' => Str::slug($doc->title) . '.pdf',
                    'detected_mime' => 'application/pdf',
                    'byte_size' => rand(45000, 250000),
                    'sha256' => $fileSha,
                    'uploaded_by' => $admin->id,
                    'uploaded_at' => $now,
                    'scan_status' => 'clean',
                    'storage_status' => 'ready',
                ]);

                VersionFile::create([
                    'document_version_id' => $version->id,
                    'file_object_id' => $file->id,
                    'purpose' => 'primary',
                    'display_order' => 1,
                ]);
            }
        }

        // 2. Seed Correspondences and Movements if count < 5
        if (Correspondence::count() < 5) {
            $docs = Document::take(6)->get();
            foreach ($docs as $idx => $doc) {
                $typeId = ($idx % 3 === 0) ? $corrIncoming : (($idx % 3 === 1) ? $corrOutgoing : $corrMemo);
                $priorityId = ($idx % 2 === 0) ? $priorityUrgent : $priorityNormal;

                $corr = Correspondence::firstOrCreate(
                    ['document_id' => $doc->id],
                    [
                        'type_id' => $typeId,
                        'priority_id' => $priorityId,
                        'purpose_code' => 'administrative_directive',
                        'external_reference' => 'MOE/HERQA/2026/' . rand(100, 999),
                        'correspondence_date' => $now->toDateString(),
                        'first_receiving_unit_id' => $unitReg,
                        'received_at' => $now->subDays(rand(1, 10)),
                        'requested_due_at' => $now->addDays(rand(5, 20)),
                        'registered_by' => $registrar->id,
                        'registered_at' => $now->subDays(rand(1, 10)),
                    ]
                );

                // Add Document Movements
                $movement = DocumentMovement::create([
                    'document_id' => $doc->id,
                    'from_unit_id' => $unitReg,
                    'to_unit_id' => ($idx % 2 === 0) ? $unitCote : $unitQa,
                    'sent_by' => $registrar->id,
                    'sent_at' => $now->subDays(rand(1, 5)),
                    'instruction' => 'For review, action, and inclusion in institutional accreditation file.',
                    'due_at' => $now->addDays(7),
                    'idempotency_key' => Str::uuid()->toString(),
                ]);

                MovementReceipt::create([
                    'movement_id' => $movement->id,
                    'acknowledged_by' => $dean->id,
                    'acknowledged_at' => $now->subDays(rand(0, 2)),
                ]);
            }
        }

        // 3. Seed Accreditation Evidence & Reviews if count < 5
        if (AccreditationEvidence::count() < 5) {
            $cycle = AccreditationCycle::first();
            $standards = AccreditationStandard::orderBy('code')->get();
            $versions = DocumentVersion::with('document')->take(10)->get();

            if ($cycle && $standards->isNotEmpty() && $versions->isNotEmpty()) {
                foreach ($standards->take(8) as $index => $standard) {
                    $ver = $versions[$index % $versions->count()];
                    
                    $evidence = AccreditationEvidence::firstOrCreate(
                        [
                            'cycle_id' => $cycle->id,
                            'standard_id' => $standard->id,
                            'document_version_id' => $ver->id,
                        ],
                        [
                            'submitted_by' => $qaDirector->id,
                            'submitted_at' => $now->subDays(rand(5, 30)),
                            'note' => 'Institutional documentation fulfilling requirement for ' . $standard->title,
                        ]
                    );

                    // Add Review
                    AccreditationReview::firstOrCreate(
                        ['evidence_id' => $evidence->id],
                        [
                            'reviewer_id' => $qaDirector->id,
                            'decision' => ($index % 3 === 2) ? 'needs_revision' : 'accepted',
                            'comment' => ($index % 3 === 2)
                                ? 'Additional documentation required for Senate minute alignment.'
                                : 'Verified and fully compliant with HERQA evaluation criteria.',
                            'reviewed_at' => $now->subDays(rand(1, 10)),
                        ]
                    );
                }
            }
        }
    }
}
