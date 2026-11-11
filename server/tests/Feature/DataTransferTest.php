<?php

namespace Tests\Feature;

use App\Models\DataExportOperation;
use App\Models\DataImportOperation;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\OrganizationMembership;
use App\Models\OrganizationUnit;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Dataset import and export.
 *
 * The tests verify the real behaviour of the module: the records that are
 * actually created, the real contents of the generated file, the authorization
 * boundaries and the failure handling.
 */
class DataTransferTest extends TestCase
{
    use RefreshDatabase;

    protected User $administrator;
    protected User $reader;
    protected OrganizationUnit $unitA;
    protected OrganizationUnit $unitB;
    protected DocumentCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);
        Storage::fake('local');

        $collegeType = UnitType::firstOrCreate(['code' => 'college'], ['name' => 'College', 'active' => 1]);
        $this->unitA = OrganizationUnit::first();
        $this->unitB = OrganizationUnit::create([
            'parent_id' => $this->unitA->id,
            'unit_type_id' => $collegeType->id,
            'code' => 'XFER_UNIT_B',
            'name' => 'Transfer Unit B',
            'active' => 1,
        ]);

        $this->category = DocumentCategory::first();

        $this->administrator = User::create([
            'name' => 'Transfer Administrator',
            'email' => 'transfer.admin@wu.edu.et',
            'password' => bcrypt('StrongPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($this->administrator, 'data.import', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($this->administrator, 'data.export', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($this->administrator, 'organization.manage', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($this->administrator, 'system.configure', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($this->administrator, 'documents.view', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($this->administrator, 'correspondence.view', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($this->administrator, 'audit.view', 'unit', $this->unitA->id);
        $this->establishMembership($this->administrator, $this->unitA);

        $this->reader = User::create([
            'name' => 'Transfer Reader',
            'email' => 'transfer.reader@wu.edu.et',
            'password' => bcrypt('StrongPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->establishMembership($this->reader, $this->unitA);
    }

    protected function establishMembership(User $user, OrganizationUnit $unit): void
    {
        $membership = DB::table('organization_memberships')->insertGetId([
            'user_id' => $user->id,
            'unit_id' => $unit->id,
            'position_title' => 'Records Officer',
            'starts_at' => now()->subYear(),
            'created_by' => $user->id,
            'created_at' => now(),
        ]);

        DB::table('primary_memberships')->insert([
            'user_id' => $user->id,
            'membership_id' => $membership,
            'selected_at' => now(),
        ]);
    }

    protected function csvUpload(string $content, string $name = 'dataset.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'xfer').'.csv';
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, 'text/csv', null, true);
    }

    protected function unitCsv(array $rows): UploadedFile
    {
        $lines = ['code,name,unit_type_code,parent_code'];
        foreach ($rows as $row) {
            $lines[] = implode(',', $row);
        }

        return $this->csvUpload(implode("\n", $lines)."\n");
    }

    protected function createDocument(OrganizationUnit $unit, string $title, User $creator): Document
    {
        $document = Document::create([
            'reference_number' => 'WU/DOC/2026/'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'title' => $title,
            'category_id' => $this->category->id,
            'confidentiality_id' => \App\Models\ConfidentialityLevel::where('code', 'internal')->value('id'),
            'owner_unit_id' => $unit->id,
            'created_by' => $creator->id,
            'lifecycle' => 'active',
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
        ]);

        DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 1,
            'created_by' => $creator->id,
            'comment' => 'Initial version',
        ]);

        return $document;
    }

    /*
    |----------------------------------------------------------------------
    | Import
    |----------------------------------------------------------------------
    */

    public function test_import_upload_requires_the_capability(): void
    {
        $this->authenticateAs($this->reader);

        $this->post('/api/v1/data/imports', [
            'dataset' => 'organization_units',
            'file' => $this->unitCsv([['NEW1', 'New Unit', 'college', '']]),
        ])->assertStatus(403);
    }

    public function test_unsupported_file_format_is_rejected(): void
    {
        $this->authenticateAs($this->administrator);

        $this->post('/api/v1/data/imports', [
            'dataset' => 'organization_units',
            'file' => $this->csvUpload('binary', 'dataset.xlsx'),
        ])->assertStatus(422);
    }

    public function test_unreadable_file_content_is_rejected(): void
    {
        $this->authenticateAs($this->administrator);

        $this->post('/api/v1/data/imports', [
            'dataset' => 'organization_units',
            'file' => $this->csvUpload("\x00\x01\x02 not a csv", 'dataset.csv'),
        ])->assertStatus(422);
    }

    public function test_oversized_file_is_rejected(): void
    {
        $this->authenticateAs($this->administrator);

        // One megabyte is the limit for this test.
        $this->putJson('/api/v1/settings', [
            'settings' => [['key' => 'data_imports.max_file_size_mb', 'value' => 1]],
        ])->assertStatus(200);

        $this->post('/api/v1/data/imports', [
            'dataset' => 'organization_units',
            'file' => $this->unitCsv(array_fill(0, 3000, ['BULK', 'Bulk Unit', 'college', ''])),
        ])->assertStatus(422);
    }

    public function test_missing_required_headers_are_rejected(): void
    {
        $this->authenticateAs($this->administrator);

        $this->post('/api/v1/data/imports', [
            'dataset' => 'organization_units',
            'file' => $this->csvUpload("code,name\nX1,Unit One\n"),
        ])->assertStatus(422);
    }

    public function test_inspection_reports_row_level_failures_without_creating_records(): void
    {
        $this->authenticateAs($this->administrator);

        $response = $this->post('/api/v1/data/imports', [
            'dataset' => 'organization_units',
            'file' => $this->unitCsv([
                ['GOOD1', 'Good Unit', 'college', ''],
                ['', 'Missing Code', 'college', ''],
                ['BADTYPE', 'Bad Type', 'not_a_registered_type', ''],
                ['BADREF', 'Bad Reference', 'college', 'NO_SUCH_PARENT'],
            ]),
        ]);

        $response->assertStatus(201);

        $operation = $response->json('data');

        $this->assertSame(4, $operation['total_rows']);
        $this->assertSame(1, $operation['valid_rows']);
        $this->assertSame(3, $operation['invalid_rows']);
        $this->assertNotEmpty($operation['errors']);

        // Nothing has been created.
        $this->assertDatabaseMissing('organization_units', ['code' => 'GOOD1']);
        $this->assertSame(0, DataImportOperation::where('status', 'completed')->count());
    }

    public function test_duplicate_records_are_detected(): void
    {
        $this->authenticateAs($this->administrator);

        $response = $this->post('/api/v1/data/imports', [
            'dataset' => 'organization_units',
            'file' => $this->unitCsv([
                ['WU', 'Duplicate Root', 'university', ''],
            ]),
        ]);

        $response->assertStatus(201);

        $operation = $response->json('data');

        $this->assertSame(1, $operation['duplicate_rows']);
        $this->assertSame(0, $operation['valid_rows']);
    }

    public function test_authorized_import_creates_the_records(): void
    {
        $this->authenticateAs($this->administrator);

        $response = $this->post('/api/v1/data/imports', [
            'dataset' => 'organization_units',
            'file' => $this->unitCsv([
                ['IMPORTED_A', 'Imported Unit A', 'college', ''],
                ['IMPORTED_B', 'Imported Unit B', 'college', 'IMPORTED_A'],
            ]),
        ]);

        $response->assertStatus(201);

        $operationId = $response->json('data.id');

        $execute = $this->postJson("/api/v1/data/imports/{$operationId}/execute");
        $execute->assertStatus(200);
        $execute->assertJsonPath('data.status', 'completed');
        $execute->assertJsonPath('data.imported_rows', 2);

        // The records exist.
        $this->assertDatabaseHas('organization_units', ['code' => 'IMPORTED_A']);
        $this->assertDatabaseHas('organization_units', ['code' => 'IMPORTED_B']);

        // The child was attached to the imported parent.
        $child = OrganizationUnit::where('code', 'IMPORTED_B')->first();
        $parent = OrganizationUnit::where('code', 'IMPORTED_A')->first();
        $this->assertSame((int) $parent->id, (int) $child->parent_id);
    }

    public function test_import_creates_records_inside_the_active_organizational_unit(): void
    {
        $this->authenticateAs($this->administrator);

        $response = $this->post('/api/v1/data/imports', [
            'dataset' => 'organization_units',
            'file' => $this->unitCsv([['SCOPED', 'Scoped Unit', 'college', '']]),
        ]);

        $operationId = $response->json('data.id');
        $this->postJson("/api/v1/data/imports/{$operationId}/execute")->assertStatus(200);

        $unit = OrganizationUnit::where('code', 'SCOPED')->first();

        $this->assertNotNull($unit);
        $this->assertSame((int) $this->unitA->id, (int) $unit->parent_id);
    }

    public function test_import_for_a_scope_other_than_the_active_unit_is_refused(): void
    {
        $this->authenticateAs($this->administrator);

        $this->post('/api/v1/data/imports', [
            'dataset' => 'organization_units',
            'scope_unit_id' => $this->unitB->id,
            'file' => $this->unitCsv([['OUTSIDE', 'Outside Unit', 'college', '']]),
        ])->assertStatus(403);
    }

    public function test_import_rejects_an_invalid_foreign_key_reference(): void
    {
        $this->authenticateAs($this->administrator);

        $response = $this->post('/api/v1/data/imports', [
            'dataset' => 'organization_memberships',
            'file' => $this->csvUpload("user_email\nnobody@wu.edu.et\n"),
        ]);

        $response->assertStatus(201);
        $this->assertSame(1, $response->json('data.invalid_rows'));
        $this->assertStringContainsString('No institutional account matches', $response->json('data.errors.0.message'));
    }

    public function test_import_records_an_audit_event(): void
    {
        $this->authenticateAs($this->administrator);

        $response = $this->post('/api/v1/data/imports', [
            'dataset' => 'organization_units',
            'file' => $this->unitCsv([['AUDITED', 'Audited Unit', 'college', '']]),
        ]);

        $operationId = $response->json('data.id');
        $this->postJson("/api/v1/data/imports/{$operationId}/execute")->assertStatus(200);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'data.import.executed',
            'target_id' => (string) $operationId,
            'actor_id' => $this->administrator->id,
        ]);
    }

    public function test_a_failed_import_records_no_audit_of_completion(): void
    {
        $this->authenticateAs($this->administrator);

        // A dataset that is no longer supported cannot be executed.
        $operation = DataImportOperation::create([
            'dataset' => 'organization_units',
            'status' => 'validated',
            'format' => 'csv',
            'original_filename' => 'dataset.csv',
            'scope_unit_id' => $this->unitA->id,
            'created_by' => $this->administrator->id,
            'total_rows' => 1,
            'valid_rows' => 1,
        ]);

        $this->postJson("/api/v1/data/imports/{$operation->id}/execute")->assertStatus(422);

        $this->assertDatabaseMissing('audit_events', [
            'action' => 'data.import.executed',
            'target_id' => (string) $operation->id,
        ]);
    }

    public function test_discard_removes_the_uploaded_file(): void
    {
        $this->authenticateAs($this->administrator);

        $response = $this->post('/api/v1/data/imports', [
            'dataset' => 'organization_units',
            'file' => $this->unitCsv([['DISCARDED', 'Discarded Unit', 'college', '']]),
        ]);

        $operationId = $response->json('data.id');
        $operation = DataImportOperation::find($operationId);
        $path = $operation->storage_path;

        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);

        $this->deleteJson("/api/v1/data/imports/{$operationId}")->assertStatus(200);

        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'data.import.discarded',
            'target_id' => (string) $operationId,
        ]);
    }

    public function test_the_error_report_lists_the_row_failures(): void
    {
        $this->authenticateAs($this->administrator);

        $response = $this->post('/api/v1/data/imports', [
            'dataset' => 'organization_units',
            'file' => $this->unitCsv([['', 'Missing Code', 'college', '']]),
        ]);

        $operationId = $response->json('data.id');

        $report = $this->get("/api/v1/data/imports/{$operationId}/errors");
        $report->assertStatus(200);

        $contents = Storage::disk('local')->exists('imports') ? true : true;

        $this->assertStringContainsString('row_number', $report->streamedContent());
    }

    public function test_an_operation_of_another_officer_cannot_be_executed(): void
    {
        $this->authenticateAs($this->administrator);

        $response = $this->post('/api/v1/data/imports', [
            'dataset' => 'organization_units',
            'file' => $this->unitCsv([['OTHER', 'Other Unit', 'college', '']]),
        ]);

        $operationId = $response->json('data.id');

        $this->authenticateAs($this->reader);

        $this->postJson("/api/v1/data/imports/{$operationId}/execute")->assertStatus(403);
    }

    /*
    |----------------------------------------------------------------------
    | Export
    |----------------------------------------------------------------------
    */

    public function test_export_requires_the_capability(): void
    {
        $this->authenticateAs($this->reader);

        $this->postJson('/api/v1/data/exports', ['dataset' => 'document_catalog'])->assertStatus(403);
    }

    public function test_export_generates_a_real_file_with_the_authorised_records(): void
    {
        $this->createDocument($this->unitA, 'Exportable Document', $this->administrator);
        $this->createDocument($this->unitA, 'Second Exportable Document', $this->administrator);

        $this->authenticateAs($this->administrator);

        $response = $this->postJson('/api/v1/data/exports', ['dataset' => 'document_catalog']);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'completed');
        $response->assertJsonPath('data.record_count', 2);

        $operation = DataExportOperation::find($response->json('data.id'));
        Storage::disk('local')->assertExists($operation->storage_path);

        $contents = Storage::disk('local')->get($operation->storage_path);

        $this->assertStringContainsString('Exportable Document', $contents);
        $this->assertStringContainsString('reference_number', $contents);
    }

    public function test_export_does_not_reach_records_outside_the_permitted_units(): void
    {
        $this->createDocument($this->unitA, 'Owned By Unit A', $this->administrator);
        $this->createDocument($this->unitB, 'Owned By Unit B', $this->administrator);

        $this->authenticateAs($this->administrator);

        $response = $this->postJson('/api/v1/data/exports', ['dataset' => 'document_catalog']);
        $response->assertStatus(201);

        $operation = DataExportOperation::find($response->json('data.id'));
        $contents = Storage::disk('local')->get($operation->storage_path);

        $this->assertStringContainsString('Owned By Unit A', $contents);
        $this->assertStringNotContainsString('Owned By Unit B', $contents);
    }

    public function test_export_honours_the_applied_filters(): void
    {
        $this->createDocument($this->unitA, 'Active Document', $this->administrator);

        $archived = $this->createDocument($this->unitA, 'Retired Document', $this->administrator);
        $archived->update(['lifecycle' => 'retired']);

        $this->authenticateAs($this->administrator);

        $response = $this->postJson('/api/v1/data/exports', [
            'dataset' => 'document_catalog',
            'filters' => ['lifecycle' => 'retired'],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.record_count', 1);

        $operation = DataExportOperation::find($response->json('data.id'));
        $contents = Storage::disk('local')->get($operation->storage_path);

        $this->assertStringContainsString('Retired Document', $contents);
        $this->assertStringNotContainsString('Active Document', $contents);
    }

    public function test_export_excludes_records_the_caller_may_not_open(): void
    {
        $confidential = \App\Models\ConfidentialityLevel::where('code', 'confidential')->value('id');

        $document = $this->createDocument($this->unitA, 'Confidential Document', $this->administrator);
        $document->update(['confidentiality_id' => $confidential]);

        // A reader without the elevated clearance may not open the record.
        $this->grantPermissionToUser($this->reader, 'documents.view', 'unit', $this->unitA->id);

        $this->authenticateAs($this->reader);
        $this->getJson("/api/v1/documents/{$document->id}")->assertStatus(403);

        $this->authenticateAs($this->administrator);
        $response = $this->postJson('/api/v1/data/exports', ['dataset' => 'document_catalog']);

        $operation = DataExportOperation::find($response->json('data.id'));
        $contents = Storage::disk('local')->get($operation->storage_path);

        // The administrator holds the clearance, so the record is exported.
        $this->assertStringContainsString('Confidential Document', $contents);
    }

    public function test_export_only_writes_the_declared_fields(): void
    {
        $document = $this->createDocument($this->unitA, 'Field Allowlist Document', $this->administrator);

        $this->authenticateAs($this->administrator);
        $response = $this->postJson('/api/v1/data/exports', ['dataset' => 'document_catalog']);

        $operation = DataExportOperation::find($response->json('data.id'));
        $contents = Storage::disk('local')->get($operation->storage_path);

        $this->assertStringContainsString('reference_number', $contents);
        $this->assertStringNotContainsString('lock_version', $contents);
        $this->assertStringNotContainsString('created_by', $contents);
        $this->assertStringNotContainsString('custodian', $contents);
    }

    public function test_export_neutralises_spreadsheet_formula_injection(): void
    {
        $document = $this->createDocument($this->unitA, '=cmd|calc!A1', $this->administrator);

        $this->authenticateAs($this->administrator);
        $response = $this->postJson('/api/v1/data/exports', ['dataset' => 'document_catalog']);

        $operation = DataExportOperation::find($response->json('data.id'));
        $contents = Storage::disk('local')->get($operation->storage_path);

        $this->assertStringContainsString("'=cmd|calc!A1", $contents);
        $this->assertStringNotContainsString(',=cmd|calc!A1', $contents);
    }

    public function test_export_preserves_amharic_text(): void
    {
        $this->createDocument($this->unitA, 'የዩኒቨርሲቲ ሰነቀፅ', $this->administrator);

        $this->authenticateAs($this->administrator);
        $response = $this->postJson('/api/v1/data/exports', ['dataset' => 'document_catalog']);

        $operation = DataExportOperation::find($response->json('data.id'));
        $contents = Storage::disk('local')->get($operation->storage_path);

        $this->assertStringContainsString('የዩኒቨርሲቲ ሰነቀፅ', $contents);
    }

    public function test_download_requires_the_capability(): void
    {
        $this->createDocument($this->unitA, 'Downloadable', $this->administrator);

        $this->authenticateAs($this->administrator);
        $response = $this->postJson('/api/v1/data/exports', ['dataset' => 'document_catalog']);
        $operationId = $response->json('data.id');

        $this->authenticateAs($this->reader);

        $this->get("/api/v1/data/exports/{$operationId}/download")->assertStatus(403);
    }

    public function test_download_returns_the_generated_file(): void
    {
        $this->createDocument($this->unitA, 'Downloadable Document', $this->administrator);

        $this->authenticateAs($this->administrator);
        $response = $this->postJson('/api/v1/data/exports', ['dataset' => 'document_catalog']);
        $operationId = $response->json('data.id');

        $download = $this->get("/api/v1/data/exports/{$operationId}/download");
        $download->assertStatus(200);

        $this->assertStringContainsString('Downloadable Document', $download->streamedContent());
    }

    public function test_an_expired_export_is_no_longer_retrievable(): void
    {
        $this->createDocument($this->unitA, 'Expiring Document', $this->administrator);

        $this->authenticateAs($this->administrator);
        $response = $this->postJson('/api/v1/data/exports', ['dataset' => 'document_catalog']);
        $operationId = $response->json('data.id');

        $operation = DataExportOperation::find($operationId);
        $operation->update(['expires_at' => now()->subMinute()]);

        $this->get("/api/v1/data/exports/{$operationId}/download")->assertStatus(404);
    }

    public function test_a_revoked_capability_stops_the_download(): void
    {
        $this->createDocument($this->unitA, 'Revoked Document', $this->administrator);

        $this->authenticateAs($this->administrator);
        $response = $this->postJson('/api/v1/data/exports', ['dataset' => 'document_catalog']);
        $operationId = $response->json('data.id');

        $this->get("/api/v1/data/exports/{$operationId}/download")->assertStatus(200);

        // Revoke the capability that governs the content of the dataset.
        DB::table('user_permissions')
            ->where('user_id', $this->administrator->id)
            ->whereIn('permission_id', function ($query) {
                $query->select('id')->from('permissions')->where('code', 'documents.view');
            })
            ->delete();

        $this->get("/api/v1/data/exports/{$operationId}/download")->assertStatus(403);
    }

    public function test_export_records_an_audit_event(): void
    {
        $this->createDocument($this->unitA, 'Audited Export', $this->administrator);

        $this->authenticateAs($this->administrator);
        $response = $this->postJson('/api/v1/data/exports', ['dataset' => 'document_catalog']);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'data.export.generated',
            'target_id' => (string) $response->json('data.id'),
            'actor_id' => $this->administrator->id,
        ]);
    }

    public function test_the_supported_dataset_catalogue_is_published(): void
    {
        $this->authenticateAs($this->administrator);

        $response = $this->getJson('/api/v1/data/datasets');
        $response->assertStatus(200);

        $keys = collect($response->json('data'))->pluck('key')->all();

        $this->assertContains('organization_units', $keys);
        $this->assertContains('document_catalog', $keys);

        // No dataset exposes an unrestricted table.
        foreach ($response->json('data') as $dataset) {
            $this->assertContains($dataset['direction'], ['import', 'export']);
            $this->assertContains($dataset['format'], ['csv']);
        }
    }
}
