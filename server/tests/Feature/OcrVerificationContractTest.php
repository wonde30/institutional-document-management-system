<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentTextExtraction;
use App\Models\DocumentVersion;
use App\Models\OrganizationUnit;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The contract of the text extraction verification.
 *
 * The verification is the human correction of an extracted text. The corrected
 * text and the remarks of the verifying officer are both part of the record,
 * and the operation is scoped to the document that owns the extraction.
 */
class OcrVerificationContractTest extends TestCase
{
    use RefreshDatabase;

    protected User $officer;
    protected OrganizationUnit $unit;
    protected Document $document;
    protected DocumentVersion $version;
    protected DocumentTextExtraction $extraction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $this->unit = OrganizationUnit::first();

        $this->officer = User::create([
            'name' => 'Extraction Officer',
            'email' => 'extraction.officer@wu.edu.et',
            'password' => bcrypt('StrongPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        $this->grantPermissionToUser($this->officer, 'documents.view', 'unit', $this->unit->id);
        $this->grantPermissionToUser($this->officer, 'documents.update', 'unit', $this->unit->id);

        $this->document = Document::create([
            'reference_number' => 'WU/DOC/2026/'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'title' => 'Digitised minute',
            'category_id' => DocumentCategory::first()->id,
            'confidentiality_id' => \App\Models\ConfidentialityLevel::where('code', 'internal')->value('id'),
            'owner_unit_id' => $this->unit->id,
            'created_by' => $this->officer->id,
            'lifecycle' => 'active',
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
        ]);

        $this->version = DocumentVersion::create([
            'document_id' => $this->document->id,
            'version_number' => 1,
            'created_by' => $this->officer->id,
            'comment' => 'Initial version',
        ]);

        $fileObject = \App\Models\FileObject::create([
            'storage_disk' => 'local',
            'storage_key' => 'documents/'.random_int(1000, 9999).'/scanned-minute.pdf',
            'original_filename' => 'scanned-minute.pdf',
            'detected_mime' => 'application/pdf',
            'byte_size' => 2048,
            'sha256' => hash('sha256', 'scanned-minute'),
            'uploaded_by' => $this->officer->id,
            'uploaded_at' => now(),
            'scan_status' => 'clean',
            'scanned_at' => now(),
            'storage_status' => 'ready',
        ]);

        $this->extraction = DocumentTextExtraction::create([
            'document_version_id' => $this->version->id,
            'file_object_id' => $fileObject->id,
            'raw_text' => 'The raw extracted text.',
            'verified_text' => null,
            'searchable_text' => null,
            'language_detected' => 'eng',
            'confidence_score' => 62.5,
            'processing_status' => 'pending_review',
            'ocr_engine' => 'tesseract',
            'extracted_at' => now(),
        ]);
    }

    protected function verifyUrl(): string
    {
        return "/api/v1/documents/{$this->document->id}/versions/{$this->version->id}".
            "/extractions/{$this->extraction->id}/verify";
    }

    public function test_the_corrected_text_and_the_remarks_are_recorded(): void
    {
        $this->authenticateAs($this->officer);

        $response = $this->postJson($this->verifyUrl(), [
            'verified_text' => 'The corrected text.',
            'notes' => 'The original scan was folded at the margin.',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.verified_text', 'The corrected text.');

        $extraction = $this->extraction->fresh();

        $this->assertSame('The corrected text.', $extraction->verified_text);
        $this->assertSame('completed', $extraction->processing_status);
        $this->assertSame($this->officer->id, (int) $extraction->verified_by);
        $this->assertNotNull($extraction->verified_at);

        // The remarks are part of the permanent record of the verification.
        $audit = DB::table('audit_events')
            ->where('action', 'ocr.verified')
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($this->officer->id, (int) $audit->actor_id);

        $details = json_decode($audit->details_json, true);

        $this->assertSame($this->extraction->id, $details['extraction_id']);
        $this->assertSame('The original scan was folded at the margin.', $details['notes']);
    }

    public function test_the_corrected_text_is_required(): void
    {
        $this->authenticateAs($this->officer);

        $response = $this->postJson($this->verifyUrl(), [
            'notes' => 'A remark without the corrected text.',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonStructure(['errors' => ['verified_text']]);

        // Nothing was written.
        $this->assertNull($this->extraction->fresh()->verified_text);
        $this->assertSame(0, DB::table('audit_events')->where('action', 'ocr.verified')->count());
    }

    public function test_a_verification_without_the_capability_is_refused(): void
    {
        $reader = User::create([
            'name' => 'Extraction Reader',
            'email' => 'extraction.reader@wu.edu.et',
            'password' => bcrypt('StrongPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        $this->grantPermissionToUser($reader, 'documents.view', 'unit', $this->unit->id);

        $this->authenticateAs($reader);

        $this->postJson($this->verifyUrl(), ['verified_text' => 'The corrected text.'])
            ->assertStatus(403);

        $this->assertNull($this->extraction->fresh()->verified_text);
        $this->assertSame(0, DB::table('audit_events')->where('action', 'ocr.verified')->count());
    }

    public function test_a_verification_from_another_unit_is_refused(): void
    {
        $collegeType = UnitType::firstOrCreate(['code' => 'college'], ['name' => 'College', 'active' => 1]);

        $otherUnit = OrganizationUnit::create([
            'parent_id' => $this->unit->id,
            'unit_type_id' => $collegeType->id,
            'code' => 'OCR_FOREIGN_UNIT',
            'name' => 'Foreign Unit',
            'active' => 1,
        ]);

        $stranger = User::create([
            'name' => 'Foreign Officer',
            'email' => 'extraction.foreign@wu.edu.et',
            'password' => bcrypt('StrongPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        $this->grantPermissionToUser($stranger, 'documents.update', 'unit', $otherUnit->id);

        $this->authenticateAs($stranger);

        $this->postJson($this->verifyUrl(), ['verified_text' => 'The corrected text.'])
            ->assertStatus(403);

        $this->assertNull($this->extraction->fresh()->verified_text);
    }

    public function test_the_flat_alias_route_records_the_same_verification(): void
    {
        $this->authenticateAs($this->officer);

        $response = $this->postJson("/api/v1/extractions/{$this->extraction->id}/verify", [
            'verified_text' => 'The corrected text.',
            'notes' => 'Verified through the flat alias.',
        ]);

        $response->assertStatus(200);

        $this->assertSame('The corrected text.', $this->extraction->fresh()->verified_text);

        $details = json_decode(
            DB::table('audit_events')->where('action', 'ocr.verified')->latest('id')->value('details_json'),
            true
        );

        $this->assertSame('Verified through the flat alias.', $details['notes']);
    }
}
