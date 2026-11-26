<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class PreflightCheckCommandTest extends TestCase
{
    /**
     * Test 1 (Restored): Verify preflight command executes cleanly with exit code 0.
     */
    public function test_preflight_command_executes_successfully(): void
    {
        $this->artisan('wu:preflight')
            ->expectsOutputToContain('Preflight Check PASSED cleanly!')
            ->assertExitCode(0);
    }

    /**
     * Test 2: Verify required extensions are checked and optional extensions emit advisory warnings.
     */
    public function test_preflight_verifies_required_extensions(): void
    {
        $this->artisan('wu:preflight')
            ->expectsOutputToContain("Required extension 'pdo_mysql' is loaded")
            ->expectsOutputToContain("Required extension 'openssl' is loaded")
            ->expectsOutputToContain("Required extension 'fileinfo' is loaded")
            ->assertExitCode(0);
    }

    /**
     * Test 3: Verify database target is isolated to test database.
     */
    public function test_preflight_verifies_isolated_database_target(): void
    {
        $this->artisan('wu:preflight')
            ->expectsOutputToContain('Database target is isolated')
            ->assertExitCode(0);
    }

    /**
     * Test 4: Verify APP_KEY is masked in console output.
     */
    public function test_preflight_masks_app_key_and_secrets(): void
    {
        $rawAppKey = config('app.key');
        $this->assertNotEmpty($rawAppKey);

        $this->artisan('wu:preflight')
            ->doesntExpectOutputToContain($rawAppKey)
            ->expectsOutputToContain('APP_KEY is configured (masked)')
            ->assertExitCode(0);
    }

    /**
     * Test 5 (Restored/New): Production mail=log or queue without worker warns.
     */
    public function test_production_mail_log_or_queue_without_worker_warns(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config([
            'database.connections.mysql.database' => 'wu_dms_test',
            'mail.default' => 'log',
            'queue.default' => 'database',
        ]);
        \Illuminate\Support\Facades\DB::purge('mysql');

        try {
            $this->artisan('wu:preflight')
                ->expectsOutputToContain("Warning: Mail transport is 'log' in non-local environment.")
                ->expectsOutputToContain("Warning: Queue connection is 'database'; ensure artisan queue:work daemon is active in production.")
                ->assertExitCode(0);
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
            config([
                'database.connections.mysql.database' => 'wu_dms_phpunit',
                'mail.default' => 'array',
                'queue.default' => 'sync',
            ]);
            \Illuminate\Support\Facades\DB::purge('mysql');
        }
    }
}
