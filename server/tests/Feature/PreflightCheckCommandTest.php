<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class PreflightCheckCommandTest extends TestCase
{
    public function test_preflight_verifies_required_extensions_and_fails_loudly_if_missing(): void
    {
        $hasIntl = extension_loaded('intl');
        $hasGd = extension_loaded('gd');

        $test = $this->artisan('wu:preflight')
            ->expectsOutputToContain("Required extension 'pdo_mysql' is loaded")
            ->expectsOutputToContain("Required extension 'openssl' is loaded")
            ->expectsOutputToContain("Required extension 'fileinfo' is loaded");

        if (! $hasIntl || ! $hasGd) {
            $test->doesntExpectOutputToContain('Preflight Check PASSED cleanly!')
                ->expectsOutputToContain('Preflight Check FAILED')
                ->expectsOutputToContain('Windows configuration instructions:')
                ->expectsOutputToContain('php.ini')
                ->assertExitCode(1);

            if (! $hasIntl) {
                $test->expectsOutputToContain("Required extension 'intl' is NOT loaded")
                    ->expectsOutputToContain('extension=intl');
            }
            if (! $hasGd) {
                $test->expectsOutputToContain("Required extension 'gd' is NOT loaded")
                    ->expectsOutputToContain('extension=gd');
            }
        } else {
            $test->expectsOutputToContain('Preflight Check PASSED cleanly!')
                ->assertExitCode(0);
        }
    }

    public function test_preflight_verifies_isolated_database_target(): void
    {
        $expectedExitCode = (extension_loaded('intl') && extension_loaded('gd')) ? 0 : 1;
        $this->artisan('wu:preflight')
            ->expectsOutputToContain('Database target is isolated')
            ->assertExitCode($expectedExitCode);
    }

    public function test_preflight_masks_app_key_and_secrets(): void
    {
        $rawAppKey = config('app.key');
        $this->assertNotEmpty($rawAppKey);

        $expectedExitCode = (extension_loaded('intl') && extension_loaded('gd')) ? 0 : 1;
        $this->artisan('wu:preflight')
            ->doesntExpectOutputToContain($rawAppKey)
            ->expectsOutputToContain('APP_KEY is configured (masked)')
            ->assertExitCode($expectedExitCode);
    }
}
