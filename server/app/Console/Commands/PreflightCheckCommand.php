<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class PreflightCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wu:preflight';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform preflight verification of WU-DMS runtime environment, database, storage, queue, mail, and security configuration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('=== Wollo University DMS Preflight Verification ===' . PHP_EOL);

        $failures = [];
        $warnings = [];

        // 1. PHP Extensions
        $this->line('<fg=cyan>1. Checking PHP Extensions...</>');
        $requiredExtensions = [
            'bcmath' => 'Arbitrary precision mathematics and timestamps',
            'fileinfo' => 'MIME type sniffing and file validation',
            'mbstring' => 'Multi-byte string handling and encoding',
            'openssl' => 'Cryptographic signing, token generation, and encryption',
            'pdo_mysql' => 'MySQL database connectivity',
            'zip' => 'Package compression and document archiving',
        ];

        foreach ($requiredExtensions as $ext => $purpose) {
            if (extension_loaded($ext)) {
                $this->line("  <fg=green>✓</> Required extension '{$ext}' is loaded ({$purpose})");
            } else {
                $this->line("  <fg=red>✗</> Required extension '{$ext}' is NOT loaded ({$purpose})");
                $failures[] = "Missing required PHP extension: {$ext} ({$purpose})";
            }
        }

        $recommendedExtensions = [
            'intl' => 'Number::format and localized calendar calculations',
            'gd' => 'Dynamic stamp and thumbnail rendering',
        ];

        foreach ($recommendedExtensions as $ext => $purpose) {
            if (extension_loaded($ext)) {
                $this->line("  <fg=green>✓</> Optional extension '{$ext}' is loaded ({$purpose})");
            } else {
                $this->line("  <fg=yellow>!</> Optional extension '{$ext}' is not loaded ({$purpose})");
                $warnings[] = "Optional PHP extension not loaded: {$ext} ({$purpose})";
            }
        }

        // 2. Required Environment Configuration Keys
        $this->line(PHP_EOL . '<fg=cyan>2. Checking Environment Configuration...</>');
        $requiredEnvKeys = [
            'app.key' => 'APP_KEY',
            'app.url' => 'APP_URL',
            'database.default' => 'DB_CONNECTION',
            'filesystems.default' => 'FILESYSTEM_DISK',
        ];

        foreach ($requiredEnvKeys as $configKey => $envName) {
            $val = config($configKey);
            if (!empty($val)) {
                $this->line("  <fg=green>✓</> {$envName} is configured ({$val})");
            } else {
                $this->line("  <fg=red>✗</> {$envName} is empty or unconfigured");
                $failures[] = "Environment configuration missing: {$envName}";
            }
        }

        // 3. Database Connection and Target
        $this->line(PHP_EOL . '<fg=cyan>3. Checking Database Connectivity and Target...</>');
        try {
            $pdo = DB::connection()->getPdo();
            $currentDb = DB::connection()->getDatabaseName();
            $this->line("  <fg=green>✓</> Successfully connected to database: <options=bold>{$currentDb}</>");

            // Safeguard: Ensure app database is not testing database
            if ($currentDb === 'wu_dms_phpunit' && app()->environment() !== 'testing') {
                $failures[] = 'Application is connected to wu_dms_phpunit instead of wu_dms_test in non-testing environment!';
                $this->line('  <fg=red>✗</> Application is pointing to the test database: wu_dms_phpunit');
            } else {
                $this->line("  <fg=green>✓</> Database target is isolated: {$currentDb}");
            }
        } catch (\Throwable $e) {
            $this->line("  <fg=red>✗</> Database connection failed: " . $e->getMessage());
            $failures[] = "Database connection failed: " . $e->getMessage();
        }

        // 4. Storage Directories and Disk Permissions
        $this->line(PHP_EOL . '<fg=cyan>4. Checking Storage Path Permissions...</>');
        $storagePaths = [
            'storage/app' => storage_path('app'),
            'storage/app/private' => storage_path('app/private'),
            'storage/framework/cache' => storage_path('framework/cache'),
            'storage/framework/sessions' => storage_path('framework/sessions'),
            'storage/framework/views' => storage_path('framework/views'),
            'storage/logs' => storage_path('logs'),
        ];

        foreach ($storagePaths as $label => $path) {
            if (!File::exists($path)) {
                File::makeDirectory($path, 0755, true, true);
            }

            if (is_writable($path)) {
                $this->line("  <fg=green>✓</> {$label} is writable");
            } else {
                $this->line("  <fg=red>✗</> {$label} is not writable ({$path})");
                $failures[] = "Storage path not writable: {$label}";
            }
        }

        // Verify private disk cannot be served directly via web
        if (config('filesystems.disks.local.serve', false) === true) {
            $this->line("  <fg=red>✗</> Private storage disk 'local' has serve=true (file exposure risk)");
            $failures[] = "Insecure filesystems configuration: local disk has serve => true";
        } else {
            $this->line("  <fg=green>✓</> Private storage disk route exposure is disabled (serve => false)");
        }

        // 5. Queue and Scheduler
        $this->line(PHP_EOL . '<fg=cyan>5. Checking Queue and Scheduler Configuration...</>');
        $queueConn = config('queue.default');
        $this->line("  <fg=green>✓</> Queue default connection: {$queueConn}");
        if ($queueConn === 'database') {
            try {
                $table = config('queue.connections.database.table', 'jobs');
                if (DB::getSchemaBuilder()->hasTable($table)) {
                    $this->line("  <fg=green>✓</> Database queue table '{$table}' exists");
                } else {
                    $this->line("  <fg=red>✗</> Database queue table '{$table}' is missing");
                    $failures[] = "Queue table '{$table}' missing from database";
                }
            } catch (\Throwable $e) {
                $failures[] = "Unable to inspect queue table: " . $e->getMessage();
            }
        }

        // 6. Mail Driver
        $this->line(PHP_EOL . '<fg=cyan>6. Checking Mail Driver Configuration...</>');
        $mailDriver = config('mail.default');
        $this->line("  <fg=green>✓</> Mail default transport: {$mailDriver}");

        // 7. CORS and Sanctum Security
        $this->line(PHP_EOL . '<fg=cyan>7. Checking CORS and Sanctum SPA Consistency...</>');
        $corsOrigins = config('cors.allowed_origins', []);
        $corsCreds = config('cors.supports_credentials', false);
        $sanctumDomains = config('sanctum.stateful', []);

        if ($corsCreds && in_array('*', $corsOrigins, true)) {
            $this->line("  <fg=red>✗</> CORS wildcard origin '*' is enabled while supports_credentials is true!");
            $failures[] = "Insecure CORS configuration: credentials enabled with wildcard origin";
        } else {
            $this->line("  <fg=green>✓</> CORS origin restrictions are safe (allowed_origins: " . json_encode($corsOrigins) . ")");
        }

        if (empty($sanctumDomains)) {
            $this->line("  <fg=red>✗</> Sanctum stateful domains list is empty");
            $failures[] = "Sanctum stateful domains not configured";
        } else {
            $this->line("  <fg=green>✓</> Sanctum stateful domains configured: " . count($sanctumDomains) . " domain(s)");
        }

        // 8. Testing Database Isolation Check
        $this->line(PHP_EOL . '<fg=cyan>8. Checking Test Database Isolation...</>');
        $phpunitXmlPath = base_path('phpunit.xml');
        if (File::exists($phpunitXmlPath)) {
            $xmlContent = File::get($phpunitXmlPath);
            if (str_contains($xmlContent, 'wu_dms_phpunit')) {
                $this->line("  <fg=green>✓</> phpunit.xml isolates tests to dedicated database: wu_dms_phpunit");
            } else {
                $this->line("  <fg=red>✗</> phpunit.xml does not isolate tests to wu_dms_phpunit");
                $failures[] = "phpunit.xml missing test database isolation (wu_dms_phpunit)";
            }
        }

        // Final Outcome
        $this->line(PHP_EOL . '==================================================');
        if (!empty($failures)) {
            $this->error("Preflight Check FAILED with " . count($failures) . " failure(s):");
            foreach ($failures as $idx => $fail) {
                $this->line("  <fg=red>" . ($idx + 1) . ". {$fail}</>");
            }
            return Command::FAILURE;
        }

        $this->info("Preflight Check PASSED cleanly! All runtime requirements and security configurations satisfied.");
        if (!empty($warnings)) {
            $this->line("<fg=yellow>Advisories (" . count($warnings) . "):</>");
            foreach ($warnings as $w) {
                $this->line("  <fg=yellow>• {$w}</>");
            }
        }

        return Command::SUCCESS;
    }
}
