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
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportExportPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected User $userWithViewOnly;
    protected User $userWithExportUnitA;
    protected OrganizationUnit $unitA;
    protected OrganizationUnit $unitB;
    protected DocumentCategory $catA;
    protected DocumentCategory $catB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $units = OrganizationUnit::take(2)->get();
        $this->unitA = $units[0];
        $this->unitB = $units->count() > 1 ? $units[1] : OrganizationUnit::create([
            'parent_id' => $this->unitA->id,
            'unit_type_id' => $this->unitA->unit_type_id,
            'code' => 'UNIT_B_' . Str::random(4),
            'name' => 'Unit B',
            'active' => 1,
        ]);

        $categories = DocumentCategory::take(2)->get();
        $this->catA = $categories[0];
        $this->catB = $categories[1];

        $conf = ConfidentialityLevel::where('code', 'internal')->first() ?? ConfidentialityLevel::first();

        // Document in Unit A
        Document::create([
            'reference_number' => 'WU/DOC/TEST/' . Str::random(6),
            'title' => 'Unit A Report Document',
            'summary' => 'Document belonging to Unit A',
            'category_id' => $this->catA->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => 1,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        // Document in Unit B
        Document::create([
            'reference_number' => 'WU/DOC/TEST/' . Str::random(6),
            'title' => 'Unit B Report Document',
            'summary' => 'Document belonging to Unit B',
            'category_id' => $this->catB->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $this->unitB->id,
            'created_by' => 1,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        // User with reports.view only (global)
        $this->userWithViewOnly = User::create([
            'name' => 'View Only User',
            'email' => 'viewonly.' . Str::random(6) . '@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($this->userWithViewOnly, 'reports.view', 'global');

        // User with reports.export scoped only to Unit A
        $this->userWithExportUnitA = User::create([
            'name' => 'Export Unit A User',
            'email' => 'exporta.' . Str::random(6) . '@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($this->userWithExportUnitA, 'reports.export', 'unit', $this->unitA->id);
    }

    public function test_user_with_reports_view_but_not_reports_export_gets_403(): void
    {
        $this->authenticateAs($this->userWithViewOnly);

        $response = $this->get('/api/v1/reports/export');

        $response->assertStatus(403);
    }

    public function test_user_with_reports_export_gets_200_and_only_their_units_rows(): void
    {
        $this->authenticateAs($this->userWithExportUnitA);

        $response = $this->get('/api/v1/reports/export');

        $response->assertStatus(200);

        // Fetch streamed content
        $content = $response->streamedContent();

        // The CSV should contain Category A (belonging to Unit A document)
        $this->assertStringContainsString($this->catA->name, $content);

        // The CSV should NOT contain Category B (belonging to Unit B document)
        $this->assertStringNotContainsString($this->catB->name, $content);
    }
}
