<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\ConfidentialityLevel;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\OrganizationUnit;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Database\Seeders\InstitutionalCredentialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RealRowReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $unitAUser;
    protected OrganizationUnit $unitA;
    protected OrganizationUnit $unitB;
    protected ConfidentialityLevel $confPublic;
    protected DocumentCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);
        $this->seed(InstitutionalCredentialSeeder::class);

        $unitType = \App\Models\UnitType::first();

        $this->unitA = OrganizationUnit::create([
            'code' => 'EXP_REAL_A_' . uniqid(),
            'name' => 'Department of Physics',
            'unit_type_id' => $unitType->id,
            'active' => true,
        ]);

        $this->unitB = OrganizationUnit::create([
            'code' => 'EXP_REAL_B_' . uniqid(),
            'name' => 'Department of Chemistry',
            'unit_type_id' => $unitType->id,
            'active' => true,
        ]);

        $this->category = DocumentCategory::firstOrCreate(
            ['code' => 'curriculum'],
            ['name' => 'Curriculum Document', 'active' => true]
        );

        $this->confPublic = ConfidentialityLevel::where('code', 'public')->first() ?? ConfidentialityLevel::first();

        // Admin
        $this->admin = User::factory()->create(['status' => 'active', 'authz_version' => 1]);
        $this->grantPermissionToUser($this->admin, 'system.configure', 'global', null);
        $this->grantPermissionToUser($this->admin, 'reports.export', 'global', null);
        $this->grantPermissionToUser($this->admin, 'documents.view', 'global', null);

        // Unit A User
        $this->unitAUser = User::factory()->create(['status' => 'active', 'authz_version' => 1]);
        $this->grantPermissionToUser($this->unitAUser, 'reports.export', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($this->unitAUser, 'documents.view', 'unit', $this->unitA->id);
    }

    /**
     * (a) Export real document rows (reference, title, category, owner unit, date).
     */
    public function test_export_outputs_real_document_rows_with_headers(): void
    {
        $doc = Document::create([
            'reference_number' => 'WU/PHYS/2026/001',
            'title' => 'Quantum Optics Syllabus',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confPublic->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->admin->id,
            'document_date' => '2026-05-10',
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $res = $this->actingAs($this->admin)->get('/api/v1/reports/export');
        $res->assertStatus(200);

        $csv = $res->streamedContent();
        $this->assertStringContainsString('Reference Number', $csv);
        $this->assertStringContainsString('Title', $csv);
        $this->assertStringContainsString('Category', $csv);
        $this->assertStringContainsString('Owner Unit', $csv);
        $this->assertStringContainsString('Document Date', $csv);
        $this->assertStringContainsString('WU/PHYS/2026/001', $csv);
        $this->assertStringContainsString('Quantum Optics Syllabus', $csv);
        $this->assertStringContainsString('Curriculum Document', $csv);
        $this->assertStringContainsString('Department of Physics', $csv);
        $this->assertStringContainsString('2026-05-10', $csv);
    }

    /**
     * Test formula injection escaping on every text column (reference, title, category, owner unit).
     */
    public function test_formula_escaping_on_every_text_column(): void
    {
        $formulaCat = DocumentCategory::create([
            'code' => 'form_cat_' . uniqid(),
            'name' => '-SUM(A1:A10)',
            'active' => true,
        ]);

        $unitType = \App\Models\UnitType::first();
        $formulaUnit = OrganizationUnit::create([
            'code' => 'FORM_U_' . uniqid(),
            'name' => '@ATTACK_UNIT',
            'unit_type_id' => $unitType->id,
            'active' => true,
        ]);

        Document::create([
            'reference_number' => '=HYPERLINK("http://evil.com","click")',
            'title' => '+CMD("calc")',
            'category_id' => $formulaCat->id,
            'confidentiality_id' => $this->confPublic->id,
            'owner_unit_id' => $formulaUnit->id,
            'created_by' => $this->admin->id,
            'document_date' => '2026-06-01',
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $res = $this->actingAs($this->admin)->get('/api/v1/reports/export');
        $res->assertStatus(200);

        $csv = $res->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv, 'Reference number formula must be escaped');
        $this->assertStringContainsString("'+CMD", $csv, 'Title formula must be escaped');
        $this->assertStringContainsString("'-SUM", $csv, 'Category formula must be escaped');
        $this->assertStringContainsString("'@ATTACK_UNIT", $csv, 'Owner unit formula must be escaped');
    }

    /**
     * Test unit scoping limits exported rows to user's permitted units.
     */
    public function test_unit_scope_restricts_documents_to_permitted_unit(): void
    {
        Document::create([
            'reference_number' => 'WU/UA/DOC/' . uniqid(),
            'title' => 'Unit A Doc Exclusive',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confPublic->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->admin->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        Document::create([
            'reference_number' => 'WU/UB/DOC/' . uniqid(),
            'title' => 'Unit B Doc Exclusive',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confPublic->id,
            'owner_unit_id' => $this->unitB->id,
            'created_by' => $this->admin->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $res = $this->actingAs($this->unitAUser)->get('/api/v1/reports/export');
        $res->assertStatus(200);

        $csv = $res->streamedContent();
        $this->assertStringContainsString('Unit A Doc Exclusive', $csv);
        $this->assertStringNotContainsString('Unit B Doc Exclusive', $csv);
    }

    /**
     * Test row cap and X-Report-Truncated header.
     */
    public function test_row_cap_truncation_sets_x_report_truncated_header(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            Document::create([
                'reference_number' => "WU/TRUNC/{$i}/" . uniqid(),
                'title' => "Document Truncation {$i}",
                'category_id' => $this->category->id,
                'confidentiality_id' => $this->confPublic->id,
                'owner_unit_id' => $this->unitA->id,
                'created_by' => $this->admin->id,
                'document_date' => now()->toDateString(),
                'registered_at' => now(),
                'lifecycle' => 'active',
                'lock_version' => 0,
            ]);
        }

        // limit=2 on 3 documents -> X-Report-Truncated must be true
        $resTrunc = $this->actingAs($this->admin)->get('/api/v1/reports/export?limit=2');
        $resTrunc->assertStatus(200);
        $resTrunc->assertHeader('X-Report-Truncated', 'true');

        // limit=10 on 3 documents -> X-Report-Truncated must be false
        $resFull = $this->actingAs($this->admin)->get('/api/v1/reports/export?limit=10');
        $resFull->assertStatus(200);
        $resFull->assertHeader('X-Report-Truncated', 'false');
    }

    /**
     * Test audit row is written after the export succeeds, with outcome: success.
     */
    public function test_audit_row_written_after_export_succeeds_with_outcome(): void
    {
        Document::create([
            'reference_number' => 'WU/AUDIT/001/' . uniqid(),
            'title' => 'Audit Test Document',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confPublic->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->admin->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $res = $this->actingAs($this->admin)->get('/api/v1/reports/export');
        $res->assertStatus(200);
        $res->streamedContent(); // Consume stream to trigger end of stream audit

        $audit = AuditEvent::where('action', 'report.exported')
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($audit, 'An audit row must be written when export succeeds.');
        $this->assertEquals('success', $audit->details_json['outcome'] ?? null);
        $this->assertArrayHasKey('rows_exported', $audit->details_json);
    }
}
