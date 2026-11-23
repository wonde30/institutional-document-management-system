<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ConfidentialityLevel;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\OrganizationUnit;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Database\Seeders\InstitutionalCredentialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportHardeningAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $unitAUser;
    protected OrganizationUnit $unitA;
    protected OrganizationUnit $unitB;
    protected ConfidentialityLevel $confPublic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);
        $this->seed(InstitutionalCredentialSeeder::class);

        $unitType = \App\Models\UnitType::first();

        $this->unitA = OrganizationUnit::create([
            'code' => 'EXP_UNIT_A_' . uniqid(),
            'name' => 'Export Test Unit A',
            'unit_type_id' => $unitType->id,
            'active' => true,
        ]);

        $this->unitB = OrganizationUnit::create([
            'code' => 'EXP_UNIT_B_' . uniqid(),
            'name' => 'Export Test Unit B',
            'unit_type_id' => $unitType->id,
            'active' => true,
        ]);

        $this->confPublic = ConfidentialityLevel::where('code', 'public')->first() ?? ConfidentialityLevel::first();

        // 1. Admin with global reports.export
        $this->admin = User::factory()->create(['status' => 'active', 'authz_version' => 1]);
        $this->grantPermissionToUser($this->admin, 'system.configure', 'global', null);
        $this->grantPermissionToUser($this->admin, 'reports.export', 'global', null);
        $this->grantPermissionToUser($this->admin, 'documents.view', 'global', null);

        // 2. Unit A user with unit-scoped reports.export and documents.view
        $this->unitAUser = User::factory()->create(['status' => 'active', 'authz_version' => 1]);
        $this->grantPermissionToUser($this->unitAUser, 'reports.export', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($this->unitAUser, 'documents.view', 'unit', $this->unitA->id);
    }

    public function test_formula_escaping_in_csv_export(): void
    {
        $formulaCat = DocumentCategory::create([
            'code' => 'formula_' . uniqid(),
            'name' => '=HYPERLINK("http://evil.com","Click")',
            'active' => true,
        ]);

        Document::create([
            'reference_number' => 'WU/EXP/FORM/' . uniqid(),
            'title' => 'Formula Escape Test Doc',
            'category_id' => $formulaCat->id,
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

        $csv = $res->streamedContent();
        // Leading '=' must be prefixed with single quote
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_unit_scope_restricts_exported_data_to_permitted_unit(): void
    {
        $catA = DocumentCategory::create(['code' => 'cat_a_' . uniqid(), 'name' => 'Unit A Only Category', 'active' => true]);
        $catB = DocumentCategory::create(['code' => 'cat_b_' . uniqid(), 'name' => 'Unit B Only Category', 'active' => true]);

        Document::create([
            'reference_number' => 'WU/EXP/UA/' . uniqid(),
            'title' => 'Doc in Unit A',
            'category_id' => $catA->id,
            'confidentiality_id' => $this->confPublic->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->admin->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        Document::create([
            'reference_number' => 'WU/EXP/UB/' . uniqid(),
            'title' => 'Doc in Unit B',
            'category_id' => $catB->id,
            'confidentiality_id' => $this->confPublic->id,
            'owner_unit_id' => $this->unitB->id,
            'created_by' => $this->admin->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        // Unit A user exports report
        $res = $this->actingAs($this->unitAUser)->get('/api/v1/reports/export');
        $res->assertStatus(200);

        $csv = $res->streamedContent();
        $this->assertStringContainsString('Unit A Only Category', $csv);
        $this->assertStringNotContainsString('Unit B Only Category', $csv);
    }

    public function test_row_cap_hit_is_visible_in_headers_and_audit(): void
    {
        // Create 3 distinct categories with documents
        for ($i = 1; $i <= 3; $i++) {
            $cat = DocumentCategory::create(['code' => "cap_cat_{$i}_" . uniqid(), 'name' => "Cap Category {$i}", 'active' => true]);
            Document::create([
                'reference_number' => "WU/EXP/CAP/{$i}/" . uniqid(),
                'title' => "Doc {$i}",
                'category_id' => $cat->id,
                'confidentiality_id' => $this->confPublic->id,
                'owner_unit_id' => $this->unitA->id,
                'created_by' => $this->admin->id,
                'document_date' => now()->toDateString(),
                'registered_at' => now(),
                'lifecycle' => 'active',
                'lock_version' => 0,
            ]);
        }

        // Test with limit=2 (cap hit because there are 3 categories)
        $res = $this->actingAs($this->admin)->get('/api/v1/reports/export?limit=2');
        $res->assertStatus(200);

        $res->assertHeader('X-Row-Cap', '2');
        $res->assertHeader('X-Row-Cap-Hit', 'true');
        $this->assertTrue($res->headers->has('X-Warning'));

        $this->assertDatabaseHas('audit_events', [
            'action' => 'report.exported',
            'target_type' => 'report',
        ]);
    }
}
