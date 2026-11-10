<?php

namespace Tests\Feature;

use App\Models\Correspondence;
use App\Models\CorrespondenceDispatch;
use App\Models\CorrespondenceType;
use App\Models\DispatchMethod;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\OfficeProcedureVersion;
use App\Models\OrganizationUnit;
use App\Models\Priority;
use App\Models\UnitType;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowState;
use App\Models\WorkflowTask;
use App\Models\WorkflowVersion;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EndToEndWorkflowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $lecturer;
    protected User $deptHead;
    protected User $dispatchOfficer;
    protected OrganizationUnit $department;
    protected OrganizationUnit $registryUnit;
    protected WorkflowVersion $workflowVersion;
    protected WorkflowState $reviewState;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $this->admin = User::where('email', 'admin@wu.edu.et')->first();
        $rootUnit = OrganizationUnit::first();

        $deptType = UnitType::where('code', 'department')->first()
            ?? UnitType::create(['code' => 'department', 'name' => 'Department', 'active' => 1]);

        $this->department = OrganizationUnit::create([
            'parent_id' => $rootUnit->id,
            'unit_type_id' => $deptType->id,
            'code' => 'INFORMATICS_DEPT',
            'name' => 'Department of Informatics',
            'active' => true,
        ]);

        $this->registryUnit = OrganizationUnit::create([
            'parent_id' => $rootUnit->id,
            'unit_type_id' => $deptType->id,
            'code' => 'CENTRAL_REGISTRY',
            'name' => 'Institutional Central Registry',
            'active' => true,
        ]);

        // Actor 1: Lecturer / Originator
        $this->lecturer = User::create([
            'name' => 'Dr. Lecturer Bekele',
            'email' => 'lecturer.bekele@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->lecturer, 'staff', 'unit', $this->department->id);
        $this->grantPermissionToUser($this->lecturer, 'correspondence.register', 'unit', $this->department->id);
        $this->grantPermissionToUser($this->lecturer, 'correspondence.view', 'unit', $this->department->id);
        $this->grantPermissionToUser($this->lecturer, 'documents.version', 'unit', $this->department->id);

        // Actor 2: Department Head
        $this->deptHead = User::create([
            'name' => 'Prof. Department Head Almaz',
            'email' => 'head.almaz@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->deptHead, 'department_head', 'unit', $this->department->id);
        $this->grantPermissionToUser($this->deptHead, 'workflow.view', 'unit', $this->department->id);
        $this->grantPermissionToUser($this->deptHead, 'workflow.review', 'unit', $this->department->id);
        $this->grantPermissionToUser($this->deptHead, 'correspondence.view', 'unit', $this->department->id);

        // Actor 3: Dispatch Officer
        $this->dispatchOfficer = User::create([
            'name' => 'Dispatch Officer Chala',
            'email' => 'dispatch.chala@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->dispatchOfficer, 'records_officer', 'unit', $this->department->id);
        $this->grantPermissionToUser($this->dispatchOfficer, 'correspondence.dispatch', 'unit', $this->department->id);
        $this->grantPermissionToUser($this->dispatchOfficer, 'correspondence.view', 'unit', $this->department->id);

        // Setup Workflow & Office Procedure
        $wfDef = WorkflowDefinition::firstOrCreate(
            ['code' => 'OUTGOING_LETTER_PROC'],
            ['name' => 'Outgoing Letter Approval Protocol', 'active' => true]
        );

        $this->workflowVersion = WorkflowVersion::create([
            'definition_id' => $wfDef->id,
            'version_number' => 1,
            'created_by' => $this->admin->id,
            'published_at' => now(),
        ]);

        $this->reviewState = WorkflowState::create([
            'workflow_version_id' => $this->workflowVersion->id,
            'code' => 'head_review',
            'name' => 'Department Head Review',
            'is_initial' => true,
            'is_terminal' => false,
        ]);

        $outType = CorrespondenceType::where('code', 'outgoing')->first()
            ?? CorrespondenceType::create(['code' => 'outgoing', 'name' => 'Outgoing', 'active' => true]);

        OfficeProcedureVersion::create([
            'unit_id' => $this->department->id,
            'correspondence_type_id' => $outType->id,
            'purpose_code' => 'external_mou',
            'version_number' => 1,
            'receipt_mode' => 'direct',
            'issue_mode' => 'workflow',
            'workflow_version_id' => $this->workflowVersion->id,
            'confirmed_at' => now(),
            'confirmed_by' => $this->admin->id,
            'confirmation_source' => 'executive_charter',
            'effective_from' => now(),
        ]);
    }

    public function test_complete_multi_actor_lifecycle_from_draft_to_dispatch(): void
    {
        $outType = CorrespondenceType::where('code', 'outgoing')->first();
        $cat = DocumentCategory::first();
        $conf = \App\Models\ConfidentialityLevel::first();
        $priority = Priority::first();
        $dispatchMethod = DispatchMethod::first();

        // ---------------------------------------------------------------------
        // STAGE 1: Lecturer drafts outgoing correspondence
        // ---------------------------------------------------------------------
        $this->authenticateAs($this->lecturer);

        $draftResponse = $this->postJson('/api/v1/correspondences', [
            'title' => 'Inter-University Research Collaboration Memorandum',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $this->department->id,
            'type_id' => $outType->id,
            'priority_id' => $priority->id,
            'purpose_code' => 'external_mou',
            'correspondence_date' => '2026-10-30',
        ]);

        $draftResponse->assertStatus(201);
        $corrId = $draftResponse->json('data.id');
        $this->assertNotNull($corrId);

        $corr = Correspondence::with('document.versions')->findOrFail($corrId);
        $this->assertEquals('active', $corr->document->lifecycle);
        $ver1 = $corr->document->versions->first();
        $this->assertNotNull($ver1);
        $this->assertEquals(1, $ver1->version_number);

        // ---------------------------------------------------------------------
        // STAGE 2: Lecturer submits Version 1 for Department Head review
        // ---------------------------------------------------------------------
        $instance1 = WorkflowInstance::create([
            'workflow_version_id' => $this->workflowVersion->id,
            'document_version_id' => $ver1->id,
            'started_by' => $this->lecturer->id,
            'started_at' => now(),
        ]);

        $task1 = WorkflowTask::create([
            'instance_id' => $instance1->id,
            'state_id' => $this->reviewState->id,
            'assigned_user_id' => $this->deptHead->id,
            'created_at' => now(),
        ]);

        // ---------------------------------------------------------------------
        // STAGE 3: Department Head logs in, finds task, reviews & approves Version 1
        // ---------------------------------------------------------------------
        $this->authenticateAs($this->deptHead);

        // Dept Head checks inbox
        $inboxResponse = $this->getJson('/api/v1/workflows/tasks?scope=mine');
        $inboxResponse->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $inboxResponse->json('meta.total'));

        // Dept Head approves Version 1
        $decision1 = $this->postJson("/api/v1/workflows/tasks/{$task1->id}/decide", [
            'decision' => 'approve',
            'reason' => 'Collaboration terms and intellectual property clauses vetted and approved.',
        ]);

        $decision1->assertStatus(200);
        $this->assertNotNull($task1->fresh()->closed_at);

        // ---------------------------------------------------------------------
        // STAGE 4: Lecturer introduces material modifications (Version 2)
        //          Attempting dispatch of Version 2 using Version 1 approval fails!
        // ---------------------------------------------------------------------
        $ver2 = DocumentVersion::create([
            'document_id' => $corr->document_id,
            'version_number' => 2,
            'created_by' => $this->lecturer->id,
            'change_note' => 'Added additional budgetary commitment clauses after initial review.',
            'created_at' => now(),
        ]);

        $this->authenticateAs($this->dispatchOfficer);

        // Dispatch officer attempts dispatch of Version 2 -> REJECTED WITH 422!
        $prematureDispatch = $this->postJson("/api/v1/correspondences/{$corrId}/dispatch", [
            'document_version_id' => $ver2->id,
            'method_id' => $dispatchMethod->id,
            'tracking_reference' => 'UNAPPROVED-V2-TRACK',
        ]);

        $prematureDispatch->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonFragment([
                'message' => "Approval mismatch: Material modifications detected. Version {$ver2->id} has not been approved. Prior approval on an earlier version cannot be reused for modified content; renewed Department Head approval is required.",
            ]);

        // ---------------------------------------------------------------------
        // STAGE 5: Version 2 submitted for renewed review; Dept Head approves
        // ---------------------------------------------------------------------
        $instance2 = WorkflowInstance::create([
            'workflow_version_id' => $this->workflowVersion->id,
            'document_version_id' => $ver2->id,
            'started_by' => $this->lecturer->id,
            'started_at' => now(),
        ]);

        $task2 = WorkflowTask::create([
            'instance_id' => $instance2->id,
            'state_id' => $this->reviewState->id,
            'assigned_user_id' => $this->deptHead->id,
            'created_at' => now(),
        ]);

        $this->authenticateAs($this->deptHead);
        $decision2 = $this->postJson("/api/v1/workflows/tasks/{$task2->id}/decide", [
            'decision' => 'approve',
            'reason' => 'Budgetary clauses acceptable under current fiscal allocation.',
        ]);
        $decision2->assertStatus(200);

        // ---------------------------------------------------------------------
        // STAGE 6: Dispatch Officer logs in and dispatches approved Version 2
        // ---------------------------------------------------------------------
        $this->authenticateAs($this->dispatchOfficer);

        $successfulDispatch = $this->postJson("/api/v1/correspondences/{$corrId}/dispatch", [
            'document_version_id' => $ver2->id,
            'method_id' => $dispatchMethod->id,
            'tracking_reference' => 'WU-POST-ET-2026-88991',
        ]);

        $successfulDispatch->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'tracking_reference' => 'WU-POST-ET-2026-88991',
            ]);

        // Verify dispatch database record exists
        $this->assertDatabaseHas('correspondence_dispatches', [
            'document_version_id' => $ver2->id,
            'tracking_reference' => 'WU-POST-ET-2026-88991',
            'dispatched_by' => $this->dispatchOfficer->id,
        ]);

        // Verify audit event was immutably recorded
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $this->dispatchOfficer->id,
            'action' => 'correspondence.dispatched',
        ]);
    }
}
