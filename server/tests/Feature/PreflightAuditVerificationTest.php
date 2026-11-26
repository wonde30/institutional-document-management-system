<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class PreflightAuditVerificationTest extends TestCase
{
    public function test_preflight_warns_on_optional_extensions_without_failing(): void
    {
        $hasIntl = extension_loaded('intl');
        $hasGd = extension_loaded('gd');

        $artisan = $this->artisan('wu:preflight');

        if (! $hasIntl || ! $hasGd) {
            $artisan->assertExitCode(0)
                ->expectsOutputToContain('Preflight Check PASSED cleanly!')
                ->expectsOutputToContain('Advisories');

            if (! $hasIntl) {
                $artisan->expectsOutputToContain("Optional extension 'intl' is NOT loaded");
            }
            if (! $hasGd) {
                $artisan->expectsOutputToContain("Optional extension 'gd' is NOT loaded");
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
