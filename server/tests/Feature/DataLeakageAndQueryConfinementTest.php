<?php

namespace Tests\Feature;

use App\Models\AuthorizationScope;
use App\Models\ConfidentialityLevel;
use App\Models\Correspondence;
use App\Models\CorrespondenceType;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\Priority;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DataLeakageAndQueryConfinementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $staffUserA;
    protected User $staffUserB;
    protected OrganizationUnit $unitA;
    protected OrganizationUnit $unitB;
    protected DocumentCategory $category;
    protected ConfidentialityLevel $internalLevel;
    protected ConfidentialityLevel $secretLevel;
    protected Priority $standardPriority;
    protected CorrespondenceType $generalType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $this->adminUser = User::where('email', 'admin@wu.edu.et')->first();

        $collegeType = UnitType::where('code', 'college')->first();
        $departmentType = UnitType::where('code', 'department')->first() ?? $collegeType;
        $parentCollege = OrganizationUnit::first();

        $this->unitA = OrganizationUnit::create([
            'parent_id' => $parentCollege->id,
            'unit_type_id' => $departmentType->id,
            'code' => 'UNIT_A',
            'name' => 'Department A',
            'active' => true,
        ]);

        $this->unitB = OrganizationUnit::create([
            'parent_id' => $parentCollege->id,
            'unit_type_id' => $departmentType->id,
            'code' => 'UNIT_B',
            'name' => 'Department B',
            'active' => true,
        ]);

        $this->staffUserA = User::create([
            'name' => 'Staff Unit A',
            'email' => 'staff.a@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        $this->staffUserB = User::create([
            'name' => 'Staff Unit B',
            'email' => 'staff.b@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        // Create unit scopes
        $scopeA = AuthorizationScope::firstOrCreate(['kind' => 'unit', 'unit_id' => $this->unitA->id]);
        $scopeB = AuthorizationScope::firstOrCreate(['kind' => 'unit', 'unit_id' => $this->unitB->id]);

        // Grant documents.view, correspondence.view, and reports.view in Unit A to Staff A
        $viewPerm = Permission::where('code', 'documents.view')->first();
        $corrViewPerm = Permission::where('code', 'correspondence.view')->first();
        $reportViewPerm = Permission::where('code', 'reports.view')->first();

        DB::table('user_permissions')->insert([
            ['user_id' => $this->staffUserA->id, 'permission_id' => $viewPerm->id, 'scope_id' => $scopeA->id, 'granted_by' => $this->adminUser->id, 'granted_at' => now()],
            ['user_id' => $this->staffUserA->id, 'permission_id' => $corrViewPerm->id, 'scope_id' => $scopeA->id, 'granted_by' => $this->adminUser->id, 'granted_at' => now()],
            ['user_id' => $this->staffUserA->id, 'permission_id' => $reportViewPerm->id, 'scope_id' => $scopeA->id, 'granted_by' => $this->adminUser->id, 'granted_at' => now()],
        ]);

        $this->category = DocumentCategory::first();
        $this->internalLevel = ConfidentialityLevel::where('code', 'internal')->first();
        $this->secretLevel = ConfidentialityLevel::where('code', 'secret')->first()
            ?? ConfidentialityLevel::where('code', 'confidential')->first();
        $this->standardPriority = Priority::first();
        $this->generalType = CorrespondenceType::first();
    }

    public function test_query_scope_prevents_cross_unit_document_leakage(): void
    {
        // Create document in Unit A
        $docA = Document::create([
            'reference_number' => 'WU/DOC/2026/000101',
            'title' => 'Unit A Operational Plan',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->internalLevel->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->adminUser->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        // Create document in Unit B
        $docB = Document::create([
            'reference_number' => 'WU/DOC/2026/000102',
            'title' => 'Unit B Financial Strategy',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->internalLevel->id,
            'owner_unit_id' => $this->unitB->id,
            'created_by' => $this->adminUser->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        // User A listing documents
        $response = $this->actingAs($this->staffUserA)
            ->getJson('/api/v1/documents');

        $response->assertOk();
        $returnedTitles = collect($response->json('data'))->pluck('title')->all();

        $this->assertContains('Unit A Operational Plan', $returnedTitles);
        $this->assertNotContains('Unit B Financial Strategy', $returnedTitles);

        // Attempting to filter by unauthorized Unit B returns 403 Forbidden
        $unauthFilter = $this->actingAs($this->staffUserA)
            ->getJson("/api/v1/documents?owner_unit_id={$this->unitB->id}");

        $unauthFilter->assertStatus(403);
    }

    public function test_high_confidentiality_documents_are_filtered_without_sensitive_clearance(): void
    {
        // Internal doc in Unit A
        Document::create([
            'reference_number' => 'WU/DOC/2026/000201',
            'title' => 'Unit A General Notice',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->internalLevel->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->adminUser->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        // Secret doc in Unit A
        Document::create([
            'reference_number' => 'WU/DOC/2026/000202',
            'title' => 'Unit A Secret Personnel Investigation',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->secretLevel->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->adminUser->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        // Staff A has view permission for Unit A, but lacks staff.view_sensitive
        $res = $this->actingAs($this->staffUserA)->getJson('/api/v1/documents');
        $res->assertOk();
        $titles = collect($res->json('data'))->pluck('title')->all();

        $this->assertContains('Unit A General Notice', $titles);
        $this->assertNotContains('Unit A Secret Personnel Investigation', $titles);

        // Now grant staff.view_sensitive in Unit A to Staff A
        $sensitivePerm = Permission::where('code', 'staff.view_sensitive')->first();
        $scopeA = AuthorizationScope::where('kind', 'unit')->where('unit_id', $this->unitA->id)->first();

        DB::table('user_permissions')->insert([
            'user_id' => $this->staffUserA->id,
            'permission_id' => $sensitivePerm->id,
            'scope_id' => $scopeA->id,
            'granted_by' => $this->adminUser->id,
            'granted_at' => now(),
        ]);
        $this->staffUserA->refresh();

        $resElevated = $this->actingAs($this->staffUserA)->getJson('/api/v1/documents');
        $resElevated->assertOk();
        $elevatedTitles = collect($resElevated->json('data'))->pluck('title')->all();

        $this->assertContains('Unit A Secret Personnel Investigation', $elevatedTitles);
    }

    public function test_global_search_and_reports_respect_confinement(): void
    {
        // Create doc in Unit B
        $docB = Document::create([
            'reference_number' => 'WU/DOC/2026/000301',
            'title' => 'Confidential Tender Strategy',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->internalLevel->id,
            'owner_unit_id' => $this->unitB->id,
            'created_by' => $this->adminUser->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        // Search for Tender by Staff A should return 0 results
        $searchRes = $this->actingAs($this->staffUserA)->getJson('/api/v1/search/documents?q=Tender');
        $searchRes->assertOk();
        $searchResults = collect($searchRes->json('data'))->pluck('title')->all();
        $this->assertNotContains('Confidential Tender Strategy', $searchResults);

        // Report summary by Staff A reflects only their permitted scope
        $reportRes = $this->actingAs($this->staffUserA)->getJson('/api/v1/reports/summary');
        $reportRes->assertOk();
        $this->assertIsInt($reportRes->json('data.totals.documents'));
    }

    public function test_last_administrator_lockout_safeguards(): void
    {
        // Confirm adminUser is currently the only active administrator
        $activeAdminCount = User::countActiveSystemAdministrators();
        $this->assertGreaterThanOrEqual(1, $activeAdminCount);

        // Attempting to suspend the only active admin via PATCH /users/{id}/status returns 422
        // We use another user to try to deactivate the admin
        $attemptDeactivate = $this->actingAs($this->adminUser)->patchJson("/api/v1/users/{$this->adminUser->id}/status", [
            'status' => 'inactive',
        ]);
        $attemptDeactivate->assertStatus(422);

        // Attempting to revoke admin role when it's the last admin
        $superAdminRole = Role::where('code', 'super_admin')->first();
        $globalScope = AuthorizationScope::firstOrCreate(['kind' => 'global', 'unit_id' => null]);

        $attemptRevoke = $this->actingAs($this->adminUser)->deleteJson('/api/v1/user-roles', [
            'user_id' => $this->adminUser->id,
            'role_id' => $superAdminRole->id,
            'scope_id' => $globalScope->id,
        ]);
        $attemptRevoke->assertStatus(422);
        $attemptRevoke->assertJsonFragment([
            'message' => 'Security restriction: Cannot revoke administrative capability from the last active System Administrator in the institution.',
        ]);
    }

    public function test_cli_emergency_recovery_attaches_global_scope_and_activates_account(): void
    {
        $lockedAdmin = User::create([
            'name' => 'Emergency Officer',
            'email' => 'emergency@wu.edu.et',
            'password' => bcrypt('InitialPass123!'),
            'status' => 'locked',
            'authz_version' => 1,
        ]);

        // Execute recovery command via CLI
        $exitCode = Artisan::call('user:make-super-admin', [
            'email' => 'emergency@wu.edu.et',
            '--force' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        $lockedAdmin->refresh();
        $this->assertEquals('active', $lockedAdmin->status);
        $this->assertTrue($lockedAdmin->hasPermission('system.configure'));

        // Verify global scope was attached
        $globalScope = AuthorizationScope::where('kind', 'global')->first();
        $this->assertNotNull($globalScope);

        $hasGlobalPivot = DB::table('user_roles')
            ->where('user_id', $lockedAdmin->id)
            ->where('scope_id', $globalScope->id)
            ->exists();

        $this->assertTrue($hasGlobalPivot);
    }

    public function test_reference_vocabularies_management_and_permission_protection(): void
    {
        // 1. Non-admin cannot create vocabulary items (403)
        $unauthRes = $this->actingAs($this->staffUserA)->postJson('/api/v1/metadata/categories', [
            'code' => 'new_academic_cat',
            'name' => 'New Academic Category',
        ]);
        $unauthRes->assertStatus(403);

        // 2. Admin creates vocabulary item
        $createRes = $this->actingAs($this->adminUser)->postJson('/api/v1/metadata/categories', [
            'code' => 'new_academic_cat',
            'name' => 'New Academic Category',
            'description' => 'Created during institutional review',
            'active' => true,
        ]);
        $createRes->assertStatus(201);
        $newId = $createRes->json('data.id');

        // 3. Admin updates vocabulary item
        $updateRes = $this->actingAs($this->adminUser)->putJson("/api/v1/metadata/categories/{$newId}", [
            'name' => 'Updated Academic Category',
            'active' => false,
        ]);
        $updateRes->assertOk();
        $this->assertEquals('Updated Academic Category', $updateRes->json('data.name'));
        $this->assertFalse((bool) $updateRes->json('data.active'));

        // 4. GET /metadata/all?include_inactive=1 shows the deactivated item
        $allRes = $this->actingAs($this->adminUser)->getJson('/api/v1/metadata/all?include_inactive=1');
        $allRes->assertOk();
        $allCats = collect($allRes->json('data.document_categories'))->pluck('code')->all();
        $this->assertContains('new_academic_cat', $allCats);

        // 5. Default GET /metadata/all hides deactivated items
        $activeOnlyRes = $this->actingAs($this->adminUser)->getJson('/api/v1/metadata/all');
        $activeOnlyRes->assertOk();
        $activeCats = collect($activeOnlyRes->json('data.document_categories'))->pluck('code')->all();
        $this->assertNotContains('new_academic_cat', $activeCats);
    }
}
