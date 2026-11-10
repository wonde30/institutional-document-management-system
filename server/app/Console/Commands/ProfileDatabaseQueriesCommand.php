<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProfileDatabaseQueriesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dms:profile-queries {--format=table : Output format (table or json)} {--analyze : Run EXPLAIN ANALYZE on queries}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Profile database execution plans, index selectivity, and query performance for all institutional operations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('================================================================');
        $this->info(' Wollo University DMS - Stage 2 Database Optimization Profiler ');
        $this->info('================================================================');

        $runAnalyze = (bool) $this->option('analyze');

        $queries = [
            // A. Document Repository
            [
                'section' => 'A. Document Repository',
                'name' => '1. Department Document Listing',
                'description' => 'Listing documents filtered by owner unit and active lifecycle ordered by registered_at',
                'sql' => "SELECT id, reference_number, title, registered_at FROM documents WHERE owner_unit_id = 1 AND lifecycle = 'active' ORDER BY registered_at DESC LIMIT 25",
            ],
            [
                'section' => 'A. Document Repository',
                'name' => '2. Document Retrieval',
                'description' => 'Single document lookup joined with category and confidentiality metadata',
                'sql' => "SELECT d.id, d.reference_number, d.title, d.owner_unit_id, c.name as category_name, cl.code as confidentiality_code FROM documents d JOIN document_categories c ON d.category_id = c.id JOIN confidentiality_levels cl ON d.confidentiality_id = cl.id WHERE d.id = 1",
            ],
            [
                'section' => 'A. Document Repository',
                'name' => '3. Document Search',
                'description' => 'Search across title and reference number within department',
                'sql' => "SELECT id, reference_number, title, registered_at FROM documents WHERE owner_unit_id = 1 AND (title LIKE '%Policy%' OR reference_number LIKE '%DOC%') LIMIT 25",
            ],
            [
                'section' => 'A. Document Repository',
                'name' => '4. Multi-attribute Filtering',
                'description' => 'Filter by unit, category, confidentiality, lifecycle, and date boundary',
                'sql' => "SELECT id, reference_number, title, document_date FROM documents WHERE owner_unit_id = 1 AND category_id = 1 AND confidentiality_id = 1 AND lifecycle = 'active' AND document_date >= '2026-01-01' ORDER BY registered_at DESC LIMIT 25",
            ],
            [
                'section' => 'A. Document Repository',
                'name' => '5. Document Pagination',
                'description' => 'Second-page retrieval offset test on ordered departmental listing',
                'sql' => "SELECT id, reference_number, registered_at FROM documents WHERE owner_unit_id = 1 AND lifecycle = 'active' ORDER BY registered_at DESC LIMIT 25 OFFSET 25",
            ],
            [
                'section' => 'A. Document Repository',
                'name' => '6. Version History',
                'description' => 'Retrieve all versions for a document ordered by version number descending',
                'sql' => "SELECT id, document_id, version_number, change_note, created_at FROM document_versions WHERE document_id = 1 ORDER BY version_number DESC",
            ],

            // B. Correspondence Management
            [
                'section' => 'B. Correspondence Management',
                'name' => '7. Incoming Correspondence Registry',
                'description' => 'Listing incoming correspondence joined with base document ordered by registered_at',
                'sql' => "SELECT c.id, c.external_reference, c.received_at, d.title, d.reference_number FROM correspondences c JOIN documents d ON c.document_id = d.id WHERE c.type_id = 1 ORDER BY c.registered_at DESC LIMIT 25",
            ],
            [
                'section' => 'B. Correspondence Management',
                'name' => '8. Outgoing Correspondence Registry',
                'description' => 'Listing outgoing correspondence ordered by registered_at',
                'sql' => "SELECT c.id, c.purpose_code, c.registered_at, d.title FROM correspondences c JOIN documents d ON c.document_id = d.id WHERE c.type_id = 2 ORDER BY c.registered_at DESC LIMIT 25",
            ],
            [
                'section' => 'B. Correspondence Management',
                'name' => '9. External Reference Lookup',
                'description' => 'Direct index lookup of correspondence by external institutional reference',
                'sql' => "SELECT id, document_id, external_reference FROM correspondences WHERE external_reference = 'WU/EXT/TEST/001' LIMIT 1",
            ],
            [
                'section' => 'B. Correspondence Management',
                'name' => '10. Correspondence Movement History',
                'description' => 'Chronological tracking of inter-office document movements',
                'sql' => "SELECT dm.id, dm.document_id, dm.from_unit_id, dm.to_unit_id, dm.sent_at FROM document_movements dm WHERE dm.document_id = 1 ORDER BY dm.sent_at DESC",
            ],
            [
                'section' => 'B. Correspondence Management',
                'name' => '11. Movement Acknowledgment Status',
                'description' => 'Checking acknowledgment receipts for transferred documents',
                'sql' => "SELECT mr.movement_id, mr.acknowledged_by, mr.acknowledged_at, dm.document_id FROM movement_receipts mr JOIN document_movements dm ON mr.movement_id = dm.id WHERE dm.document_id = 1",
            ],
            [
                'section' => 'B. Correspondence Management',
                'name' => '12. Linked Response Lookup',
                'description' => 'Find linked responses to incoming letters via 3NF document relations',
                'sql' => "SELECT dr.source_id, dr.target_id, dr.relation_type_id, d.title FROM document_relations dr JOIN documents d ON dr.target_id = d.id WHERE dr.source_id = 1 AND dr.relation_type_id = 1",
            ],
            [
                'section' => 'B. Correspondence Management',
                'name' => '13. Dispatch History',
                'description' => 'Official dispatch records and postal tracking for an approved version',
                'sql' => "SELECT cd.id, cd.document_version_id, cd.dispatched_at, cd.tracking_reference, dm.code as method FROM correspondence_dispatches cd JOIN dispatch_methods dm ON cd.method_id = dm.id WHERE cd.document_version_id = 1 ORDER BY cd.dispatched_at DESC",
            ],

            // C. Workflow Management
            [
                'section' => 'C. Workflow Management',
                'name' => '14. Workflow Task Inbox (User)',
                'description' => 'Open workflow review tasks assigned to specific officer ordered by due date',
                'sql' => "SELECT wt.id, wt.instance_id, wt.due_at, wt.assigned_user_id FROM workflow_tasks wt WHERE wt.assigned_user_id = 1 AND wt.closed_at IS NULL ORDER BY wt.due_at ASC LIMIT 25",
            ],
            [
                'section' => 'C. Workflow Management',
                'name' => '15. Departmental Task Inbox',
                'description' => 'Open workflow review tasks routed to departmental office queue',
                'sql' => "SELECT wt.id, wt.instance_id, wt.due_at, wt.assigned_unit_id FROM workflow_tasks wt WHERE wt.assigned_unit_id = 1 AND wt.closed_at IS NULL ORDER BY wt.due_at ASC LIMIT 25",
            ],
            [
                'section' => 'C. Workflow Management',
                'name' => '16. Exact-Version Workflow Approval Verification',
                'description' => 'Join across workflow instances, tasks, and decisions to confirm version approval',
                'sql' => "SELECT wtd.id, wtd.decision FROM workflow_task_decisions wtd JOIN workflow_tasks wt ON wtd.task_id = wt.id JOIN workflow_instances wi ON wt.instance_id = wi.id WHERE wi.document_version_id = 1 AND wtd.decision = 'approve' LIMIT 1",
            ],
            [
                'section' => 'C. Workflow Management',
                'name' => '17. Workflow Decision History',
                'description' => 'Decision and signature chain recorded for a specific workflow task',
                'sql' => "SELECT wtd.id, wtd.task_id, wtd.decision, wtd.decided_at, u.name as decider FROM workflow_task_decisions wtd JOIN users u ON wtd.decided_by = u.id WHERE wtd.task_id = 1",
            ],

            // D. Archive Management
            [
                'section' => 'D. Archive Management',
                'name' => '18. Archive Collection Hierarchy',
                'description' => 'Structured hierarchy of university archival fonds and sub-fonds',
                'sql' => "SELECT id, parent_id, code, name FROM archive_collections ORDER BY parent_id, name",
            ],
            [
                'section' => 'D. Archive Management',
                'name' => '19. Physical Original Custody Chain',
                'description' => 'Retrieving custody events for physical record ordered by occurrence timestamp',
                'sql' => "SELECT pce.id, pce.action, pce.occurred_at, po.physical_reference FROM physical_custody_events pce JOIN physical_originals po ON pce.physical_original_id = po.id WHERE pce.physical_original_id = 1 ORDER BY pce.occurred_at DESC LIMIT 50",
            ],
            [
                'section' => 'D. Archive Management',
                'name' => '20. Archive Retention & Legal Holds',
                'description' => 'Querying archived records joined with legal hold status',
                'sql' => "SELECT ae.id, ae.document_id, ae.archived_at, lhd.hold_id FROM archive_entries ae LEFT JOIN legal_hold_documents lhd ON ae.document_id = lhd.document_id WHERE ae.collection_id = 1 LIMIT 50",
            ],

            // E. Institutional Modules
            [
                'section' => 'E. Institutional Modules',
                'name' => '21. Research Repository',
                'description' => 'Listing departmental finalized research publications joined with documents',
                'sql' => "SELECT rr.id, rr.document_id, d.title, d.owner_unit_id FROM research_records rr JOIN documents d ON rr.document_id = d.id WHERE d.owner_unit_id = 1 ORDER BY rr.id DESC LIMIT 25",
            ],
            [
                'section' => 'E. Institutional Modules',
                'name' => '22. HERQA Accreditation Evidence',
                'description' => 'Locating evidence documents mapped to institutional accreditation standards',
                'sql' => "SELECT ae.id, ae.cycle_id, ae.standard_id, ae.document_version_id FROM accreditation_evidence ae WHERE ae.cycle_id = 1 AND ae.standard_id = 1",
            ],
            [
                'section' => 'E. Institutional Modules',
                'name' => '23. Digital Signature Audit Trail',
                'description' => 'Querying digital signature audit events and timestamp verification',
                'sql' => "SELECT sv.id, sv.signature_id, sv.verified_at, sv.outcome FROM signature_verifications sv JOIN digital_signatures ds ON sv.signature_id = ds.id WHERE ds.request_id = 1 ORDER BY sv.verified_at DESC",
            ],
            [
                'section' => 'E. Institutional Modules',
                'name' => '24. Institutional Audit Ledger Query',
                'description' => 'Auditing immutable event stream by target type and timestamp range',
                'sql' => "SELECT id, actor_id, action, occurred_at FROM audit_events WHERE target_type = 'document' AND occurred_at >= '2026-01-01 00:00:00' ORDER BY occurred_at DESC LIMIT 50",
            ],
            [
                'section' => 'E. Institutional Modules',
                'name' => '25. User Notifications Queue',
                'description' => 'Fetching chronologically ordered notifications for an active user',
                'sql' => "SELECT id, user_id, event_id, read_at, created_at FROM user_notifications WHERE user_id = 1 ORDER BY created_at DESC LIMIT 20",
            ],
            [
                'section' => 'E. Institutional Modules',
                'name' => '26. Dashboard Metrics & Aggregations',
                'description' => 'Executive KPI summary aggregating documents by lifecycle state',
                'sql' => "SELECT lifecycle, count(*) as count FROM documents GROUP BY lifecycle",
            ],
        ];

        $results = [];
        $currentSection = '';

        foreach ($queries as $q) {
            if ($currentSection !== $q['section']) {
                $currentSection = $q['section'];
                $this->line('');
                $this->info("====================================================");
                $this->info(" Section: {$currentSection}");
                $this->info("====================================================");
            }

            $this->comment("--> Profiling: {$q['name']}");
            $this->line("    SQL: {$q['sql']}");

            $start = microtime(true);
            $explain = DB::select("EXPLAIN {$q['sql']}");
            $durationMs = round((microtime(true) - $start) * 1000, 2);

            $analyzeOutput = null;
            if ($runAnalyze) {
                try {
                    $analyzeResult = DB::select("EXPLAIN ANALYZE {$q['sql']}");
                    $analyzeOutput = json_encode($analyzeResult);
                } catch (\Throwable $e) {
                    $analyzeOutput = 'Error: ' . $e->getMessage();
                }
            }

            foreach ($explain as $row) {
                $results[] = [
                    'section' => $q['section'],
                    'query' => $q['name'],
                    'table' => $row->table ?? 'N/A',
                    'type' => $row->type ?? 'N/A',
                    'possible_keys' => $row->possible_keys ?? 'NONE',
                    'key' => $row->key ?? 'NONE',
                    'key_len' => $row->key_len ?? 'N/A',
                    'rows' => $row->rows ?? 0,
                    'filtered' => ($row->filtered ?? 100) . '%',
                    'extra' => $row->Extra ?? 'None',
                    'time_ms' => $durationMs,
                    'analyze' => $analyzeOutput,
                ];
            }
        }

        if ($this->option('format') === 'json') {
            $this->line(json_encode($results, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $this->line('');
        $this->info('Detailed Execution Plan Matrix:');
        $this->table(
            ['Query Plan', 'Table', 'Type', 'Possible Keys', 'Index Used', 'Key Len', 'Rows', 'Filtered', 'Optimizer Notes', 'Plan Time (ms)'],
            array_map(fn ($r) => [
                $r['query'],
                $r['table'],
                $r['type'],
                $r['possible_keys'],
                $r['key'],
                $r['key_len'],
                $r['rows'],
                $r['filtered'],
                $r['extra'],
                $r['time_ms'],
            ], $results)
        );

        $this->info('Profiling completed successfully across all 26 institutional query plans.');
        return Command::SUCCESS;
    }
}
