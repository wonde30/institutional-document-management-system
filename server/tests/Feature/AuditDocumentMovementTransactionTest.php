<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\ConfidentialityLevel;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentMovement;
use App\Models\OrganizationUnit;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditDocumentMovementTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);
    }

    public function test_document_movement_recording_rolls_back_if_audit_fails(): void
    {
        $admin = User::where('email', 'admin@wu.edu.et')->first();
        $units = OrganizationUnit::take(2)->get();
        $cat = DocumentCategory::first();
        $conf = ConfidentialityLevel::where('code', 'internal')->first();

        $doc = Document::create([
            'reference_number' => 'DOC-MOV-TXN-01',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $units[0]->id,
            'title' => 'Movement Test Doc',
            'summary' => 'Summary',
            'created_by' => $admin->id,
            'lifecycle' => 'active',
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
        ]);

        $initialMovementCount = DocumentMovement::where('document_id', $doc->id)->count();

        // Force AuditEvent saving to throw
        AuditEvent::saving(function () {
            throw new \RuntimeException('Forced audit failure for movement rollback');
        });

        $this->authenticateAs($admin);

        try {
            $this->postJson("/api/v1/documents/{$doc->id}/movements", [
                'from_unit_id' => $units[0]->id,
                'to_unit_id' => $units[1]->id,
                'instruction' => 'Route for review',
            ]);
        } catch (\Throwable) {
            // Expected
        }

        // Verify movement was NOT persisted
        $this->assertEquals(
            $initialMovementCount,
            DocumentMovement::where('document_id', $doc->id)->count(),
            'Movement record must roll back if audit event creation fails.'
        );
    }
}
