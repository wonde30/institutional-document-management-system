<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoMasterSeeder extends Seeder
{
    /**
     * Master orchestrator for Wollo University DMS demonstration seeding.
     * Executes seeders in strict referential-integrity dependency order.
     */
    public function run(): void
    {
        // 1. Strict Target Database Guard
        $currentDb = DB::select('SELECT DATABASE() as db')[0]->db ?? null;
        $configuredDb = config('database.connections.mysql.database');

        if ($currentDb !== 'wu_dms_demo' || $configuredDb !== 'wu_dms_demo') {
            throw new RuntimeException(
                "CRITICAL ABORT: DemoMasterSeeder cannot execute against database [{$currentDb}]. " .
                "Target database must strictly be 'wu_dms_demo'."
            );
        }

        $this->command?->info("=================================================================");
        $this->command?->info("Starting Wollo University DMS Demonstration Seeder (wu_dms_demo)");
        $this->command?->info("=================================================================");

        $seeders = [
            'Baseline Vocabulary & Workflows' => DemoReferenceDataSeeder::class,
            'Institutional Organizational Units & Scopes' => DemoOrganizationSeeder::class,
            'Demonstration User Accounts & Profiles' => DemoUserSeeder::class,
            'Fine-Grained RBAC Permissions' => DemoRbacSeeder::class,
            'Demonstration Documents & Movements' => DemoDocumentSeeder::class,
            'Institutional Correspondences & Registry' => DemoCorrespondenceSeeder::class,
            'Active Workflows & Task Queues' => DemoWorkflowSeeder::class,
            'Archival Records & Physical Storage' => DemoArchiveSeeder::class,
            'Institutional Modules (Research, HR, Accreditation, Audit)' => DemoInstitutionalModuleSeeder::class,
        ];

        $step = 1;
        $total = count($seeders);

        foreach ($seeders as $description => $seederClass) {
            $this->command?->info("[Step {$step}/{$total}] Seeding {$description}...");
            $startTime = microtime(true);

            $this->call($seederClass);

            $elapsed = round(microtime(true) - $startTime, 2);
            $this->command?->info(" ✓ Completed {$description} in {$elapsed}s");
            $step++;
        }

        $this->command?->info("=================================================================");
        $this->command?->info("Demonstration Data Seeding Completed Successfully!");
        $this->command?->info("=================================================================");
    }
}
