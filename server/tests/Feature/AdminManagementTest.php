<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\OrganizationUnit;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $this->admin = User::where('email', 'admin@wu.edu.et')->first();

        $this->staff = User::create([
            'name' => 'General Staff Member',
            'email' => 'staff@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->staff, 'staff');
    }

    public function test_admin_can_create_and_list_organization_units(): void
    {
        $this->authenticateAs($this->admin);

        $collegeType = UnitType::where('code', 'college')->first();
        $parent = OrganizationUnit::first();

        $response = $this->postJson('/api/v1/organization-units', [
            'name' => 'College of Law and Governance',
            'code' => 'CLG',
            'unit_type_id' => $collegeType->id,
            'parent_id' => $parent->id,
            'active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'CLG');

        $this->assertDatabaseHas('organization_units', [
            'code' => 'CLG',
            'name' => 'College of Law and Governance',
        ]);

        // List tree
        $treeRes = $this->getJson('/api/v1/organization-units/tree');
        $treeRes->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_staff_cannot_create_organization_units(): void
    {
        $this->authenticateAs($this->staff);

        $collegeType = UnitType::where('code', 'college')->first();
        $parent = OrganizationUnit::first();

        $response = $this->postJson('/api/v1/organization-units', [
            'name' => 'Unauthorized Unit',
            'code' => 'UNAUTH',
            'unit_type_id' => $collegeType->id,
            'parent_id' => $parent->id,
            'active' => true,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_admin_can_retrieve_and_filter_audit_events(): void
    {
        $this->authenticateAs($this->admin);

        AuditEvent::create([
            'actor_id' => $this->admin->id,
            'action' => 'security_audit_test',
            'target_type' => 'App\\Models\\User',
            'target_id' => (string) $this->admin->id,
            'request_id' => 'req-test-uuid-001',
            'occurred_at' => now(),
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->getJson('/api/v1/audit-events');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'action', 'target_type'],
                ],
                'meta' => ['current_page', 'last_page', 'total'],
            ]);

        // Filter by action
        $filterRes = $this->getJson('/api/v1/audit-events?action=security_audit_test');
        $filterRes->assertStatus(200);
        $this->assertCount(1, $filterRes->json('data'));

        // Filter by nonexistent action
        $emptyRes = $this->getJson('/api/v1/audit-events?action=nonexistent_action_xyz');
        $emptyRes->assertStatus(200);
        $this->assertCount(0, $emptyRes->json('data'));
    }

    public function test_staff_cannot_retrieve_audit_events(): void
    {
        $this->authenticateAs($this->staff);

        $response = $this->getJson('/api/v1/audit-events');
        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }
}
