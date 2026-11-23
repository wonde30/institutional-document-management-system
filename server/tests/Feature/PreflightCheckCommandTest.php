<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class PreflightCheckCommandTest extends TestCase
{
    public function test_preflight_command_executes_successfully(): void
    {
        $this->artisan('wu:preflight')
            ->expectsOutputToContain('Preflight Check PASSED cleanly!')
            ->assertExitCode(0);
    }

    public function test_preflight_verifies_required_extensions(): void
    {
        $this->artisan('wu:preflight')
            ->expectsOutputToContain("Required extension 'pdo_mysql' is loaded")
            ->expectsOutputToContain("Required extension 'openssl' is loaded")
            ->expectsOutputToContain("Required extension 'fileinfo' is loaded")
            ->assertExitCode(0);
    }

    public function test_preflight_verifies_isolated_database_target(): void
    {
        $this->artisan('wu:preflight')
            ->expectsOutputToContain('Database target is isolated')
            ->assertExitCode(0);
    }
}
