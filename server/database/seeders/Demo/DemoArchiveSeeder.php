<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Models\ArchiveCollection;
use App\Models\ArchiveEntry;
use App\Models\DispositionRequest;
use App\Models\Document;
use App\Models\PhysicalOriginal;
use App\Models\RetentionPolicyVersion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoArchiveSeeder extends Seeder
{
    /**
     * Seed archival collections, entries, retention review dates, disposition requests, and physical custody.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $archivist = User::where('email', 'demo.archivist@example.test')->first()
            ?? User::where('email', 'like', 'archivist%')->first()
            ?? User::first();

        $archivistId = $archivist ? $archivist->id : 1;

        // 1. Resolve / Create Collections
        $colSenate = ArchiveCollection::firstOrCreate(
            ['code' => 'senate_records'],
            ['name' => 'Academic Senate & Executive Records', 'description' => 'Permanent retention for Senate charter and executive decrees']
        )->id;

        $colCurriculum = ArchiveCollection::firstOrCreate(
            ['code' => 'curriculum_records'],
            ['name' => 'Curriculum & Program Archives', 'description' => 'Accredited undergraduate and graduate curricula records']
        )->id;

        $colPersonnel = ArchiveCollection::firstOrCreate(
            ['code' => 'personnel_records'],
            ['name' => 'Personnel & Employment Records Archive', 'description' => 'Historical staff files and employment records']
        )->id;

        $colFinance = ArchiveCollection::firstOrCreate(
            ['code' => 'financial_records'],
            ['name' => 'Financial Audit & Fiscal Archives', 'description' => 'Archival records of annual audits and fiscal closures']
        )->id;

        // 2. Resolve Retention Policy Version
        $policyVersion = RetentionPolicyVersion::first();
        $policyVersionId = $policyVersion ? $policyVersion->id : null;

        // 3. Candidate documents to archive
        $archiveMappings = [
            ['ref' => 'WU-DOC-2026-0009', 'col' => $colSenate, 'review_in_months' => 60, 'disposition' => null],
            ['ref' => 'WU-DOC-2026-0020', 'col' => $colCurriculum, 'review_in_months' => 24, 'disposition' => 'transfer'],
            ['ref' => 'WU-DOC-2026-0029', 'col' => $colPersonnel, 'review_in_months' => 120, 'disposition' => null],
            ['ref' => 'WU-DOC-2026-0039', 'col' => $colFinance, 'review_in_months' => 84, 'disposition' => null],
            ['ref' => 'WU-DOC-2026-0049', 'col' => $colCurriculum, 'review_in_months' => 36, 'disposition' => null],
            ['ref' => 'WU-DOC-2026-0059', 'col' => $colSenate, 'review_in_months' => 48, 'disposition' => null],
            ['ref' => 'WU-DOC-2026-0071', 'col' => $colSenate, 'review_in_months' => 240, 'disposition' => 'transfer'],
            ['ref' => 'WU-DOC-2026-0072', 'col' => $colFinance, 'review_in_months' => 120, 'disposition' => null],
            ['ref' => 'WU-DOC-2026-0074', 'col' => $colPersonnel, 'review_in_months' => 60, 'disposition' => null],
        ];

        foreach ($archiveMappings as $m) {
            $doc = Document::where('reference_number', $m['ref'])->first();
            if (! $doc) {
                continue;
            }

            $archivedAt = Carbon::now()->subMonths(6);
            $nextReview = (clone $archivedAt)->addMonths($m['review_in_months']);

            $entry = ArchiveEntry::firstOrCreate(
                ['document_id' => $doc->id],
                [
                    'collection_id' => $m['col'],
                    'retention_policy_version_id' => $policyVersionId,
                    'archived_by' => $archivistId,
                    'archived_at' => $archivedAt,
                    'trigger_occurred_at' => $archivedAt,
                    'next_review_at' => $nextReview,
                ]
            );

            // Create disposition request if specified
            if ($m['disposition']) {
                DispositionRequest::firstOrCreate(
                    [
                        'archive_entry_id' => $entry->id,
                        'requested_action' => $m['disposition'],
                    ],
                    [
                        'requested_by' => $archivistId,
                        'requested_at' => $now->subDays(15),
                        'justification' => 'Statutory institutional charter and accredited academic records designated for indefinite permanent preservation under National Archives Act.',
                    ]
                );
            }

            // Create physical original custody record for historic charter
            if ($m['ref'] === 'WU-DOC-2026-0071') {
                PhysicalOriginal::firstOrCreate(
                    ['physical_reference' => 'WU-ARCH-VAULT-A1-BOX-01'],
                    [
                        'document_id' => $doc->id,
                        'retention_requirement' => 'required',
                        'retention_policy_version_id' => $policyVersionId,
                        'created_by' => $archivistId,
                        'created_at' => $archivedAt,
                    ]
                );
            }
        }
    }
}
