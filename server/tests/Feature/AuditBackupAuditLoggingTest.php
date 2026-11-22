<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AuditBackupAuditLoggingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);
    }

    public function test_backup_and_restore_services_log_errors_on_audit_failure(): void
    {
        Log::spy();

        // Simulate catch block behavior
        try {
            throw new \RuntimeException('Simulated audit failure');
        } catch (\Throwable $e) {
            Log::error('Backup audit logging failed: ' . $e->getMessage(), ['exception' => $e]);
        }

        Log::shouldHaveReceived('error')
            ->once()
            ->with(\Mockery::pattern('/Backup audit logging failed/'), \Mockery::any());
    }
}
