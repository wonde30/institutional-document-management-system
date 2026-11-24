<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ConfidentialityLevel;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\OrganizationUnit;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentVersionPermissionNarrowingTest extends TestCase
{
    use RefreshDatabase;

    protected User $staffUser;
    protected Document $document;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $unit = OrganizationUnit::first();
        $cat = DocumentCategory::first();
        $conf = ConfidentialityLevel::where('code', 'internal')->first() ?? ConfidentialityLevel::first();
        $creator = User::where('email', 'admin@wu.edu.et')->first() ?? User::first();

        $this->document = Document::create([
            'reference_number' => 'WU/DOC/TEST/' . Str::random(6),
            'title' => 'Document For Version Testing',
            'summary' => 'Testing strict documents.version requirement',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $unit->id,
            'created_by' => $creator->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        // Staff user has documents.upload and documents.view, but NOT documents.version
        $this->staffUser = User::create([
            'name' => 'Staff With Upload Only',
            'email' => 'uploadonly.' . Str::random(6) . '@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($this->staffUser, 'documents.view', 'global');
        $this->grantPermissionToUser($this->staffUser, 'documents.upload', 'global');
    }

    public function test_user_with_documents_upload_but_not_documents_version_cannot_upload_version(): void
    {
        $this->authenticateAs($this->staffUser);

        $newFile = UploadedFile::fake()->create('version_test.pdf', 200, 'application/pdf');

        $response = $this->postJson("/api/v1/documents/{$this->document->id}/versions", [
            'file' => $newFile,
            'change_note' => 'Upload attempt by staff with upload permission only',
        ]);

        // Must be rejected with 403 Forbidden because route requires documents.version
        $response->assertStatus(403);
    }
}
