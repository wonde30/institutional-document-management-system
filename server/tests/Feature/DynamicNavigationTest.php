<?php

namespace Tests\Feature;

use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DynamicNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/navigation');
        $response->assertStatus(401);
    }

    public function test_authenticated_user_receives_dynamic_navigation(): void
    {
        $unit = OrganizationUnit::first();
        $user = User::create([
            'name' => 'Department Officer',
            'email' => 'officer@wu.edu.et',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        $this->assignRoleToUser($user, 'department_head', 'unit', $unit->id);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/navigation');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'icon',
                        'items' => [
                            '*' => [
                                'name',
                                'to',
                                'icon',
                                'permission',
                            ],
                        ],
                    ],
                ],
                'meta' => [
                    'user_id',
                    'is_super_admin',
                    'permissions_count',
                    'generated_at',
                ],
            ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        // Department head has documents.view, so Documents menu must be present
        $allItemPaths = [];
        foreach ($data as $section) {
            foreach ($section['items'] as $item) {
                $allItemPaths[] = $item['to'];
            }
        }

        $this->assertContains('/dashboard', $allItemPaths);
        $this->assertContains('/documents', $allItemPaths);
    }

    public function test_superadmin_receives_complete_navigation_catalog(): void
    {
        $unit = OrganizationUnit::first();
        $admin = User::create([
            'name' => 'System Admin',
            'email' => 'superadmin@wu.edu.et',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        $this->assignRoleToUser($admin, 'super_admin', 'global');
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/navigation');
        $response->assertStatus(200);

        $data = $response->json('data');
        $sectionIds = array_column($data, 'id');

        $this->assertContains('workspace', $sectionIds);
        $this->assertContains('records', $sectionIds);
        $this->assertContains('institutional', $sectionIds);
        $this->assertContains('trust', $sectionIds);
        $this->assertContains('admin', $sectionIds);
    }
}
