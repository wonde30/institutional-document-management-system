<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class PreflightAuditVerificationTest extends TestCase
{
    public function test_preflight_fails_with_actionable_instructions_if_extensions_missing(): void
    {
        $hasIntl = extension_loaded('intl');
        $hasGd = extension_loaded('gd');

        $artisan = $this->artisan('wu:preflight');

        if (! $hasIntl || ! $hasGd) {
            $artisan->assertExitCode(1)
                ->expectsOutputToContain('Preflight Check FAILED')
                ->expectsOutputToContain('Windows configuration instructions:');

            $ini = php_ini_loaded_file() ?: 'php.ini';
            $artisan->expectsOutputToContain("Open your active php.ini: {$ini}");

            if (! $hasIntl) {
                $artisan->expectsOutputToContain('extension=intl');
            }
            if (! $hasGd) {
                $artisan->expectsOutputToContain('extension=gd');
            }
        } else {
            $artisan->assertExitCode(0)
                ->expectsOutputToContain('Preflight Check PASSED cleanly!');
        }
    }

    public function test_preflight_completely_masks_app_key(): void
    {
        $rawAppKey = (string) config('app.key');
        $this->assertNotEmpty($rawAppKey);

        $this->artisan('wu:preflight')
            ->doesntExpectOutputToContain($rawAppKey)
            ->expectsOutputToContain('APP_KEY is configured (masked)');
    }
}
