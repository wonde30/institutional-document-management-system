<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Models\CommunicationParty;
use App\Models\Correspondence;
use App\Models\CorrespondenceDispatch;
use App\Models\CorrespondenceParticipant;
use App\Models\CorrespondenceType;
use App\Models\DispatchMethod;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\ExternalParty;
use App\Models\OrganizationUnit;
use App\Models\Priority;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoCorrespondenceSeeder extends Seeder
{
    /**
     * Seed incoming, outgoing, and internal correspondence records, parties, participants, and dispatches.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $corrTypes = CorrespondenceType::pluck('id', 'code')->toArray();
        $priorities = Priority::pluck('id', 'code')->toArray();
        $methods = DispatchMethod::pluck('id', 'code')->toArray();
        $units = OrganizationUnit::pluck('id', 'code')->toArray();
        $users = User::pluck('id', 'email')->toArray();

        $recordsId = $users['demo.records@example.test'] ?? 1;
        $approverId = $users['demo.approver@example.test'] ?? 1;
        $headCsId = $users['demo.head.cs@example.test'] ?? 1;
        $financeId = $users['demo.finance@example.test'] ?? 1;

        $typeIn = $corrTypes['incoming'] ?? 1;
        $typeOut = $corrTypes['outgoing'] ?? 2;
        $typeMemo = $corrTypes['internal_memo'] ?? 3;

        $prioNormal = $priorities['normal'] ?? 1;
        $prioUrgent = $priorities['urgent'] ?? 2;
        $prioCritical = $priorities['critical'] ?? 3;

        $methodHand = $methods['hand_delivery'] ?? 1;
        $methodPost = $methods['post'] ?? 2;
        $methodEmail = $methods['email'] ?? 3;
        $methodInternal = $methods['internal'] ?? 4;

        $unitCro = $units['CRO'] ?? 1;
        $unitCs = $units['CS'] ?? 1;
        $unitCote = $units['COTE'] ?? 1;
        $unitVpaa = $units['VPAA'] ?? 1;
        $unitFpd = $units['FPD'] ?? 1;
        $unitWu = $units['WU'] ?? 1;

        // 1. External Parties
        $externalPartiesData = [
            ['name' => 'Federal Ministry of Education (MoE)', 'party_type' => 'organization', 'external_identifier' => 'MOE-ETH'],
            ['name' => 'Higher Education Relevance and Quality Agency (HERQA)', 'party_type' => 'organization', 'external_identifier' => 'HERQA-ETH'],
            ['name' => 'Addis Ababa University (AAU)', 'party_type' => 'organization', 'external_identifier' => 'AAU-ETH'],
            ['name' => 'Commercial Bank of Ethiopia (CBE)', 'party_type' => 'organization', 'external_identifier' => 'CBE-ETH'],
            ['name' => 'Amhara Regional Health Bureau', 'party_type' => 'organization', 'external_identifier' => 'ARHB-ETH'],
            ['name' => 'Ethiopian Artificial Intelligence Institute', 'party_type' => 'organization', 'external_identifier' => 'EAII-ETH'],
        ];

        $extPartyIds = [];
        foreach ($externalPartiesData as $ep) {
            $record = ExternalParty::firstOrCreate(
                ['external_identifier' => $ep['external_identifier']],
                [
                    'name' => $ep['name'],
                    'party_type' => $ep['party_type'],
                    'created_at' => $now,
                ]
            );
            $extPartyIds[$ep['external_identifier']] = $record->id;
        }

        // 2. Communication Parties
        $commPartyIds = [];

        // External parties
        foreach ($extPartyIds as $identifier => $extId) {
            $cp = CommunicationParty::firstOrCreate(
                ['external_party_id' => $extId],
                ['unit_id' => null, 'user_id' => null]
            );
            $commPartyIds[$identifier] = $cp->id;
        }

        // Internal unit parties
        foreach ($units as $code => $uId) {
            $cp = CommunicationParty::firstOrCreate(
                ['unit_id' => $uId],
                ['external_party_id' => null, 'user_id' => null]
            );
            $commPartyIds["UNIT_{$code}"] = $cp->id;
        }

        // Internal user parties
        foreach ($users as $email => $uId) {
            $cp = CommunicationParty::firstOrCreate(
                ['user_id' => $uId],
                ['external_party_id' => null, 'unit_id' => null]
            );
            $commPartyIds["USER_{$email}"] = $cp->id;
        }

        // 3. Correspondence Items (linked to documents 51-60)
        $correspondenceRecords = [
            [
                'doc_ref' => 'WU-DOC-2026-0051',
                'type' => $typeIn,
                'priority' => $prioCritical,
                'purpose' => 'action',
                'external_ref' => 'MOE/HE/2026/894-EXAM',
                'sender_party' => $commPartyIds['MOE-ETH'],
                'recipient_party' => $commPartyIds['UNIT_WU'],
                'cc_party' => $commPartyIds['UNIT_VPAA'],
                'first_unit' => $unitCro,
                'days_ago' => 4,
                'due_in_days' => 10,
                'dispatches' => [],
            ],
            [
                'doc_ref' => 'WU-DOC-2026-0052',
                'type' => $typeOut,
                'priority' => $prioUrgent,
                'purpose' => 'reply',
                'external_ref' => 'WU/PRES/2026/1042-OUT',
                'sender_party' => $commPartyIds['UNIT_WU'],
                'recipient_party' => $commPartyIds['MOE-ETH'],
                'cc_party' => $commPartyIds['UNIT_CRO'],
                'first_unit' => $unitCro,
                'days_ago' => 2,
                'due_in_days' => null,
                'dispatches' => [
                    ['method' => $methodEmail, 'ref' => 'TRK-ETH-2026-08912', 'by' => $recordsId],
                ],
            ],
            [
                'doc_ref' => 'WU-DOC-2026-0053',
                'type' => $typeIn,
                'priority' => $prioUrgent,
                'purpose' => 'audit_schedule',
                'external_ref' => 'HERQA/AUDIT/2026/12-COMP',
                'sender_party' => $commPartyIds['HERQA-ETH'],
                'recipient_party' => $commPartyIds['UNIT_QAO'],
                'cc_party' => $commPartyIds['UNIT_VPAA'],
                'first_unit' => $unitCro,
                'days_ago' => 7,
                'due_in_days' => 14,
                'dispatches' => [],
            ],
            [
                'doc_ref' => 'WU-DOC-2026-0054',
                'type' => $typeMemo,
                'priority' => $prioNormal,
                'purpose' => 'facility_request',
                'external_ref' => 'CS/MEMO/2026/044',
                'sender_party' => $commPartyIds['UNIT_CS'],
                'recipient_party' => $commPartyIds['UNIT_COTE'],
                'cc_party' => null,
                'first_unit' => $unitCote,
                'days_ago' => 6,
                'due_in_days' => 7,
                'dispatches' => [
                    ['method' => $methodInternal, 'ref' => 'INT-MEMO-CS-COTE-044', 'by' => $headCsId],
                ],
            ],
            [
                'doc_ref' => 'WU-DOC-2026-0055',
                'type' => $typeIn,
                'priority' => $prioNormal,
                'purpose' => 'consortium_proposal',
                'external_ref' => 'AAU/VPAA/2026/455-COOP',
                'sender_party' => $commPartyIds['AAU-ETH'],
                'recipient_party' => $commPartyIds['UNIT_VPAA'],
                'cc_party' => $commPartyIds['UNIT_CS'],
                'first_unit' => $unitCro,
                'days_ago' => 14,
                'due_in_days' => 30,
                'dispatches' => [],
            ],
            [
                'doc_ref' => 'WU-DOC-2026-0056',
                'type' => $typeOut,
                'priority' => $prioUrgent,
                'purpose' => 'banking_clearance',
                'external_ref' => 'WU/FIN/2026/8932-CBE',
                'sender_party' => $commPartyIds['UNIT_FPD'],
                'recipient_party' => $commPartyIds['CBE-ETH'],
                'cc_party' => null,
                'first_unit' => $unitFpd,
                'days_ago' => 11,
                'due_in_days' => null,
                'dispatches' => [
                    ['method' => $methodHand, 'ref' => 'COURIER-CBE-DESSIE-001', 'by' => $financeId],
                ],
            ],
            [
                'doc_ref' => 'WU-DOC-2026-0057',
                'type' => $typeMemo,
                'priority' => $prioUrgent,
                'purpose' => 'network_notice',
                'external_ref' => 'ICTD/CIR/2026/012',
                'sender_party' => $commPartyIds['UNIT_WU'],
                'recipient_party' => $commPartyIds['UNIT_WU'],
                'cc_party' => null,
                'first_unit' => $unitCro,
                'days_ago' => 3,
                'due_in_days' => null,
                'dispatches' => [
                    ['method' => $methodEmail, 'ref' => 'BROADCAST-ALL-STAFF-012', 'by' => $recordsId],
                ],
            ],
            [
                'doc_ref' => 'WU-DOC-2026-0058',
                'type' => $typeIn,
                'priority' => $prioNormal,
                'purpose' => 'health_cooperation',
                'external_ref' => 'ARHB/DESSIE/2026/304',
                'sender_party' => $commPartyIds['ARHB-ETH'],
                'recipient_party' => $commPartyIds['UNIT_WU'],
                'cc_party' => null,
                'first_unit' => $unitCro,
                'days_ago' => 21,
                'due_in_days' => 14,
                'dispatches' => [],
            ],
        ];

        foreach ($correspondenceRecords as $cData) {
            $doc = Document::where('reference_number', $cData['doc_ref'])->first();
            if (! $doc) {
                continue;
            }

            $corrDate = Carbon::now()->subDays($cData['days_ago']);
            $dueDate = $cData['due_in_days'] ? (clone $corrDate)->addDays($cData['due_in_days']) : null;

            $corr = Correspondence::updateOrCreate(
                ['document_id' => $doc->id],
                [
                    'type_id' => $cData['type'],
                    'priority_id' => $cData['priority'],
                    'purpose_code' => $cData['purpose'],
                    'external_reference' => $cData['external_ref'],
                    'correspondence_date' => $corrDate->toDateString(),
                    'first_receiving_unit_id' => $cData['first_unit'],
                    'received_at' => $corrDate,
                    'requested_due_at' => $dueDate,
                    'registered_by' => $recordsId,
                    'registered_at' => $corrDate,
                ]
            );

            // Add Sender Participant
            $senderParticipant = CorrespondenceParticipant::firstOrCreate(
                [
                    'correspondence_id' => $corr->id,
                    'participation_type' => 'sender',
                ],
                [
                    'party_id' => $cData['sender_party'],
                ]
            );

            // Add Recipient Participant
            $recipientParticipant = CorrespondenceParticipant::firstOrCreate(
                [
                    'correspondence_id' => $corr->id,
                    'participation_type' => 'to',
                ],
                [
                    'party_id' => $cData['recipient_party'],
                ]
            );

            // Add CC Participant if available
            if ($cData['cc_party']) {
                CorrespondenceParticipant::firstOrCreate(
                    [
                        'correspondence_id' => $corr->id,
                        'participation_type' => 'cc',
                    ],
                    [
                        'party_id' => $cData['cc_party'],
                    ]
                );
            }

            // Create Dispatches if specified
            $docVersion = DocumentVersion::where('document_id', $doc->id)->first();
            if ($docVersion) {
                foreach ($cData['dispatches'] as $disp) {
                    CorrespondenceDispatch::firstOrCreate(
                        ['idempotency_key' => "DISP-{$cData['doc_ref']}-{$disp['ref']}"],
                        [
                            'participant_id' => $recipientParticipant->id,
                            'document_version_id' => $docVersion->id,
                            'method_id' => $disp['method'],
                            'dispatched_by' => $disp['by'],
                            'dispatched_at' => (clone $corrDate)->addHours(4),
                            'tracking_reference' => $disp['ref'],
                            'evidence_file_id' => null,
                        ]
                    );
                }
            }
        }
    }
}
