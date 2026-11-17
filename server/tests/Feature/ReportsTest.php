<?php

namespace Tests\Feature;

use App\Models\ConfidentialityLevel;
use App\Models\Correspondence;
use App\Models\CorrespondenceType;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\OrganizationUnit;
use App\Models\Priority;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
        $conf = ConfidentialityLevel::first();

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

    public function test_dashboard_metrics_respects_departmental_scope(): void
    {
        $cat = DocumentCategory::first();
        $conf = ConfidentialityLevel::first();

        $scopedUser = User::create([
            'name' => 'Scoped Unit A Officer',
            'email' => 'unita_officer@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($scopedUser, 'documents.view', 'unit', $this->unit->id);

        // The baselines are measured, not assumed: the suite shares one database and
        // earlier non-transactional classes may leave committed records behind.
        $this->authenticateAs($scopedUser);
        $scopedBaseline = $this->getJson('/api/v1/dashboard/metrics')
            ->assertStatus(200)
            ->json('data.overview.total_documents');

        $this->authenticateAs($this->admin);
        $adminBaseline = $this->getJson('/api/v1/dashboard/metrics')
            ->assertStatus(200)
            ->json('data.overview.total_documents');

        $unitB = OrganizationUnit::create([
            'code' => 'UNIT-B-'.Str::random(6),
            'name' => 'Department of Physics',
            'unit_type_id' => $this->unit->unit_type_id,
            'active' => 1,
        ]);

        $foreignDocument = Document::create([
            'reference_number' => 'WU/DOC/2026/'.Str::upper(Str::random(10)),
            'title' => 'Physics Research Plan',
            'summary' => 'Department B document',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $unitB->id,
            'created_by' => $this->admin->id,
            'document_date' => '2026-10-30',
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        // The scoped caller must not see the other unit's document.
        $this->authenticateAs($scopedUser);
        $scopedResponse = $this->getJson('/api/v1/dashboard/metrics')->assertStatus(200);
        $this->assertSame($scopedBaseline, $scopedResponse->json('data.overview.total_documents'));
        $this->assertNotContains(
            $foreignDocument->id,
            $scopedResponse->json('data.recent_documents.*.id'),
            'A document owned by another unit must not appear in the scoped dashboard.'
        );

        // The global caller must see it.
        $this->authenticateAs($this->admin);
        $adminResponse = $this->getJson('/api/v1/dashboard/metrics')->assertStatus(200);
        $this->assertSame($adminBaseline + 1, $adminResponse->json('data.overview.total_documents'));
        $this->assertContains(
            $foreignDocument->id,
            $adminResponse->json('data.recent_documents.*.id'),
            'A document owned by another unit must be visible to a global capability holder.'
        );
    }
}
