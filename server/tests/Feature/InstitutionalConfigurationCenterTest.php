<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ArchiveCollection;
use App\Models\ArchiveEntry;
use App\Models\ConfidentialityLevel;
use App\Models\CorrespondenceType;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\LegalHold;
use App\Models\NumberingPolicy;
use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\Priority;
use App\Models\RetentionPolicy;
use App\Models\RetentionPolicyVersion;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowState;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Services\DocumentRepositoryService;
use App\Services\EthiopianDateService;
use App\Models\UserPermission;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InstitutionalConfigurationCenterTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $staffUser;
    protected OrganizationUnit $unitA;
    protected OrganizationUnit $unitB;
    protected DocumentCategory $category;
    protected ConfidentialityLevel $confidentiality;
    protected Priority $priority;
    protected CorrespondenceType $corrType;
    protected int $globalScopeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $this->adminUser = User::where('email', 'admin@wu.edu.et')->firstOrFail();
        $this->globalScopeId = (int) (DB::table('authorization_scopes')->where('kind', 'global')->value('id') ?? DB::table('authorization_scopes')->value('id'));

        $collegeType = \App\Models\UnitType::where('code', 'college')->first();
        $departmentType = \App\Models\UnitType::where('code', 'department')->first() ?? $collegeType;
        $parentCollege = OrganizationUnit::first();

        $this->unitA = OrganizationUnit::create([
            'parent_id' => $parentCollege?->id,
            'unit_type_id' => $departmentType->id,
            'code' => 'TEST-UNIT-A',
            'name' => 'Test Unit Alpha',
            'active' => 1,
        ]);

        $this->unitB = OrganizationUnit::create([
            'parent_id' => $parentCollege?->id,
            'unit_type_id' => $departmentType->id,
            'code' => 'TEST-UNIT-B',
            'name' => 'Test Unit Beta',
            'active' => 1,
        ]);

        $this->category = DocumentCategory::firstOrCreate(
            ['code' => 'CONFIG-CAT'],
            ['name' => 'Configured Category', 'active' => 1]
        );

        $this->confidentiality = ConfidentialityLevel::firstOrCreate(
            ['code' => 'CONF-STD'],
            ['name' => 'Standard Confidential', 'level' => 1, 'active' => 1]
        );

        $this->priority = Priority::firstOrCreate(
            ['code' => 'ROUTINE-CFG'],
            ['name' => 'Routine Config', 'rank' => 1, 'active' => 1]
        );

        $this->corrType = CorrespondenceType::firstOrCreate(
            ['code' => 'OFFICIAL-MEMO'],
            ['name' => 'Official Memo', 'active' => 1]
        );

        // Standard staff lacking system.configure
        $this->staffUser = User::create([
            'name' => 'Staff Officer',
            'email' => 'staff.officer@wu.edu.et',
            'password' => bcrypt('StaffPass123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
    }

    /**
     * Test 1: Authorization Boundary - Non-administrators are denied access.
     */
    public function test_non_admin_cannot_access_configuration_endpoints(): void
    {
        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->getJson('/api/v1/admin/workflows');
        $response->assertStatus(403);

        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->getJson('/api/v1/admin/numbering-policies');
        $response->assertStatus(403);

        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->getJson('/api/v1/admin/routing-policies');
        $response->assertStatus(403);

        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->postJson('/api/v1/admin/retention-policies', [
                'code' => 'HACK_RET',
                'name' => 'Unauthorized Policy',
                'trigger_code' => 'date_created',
                'duration_months' => 12,
                'disposition_action' => 'permanent_archive',
            ]);
        $response->assertStatus(403);
    }

    /**
     * Test 2: Workflow Definition, Stage Modeling, Graph Validation & Publishing.
     */
    public function test_workflow_definition_lifecycle_validation_and_publishing(): void
    {
        // 1. Create definition
        $defRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/admin/workflows', [
                'code' => 'ACADEMIC_PROMOTION',
                'name' => 'Academic Promotion Approval Workflow',
                'description' => 'Multi-stage faculty promotion approval process',
                'document_category_id' => $this->category->id,
                'active' => true,
            ]);

        $defRes->assertStatus(201);
        $defId = $defRes->json('data.definition.id');
        $verId = $defRes->json('data.version.id');

        // 2. Add Stages (Initial, Review, Terminal)
        $s1Res = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/workflows/{$defId}/versions/{$verId}/states", [
                'code' => 'SUBMITTED',
                'name' => 'Dossier Submitted',
                'is_initial' => true,
                'is_terminal' => false,
                'assigned_unit_id' => $this->unitA->id,
                'sla_hours' => 48,
            ]);
        $s1Res->assertStatus(201);
        $s1Id = $s1Res->json('data.id');

        $s2Res = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/workflows/{$defId}/versions/{$verId}/states", [
                'code' => 'COMMITTEE_REVIEW',
                'name' => 'Faculty Committee Review',
                'is_initial' => false,
                'is_terminal' => false,
                'assigned_unit_id' => $this->unitB->id,
                'sla_hours' => 72,
            ]);
        $s2Res->assertStatus(201);
        $s2Id = $s2Res->json('data.id');

        $s3Res = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/workflows/{$defId}/versions/{$verId}/states", [
                'code' => 'APPROVED',
                'name' => 'Senate Confirmed',
                'is_initial' => false,
                'is_terminal' => true,
            ]);
        $s3Res->assertStatus(201);
        $s3Id = $s3Res->json('data.id');

        // 3. Test Graph Validation: should fail before transitions are linked
        $valRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/workflows/{$defId}/versions/{$verId}/validate");
        $valRes->assertStatus(200);
        $this->assertFalse($valRes->json('data.valid'));

        // 4. Test Self-Loop transition refusal
        $loopRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/workflows/{$defId}/versions/{$verId}/transitions", [
                'from_state_id' => $s1Id,
                'to_state_id' => $s1Id,
                'action_code' => 'loop_myself',
            ]);
        $loopRes->assertStatus(422);

        // 5. Add valid transitions
        $t1 = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/workflows/{$defId}/versions/{$verId}/transitions", [
                'from_state_id' => $s1Id,
                'to_state_id' => $s2Id,
                'action_code' => 'forward_committee',
                'approval_policy' => 'single',
            ]);
        $t1->assertStatus(201);

        $t2 = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/workflows/{$defId}/versions/{$verId}/transitions", [
                'from_state_id' => $s2Id,
                'to_state_id' => $s3Id,
                'action_code' => 'senate_approve',
                'prohibit_self_approval' => true,
                'approval_policy' => 'majority',
            ]);
        $t2->assertStatus(201);

        // 6. Graph is now structurally valid: publish version 1
        $pubRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/workflows/{$defId}/versions/{$verId}/publish");
        $pubRes->assertStatus(200);
        $this->assertNotNull($pubRes->json('data.published_at'));

        // 7. Verify immutability: published version rejects new states
        $mutateRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/workflows/{$defId}/versions/{$verId}/states", [
                'code' => 'ILLEGAL_STATE',
                'name' => 'Illegal State Modification',
            ]);
        $mutateRes->assertStatus(422);
    }

    /**
     * Test 3: Workflow Versioning - Active documents continue on v1 while new documents adopt v2.
     */
    public function test_workflow_version_isolation_between_active_and_new_instances(): void
    {
        // Setup Definition with Version 1
        $def = WorkflowDefinition::create([
            'code' => 'CURRICULUM_REVIEW',
            'name' => 'Curriculum Review Workflow',
            'active' => true,
        ]);

        $v1 = WorkflowVersion::create([
            'definition_id' => $def->id,
            'version_number' => 1,
            'created_by' => $this->adminUser->id,
            'published_at' => now(),
        ]);

        $v1Initial = WorkflowState::create([
            'workflow_version_id' => $v1->id,
            'code' => 'DRAFT',
            'name' => 'Draft Curriculum',
            'is_initial' => true,
            'is_terminal' => false,
            'assigned_unit_id' => $this->unitA->id,
            'sla_hours' => 24,
        ]);

        $v1Terminal = WorkflowState::create([
            'workflow_version_id' => $v1->id,
            'code' => 'PASSED',
            'name' => 'Curriculum Passed',
            'is_initial' => false,
            'is_terminal' => true,
        ]);

        WorkflowTransition::create([
            'from_state_id' => $v1Initial->id,
            'to_state_id' => $v1Terminal->id,
            'action_code' => 'approve_curriculum',
            'permission_id' => Permission::where('code', 'workflow.review')->value('id') ?? 1,
        ]);

        // Create Doc 1 and start Workflow Instance 1 (under Version 1)
        $doc1 = Document::create([
            'reference_number' => 'DOC-V1-001',
            'title' => 'Biology Curriculum v1',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->unitA->id,
            'custodian_id' => $this->adminUser->id,
            'created_by' => $this->adminUser->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
        ]);
        $docVer1 = DocumentVersion::create([
            'document_id' => $doc1->id,
            'version_number' => 1,
            'created_by' => $this->adminUser->id,
            'created_at' => now(),
        ]);

        $engine = app(\App\Services\WorkflowEngineService::class);
        $instance1 = $engine->startInstance($def->id, $docVer1->id, null, $this->adminUser);
        $this->assertEquals($v1->id, $instance1->workflow_version_id);

        // Admin clones Version 1 into Version 2 and publishes Version 2
        $cloneRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/workflows/{$def->id}/versions", [
                'clone_from_version_id' => $v1->id,
            ]);
        $cloneRes->assertStatus(201);
        $v2Id = $cloneRes->json('data.id');

        $pubRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/workflows/{$def->id}/versions/{$v2Id}/publish");
        $pubRes->assertStatus(200);

        // Create Doc 2 and start Workflow Instance 2: it must adopt Version 2
        $doc2 = Document::create([
            'reference_number' => 'DOC-V2-002',
            'title' => 'Chemistry Curriculum v2',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->unitA->id,
            'custodian_id' => $this->adminUser->id,
            'created_by' => $this->adminUser->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
        ]);
        $docVer2 = DocumentVersion::create([
            'document_id' => $doc2->id,
            'version_number' => 1,
            'created_by' => $this->adminUser->id,
            'created_at' => now(),
        ]);

        $instance2 = $engine->startInstance($def->id, $docVer2->id, null, $this->adminUser);
        $this->assertEquals($v2Id, $instance2->workflow_version_id);

        // Instance 1 remains pinned to Version 1
        $this->assertEquals($v1->id, $instance1->fresh()->workflow_version_id);
    }

    /**
     * Test 4: Dynamic Reference Numbering Policies with Ethiopian Fiscal Year & Collision-Free Atomic Increment.
     */
    public function test_configurable_numbering_policy_generation(): void
    {
        // 1. Create a custom numbering policy
        $policyRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/admin/numbering-policies', [
                'code' => 'PRES',
                'name' => 'President Office Executive Orders',
                'prefix' => 'WU-PRES',
                'include_unit_code' => true,
                'include_category_code' => true,
                'year_format' => 'ethiopian_year',
                'sequence_length' => 4,
                'reset_policy' => 'annual',
                'active' => true,
            ]);

        $policyRes->assertStatus(201);
        $policyId = $policyRes->json('data.id');

        // 2. Test live preview endpoint without mutating sequence
        $prevRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/admin/numbering-policies/preview', [
                'prefix' => 'WU-PRES',
                'include_unit_code' => true,
                'include_category_code' => true,
                'year_format' => 'ethiopian_year',
                'sequence_length' => 4,
                'unit_id' => $this->unitA->id,
                'category_id' => $this->category->id,
                'sample_sequence' => 1,
            ]);
        $prevRes->assertStatus(200);

        $eth = EthiopianDateService::fromGregorian((int) date('Y'), (int) date('n'), (int) date('j'));
        $expectedEthYear = (string) $eth['year'];
        $expectedPreview = "WU-PRES/{$this->unitA->code}/{$this->category->code}/{$expectedEthYear}/0001";
        $this->assertEquals($expectedPreview, $prevRes->json('data.preview'));

        // 3. Test concrete generation in DocumentRepositoryService
        $service = app(DocumentRepositoryService::class);
        $num1 = $service->generateReferenceNumber('PRES', $this->unitA->id, $this->category->id);
        $num2 = $service->generateReferenceNumber('PRES', $this->unitA->id, $this->category->id);

        $this->assertEquals("WU-PRES/{$this->unitA->code}/{$this->category->code}/{$expectedEthYear}/0001", $num1);
        $this->assertEquals("WU-PRES/{$this->unitA->code}/{$this->category->code}/{$expectedEthYear}/0002", $num2);
    }

    /**
     * Test 5: Organizational Routing Policy Configuration & Dynamic SLA/Destination Matching.
     */
    public function test_routing_policy_configuration_and_correspondence_matching(): void
    {
        // 1. Create a routing policy: from Unit A to Unit B with 36hr SLA
        $routeRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/admin/routing-policies', [
                'code' => 'ALPHA_TO_BETA',
                'name' => 'Department Inter-Unit Expedited Route',
                'from_unit_id' => $this->unitA->id,
                'to_unit_id' => $this->unitB->id,
                'correspondence_type_id' => $this->corrType->id,
                'sla_hours' => 36,
                'active' => true,
            ]);
        $routeRes->assertStatus(201);

        // 2. Test matcher endpoint
        $matchRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/routing-policies/match', [
                'from_unit_id' => $this->unitA->id,
                'correspondence_type_id' => $this->corrType->id,
            ]);
        $matchRes->assertStatus(200);
        $this->assertTrue($matchRes->json('has_match'));
        $this->assertEquals($this->unitB->id, $matchRes->json('data.to_unit_id'));
        $this->assertEquals(36, $matchRes->json('data.sla_hours'));

        // 3. Register correspondence without explicit due date or first receiving unit
        $permPerm = Permission::firstOrCreate(['code' => 'correspondence.register'], ['name' => 'Register', 'category' => 'corr']);
        UserPermission::firstOrCreate(['user_id' => $this->adminUser->id, 'permission_id' => $permPerm->id, 'scope_id' => $this->globalScopeId], ['granted_by' => $this->adminUser->id, 'granted_at' => now()]);

        $corrRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/correspondences', [
                'title' => 'Inter-Office Dispatch for Alpha to Beta',
                'category_id' => $this->category->id,
                'confidentiality_id' => $this->confidentiality->id,
                'owner_unit_id' => $this->unitA->id,
                'type_id' => $this->corrType->id,
                'priority_id' => $this->priority->id,
                'purpose_code' => 'information',
                'correspondence_date' => now()->toDateString(),
            ]);

        $corrRes->assertStatus(201);
        $corrId = $corrRes->json('data.id');

        $corr = \App\Models\Correspondence::findOrFail($corrId);
        // Assert dynamic routing defaulted the receiving unit to Unit B and SLA to ~36 hours
        $this->assertEquals($this->unitB->id, $corr->first_receiving_unit_id);
        $this->assertNotNull($corr->requested_due_at);
        $this->assertTrue(now()->addHours(35)->lt($corr->requested_due_at));
        $this->assertTrue(now()->addHours(37)->gt($corr->requested_due_at));
    }

    /**
     * Test 6: Records Retention Schedule Configuration & Automatic Review Calculation.
     */
    public function test_retention_schedule_configuration_and_automatic_review_calculation(): void
    {
        // 1. Create a 48-month retention schedule
        $retRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/admin/retention-policies', [
                'code' => 'FIN_AUDIT_4Y',
                'name' => 'Financial Audit 4-Year Retention Schedule',
                'document_category_id' => $this->category->id,
                'trigger_code' => 'date_archived',
                'duration_months' => 48,
                'disposition_action' => 'review_before_disposal',
                'active' => true,
            ]);
        $retRes->assertStatus(201);
        $policyId = $retRes->json('data.policy.id');
        $verId = $retRes->json('data.version.id');

        // 2. Setup Archive Collection and Document
        $collection = ArchiveCollection::firstOrCreate(
            ['code' => 'CENTRAL_ARCHIVE'],
            ['name' => 'Wollo Central Archive', 'custodian_unit_id' => $this->unitA->id, 'active' => 1]
        );

        $doc = Document::create([
            'reference_number' => 'DOC-ARCH-TEST',
            'title' => 'Annual Financial Audit Report 2026',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->unitA->id,
            'custodian_id' => $this->adminUser->id,
            'created_by' => $this->adminUser->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $archivePerm = Permission::firstOrCreate(['code' => 'archive.transfer'], ['name' => 'Archive', 'category' => 'archive']);
        UserPermission::firstOrCreate(['user_id' => $this->adminUser->id, 'permission_id' => $archivePerm->id, 'scope_id' => $this->globalScopeId], ['granted_by' => $this->adminUser->id, 'granted_at' => now()]);

        // 3. Transfer to archive WITHOUT passing next_review_at
        $transRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/archive/transfer', [
                'document_id' => $doc->id,
                'collection_id' => $collection->id,
                'retention_policy_version_id' => $verId,
            ]);

        $transRes->assertStatus(201);
        $entryId = $transRes->json('data.id');

        $entry = ArchiveEntry::findOrFail($entryId);
        $this->assertNotNull($entry->next_review_at);

        // Verify next_review_at is ~48 months into the future
        $expectedDate = now()->addMonths(48);
        $this->assertEquals($expectedDate->format('Y-m'), $entry->next_review_at->format('Y-m'));

        // 4. Test Legal Hold Protection prevents disposal
        $holdRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/archive/legal-holds', [
                'code' => 'LH-AUDIT-001',
                'reason' => 'Mandatory federal review',
                'document_ids' => [$doc->id],
            ]);
        $holdRes->assertStatus(201);

        $dispPerm = Permission::firstOrCreate(['code' => 'archive.request_disposal'], ['name' => 'Disposal', 'category' => 'archive']);
        UserPermission::firstOrCreate(['user_id' => $this->adminUser->id, 'permission_id' => $dispPerm->id, 'scope_id' => $this->globalScopeId], ['granted_by' => $this->adminUser->id, 'granted_at' => now()]);

        $dispRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/archive/entries/{$entry->id}/request-disposal", [
                'requested_action' => 'destroy_digital',
                'justification' => 'Disposal requested at end of retention cycle',
            ]);

        $dispRes->assertStatus(422);
        $this->assertStringContainsString('legal hold', strtolower($dispRes->json('message')));
    }
}
