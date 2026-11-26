<?php

namespace Tests\Feature;

use App\Models\ArchiveCollection;
use App\Models\ArchiveEntry;
use App\Models\AuditEvent;
use App\Models\ConfidentialityLevel;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\OrganizationUnit;
use App\Models\RetentionPolicy;
use App\Models\RetentionPolicyVersion;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditArchiveRestoreTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);
    }

    public function test_archive_restore_rolls_back_atomically_if_audit_fails(): void
    {
        $admin = User::where('email', 'admin@wu.edu.et')->first();
        $unit = OrganizationUnit::first();
        $cat = DocumentCategory::first();
        $conf = ConfidentialityLevel::where('code', 'internal')->first();

        $doc = Document::create([
            'reference_number' => 'DOC-ARCH-TXN-01',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $unit->id,
            'title' => 'Archived Doc',
            'summary' => 'Summary',
            'created_by' => $admin->id,
            'lifecycle' => 'retired',
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
        ]);

        $col = ArchiveCollection::create(['code' => 'TXN_COL', 'name' => 'Col']);
        $pol = RetentionPolicy::create(['code' => 'TXN_POL', 'name' => 'Pol', 'active' => true]);
        $ver = RetentionPolicyVersion::create([
            'policy_id' => $pol->id,
            'version_number' => 1,
            'duration_months' => 12,
            'trigger_code' => 'creation_date',
            'effective_from' => now()->toDateString(),
            'disposition_action' => 'destroy',
        ]);

        $entry = ArchiveEntry::create([
            'document_id' => $doc->id,
            'collection_id' => $col->id,
            'retention_policy_version_id' => $ver->id,
            'archived_by' => $admin->id,
            'archived_at' => now(),
        ]);

        // Force AuditEvent saving to throw
        AuditEvent::saving(function () {
            throw new \RuntimeException('Forced audit database failure for rollback test');
        });

        $this->authenticateAs($admin);

        try {
            $this->postJson("/api/v1/archive/entries/{$entry->id}/restore");
        } catch (\Throwable) {
            // Expected
        }

        // Verify document lifecycle was NOT left in 'active' state due to rollback
        $doc->refresh();
        $this->assertEquals('retired', $doc->lifecycle, 'Document lifecycle must roll back to retired if transaction fails.');
    }
}
