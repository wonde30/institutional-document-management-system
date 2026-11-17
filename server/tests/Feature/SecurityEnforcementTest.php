<?php

namespace Tests\Feature;

use App\Models\AuthorizationScope;
use App\Models\ConfidentialityLevel;
use App\Models\CorrespondenceType;
use App\Models\DocumentCategory;
use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\Priority;
use App\Models\UnitType;
use App\Models\User;
use App\Models\UserPermission;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $staff;

    protected OrganizationUnit $unitA;

    protected OrganizationUnit $unitB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $collegeType = UnitType::where('code', 'college')->first();
        $this->unitA = OrganizationUnit::first();
        $this->unitB = OrganizationUnit::create([
            'parent_id' => $this->unitA->id,
            'unit_type_id' => $collegeType->id,
            'code' => 'UNIT_SEC_B',
            'name' => 'Secured College B',
            'active' => true,
        ]);

        $this->superAdmin = User::where('email', 'admin@wu.edu.et')->first();

        $this->staff = User::create([
            'name' => 'General Staff Member',
            'email' => 'staff.sec@wu.edu.et',
            'password' => bcrypt('SecretPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->staff, 'staff');
    }

    public function test_deactivated_account_is_blocked_with_403(): void
    {
        $this->authenticateAs($this->staff);

        // Active account succeeds
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(200);

        // Deactivate account. Do NOT call forgetGuards() here: the guard
        // instance already holds the authenticated user, so the next request
        // reaches ValidateAuthorizationVersion which checks the user's status
        // and returns 403 Forbidden before serving any data.
        // (Calling forgetGuards() would drop the guard instance so Sanctum
        // cannot re-resolve the user from the array-driver session, yielding
        // 401 instead — a correct block but a weaker verification.)
        $this->staff->update(['status' => 'inactive']);

        // Subsequent request must be rejected with 403 by the account-status middleware.
        $response = $this->getJson('/api/v1/auth/me');
        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_unauthenticated_request_is_blocked_with_401(): void
    {
        $response = $this->withHeader('Accept', 'application/json')
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    public function test_expired_direct_permission_fails_closed(): void
    {
        $restrictedUser = User::create([
            'name' => 'Temporary Grant User',
            'email' => 'temp.grant@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        // Grant permission expired 5 minutes ago
        $scope = AuthorizationScope::firstOrCreate(['kind' => 'global', 'unit_id' => null]);
        $perm = Permission::where('code', 'documents.create')->first();

        UserPermission::create([
            'user_id' => $restrictedUser->id,
            'permission_id' => $perm->id,
            'scope_id' => $scope->id,
            'granted_by' => $this->superAdmin->id,
            'granted_at' => now()->subDay(),
            'expires_at' => now()->subMinutes(5),
        ]);

        $this->authenticateAs($restrictedUser);

        // Server check rejects because grant is expired
        $this->assertFalse($restrictedUser->fresh()->hasPermission('documents.create'));

        $response = $this->postJson('/api/v1/documents', [
            'title' => 'Expired Grant Registration Attempt',
            'category_id' => DocumentCategory::first()->id,
            'confidentiality_id' => ConfidentialityLevel::first()->id,
            'owner_unit_id' => $this->unitA->id,
            'document_date' => '2026-10-29',
        ]);

        $response->assertStatus(403);
    }

    public function test_unauthorized_user_cannot_access_audit_ledger_or_operations(): void
    {
        $this->authenticateAs($this->staff);

        // Audit events require audit.view
        $this->getJson('/api/v1/audit-events')
            ->assertStatus(403);

        // System operations require system.configure
        $this->getJson('/api/v1/operations/status')
            ->assertStatus(403);
    }

    public function test_cross_unit_boundary_enforcement(): void
    {
        $scopedOfficer = User::create([
            'name' => 'Unit A Officer',
            'email' => 'officer.a@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        // Grant records_officer strictly in Unit A
        $this->assignRoleToUser($scopedOfficer, 'records_officer', 'unit', $this->unitA->id);

        $this->authenticateAs($scopedOfficer);

        // Permitted to register in Unit A
        $this->assertTrue($scopedOfficer->hasPermission('correspondence.register', $this->unitA->id));

        // Denied from registering in Unit B
        $this->assertFalse($scopedOfficer->hasPermission('correspondence.register', $this->unitB->id));

        $crossUnitAttempt = $this->withHeader('X-Organization-Unit-Id', (string) $this->unitB->id)
            ->postJson('/api/v1/correspondences', [
                'title' => 'Cross Unit Rogue Correspondence',
                'category_id' => DocumentCategory::first()->id,
                'confidentiality_id' => ConfidentialityLevel::first()->id,
                'owner_unit_id' => $this->unitB->id,
                'type_id' => CorrespondenceType::first()->id,
                'priority_id' => Priority::first()->id,
                'purpose_code' => 'rogue_entry',
                'correspondence_date' => '2026-10-29',
            ]);

        $crossUnitAttempt->assertStatus(403);
    }
}
