<?php

namespace Tests\Feature;

use App\Models\AuthorizationScope;
use App\Models\ConfidentialityLevel;
use App\Models\Correspondence;
use App\Models\CorrespondenceType;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentTemplate;
use App\Models\DocumentVersion;
use App\Models\FileObject;
use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\Priority;
use App\Models\Role;
use App\Models\TemplateVersion;
use App\Models\UnitType;
use App\Models\User;
use App\Models\VersionFile;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowState;
use App\Models\WorkflowTask;
use App\Models\WorkflowTransition;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityAndAccessControlAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $officerUnitA;
    protected User $officerUnitB;
    protected User $unauthorizedStaff;
    protected OrganizationUnit $unitA;
    protected OrganizationUnit $unitB;
    protected DocumentCategory $category;
    protected ConfidentialityLevel $confidentiality;
    protected ConfidentialityLevel $restrictedConfidentiality;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);
        Storage::fake('local');

        $collegeType = UnitType::firstOrCreate(['code' => 'college'], ['name' => 'College', 'active' => 1]);
        $this->unitA = OrganizationUnit::first();
        $this->unitB = OrganizationUnit::create([
            'parent_id' => $this->unitA->id,
            'unit_type_id' => $collegeType->id,
            'code' => 'AUDIT_UNIT_B',
            'name' => 'Audit Security Secondary Unit B',
            'active' => 1,
        ]);

        $this->superAdmin = User::where('email', 'admin@wu.edu.et')->first();
        $this->category = DocumentCategory::first();
        $this->confidentiality = ConfidentialityLevel::where('code', 'internal')->first() ?? ConfidentialityLevel::first();
        $this->restrictedConfidentiality = ConfidentialityLevel::where('code', 'confidential')->first() ?? ConfidentialityLevel::first();

        // Unit A Officer
        $this->officerUnitA = User::create([
            'name' => 'Audit Officer Unit A',
            'email' => 'officer.a.audit@wu.edu.et',
            'password' => bcrypt('StrongPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->officerUnitA, 'records_officer', 'unit', $this->unitA->id);

        // Unit B Officer
        $this->officerUnitB = User::create([
            'name' => 'Audit Officer Unit B',
            'email' => 'officer.b.audit@wu.edu.et',
            'password' => bcrypt('StrongPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->officerUnitB, 'records_officer', 'unit', $this->unitB->id);

        // Plain Staff with no operational grants
        $this->unauthorizedStaff = User::create([
            'name' => 'Plain Staff Member',
            'email' => 'staff.no.perm@wu.edu.et',
            'password' => bcrypt('StrongPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
    }

    /**
     * Helper to wire a document with initial version and file.
     */
    protected function createTestDocument(OrganizationUnit $unit, User $creator, ?ConfidentialityLevel $conf = null): Document
    {
        $doc = Document::create([
            'reference_number' => 'REF-AUDIT-'.uniqid(),
            'title' => 'Audit Test Document for '.$unit->name,
            'category_id' => $this->category->id,
            'confidentiality_id' => ($conf ?? $this->confidentiality)->id,
            'owner_unit_id' => $unit->id,
            'created_by' => $creator->id,
            'lifecycle' => 'active',
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
        ]);

        $version = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $creator->id,
            'comment' => 'Initial version',
        ]);

        $fileObject = FileObject::create([
            'storage_disk' => 'local',
            'storage_key' => 'documents/audit-test-'.uniqid().'.pdf',
            'original_filename' => 'audit_evidence.pdf',
            'detected_mime' => 'application/pdf',
            'byte_size' => 2048,
            'sha256' => hash('sha256', 'secure_evidence_content_'.uniqid()),
            'uploaded_by' => $creator->id,
            'uploaded_at' => now(),
            'scan_status' => 'clean',
            'storage_status' => 'ready',
        ]);

        VersionFile::create([
            'document_version_id' => $version->id,
            'file_object_id' => $fileObject->id,
            'purpose' => 'primary',
            'display_order' => 0,
        ]);

        return $doc;
    }

    /**
     * 1. Cross-Department Access Blocks:
     * Officer in Unit B must NOT be able to view or modify Unit A's document.
     * Officer in Unit A CAN view their own unit's document.
     */
    public function test_cross_department_access_is_blocked_and_unit_boundary_enforced(): void
    {
        $docA = $this->createTestDocument($this->unitA, $this->officerUnitA);

        // Unit A officer can view docA
        $this->authenticateAs($this->officerUnitA);
        $allowedResp = $this->getJson("/api/v1/documents/{$docA->id}");
        $allowedResp->assertStatus(200);

        // Unit B officer cannot view docA (fails closed with 403)
        $this->authenticateAs($this->officerUnitB);
        $deniedResp = $this->getJson("/api/v1/documents/{$docA->id}");
        $deniedResp->assertStatus(403);
        $deniedResp->assertJsonPath('success', false);

        // Unit B officer cannot update docA
        $deniedUpdate = $this->putJson("/api/v1/documents/{$docA->id}", [
            'title' => 'Tampered Title by Cross-Unit Actor',
        ]);
        $deniedUpdate->assertStatus(403);
    }

    /**
     * 2. Anti-Self-Approval (INV-04):
     * The author/creator of a document or version cannot approve their own workflow task.
     */
    public function test_anti_self_approval_inv_04_prevents_creator_from_approving_own_workflow_instance(): void
    {
        $docA = $this->createTestDocument($this->unitA, $this->officerUnitA);
        $version = $docA->versions()->first();

        // Ensure workflow definition and version exist
        $wfDef = WorkflowDefinition::firstOrCreate(
            ['code' => 'AUDIT_APPROVAL_FLOW'],
            ['name' => 'Audit Approval Workflow', 'is_active' => 1, 'category_id' => $this->category->id]
        );

        $wfVer = \App\Models\WorkflowVersion::firstOrCreate([
            'definition_id' => $wfDef->id,
            'version_number' => 1,
        ], [
            'created_by' => $this->officerUnitA->id,
            'created_at' => now(),
        ]);

        $state1 = WorkflowState::firstOrCreate([
            'workflow_version_id' => $wfVer->id,
            'code' => 'review',
        ], [
            'name' => 'Under Review',
            'is_initial' => 1,
            'is_terminal' => 0,
        ]);

        $state2 = WorkflowState::firstOrCreate([
            'workflow_version_id' => $wfVer->id,
            'code' => 'approved',
        ], [
            'name' => 'Approved',
            'is_initial' => 0,
            'is_terminal' => 1,
        ]);

        $perm = Permission::where('code', 'workflow.review')->firstOrFail();

        WorkflowTransition::firstOrCreate([
            'from_state_id' => $state1->id,
            'action_code' => 'approve',
        ], [
            'to_state_id' => $state2->id,
            'permission_id' => $perm->id,
            'prohibit_self_approval' => 1,
        ]);

        $instance = WorkflowInstance::create([
            'workflow_version_id' => $wfVer->id,
            'document_version_id' => $version->id,
            'current_state_id' => $state1->id,
            'status' => 'running',
            'started_by' => $this->officerUnitA->id,
            'started_at' => now(),
        ]);

        $task = WorkflowTask::create([
            'instance_id' => $instance->id,
            'state_id' => $state1->id,
            'assigned_user_id' => $this->officerUnitA->id,
            'assigned_unit_id' => $this->unitA->id,
            'created_at' => now(),
        ]);

        // Grant review permission in Unit A to Officer A
        $this->grantPermissionToUser($this->officerUnitA, 'workflow.review', 'unit', $this->unitA->id);
        $this->authenticateAs($this->officerUnitA);

        // Attempt self-approval as creator of document / version
        $response = $this->postJson("/api/v1/workflows/tasks/{$task->id}/decide", [
            'decision' => 'approve',
            'comment' => 'Self-approving my own submission',
        ]);

        // Must fail with 422 Anti-self-approval violation (INV-04)
        $response->assertStatus(422);
        $this->assertStringContainsString('Anti-self-approval violation', $response->json('message'));
    }

    /**
     * 3. Template Unit Scopes:
     * User without permission in Unit B cannot generate a document targeting Unit B.
     */
    public function test_template_generation_fails_closed_when_targeting_unauthorized_unit(): void
    {
        $template = DocumentTemplate::create([
            'code' => 'TMPL-AUDIT-'.uniqid(),
            'name' => 'Institutional Audit Memo Template',
            'category_id' => $this->category->id,
            'owner_unit_id' => $this->unitA->id,
            'visibility_scope' => 'global',
            'is_active' => 1,
            'created_by' => $this->officerUnitA->id,
        ]);

        TemplateVersion::create([
            'template_id' => $template->id,
            'version_number' => 1,
            'status' => 'published',
            'schema_definition' => ['fields' => [['key' => 'body', 'type' => 'text']]],
            'body_template_html' => '<p>{{body}}</p>',
            'created_by' => $this->officerUnitA->id,
            'published_by' => $this->officerUnitA->id,
            'published_at' => now(),
        ]);

        // Unit A officer attempts to generate document targeting Unit B
        $this->authenticateAs($this->officerUnitA);

        $response = $this->postJson("/api/v1/templates/{$template->id}/generate-document", [
            'title' => 'Unauthorized Document for Unit B',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->unitB->id, // Targeting unauthorized unit
            'template_data' => ['body' => 'Notice content'],
        ]);

        // Must fail closed with 403 Forbidden
        $response->assertStatus(403);
    }

    /**
     * 4. Unauthorized Directive & Assignment Blocks:
     * User lacking correspondence.assign permission cannot store an assignment on a document.
     */
    public function test_unauthorized_user_cannot_create_document_assignment(): void
    {
        $docA = $this->createTestDocument($this->unitA, $this->officerUnitA);

        // Plain staff without correspondence.assign attempts to assign
        $this->authenticateAs($this->unauthorizedStaff);

        $response = $this->postJson("/api/v1/documents/{$docA->id}/assignments", [
            'assigned_user_id' => $this->officerUnitB->id,
            'assigned_unit_id' => $this->unitB->id,
            'directive' => 'Unauthorized directive assignment attempt',
        ]);

        $response->assertStatus(403);
    }

    /**
     * 5. Direct API Requests Fail Without Authentication (401):
     * Unauthenticated Sanctum requests to protected endpoints return 401.
     */
    public function test_unauthenticated_api_requests_are_rejected_with_401(): void
    {
        $endpoints = [
            ['GET', '/api/v1/documents'],
            ['GET', '/api/v1/correspondences'],
            ['GET', '/api/v1/users'],
            ['GET', '/api/v1/audit-events'],
        ];

        foreach ($endpoints as [$method, $uri]) {
            $response = $this->withHeader('Accept', 'application/json')
                ->json($method, $uri);
            $response->assertStatus(401);
        }
    }

    /**
     * 6. IDOR Defenses on File Downloads:
     * - Non-existent file ID returns 404.
     * - File belonging to another unit's restricted document returns 403.
     */
    public function test_idor_defenses_prevent_unauthorized_file_access_and_enumeration(): void
    {
        // 6a. Non-existent file ID
        $this->authenticateAs($this->officerUnitA);
        $notFoundResp = $this->getJson('/api/v1/files/99999999/download');
        $notFoundResp->assertStatus(404);

        // 6b. File attached to confidential document in Unit B
        $docB = $this->createTestDocument($this->unitB, $this->officerUnitB, $this->restrictedConfidentiality);
        $fileB = $docB->versions()->first()->versionFiles()->first()->fileObject;

        // Officer Unit A attempts to download Unit B's file
        $idorAttempt = $this->getJson("/api/v1/files/{$fileB->id}/download");
        $idorAttempt->assertStatus(403);
    }

    /**
     * 7. Sanctum Session Lifecycle and Logout:
     * Logging out properly invalidates the session and subsequent requests fail.
     */
    public function test_sanctum_session_lifecycle_and_logout_invalidation(): void
    {
        $this->authenticateAs($this->officerUnitA);

        // Active request succeeds
        $this->getJson('/api/v1/auth/me')->assertStatus(200);

        // Logout
        $logoutResp = $this->postJson('/api/v1/auth/logout');
        $logoutResp->assertStatus(200);

        // Clear resolved web guard and authenticate mock headers
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        // Subsequent call without valid session is unauthenticated
        $afterLogout = $this->withHeader('Accept', 'application/json')
            ->getJson('/api/v1/auth/me');
        $afterLogout->assertStatus(401);
    }

    /**
     * 8. Fail-Closed Behavior on Missing Scope:
     * If user has a permission in one unit, attempting action in another unit fails closed.
     */
    public function test_fail_closed_behavior_when_scope_is_missing(): void
    {
        // Officer A has correspondence.register strictly in Unit A, not Unit B
        $this->authenticateAs($this->officerUnitA);

        $response = $this->postJson('/api/v1/correspondences', [
            'title' => 'Rogue Cross-Unit Correspondence',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->unitB->id, // Unauthorized unit target
            'type_id' => CorrespondenceType::first()->id,
            'priority_id' => Priority::first()->id,
            'purpose_code' => 'cross_unit_test',
            'correspondence_date' => now()->toDateString(),
        ]);

        $response->assertStatus(403);
    }

    /**
     * 9. Unauthorized Workflow Delegation:
     * A user who is NOT assigned to a task and lacks delegation permission cannot delegate it.
     */
    public function test_unauthorized_workflow_delegation_is_blocked(): void
    {
        $docA = $this->createTestDocument($this->unitA, $this->officerUnitA);
        $version = $docA->versions()->first();

        $wfDef = WorkflowDefinition::firstOrCreate(
            ['code' => 'AUDIT_DELEGATION_FLOW'],
            ['name' => 'Audit Delegation Workflow', 'is_active' => 1, 'category_id' => $this->category->id]
        );
        $wfVer = \App\Models\WorkflowVersion::firstOrCreate([
            'definition_id' => $wfDef->id,
            'version_number' => 1,
        ], [
            'created_by' => $this->officerUnitA->id,
            'created_at' => now(),
        ]);
        $state = WorkflowState::firstOrCreate([
            'workflow_version_id' => $wfVer->id,
            'code' => 'review_step',
        ], [
            'name' => 'Review Step',
            'is_initial' => 1,
            'is_terminal' => 0,
        ]);

        $instance = WorkflowInstance::create([
            'workflow_version_id' => $wfVer->id,
            'document_version_id' => $version->id,
            'current_state_id' => $state->id,
            'status' => 'running',
            'started_by' => $this->officerUnitA->id,
            'started_at' => now(),
        ]);

        $task = WorkflowTask::create([
            'instance_id' => $instance->id,
            'state_id' => $state->id,
            'assigned_user_id' => $this->officerUnitA->id,
            'assigned_unit_id' => $this->unitA->id,
            'created_at' => now(),
        ]);

        // Officer Unit B (from Unit B) attempts to delegate Officer Unit A's task
        $this->authenticateAs($this->officerUnitB);

        $response = $this->postJson("/api/v1/workflows/tasks/{$task->id}/delegate", [
            'target_user_id' => $this->unauthorizedStaff->id,
            'reason' => 'Unauthorized delegation by cross-unit actor',
        ]);

        $response->assertStatus(403);
    }

    /**
     * 10. Unauthorized Task Reassignment:
     * A user without assignment or management authority cannot reassign a directive.
     */
    public function test_unauthorized_task_reassignment_is_blocked(): void
    {
        $docA = $this->createTestDocument($this->unitA, $this->officerUnitA);

        $assignment = \App\Models\DocumentAssignment::create([
            'document_id' => $docA->id,
            'assigned_by' => $this->superAdmin->id,
            'assigned_user_id' => $this->officerUnitA->id,
            'assigned_unit_id' => $this->unitA->id,
            'directive' => 'Execute internal unit assessment',
            'status' => 'assigned',
            'assigned_at' => now(),
            'due_at' => now()->addDays(5),
        ]);

        // Unauthorized staff attempts to record a 'reassigned' lifecycle event
        $this->authenticateAs($this->unauthorizedStaff);

        $response = $this->postJson("/api/v1/assignments/{$assignment->id}/events", [
            'action' => 'reassigned',
            'target_user_id' => $this->officerUnitB->id,
            'comment' => 'Illicit reassignment attempt',
        ]);

        $response->assertStatus(403);
    }

    /**
     * 11. Cross-Unit Restricted Template Access:
     * Templates with unit_restricted visibility scope cannot be viewed or authored by other units.
     */
    public function test_cross_unit_restricted_template_access_is_blocked(): void
    {
        $restrictedTemplate = DocumentTemplate::create([
            'code' => 'TMPL-RESTRICTED-'.uniqid(),
            'name' => 'Unit A Private Template',
            'category_id' => $this->category->id,
            'owner_unit_id' => $this->unitA->id,
            'visibility_scope' => 'unit_restricted',
            'is_active' => 1,
            'created_by' => $this->officerUnitA->id,
        ]);

        // Officer Unit B attempts to view Unit A's restricted template
        $this->authenticateAs($this->officerUnitB);

        $viewResponse = $this->getJson("/api/v1/templates/{$restrictedTemplate->id}");
        $viewResponse->assertStatus(403);

        // Officer Unit B attempts to author a draft version on Unit A's restricted template
        $versionResponse = $this->postJson("/api/v1/templates/{$restrictedTemplate->id}/versions", [
            'schema_definition' => ['fields' => [['key' => 'note', 'type' => 'text']]],
            'body_template_html' => '<p>{{note}}</p>',
        ]);
        $versionResponse->assertStatus(403);
    }

    /**
     * 12. Confidential Archive Access:
     * Cross-unit officers cannot view confidential archive entries or transfer foreign documents.
     */
    public function test_confidential_archive_access_is_blocked_for_cross_unit_officer(): void
    {
        $collection = \App\Models\ArchiveCollection::firstOrCreate(
            ['code' => 'AUDIT_COLL_A'],
            ['name' => 'Audit Archive Collection A', 'active' => 1]
        );
        $policy = \App\Models\RetentionPolicy::firstOrCreate(
            ['code' => 'AUDIT_RET_POL'],
            ['name' => 'Standard Audit Retention', 'active' => 1]
        );
        $policyVer = \App\Models\RetentionPolicyVersion::firstOrCreate([
            'policy_id' => $policy->id,
            'version_number' => 1,
        ], [
            'trigger_code' => 'creation_date',
            'duration_months' => 60,
            'disposition_action' => 'review',
            'effective_from' => '2026-01-01',
            'created_by' => $this->superAdmin->id,
            'created_at' => now(),
        ]);

        // Confidential document in Unit A
        $confDocA = $this->createTestDocument($this->unitA, $this->officerUnitA, $this->restrictedConfidentiality);

        \App\Models\ArchiveEntry::create([
            'document_id' => $confDocA->id,
            'collection_id' => $collection->id,
            'retention_policy_version_id' => $policyVer->id,
            'archived_by' => $this->officerUnitA->id,
            'archived_at' => now(),
            'trigger_occurred_at' => now(),
        ]);

        // Officer Unit B (from Unit B) queries archive entries
        $this->grantPermissionToUser($this->officerUnitB, 'archive.view', 'unit', $this->unitB->id);
        $this->authenticateAs($this->officerUnitB);

        $entriesResp = $this->getJson('/api/v1/archive/entries');
        $entriesResp->assertStatus(200);
        $entryDocIds = collect($entriesResp->json('data.data'))->pluck('document_id')->all();
        $this->assertNotContains($confDocA->id, $entryDocIds);

        // Officer Unit B attempts to transfer a new Unit A document into archive
        $newDocA = $this->createTestDocument($this->unitA, $this->officerUnitA);
        $this->grantPermissionToUser($this->officerUnitB, 'archive.transfer', 'unit', $this->unitB->id);

        $transferResp = $this->postJson('/api/v1/archive/transfer', [
            'document_id' => $newDocA->id,
            'collection_id' => $collection->id,
            'retention_policy_version_id' => $policyVer->id,
        ]);

        $transferResp->assertStatus(403);
    }

    /**
     * 13. Unauthorized Document Rejection and Approval:
     * An actor without task assignment or review permissions cannot approve or reject.
     */
    public function test_unauthorized_document_rejection_and_approval_is_blocked(): void
    {
        $docA = $this->createTestDocument($this->unitA, $this->officerUnitA);
        $version = $docA->versions()->first();

        $wfDef = WorkflowDefinition::firstOrCreate(
            ['code' => 'AUDIT_DECISION_FLOW'],
            ['name' => 'Audit Decision Flow', 'is_active' => 1, 'category_id' => $this->category->id]
        );
        $wfVer = \App\Models\WorkflowVersion::firstOrCreate([
            'definition_id' => $wfDef->id,
            'version_number' => 1,
        ], [
            'created_by' => $this->superAdmin->id,
            'created_at' => now(),
        ]);
        $stateInit = WorkflowState::firstOrCreate([
            'workflow_version_id' => $wfVer->id,
            'code' => 'state_init',
        ], [
            'name' => 'Initial Review',
            'is_initial' => 1,
            'is_terminal' => 0,
        ]);
        $stateApproved = WorkflowState::firstOrCreate([
            'workflow_version_id' => $wfVer->id,
            'code' => 'state_approved',
        ], [
            'name' => 'Approved',
            'is_initial' => 0,
            'is_terminal' => 1,
        ]);
        $stateRejected = WorkflowState::firstOrCreate([
            'workflow_version_id' => $wfVer->id,
            'code' => 'state_rejected',
        ], [
            'name' => 'Rejected',
            'is_initial' => 0,
            'is_terminal' => 1,
        ]);

        $perm = Permission::where('code', 'workflow.review')->firstOrFail();
        WorkflowTransition::firstOrCreate([
            'from_state_id' => $stateInit->id,
            'action_code' => 'approve',
        ], [
            'to_state_id' => $stateApproved->id,
            'permission_id' => $perm->id,
            'prohibit_self_approval' => 0,
        ]);
        WorkflowTransition::firstOrCreate([
            'from_state_id' => $stateInit->id,
            'action_code' => 'reject',
        ], [
            'to_state_id' => $stateRejected->id,
            'permission_id' => $perm->id,
            'prohibit_self_approval' => 0,
        ]);

        $instance = WorkflowInstance::create([
            'workflow_version_id' => $wfVer->id,
            'document_version_id' => $version->id,
            'current_state_id' => $stateInit->id,
            'status' => 'running',
            'started_by' => $this->superAdmin->id,
            'started_at' => now(),
        ]);

        $task = WorkflowTask::create([
            'instance_id' => $instance->id,
            'state_id' => $stateInit->id,
            'assigned_user_id' => $this->officerUnitA->id,
            'assigned_unit_id' => $this->unitA->id,
            'created_at' => now(),
        ]);

        // Officer Unit B attempts to approve Officer Unit A's task
        $this->authenticateAs($this->officerUnitB);

        $approveResp = $this->postJson("/api/v1/workflows/tasks/{$task->id}/decide", [
            'decision' => 'approve',
            'comment' => 'Illicit approval by outsider',
        ]);
        $approveResp->assertStatus(403);

        // Officer Unit B attempts to reject Officer Unit A's task
        $rejectResp = $this->postJson("/api/v1/workflows/tasks/{$task->id}/decide", [
            'decision' => 'reject',
            'comment' => 'Illicit rejection by outsider',
        ]);
        $rejectResp->assertStatus(403);
    }

    /**
     * 14. Permission Revocation During Authenticated Session:
     * When user's authz_version is incremented remotely, subsequent requests fail closed.
     */
    public function test_permission_revocation_during_existing_authenticated_session_fails_closed(): void
    {
        $this->authenticateAs($this->officerUnitA);

        // Initial request succeeds
        $this->getJson('/api/v1/auth/me')->assertStatus(200);

        // Remote administrative action: Revoke privileges by bumping authz_version
        $this->officerUnitA->increment('authz_version');

        // Next request with stale session payload fails closed with 401 or 403
        $deniedResp = $this->getJson('/api/v1/auth/me');
        $this->assertTrue(in_array($deniedResp->status(), [401, 403], true));
    }

    /**
     * 15. Attempts to Manipulate Unit IDs Through Headers, Query & Payload:
     * Tampering unit parameters across boundaries fails closed with 403.
     */
    public function test_attempts_to_manipulate_unit_ids_through_headers_query_and_payload_are_blocked(): void
    {
        $this->authenticateAs($this->officerUnitA);

        // 15a. Query parameter tampering: attempting to list documents belonging to foreign Unit B
        $queryTamper = $this->getJson("/api/v1/documents?owner_unit_id={$this->unitB->id}");
        $queryTamper->assertStatus(403);

        // 15b. Header parameter tampering: sending foreign unit ID in X-Organization-Unit-Id
        $headerTamper = $this->withHeader('X-Organization-Unit-Id', (string) $this->unitB->id)
            ->getJson('/api/v1/documents');
        $headerTamper->assertStatus(403);

        // 15c. Cross-unit payload mismatch tampering: Header claims Unit A, but payload targets Unit B
        $mismatchTamper = $this->withHeader('X-Organization-Unit-Id', (string) $this->unitA->id)
            ->postJson('/api/v1/documents', [
                'reference_number' => 'DOC-TAMPER-'.uniqid(),
                'title' => 'Tampered Unit Target Document',
                'category_id' => $this->category->id,
                'confidentiality_id' => $this->confidentiality->id,
                'owner_unit_id' => $this->unitB->id, // Disallowed payload unit
                'document_date' => now()->toDateString(),
            ]);
        $mismatchTamper->assertStatus(403);
    }
}
