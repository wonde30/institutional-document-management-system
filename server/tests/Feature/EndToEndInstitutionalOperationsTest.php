<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ArchiveCollection;
use App\Models\ArchiveEntry;
use App\Models\ConfidentialityLevel;
use App\Models\Correspondence;
use App\Models\CorrespondenceType;
use App\Models\DispatchMethod;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentMovement;
use App\Models\DocumentVersion;
use App\Models\LegalHold;
use App\Models\OfficeProcedureVersion;
use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\Priority;
use App\Models\RetentionPolicy;
use App\Models\RetentionPolicyVersion;
use App\Models\Role;
use App\Models\OrganizationMembership;
use App\Models\PrimaryMembership;
use App\Models\UnitType;
use App\Models\User;
use App\Models\UserPermission;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowState;
use App\Models\WorkflowTask;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EndToEndInstitutionalOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $staffUser;
    protected User $reviewerUser;
    protected OrganizationUnit $originUnit;
    protected OrganizationUnit $targetUnit;
    protected DocumentCategory $category;
    protected ConfidentialityLevel $confidentiality;
    protected Priority $priority;
    protected CorrespondenceType $incomingType;
    protected CorrespondenceType $outgoingType;
    protected DispatchMethod $dispatchMethod;
    protected ArchiveCollection $collection;
    protected Role $reviewerRole;
    protected int $globalScopeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $this->adminUser = User::where('email', 'admin@wu.edu.et')->firstOrFail();
        $this->globalScopeId = (int) (DB::table('authorization_scopes')->where('kind', 'global')->value('id') ?? DB::table('authorization_scopes')->value('id'));

        $deptType = UnitType::where('code', 'department')->first() ?? UnitType::first();

        $this->originUnit = OrganizationUnit::create([
            'parent_id' => null,
            'unit_type_id' => $deptType->id,
            'code' => 'REGISTRAR',
            'name' => 'Office of the Registrar',
            'active' => 1,
        ]);

        $this->targetUnit = OrganizationUnit::create([
            'parent_id' => null,
            'unit_type_id' => $deptType->id,
            'code' => 'ACAD-AFFAIRS',
            'name' => 'Academic Affairs Directorate',
            'active' => 1,
        ]);

        $this->category = DocumentCategory::firstOrCreate(
            ['code' => 'ACAD-POLICY'],
            ['name' => 'Academic Policy Document', 'active' => 1]
        );

        $this->confidentiality = ConfidentialityLevel::firstOrCreate(
            ['code' => 'CONF-STD'],
            ['name' => 'Standard', 'level' => 1, 'active' => 1]
        );

        $this->priority = Priority::firstOrCreate(
            ['code' => 'ROUTINE'],
            ['name' => 'Routine Priority', 'rank' => 1, 'active' => 1]
        );

        $this->incomingType = CorrespondenceType::where('code', 'incoming')->first() ?? CorrespondenceType::create([
            'code' => 'incoming',
            'name' => 'Incoming Letter',
            'active' => 1,
        ]);

        $this->outgoingType = CorrespondenceType::where('code', 'outgoing')->first() ?? CorrespondenceType::create([
            'code' => 'outgoing',
            'name' => 'Outgoing Letter',
            'active' => 1,
        ]);

        $this->dispatchMethod = DispatchMethod::firstOrCreate(
            ['code' => 'COURIER'],
            ['name' => 'Official University Courier', 'active' => 1]
        );

        $this->collection = ArchiveCollection::create([
            'code' => 'COL-SENATE',
            'name' => 'University Senate Records Collection',
        ]);

        $this->reviewerRole = Role::firstOrCreate(
            ['code' => 'lead_reviewer'],
            ['name' => 'Lead Institutional Reviewer', 'scope' => 'system']
        );

        $this->staffUser = User::create([
            'name' => 'Staff Registrar',
            'email' => 'staff.registrar@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        $this->reviewerUser = User::create([
            'name' => 'Lead Reviewer Academic',
            'email' => 'reviewer.academic@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        $m1 = OrganizationMembership::create([
            'unit_id' => $this->originUnit->id,
            'user_id' => $this->staffUser->id,
            'position_title' => 'Registrar Officer',
            'starts_at' => now(),
            'created_by' => $this->adminUser->id,
            'created_at' => now(),
        ]);

        PrimaryMembership::create([
            'user_id' => $this->staffUser->id,
            'membership_id' => $m1->id,
            'selected_at' => now(),
        ]);

        $m2 = OrganizationMembership::create([
            'unit_id' => $this->targetUnit->id,
            'user_id' => $this->reviewerUser->id,
            'position_title' => 'Lead Reviewer',
            'starts_at' => now(),
            'created_by' => $this->adminUser->id,
            'created_at' => now(),
        ]);

        PrimaryMembership::create([
            'user_id' => $this->reviewerUser->id,
            'membership_id' => $m2->id,
            'selected_at' => now(),
        ]);

        // Grant required operational permissions
        $permCodes = [
            'documents.create',
            'documents.view',
            'documents.update',
            'correspondence.register',
            'correspondence.view',
            'correspondence.forward',
            'correspondence.acknowledge',
            'correspondence.assign',
            'correspondence.dispatch',
            'workflow.view',
            'workflow.review',
            'archive.view',
            'archive.transfer',
            'archive.manage',
            'archive.request_disposal',
            'archive.approve_disposal',
            'archive.execute_disposal',
        ];

        foreach ($permCodes as $code) {
            $perm = Permission::where('code', $code)->first();
            if ($perm) {
                UserPermission::firstOrCreate(
                    [
                        'user_id' => $this->staffUser->id,
                        'permission_id' => $perm->id,
                        'scope_id' => $this->globalScopeId,
                    ],
                    [
                        'granted_by' => $this->adminUser->id,
                        'granted_at' => now(),
                    ]
                );
                UserPermission::firstOrCreate(
                    [
                        'user_id' => $this->reviewerUser->id,
                        'permission_id' => $perm->id,
                        'scope_id' => $this->globalScopeId,
                    ],
                    [
                        'granted_by' => $this->adminUser->id,
                        'granted_at' => now(),
                    ]
                );
            }
        }
    }

    /**
     * Test 1: Complete Document-to-Workflow Execution
     * Automatic workflow resolution upon registration, task assignment, transition advancement, consensus & completion.
     */
    public function test_document_to_workflow_execution_lifecycle(): void
    {
        // 1. Super Admin builds and publishes a 2-stage approval workflow mapped to ACAD-POLICY category
        $def = WorkflowDefinition::create([
            'code' => 'ACAD_APPROVAL_WF',
            'name' => 'Academic Policy Approval Workflow',
            'document_category_id' => $this->category->id,
            'active' => 1,
        ]);

        $ver = WorkflowVersion::create([
            'definition_id' => $def->id,
            'version_number' => 1,
            'created_by' => $this->adminUser->id,
            'published_at' => now(),
            'published_by' => $this->adminUser->id,
        ]);

        $reviewPermId = Permission::where('code', 'workflow.review')->value('id') ?? 1;

        $state1 = WorkflowState::create([
            'workflow_version_id' => $ver->id,
            'code' => 'INITIAL_REVIEW',
            'name' => 'Initial Academic Review',
            'is_initial' => 1,
            'is_terminal' => 0,
            'assigned_unit_id' => $this->targetUnit->id,
            'assigned_role_id' => $this->reviewerRole->id,
            'sla_hours' => 48,
        ]);

        $state2 = WorkflowState::create([
            'workflow_version_id' => $ver->id,
            'code' => 'FINAL_APPROVED',
            'name' => 'Final Approved',
            'is_initial' => 0,
            'is_terminal' => 1,
        ]);

        WorkflowTransition::create([
            'from_state_id' => $state1->id,
            'to_state_id' => $state2->id,
            'action_code' => 'approve',
            'permission_id' => $reviewPermId,
            'approval_policy' => 'single',
        ]);

        // 2. Staff registers document in ACAD-POLICY category
        $createRes = $this->actingAs($this->staffUser, 'sanctum')
            ->postJson('/api/v1/documents', [
                'title' => 'Undergraduate Academic Policy Reform 2026',
                'summary' => 'Comprehensive reform syllabus and credit framework',
                'category_id' => $this->category->id,
                'confidentiality_id' => $this->confidentiality->id,
                'owner_unit_id' => $this->originUnit->id,
                'document_date' => now()->toDateString(),
            ]);

        $createRes->assertStatus(201);
        $docData = $createRes->json('data');
        $docId = $docData['id'];

        // Verify document reference number minted
        $this->assertNotEmpty($docData['reference_number']);

        // Verify workflow instance automatically initiated
        $workflowInstances = $docData['workflow_instances'] ?? [];
        $this->assertNotEmpty($workflowInstances, 'Expected workflow instance to be auto-instantiated for category');
        $initialInstance = $workflowInstances[0];
        $this->assertEquals($def->id, $initialInstance['workflow_definition']['id']);

        // Verify initial task assigned to targetUnit and reviewerRole
        $tasks = $initialInstance['tasks'] ?? [];
        $this->assertNotEmpty($tasks);
        $initialTask = $tasks[0];
        $this->assertEquals('INITIAL_REVIEW', $initialTask['state']['code']);
        $this->assertEquals($this->targetUnit->id, $initialTask['assigned_unit']['id']);
        $this->assertNull($initialTask['closed_at']);

        // 3. Reviewer executes decision: Approve
        $taskId = $initialTask['id'];
        $decideRes = $this->actingAs($this->reviewerUser, 'sanctum')
            ->postJson("/api/v1/workflows/tasks/{$taskId}/decide", [
                'decision' => 'approve',
                'reason' => 'Approved following academic senate standards review.',
            ]);

        $decideRes->assertStatus(200);
        $decideData = $decideRes->json('data');
        $this->assertEquals('completed', $decideData['status']);
        $this->assertTrue($decideData['task_closed']);

        // Verify task closed in database
        $this->assertNotNull(WorkflowTask::find($taskId)->closed_at);

        // Verify terminal state event logged in audit ledger
        $this->assertDatabaseHas('audit_events', [
            'action' => 'workflow.task_decided',
            'target_id' => (string) $taskId,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'workflow.terminal_state_reached',
            'target_id' => (string) $initialInstance['id'],
        ]);
    }

    /**
     * Test 2: Correspondence Routing Policy & Complete Dispatch Operations
     */
    public function test_correspondence_routing_movement_acknowledgment_and_dispatch(): void
    {
        // 1. Create active routing policy: Incoming letters in ACAD-POLICY route to Academic Affairs (targetUnit)
        \App\Models\RoutingPolicy::create([
            'code' => 'INCOMING_ACAD_ROUTE',
            'name' => 'Route Incoming Academic Letters',
            'correspondence_type_id' => $this->incomingType->id,
            'document_category_id' => $this->category->id,
            'priority_id' => $this->priority->id,
            'to_unit_id' => $this->targetUnit->id,
            'default_assignee_role_id' => $this->reviewerRole->id,
            'sla_hours' => 24,
            'active' => 1,
        ]);

        // 2. Register incoming correspondence without specifying receiving unit
        $corrRes = $this->actingAs($this->staffUser, 'sanctum')
            ->postJson('/api/v1/correspondences', [
                'title' => 'Ministry Directive on Modular Curricula',
                'summary' => 'National implementation guidelines for modular course offering',
                'type_id' => $this->incomingType->id,
                'category_id' => $this->category->id,
                'confidentiality_id' => $this->confidentiality->id,
                'owner_unit_id' => $this->originUnit->id,
                'priority_id' => $this->priority->id,
                'purpose_code' => 'for_action',
                'correspondence_date' => now()->toDateString(),
                'sender_external_name' => 'Ministry of Education Ethiopia',
            ]);

        $corrRes->assertStatus(201);
        $corrData = $corrRes->json('data');
        $corrId = $corrData['id'];

        // Verify matched routing policy resolved target unit and SLA
        $this->assertEquals($this->targetUnit->id, $corrData['first_receiving_unit_id']);
        $this->assertNotNull($corrData['requested_due_at']);

        // Verify automatic document movement recorded
        $movement = DocumentMovement::where('document_id', $corrData['document_id'])->first();
        $this->assertNotNull($movement, 'Expected initial routing movement to be recorded automatically');
        $this->assertEquals($this->targetUnit->id, $movement->to_unit_id);

        // 3. Recipient unit acknowledges receipt of movement
        $ackRes = $this->actingAs($this->reviewerUser, 'sanctum')
            ->postJson("/api/v1/correspondences/{$corrId}/movements/{$movement->id}/acknowledge");

        $ackRes->assertStatus(200);
        $ackRes->assertJsonPath('success', true);

        // Verify correspondence received_at updated upon arrival
        $freshCorr = Correspondence::find($corrId);
        $this->assertNotNull($freshCorr->received_at);

        // 4. Close correspondence item
        $closeRes = $this->actingAs($this->staffUser, 'sanctum')
            ->postJson("/api/v1/correspondences/{$corrId}/close", [
                'reason' => 'All actionable directives implemented and filed.',
            ]);

        $closeRes->assertStatus(200);
        $closeRes->assertJsonPath('success', true);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'correspondence.closed',
            'target_id' => (string) $corrId,
        ]);
    }

    /**
     * Test 3: Records Retention Lifecycle, Reviews & Legal Hold Protection
     */
    public function test_retention_lifecycle_operational_review_and_legal_hold_defense(): void
    {
        // 1. Create document and retention policy with 12 months duration
        $policy = RetentionPolicy::create([
            'code' => 'FIN_AUDIT_1YR',
            'name' => 'Financial Audit One Year Retention',
            'active' => 1,
        ]);

        $policyVer = RetentionPolicyVersion::create([
            'policy_id' => $policy->id,
            'version_number' => 1,
            'trigger_code' => 'date_created',
            'duration_months' => 12,
            'disposition_action' => 'review',
            'effective_from' => now()->toDateString(),
        ]);

        $docRes = $this->actingAs($this->staffUser, 'sanctum')
            ->postJson('/api/v1/documents', [
                'title' => 'Annual Fiscal Audit Report 2025/2026',
                'summary' => 'Comprehensive financial statement and compliance audit',
                'category_id' => $this->category->id,
                'confidentiality_id' => $this->confidentiality->id,
                'owner_unit_id' => $this->originUnit->id,
                'document_date' => now()->toDateString(),
            ]);

        $docId = $docRes->json('data.id');

        // 2. Transfer document to archive
        $transferRes = $this->actingAs($this->staffUser, 'sanctum')
            ->postJson('/api/v1/archive/transfer', [
                'document_id' => $docId,
                'collection_id' => $this->collection->id,
                'retention_policy_version_id' => $policyVer->id,
            ]);

        $transferRes->assertStatus(201);
        $entryId = $transferRes->json('data.id');

        // Verify next_review_at automatically set 12 months ahead
        $entry = ArchiveEntry::find($entryId);
        $this->assertNotNull($entry->next_review_at);
        $this->assertTrue($entry->next_review_at > now()->addMonths(11));

        // Verify document lifecycle transitioned to 'retired'
        $this->assertEquals('retired', Document::find($docId)->lifecycle);

        // 3. Fast-forward review date to test expired review queue
        $entry->update(['next_review_at' => now()->subDay()]);

        $queueRes = $this->actingAs($this->staffUser, 'sanctum')
            ->getJson('/api/v1/archive/entries?queue=expired_review');
        $queueRes->assertStatus(200);
        $this->assertNotEmpty($queueRes->json('data'));

        // 4. Place a statutory legal hold on this document
        $holdRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/archive/legal-holds', [
                'code' => 'HOLD-FED-AUDIT-01',
                'reason' => 'Federal Auditor General inquiry into capital expenditure',
                'document_ids' => [$docId],
            ]);
        $holdRes->assertStatus(201);
        $holdId = $holdRes->json('data.id');

        // 5. Attempt disposal request under active hold -> Strict rejection
        $disposalFailRes = $this->actingAs($this->staffUser, 'sanctum')
            ->postJson("/api/v1/archive/entries/{$entryId}/request-disposal", [
                'requested_action' => 'destroy_digital',
                'justification' => 'Retention period has expired, requesting routine purge.',
            ]);

        $disposalFailRes->assertStatus(422);
        $this->assertTrue(str_contains(strtolower($disposalFailRes->json('message')), 'legal hold'));

        // 6. Perform retention review: Extend retention by 6 months
        $reviewRes = $this->actingAs($this->staffUser, 'sanctum')
            ->postJson("/api/v1/archive/entries/{$entryId}/review", [
                'review_action' => 'extend',
                'months_extended' => 6,
                'notes' => 'Extended due to pending federal audit inquiry and statutory hold.',
            ]);

        $reviewRes->assertStatus(200);
        $this->assertNotNull(ArchiveEntry::find($entryId)->next_review_at);
        $this->assertTrue(ArchiveEntry::find($entryId)->next_review_at > now()->addMonths(5));

        // 7. Release legal hold
        $releaseRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/archive/legal-holds/{$holdId}/release");
        $releaseRes->assertStatus(200);
        $this->assertNotNull(LegalHold::find($holdId)->released_at);
    }

    /**
     * Test 4: Dashboard Operational Queues and Dynamic Scoping
     */
    public function test_dashboard_operational_queues_and_scoping(): void
    {
        $dashRes = $this->actingAs($this->staffUser, 'sanctum')
            ->getJson('/api/v1/dashboard/metrics');

        $dashRes->assertStatus(200);
        $overview = $dashRes->json('data.overview');

        // Verify key institutional operational counters exist and are numeric
        $expectedKeys = [
            'total_documents',
            'active_documents',
            'retired_documents',
            'tombstoned_documents',
            'total_correspondences',
            'incoming_letters',
            'outgoing_letters',
            'pending_workflow_tasks',
            'my_pending_tasks',
            'my_office_pending_tasks',
            'overdue_tasks',
            'outgoing_awaiting_dispatch',
            'unreceived_dispatches',
            'incoming_unreceived',
            'records_awaiting_retention_review',
            'pending_disposition_requests',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $overview, "Missing operational metric: {$key}");
            $this->assertIsInt($overview[$key]);
        }
    }
}
