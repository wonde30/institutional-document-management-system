<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FileObject;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class DemoStatusCommand extends Command
{
    protected $signature = 'dms:demo-status';
    protected $description = 'Display comprehensive entity summary and storage metrics for wu_dms_demo';

    public function handle(): int
    {
        $this->info('================================================================');
        $this->info(' Wollo University DMS - Demonstration Environment Status Report ');
        $this->info('================================================================');

        // 1. Strict Target Database Guard
        try {
            $currentDb = DB::select('SELECT DATABASE() as db')[0]->db ?? null;
            $configuredDb = config('database.connections.mysql.database');
        } catch (Throwable $e) {
            $this->error("Database connection failed: {$e->getMessage()}");
            return 1;
        }

        if ($currentDb !== 'wu_dms_demo' || $configuredDb !== 'wu_dms_demo') {
            $this->error("CRITICAL SAFETY VIOLATION: Current database is [{$currentDb}].");
            $this->error("This status command is strictly restricted to database 'wu_dms_demo'.");
            return 1;
        }

        $tables = [
            'Users & Identity' => [
                'users' => DB::table('users')->count(),
                'roles' => DB::table('roles')->count(),
                'permissions' => DB::table('permissions')->count(),
                'staff_profiles' => DB::table('staff_profiles')->count(),
                'organization_memberships' => DB::table('organization_memberships')->count(),
                'primary_memberships' => DB::table('primary_memberships')->count(),
            ],
            'Organization Hierarchy' => [
                'organization_units' => DB::table('organization_units')->count(),
                'unit_types' => DB::table('unit_types')->count(),
                'authorization_scopes' => DB::table('authorization_scopes')->count(),
            ],
            'Document Management' => [
                'documents' => DB::table('documents')->count(),
                'document_versions' => DB::table('document_versions')->count(),
                'version_files' => DB::table('version_files')->count(),
                'file_objects' => DB::table('file_objects')->count(),
                'document_movements' => DB::table('document_movements')->count(),
                'document_comments' => DB::table('document_comments')->count(),
                'document_assignments' => DB::table('document_assignments')->count(),
            ],
            'Institutional Registry' => [
                'correspondences' => DB::table('correspondences')->count(),
                'external_parties' => DB::table('external_parties')->count(),
                'communication_parties' => DB::table('communication_parties')->count(),
                'correspondence_participants' => DB::table('correspondence_participants')->count(),
                'correspondence_dispatches' => DB::table('correspondence_dispatches')->count(),
            ],
            'Workflows & Approvals' => [
                'workflow_definitions' => DB::table('workflow_definitions')->count(),
                'workflow_instances' => DB::table('workflow_instances')->count(),
                'workflow_tasks' => DB::table('workflow_tasks')->count(),
                'workflow_transitions' => DB::table('workflow_transitions')->count(),
            ],
            'Archives & Disposition' => [
                'archive_collections' => DB::table('archive_collections')->count(),
                'archive_entries' => DB::table('archive_entries')->count(),
                'disposition_requests' => DB::table('disposition_requests')->count(),
                'physical_originals' => DB::table('physical_originals')->count(),
            ],
            'Institutional Modules' => [
                'research_records' => DB::table('research_records')->count(),
                'research_authors' => DB::table('research_authors')->count(),
                'staff_employment_records' => DB::table('staff_employment_records')->count(),
                'staff_qualifications' => DB::table('staff_qualifications')->count(),
                'accreditation_cycles' => DB::table('accreditation_cycles')->count(),
                'accreditation_standards' => DB::table('accreditation_standards')->count(),
                'accreditation_evidence' => DB::table('accreditation_evidence')->count(),
                'projects' => DB::table('projects')->count(),
                'project_proposals' => DB::table('project_proposals')->count(),
                'signing_identities' => DB::table('signing_identities')->count(),
                'digital_signatures' => DB::table('digital_signatures')->count(),
                'user_notifications' => DB::table('user_notifications')->count(),
                'audit_events' => DB::table('audit_events')->count(),
            ],
        ];

        $reportRows = [];
        $totalEntities = 0;

        foreach ($tables as $category => $counts) {
            foreach ($counts as $table => $count) {
                $reportRows[] = [$category, $table, (string) $count];
                $totalEntities += $count;
            }
        }

        $this->table(['Domain Category', 'Database Table', 'Record Count'], $reportRows);

        // Storage metrics
        $totalBytes = (int) DB::table('file_objects')->sum('byte_size');
        $formattedSize = round($totalBytes / 1024, 2) . ' KB';
        if ($totalBytes > 1048576) {
            $formattedSize = round($totalBytes / 1048576, 2) . ' MB';
        }

        $this->line('');
        $this->info("Database: wu_dms_demo | Total Tracked Entities: {$totalEntities} | Storage: {$formattedSize} across " . FileObject::count() . " files");
        return 0;
    }
}
