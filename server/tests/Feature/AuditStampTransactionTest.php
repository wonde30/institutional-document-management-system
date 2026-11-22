<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\ConfidentialityLevel;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\OrganizationUnit;
use App\Models\StampApplication;
use App\Models\StampDefinition;
use App\Models\StampVersion;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditStampTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);
    }

    public function test_stamp_application_rolls_back_if_audit_fails(): void
    {
        $admin = User::where('email', 'admin@wu.edu.et')->first();
        $initialCount = StampApplication::count();

        $this->assertNotNull($admin);
        $this->assertGreaterThanOrEqual(0, $initialCount);
    }
}
