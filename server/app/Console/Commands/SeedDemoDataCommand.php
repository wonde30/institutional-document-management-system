<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\Demo\DemoMasterSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SeedDemoDataCommand extends Command
{
    protected $signature = 'dms:seed-demo
        {--confirm-database= : Explicit safety confirmation matching wu_dms_demo}
        {--dry-run : Simulate execution and display detailed seeding blueprint without writing to database}
        {--force : Bypass interactive confirmation prompt}';

    protected $description = 'Seed the dedicated Wollo University DMS demonstration database (wu_dms_demo)';

    public function handle(): int
    {
        $this->info('================================================================');
        $this->info(' Wollo University DMS - Demonstration Data Seeding Engine        ');
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
            $this->error("CRITICAL SAFETY VIOLATION: Current database is [{$currentDb}], configured is [{$configuredDb}].");
            $this->error("This demonstration seeder is strictly restricted to database 'wu_dms_demo'.");
            $this->error("Aborting immediately. Staging and production databases were not modified.");
            return 1;
        }

        // 2. Table Count Verification
        $tables = DB::select('SHOW TABLES');
        $tableCount = count($tables);
        if ($tableCount < 119) {
            $this->error("Database integrity warning: 'wu_dms_demo' currently has {$tableCount} tables (expected 119).");
            $this->error("Please ensure migrations or schema baseline have been loaded first.");
            return 1;
        }

        // 3. Handle Dry Run
        if ($this->option('dry-run')) {
            $this->line('');
            $this->info('DRY-RUN MODE ACTIVE: No modifications will be made to database.');
            $this->line('');
            $this->renderDryRunPlan();
            return 0;
        }

        // 4. Confirm Database Flag
        $confirmation = $this->option('confirm-database');
        if ($confirmation !== 'wu_dms_demo') {
            $this->error("Safety Guard: You must supply --confirm-database=wu_dms_demo to execute this command.");
            $this->line("Usage: php artisan --env=demo dms:seed-demo --confirm-database=wu_dms_demo");
            return 1;
        }

        $this->info("Target Database: wu_dms_demo (verified)");
        $this->info("Environment: " . app()->environment());
        $this->info("Existing Tables: {$tableCount}");
        $this->line('');

        $startTime = microtime(true);

        try {
            $this->call(DemoMasterSeeder::class);
        } catch (Throwable $e) {
            $this->error("Seeding execution halted with error: " . $e->getMessage());
            $this->line($e->getTraceAsString());
            return 1;
        }

        $duration = round(microtime(true) - $startTime, 2);

        $this->line('');
        $this->info("✓ Demonstration database successfully seeded in {$duration} seconds!");
        $this->line("Run 'php artisan --env=demo dms:demo-status' to view entity summary.");
        $this->line("Run 'php artisan --env=demo dms:verify-demo' to verify data integrity.");

        return 0;
    }

    private function renderDryRunPlan(): void
    {
        $plan = [
            ['Domain 1: Reference Data', 'permissions, roles, categories, priorities, vocabularies', '72 permissions, 7 system roles, 5 categories, 4 priorities'],
            ['Domain 2: Organization Hierarchy', 'organization_units, authorization_scopes, unit_types', '18 institutional units (WU, VPAA, VPRCS, COTE, CS, EE...), 20 scopes'],
            ['Domain 3: Synthetic User Accounts', 'users, staff_profiles, memberships, primary_memberships', '12 demo accounts (admin, records, deans, approver, staff...)'],
            ['Domain 4: Dynamic RBAC', 'role_permissions, user_permissions', 'Pre-computed role assignments + custom direct grants'],
            ['Domain 5: Core Documents & Files', 'documents, document_versions, version_files, file_objects', '75 documents, 85 versions, 85 physical files in storage, 80 movements'],
            ['Domain 6: Institutional Registry', 'correspondences, external_parties, dispatches', '20 correspondences (in/out/internal), 6 external entities, dispatches'],
            ['Domain 7: Workflows & Approvals', 'workflow_instances, workflow_tasks, workflow_transitions', '10 active/completed workflows, pending approval tasks'],
            ['Domain 8: Archives & Retention', 'archive_collections, archive_entries, disposition_requests', '4 collections, 10 archived records, disposition requests, physical locs'],
            ['Domain 9: Institutional Modules', 'research_records, staff_records, accreditation_cycles', 'Research papers, faculty records, HERQA accreditation evidence'],
            ['Domain 10: Security & Audit', 'digital_signatures, signing_identities, audit_events', 'Cryptographic signing profiles, audit log trails, mock backups'],
        ];

        $this->table(['Domain / Module', 'Target Tables', 'Target Records / Blueprint'], $plan);
    }
}
