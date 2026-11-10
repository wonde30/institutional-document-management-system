<?php

namespace Tests\Feature;

use App\Models\Correspondence;
use App\Models\CorrespondenceType;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\OrganizationUnit;
use App\Models\Priority;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;
    protected OrganizationUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $this->admin = User::where('email', 'admin@wu.edu.et')->first();
        $this->unit = OrganizationUnit::first();

        $this->staff = User::create([
            'name' => 'Report Inquirer Staff',
            'email' => 'inquirer@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->staff, 'staff');

        // Seed some sample document & correspondence data
        $cat = DocumentCategory::first();
        $conf = \App\Models\ConfidentialityLevel::first();

        $doc = Document::create([
            'reference_number' => 'WU/DOC/2026/000888',
            'title' => 'Executive Strategic Plan 2026-2030',
            'summary' => 'Five year institutional road map',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $this->unit->id,
            'created_by' => $this->admin->id,
            'document_date' => '2026-10-29',
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        Correspondence::create([
            'document_id' => $doc->id,
            'type_id' => CorrespondenceType::first()->id,
            'priority_id' => Priority::first()->id,
            'purpose_code' => 'strategic_planning',
            'external_reference' => 'STRAT-PLAN-2026',
            'correspondence_date' => '2026-10-29',
            'registered_by' => $this->admin->id,
            'registered_at' => now(),
        ]);
    }

    public function test_can_retrieve_dashboard_metrics(): void
    {
        $this->authenticateAs($this->admin);

        $response = $this->getJson('/api/v1/dashboard/metrics');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'overview' => [
                        'total_documents',
                        'active_documents',
                        'retired_documents',
                        'total_correspondences',
                        'active_users',
                        'organization_units',
                    ],
                    'recent_documents',
                    'recent_correspondences',
                ],
            ]);

        $this->assertGreaterThanOrEqual(1, $response->json('data.overview.total_documents'));
        $this->assertGreaterThanOrEqual(1, $response->json('data.overview.total_correspondences'));
    }

    public function test_can_retrieve_institutional_summary_report(): void
    {
        $this->authenticateAs($this->admin);

        $response = $this->getJson('/api/v1/reports/summary');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'period' => ['from', 'to'],
                    'documents_by_category',
                    'documents_by_confidentiality',
                    'correspondence_by_type',
                    'top_movement_destinations',
                    'totals' => ['documents', 'correspondence', 'movements', 'archive_entries'],
                ],
            ]);
    }

    public function test_staff_without_reports_view_permission_is_forbidden(): void
    {
        $this->authenticateAs($this->staff);

        $response = $this->getJson('/api/v1/reports/summary');
        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }
}
