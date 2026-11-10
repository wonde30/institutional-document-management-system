<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Correspondence;
use App\Models\Document;
use App\Models\DocumentAssignment;
use App\Models\DocumentCategory;
use App\Models\DocumentTemplate;
use App\Models\DocumentVersion;
use App\Models\OrganizationUnit;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowState;
use App\Models\WorkflowTask;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Services\OcrTextExtractionService;
use App\Services\TemplateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UnifiedRepositoryEndToEndTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected User $staffUser1;
    protected User $staffUser2;
    protected User $staffUser3;
    protected OrganizationUnit $itUnit;
    protected OrganizationUnit $academicUnit;
    protected int $categoryId;
    protected int $confidentialityId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(\Database\Seeders\BaselineReferenceVocabularySeeder::class);

        $this->adminUser = User::where('email', 'admin@wu.edu.et')->first()
            ?? User::factory()->create(['email' => 'admin@wu.edu.et', 'status' => 'active']);

        $this->assignRoleToUser($this->adminUser, 'super_admin', 'global');

        $this->staffUser1 = User::factory()->create(['status' => 'active', 'authz_version' => 1]);
        $this->staffUser2 = User::factory()->create(['status' => 'active', 'authz_version' => 1]);
        $this->staffUser3 = User::factory()->create(['status' => 'active', 'authz_version' => 1]);

        $this->grantPermissionToUser($this->staffUser1, 'workflow.view', 'global');
        $this->grantPermissionToUser($this->staffUser1, 'workflow.review', 'global');
        $this->grantPermissionToUser($this->staffUser2, 'workflow.view', 'global');
        $this->grantPermissionToUser($this->staffUser2, 'workflow.review', 'global');
        $this->grantPermissionToUser($this->staffUser3, 'workflow.view', 'global');
        $this->grantPermissionToUser($this->staffUser3, 'workflow.review', 'global');

        $unitTypeId = DB::table('unit_types')->value('id') ?? DB::table('unit_types')->insertGetId(['code' => 'department', 'name' => 'Department', 'active' => 1]);

        $this->itUnit = OrganizationUnit::firstOrCreate(
            ['code' => 'IT_TEST'],
            ['name' => 'Information Technology Directorate', 'unit_type_id' => $unitTypeId, 'is_active' => 1]
        );

        $this->academicUnit = OrganizationUnit::firstOrCreate(
            ['code' => 'ACAD_TEST'],
            ['name' => 'Academic Affairs Directorate', 'unit_type_id' => $unitTypeId, 'is_active' => 1]
        );

        $this->categoryId = (int) DB::table('document_categories')->value('id');
        $this->confidentialityId = (int) DB::table('confidentiality_levels')->value('id');
    }

    /**
     * Scenario 1: Incoming correspondence registration, physical original tracking,
     * inter-office movement, acknowledgment receipt, and closure.
     */
    public function test_scenario_1_incoming_correspondence_registration_and_handling(): void
    {
        $this->authenticateAs($this->adminUser);

        $incomingTypeId = (int) DB::table('correspondence_types')->where('code', 'incoming')->value('id');
        $priorityId = (int) DB::table('priorities')->where('code', 'urgent')->value('id');

        // Confirmed office procedure
        DB::table('office_procedure_versions')->updateOrInsert(
            ['unit_id' => $this->academicUnit->id, 'correspondence_type_id' => $incomingTypeId, 'version_number' => 1],
            [
                'receipt_mode' => 'direct',
                'issue_mode' => 'direct',
                'purpose_code' => 'for_action',
                'confirmed_at' => now(),
                'confirmed_by' => $this->adminUser->id,
                'effective_from' => now()->toDateString(),
            ]
        );

        // 1. Register incoming correspondence
        $corrResponse = $this->postJson('/api/v1/correspondences', [
            'title' => 'Ministry Directive on Academic Calibrations',
            'summary' => 'Official directive received via courier from Ministry of Education',
            'category_id' => $this->categoryId,
            'confidentiality_id' => $this->confidentialityId,
            'owner_unit_id' => $this->academicUnit->id,
            'type_id' => $incomingTypeId,
            'priority_id' => $priorityId,
            'purpose_code' => 'for_action',
            'external_reference' => 'MOE/DIR/2026/0491',
            'correspondence_date' => now()->toDateString(),
            'first_receiving_unit_id' => $this->academicUnit->id,
        ]);

        $corrResponse->assertStatus(201);
        $docId = $corrResponse->json('data.document_id');
        $corrId = $corrResponse->json('data.id');

        // 2. Track physical paper original
        $physResponse = $this->postJson("/api/v1/documents/{$docId}/physical-originals", [
            'physical_reference' => "ARCH-MOE-2026-BOX-{$docId}",
            'retention_requirement' => 'required',
            'custody_reason' => 'Secured in Central Registry Vault Box 1',
        ]);
        $physResponse->assertStatus(201);
        $this->assertDatabaseHas('physical_originals', [
            'document_id' => $docId,
            'physical_reference' => "ARCH-MOE-2026-BOX-{$docId}",
        ]);

        // 3. Move correspondence between offices
        $movResponse = $this->postJson("/api/v1/documents/{$docId}/movements", [
            'from_unit_id' => $this->academicUnit->id,
            'to_unit_id' => $this->itUnit->id,
            'instruction' => 'Review digital infrastructure requirements',
            'due_at' => now()->addDays(5)->toDateTimeString(),
        ]);
        $movResponse->assertStatus(201);
        $movementId = $movResponse->json('data.id');

        // 4. Recipient office acknowledges receipt
        $ackResponse = $this->postJson("/api/v1/movements/{$movementId}/acknowledge");
        $ackResponse->assertStatus(200);
        $this->assertDatabaseHas('movement_receipts', [
            'movement_id' => $movementId,
            'acknowledged_by' => $this->adminUser->id,
        ]);
    }

    /**
     * Scenario 2: Department assignment to IT Department, sub-assignment to staff member,
     * work queue retrieval, and collaboration events.
     */
    public function test_scenario_2_department_assignment_to_staff_and_work_queue(): void
    {
        $this->authenticateAs($this->adminUser);

        $doc = Document::create([
            'reference_number' => 'WU/DOC/2026/999001',
            'title' => 'Presidential Directive: LMS Upgrade Protocol',
            'category_id' => $this->categoryId,
            'confidentiality_id' => $this->confidentialityId,
            'owner_unit_id' => $this->itUnit->id,
            'created_by' => $this->adminUser->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        // 1. Assign document to IT Department with assigned staff lead
        $assignResponse = $this->postJson("/api/v1/documents/{$doc->id}/assignments", [
            'assigned_user_id' => $this->staffUser1->id,
            'assigned_unit_id' => $this->itUnit->id,
            'instruction' => 'Draft the implementation technical proposal',
            'due_at' => now()->addDays(3)->toDateTimeString(),
            'participants' => [
                ['user_id' => $this->staffUser2->id, 'role_code' => 'writer'],
                ['user_id' => $this->staffUser3->id, 'role_code' => 'contributor'],
            ],
        ]);
        $assignResponse->assertStatus(201);
        $assignmentId = $assignResponse->json('data.id');

        // 2. Query work queue for IT Department
        $queueResponse = $this->getJson("/api/v1/assignments?assigned_unit_id={$this->itUnit->id}");
        $queueResponse->assertStatus(200);
        $items = $queueResponse->json('data');
        $this->assertNotEmpty($items);
        $this->assertEquals($assignmentId, $items[0]['id']);

        // 3. Fulfill assignment with linked response document
        $responseDoc = Document::create([
            'reference_number' => 'WU/DOC/2026/999002',
            'title' => 'Final Implementation Technical Proposal for LMS',
            'category_id' => $this->categoryId,
            'confidentiality_id' => $this->confidentialityId,
            'owner_unit_id' => $this->itUnit->id,
            'created_by' => $this->staffUser1->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $linkResponse = $this->postJson("/api/v1/assignments/{$assignmentId}/responses", [
            'response_document_id' => $responseDoc->id,
            'relationship_role' => 'full_reply',
            'is_fulfilling' => true,
        ]);
        $linkResponse->assertStatus(201);

        $this->assertDatabaseHas('document_relations', [
            'source_id' => $responseDoc->id,
            'target_id' => $doc->id,
        ]);

        $latestEvent = DB::table('assignment_events')
            ->where('assignment_id', $assignmentId)
            ->orderByDesc('sequence_number')
            ->first();
        $this->assertEquals('completed', $latestEvent->action);
    }

    /**
     * Scenario 3: Template-generated document, schema validation, declarative interpolation,
     * and file retrieval/download.
     */
    public function test_scenario_3_template_generated_official_letter_and_pdf_download(): void
    {
        $this->authenticateAs($this->adminUser);

        // 1. Author official letter template
        $templateResponse = $this->postJson('/api/v1/templates', [
            'code' => 'WU-OFFICIAL-LETTER-2026',
            'name' => 'Wollo University Official Dispatched Letter',
            'description' => 'Standard official letterhead template',
            'category_id' => $this->categoryId,
            'owner_unit_id' => null, // Global
            'visibility_scope' => 'global',
        ]);
        $templateResponse->assertStatus(201);
        $templateId = $templateResponse->json('data.id');

        // 2. Draft template version with schema
        $versionResponse = $this->postJson("/api/v1/templates/{$templateId}/versions", [
            'schema_definition' => [
                'type' => 'object',
                'required' => ['recipient_name', 'subject', 'body_text'],
                'properties' => [
                    'recipient_name' => ['type' => 'string'],
                    'subject' => ['type' => 'string'],
                    'body_text' => ['type' => 'string'],
                ],
            ],
            'body_template_html' => '<div class="letter"><h2>Wollo University</h2><p>To: {{recipient_name}}</p><p>Subject: {{subject}}</p><p>{{body_text}}</p></div>',
        ]);
        $versionResponse->assertStatus(201);
        $versionId = $versionResponse->json('data.id');

        // 3. Publish template version
        $publishResponse = $this->postJson("/api/v1/templates/{$templateId}/versions/{$versionId}/publish");
        $publishResponse->assertStatus(200);

        // 4. Render document
        $renderResponse = $this->postJson("/api/v1/templates/{$templateId}/render", [
            'template_version_id' => $versionId,
            'template_data' => [
                'recipient_name' => 'Ministry of Innovation and Technology',
                'subject' => 'Partnership Agreement on AI Incubation Hub',
                'body_text' => 'We are pleased to submit the finalized university partnership terms.',
            ],
            'title' => 'Official Letter: AI Incubation Hub Partnership',
            'category_id' => $this->categoryId,
            'confidentiality_id' => $this->confidentialityId,
            'owner_unit_id' => $this->itUnit->id,
        ]);
        $renderResponse->assertStatus(201);
        $generatedDocId = $renderResponse->json('data.id');

        $this->assertDatabaseHas('document_versions', [
            'document_id' => $generatedDocId,
            'ingestion_method' => 'template_generated',
            'template_version_id' => $versionId,
        ]);

        // 5. Retrieve/download generated PDF file
        $versionFile = DB::table('version_files')
            ->join('document_versions', 'version_files.document_version_id', '=', 'document_versions.id')
            ->where('document_versions.document_id', $generatedDocId)
            ->first();
        $this->assertNotNull($versionFile);

        $downloadResponse = $this->get("/api/v1/files/{$versionFile->file_object_id}/download");
        $downloadResponse->assertStatus(200);
        $this->assertEquals('application/pdf', $downloadResponse->headers->get('content-type'));
    }

    /**
     * Scenario 4: Existing paper document upload (scanned PDF without template)
     * and independent physical original tracking.
     */
    public function test_scenario_4_existing_paper_document_upload_and_physical_tracking(): void
    {
        $this->authenticateAs($this->adminUser);

        $file = UploadedFile::fake()->create('scanned_audit_report.pdf', 1200, 'application/pdf');

        $uploadResponse = $this->postJson('/api/v1/documents', [
            'title' => 'External Financial Audit Report 2025/2026',
            'summary' => 'Scanned original audit dossier received from Federal Auditor General',
            'category_id' => $this->categoryId,
            'confidentiality_id' => $this->confidentialityId,
            'owner_unit_id' => $this->academicUnit->id,
            'document_date' => now()->toDateString(),
            'file' => $file,
        ]);
        $uploadResponse->assertStatus(201);
        $paperDocId = $uploadResponse->json('data.id');

        // Track physical original independently
        $physResponse = $this->postJson("/api/v1/documents/{$paperDocId}/physical-originals", [
            'physical_reference' => "AUDIT-DEPT-BOX-2026-{$paperDocId}",
            'retention_requirement' => 'required',
            'custody_reason' => 'Stored in Finance Archival Safe #2',
        ]);
        $physResponse->assertStatus(201);

        $this->assertDatabaseHas('documents', ['id' => $paperDocId]);
        $this->assertDatabaseHas('physical_originals', [
            'document_id' => $paperDocId,
            'physical_reference' => "AUDIT-DEPT-BOX-2026-{$paperDocId}",
        ]);
    }

    /**
     * Scenario 5: Multi-stage approval workflow with rejection, return for correction,
     * resubmission, and final approval.
     */
    public function test_scenario_5_multi_stage_approval_workflow_with_rejection_and_resubmission(): void
    {
        $this->authenticateAs($this->adminUser);

        $wfDef = WorkflowDefinition::firstOrCreate(
            ['name' => 'Multi-Stage Academic Proposal Review'],
            ['code' => 'MULTI_STAGE_ACAD_APPR', 'active' => 1]
        );

        $wfVersion = WorkflowVersion::create([
            'definition_id' => $wfDef->id,
            'version_number' => 1,
            'created_by' => $this->adminUser->id,
            'published_at' => now(),
            'created_at' => now(),
        ]);

        $stateDraft = WorkflowState::create([
            'workflow_version_id' => $wfVersion->id,
            'code' => 'stg_dept_review',
            'name' => 'Department Review Stage',
            'is_initial' => true,
            'is_terminal' => false,
        ]);

        $stateDean = WorkflowState::create([
            'workflow_version_id' => $wfVersion->id,
            'code' => 'stg_dean_review',
            'name' => 'College Dean Review Stage',
            'is_initial' => false,
            'is_terminal' => false,
        ]);

        $stateApproved = WorkflowState::create([
            'workflow_version_id' => $wfVersion->id,
            'code' => 'stg_final_approved',
            'name' => 'Officially Approved',
            'is_initial' => false,
            'is_terminal' => true,
        ]);

        $permId = (int) DB::table('permissions')->value('id');

        // Dept -> Dean (approve)
        WorkflowTransition::create([
            'from_state_id' => $stateDraft->id,
            'to_state_id' => $stateDean->id,
            'action_code' => 'approve',
            'permission_id' => $permId,
            'approval_policy' => 'single',
        ]);

        // Dean -> Dept (return for correction)
        WorkflowTransition::create([
            'from_state_id' => $stateDean->id,
            'to_state_id' => $stateDraft->id,
            'action_code' => 'return',
            'permission_id' => $permId,
            'approval_policy' => 'single',
        ]);

        // Dean -> Approved (approve)
        WorkflowTransition::create([
            'from_state_id' => $stateDean->id,
            'to_state_id' => $stateApproved->id,
            'action_code' => 'approve',
            'permission_id' => $permId,
            'approval_policy' => 'single',
        ]);

        $doc = Document::create([
            'reference_number' => 'WU/DOC/2026/444001',
            'title' => 'Laboratory Equipment Procurement Justification',
            'category_id' => $this->categoryId,
            'confidentiality_id' => $this->confidentialityId,
            'owner_unit_id' => $this->itUnit->id,
            'created_by' => $this->staffUser1->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $ver = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'ingestion_method' => 'digital_upload',
            'created_by' => $this->staffUser1->id,
            'created_at' => now(),
        ]);

        // Start workflow
        $instRes = $this->postJson('/api/v1/workflows/instances', [
            'workflow_definition_id' => $wfDef->id,
            'document_version_id' => $ver->id,
        ]);
        $instRes->assertStatus(201);
        $task1Id = $instRes->json('data.tasks.0.id');

        // Stage 1: Department Reviewer approves -> transitions to Dean stage
        $this->authenticateAs($this->staffUser2);
        $this->postJson("/api/v1/workflows/tasks/{$task1Id}/decide", [
            'decision' => 'approve',
            'reason' => 'Specifications verified by department committee',
        ])->assertStatus(200);

        // Stage 2: Dean returns for correction
        $task2 = WorkflowTask::where('instance_id', $instRes->json('data.id'))
            ->where('state_id', $stateDean->id)
            ->whereNull('closed_at')
            ->first();
        $this->assertNotNull($task2);

        $this->authenticateAs($this->staffUser3);
        $this->postJson("/api/v1/workflows/tasks/{$task2->id}/decide", [
            'decision' => 'return',
            'reason' => 'Price quotations must include three vendor estimates',
        ])->assertStatus(200);

        // Stage 3: Returned task in Dept stage is approved after correction
        $task3 = WorkflowTask::where('instance_id', $instRes->json('data.id'))
            ->where('state_id', $stateDraft->id)
            ->whereNull('closed_at')
            ->first();
        $this->assertNotNull($task3);

        $this->authenticateAs($this->staffUser2);
        $this->postJson("/api/v1/workflows/tasks/{$task3->id}/decide", [
            'decision' => 'approve',
            'reason' => 'Vendor estimates attached as requested',
        ])->assertStatus(200);

        // Stage 4: Dean reviews and performs final approval
        $task4 = WorkflowTask::where('instance_id', $instRes->json('data.id'))
            ->where('state_id', $stateDean->id)
            ->whereNull('closed_at')
            ->first();
        $this->assertNotNull($task4);

        $this->authenticateAs($this->staffUser3);
        $this->postJson("/api/v1/workflows/tasks/{$task4->id}/decide", [
            'decision' => 'approve',
            'reason' => 'Fully verified and approved',
        ])->assertStatus(200);

        $openTasks = WorkflowTask::where('instance_id', $instRes->json('data.id'))->whereNull('closed_at')->count();
        $this->assertEquals(0, $openTasks);
    }

    /**
     * Scenario 6: Workflow task delegation and operational assignment reassignment.
     */
    public function test_scenario_6_workflow_delegation_and_assignment_reassignment(): void
    {
        $this->authenticateAs($this->adminUser);

        $doc = Document::create([
            'reference_number' => 'WU/DOC/2026/333999',
            'title' => 'Faculty Governance Reassignment Case',
            'category_id' => $this->categoryId,
            'confidentiality_id' => $this->confidentialityId,
            'owner_unit_id' => $this->itUnit->id,
            'created_by' => $this->adminUser->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $ver = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'ingestion_method' => 'digital_upload',
            'created_by' => $this->adminUser->id,
            'created_at' => now(),
        ]);

        // 1. Workflow task delegation
        $wfDef = WorkflowDefinition::firstOrCreate(
            ['name' => 'Delegation Flow Test'],
            ['code' => 'DEL_TEST', 'active' => 1]
        );
        $wfVer = WorkflowVersion::create([
            'definition_id' => $wfDef->id,
            'version_number' => 1,
            'created_by' => $this->adminUser->id,
            'created_at' => now(),
        ]);
        $stateInit = WorkflowState::create([
            'workflow_version_id' => $wfVer->id,
            'code' => 'init_del',
            'name' => 'Initial Review',
            'is_initial' => true,
            'is_terminal' => false,
        ]);

        $inst = WorkflowInstance::create([
            'workflow_version_id' => $wfVer->id,
            'document_version_id' => $ver->id,
            'started_by' => $this->adminUser->id,
            'started_at' => now(),
        ]);

        $task = WorkflowTask::create([
            'instance_id' => $inst->id,
            'state_id' => $stateInit->id,
            'assigned_user_id' => $this->staffUser1->id,
            'assigned_unit_id' => $this->itUnit->id,
            'created_at' => now(),
        ]);

        $delResponse = $this->postJson("/api/v1/workflows/tasks/{$task->id}/delegate", [
            'target_user_id' => $this->staffUser2->id,
            'reason' => 'Primary reviewer on official university field duty',
        ]);
        $delResponse->assertStatus(200);
        $this->assertEquals($this->staffUser2->id, $delResponse->json('data.delegated_to_user_id'));

        // 2. Operational assignment reassignment
        $assignment = DocumentAssignment::create([
            'document_id' => $doc->id,
            'assigned_unit_id' => $this->itUnit->id,
            'assigned_user_id' => $this->staffUser1->id,
            'assigned_by' => $this->adminUser->id,
            'assigned_at' => now(),
            'instruction' => 'Conduct faculty system audit',
        ]);

        $reassignResponse = $this->postJson("/api/v1/assignments/{$assignment->id}/events", [
            'action' => 'reassigned',
            'target_user_id' => $this->staffUser3->id,
            'comment' => 'Reassigned due to workload rebalancing across departments',
        ]);
        $reassignResponse->assertStatus(201);
        $this->assertDatabaseHas('assignment_events', [
            'assignment_id' => $assignment->id,
            'action' => 'reassigned',
            'target_user_id' => $this->staffUser3->id,
        ]);
    }

    /**
     * Scenario 7: Physical original movement, transfer between offices, and custody acknowledgment.
     */
    public function test_scenario_7_physical_original_movement_and_custody_transfer(): void
    {
        $this->authenticateAs($this->adminUser);

        $doc = Document::create([
            'reference_number' => 'WU/DOC/2026/222111',
            'title' => 'Official Property Deed and Title Registry',
            'category_id' => $this->categoryId,
            'confidentiality_id' => $this->confidentialityId,
            'owner_unit_id' => $this->itUnit->id,
            'created_by' => $this->adminUser->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $phys = DB::table('physical_originals')->insertGetId([
            'document_id' => $doc->id,
            'physical_reference' => 'WU-PHYS-ORIG-77',
            'retention_requirement' => 'required',
            'created_by' => $this->adminUser->id,
            'created_at' => now(),
        ]);

        // Dispatch movement
        $movResponse = $this->postJson("/api/v1/documents/{$doc->id}/movements", [
            'from_unit_id' => $this->itUnit->id,
            'to_unit_id' => $this->academicUnit->id,
            'instruction' => 'Physical file transfer for permanent archival custody',
            'due_at' => now()->addDays(2)->toDateTimeString(),
        ]);
        $movResponse->assertStatus(201);
        $movementId = $movResponse->json('data.id');

        // Recipient office acknowledges receipt
        $ackResponse = $this->postJson("/api/v1/movements/{$movementId}/acknowledge");
        $ackResponse->assertStatus(200);

        // Record physical custody transfer
        DB::table('physical_custody_events')->insert([
            'physical_original_id' => $phys,
            'action' => 'transfer',
            'custodian_id' => $this->staffUser2->id,
            'actor_id' => $this->adminUser->id,
            'occurred_at' => now(),
            'reason' => 'Physical folder accepted into Academic Archive Shelf C1',
        ]);

        $this->assertDatabaseHas('physical_custody_events', [
            'physical_original_id' => $phys,
            'action' => 'transfer',
        ]);
    }

    /**
     * Scenario 8: Research proposal multi-level institutional review:
     * Department Review -> College Review -> Research Directorate -> Committee Review.
     */
    public function test_scenario_8_research_proposal_multi_level_institutional_review(): void
    {
        $this->authenticateAs($this->adminUser);

        $resWf = WorkflowDefinition::firstOrCreate(
            ['name' => 'Institutional Research Grant Proposal Review Pipeline'],
            ['code' => 'RES_GRANT_PIPELINE', 'active' => 1]
        );

        $resVersion = WorkflowVersion::create([
            'definition_id' => $resWf->id,
            'version_number' => 1,
            'created_by' => $this->adminUser->id,
            'published_at' => now(),
            'created_at' => now(),
        ]);

        $stDept = WorkflowState::create([
            'workflow_version_id' => $resVersion->id,
            'code' => 'dept_eval',
            'name' => 'Department Academic Evaluation',
            'is_initial' => true,
            'is_terminal' => false,
        ]);

        $stCollege = WorkflowState::create([
            'workflow_version_id' => $resVersion->id,
            'code' => 'college_eval',
            'name' => 'College Research Council Review',
            'is_initial' => false,
            'is_terminal' => false,
        ]);

        $stDirectorate = WorkflowState::create([
            'workflow_version_id' => $resVersion->id,
            'code' => 'directorate_eval',
            'name' => 'Research Directorate Validation',
            'is_initial' => false,
            'is_terminal' => false,
        ]);

        $stCommittee = WorkflowState::create([
            'workflow_version_id' => $resVersion->id,
            'code' => 'committee_eval',
            'name' => 'University Research Ethics Committee',
            'is_initial' => false,
            'is_terminal' => false,
        ]);

        $stApproved = WorkflowState::create([
            'workflow_version_id' => $resVersion->id,
            'code' => 'research_awarded',
            'name' => 'Research Grant Formally Awarded',
            'is_initial' => false,
            'is_terminal' => true,
        ]);

        $permId = (int) DB::table('permissions')->value('id');

        WorkflowTransition::create(['from_state_id' => $stDept->id, 'to_state_id' => $stCollege->id, 'action_code' => 'approve', 'permission_id' => $permId]);
        WorkflowTransition::create(['from_state_id' => $stCollege->id, 'to_state_id' => $stDirectorate->id, 'action_code' => 'approve', 'permission_id' => $permId]);
        WorkflowTransition::create(['from_state_id' => $stDirectorate->id, 'to_state_id' => $stCommittee->id, 'action_code' => 'approve', 'permission_id' => $permId]);
        WorkflowTransition::create([
            'from_state_id' => $stCommittee->id,
            'to_state_id' => $stApproved->id,
            'action_code' => 'approve',
            'permission_id' => $permId,
            'approval_policy' => 'majority',
            'quorum_count' => 2,
        ]);

        $resDoc = Document::create([
            'reference_number' => 'WU/RES/2026/001',
            'title' => 'AI-Driven Crop Disease Detection for South Wollo Farmers',
            'category_id' => $this->categoryId,
            'confidentiality_id' => $this->confidentialityId,
            'owner_unit_id' => $this->itUnit->id,
            'created_by' => $this->staffUser1->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $resVer = DocumentVersion::create([
            'document_id' => $resDoc->id,
            'version_number' => 1,
            'ingestion_method' => 'digital_upload',
            'created_by' => $this->staffUser1->id,
            'created_at' => now(),
        ]);

        $inst = $this->postJson('/api/v1/workflows/instances', [
            'workflow_definition_id' => $resWf->id,
            'document_version_id' => $resVer->id,
        ]);
        $inst->assertStatus(201);
        $t1 = $inst->json('data.tasks.0.id');

        // Dept Reviewer approves -> moves to College
        $this->authenticateAs($this->staffUser2);
        $this->postJson("/api/v1/workflows/tasks/{$t1}/decide", ['decision' => 'approve'])->assertStatus(200);

        // College Reviewer approves -> moves to Directorate
        $t2 = WorkflowTask::where('instance_id', $inst->json('data.id'))->where('state_id', $stCollege->id)->whereNull('closed_at')->value('id');
        $this->postJson("/api/v1/workflows/tasks/{$t2}/decide", ['decision' => 'approve'])->assertStatus(200);

        // Directorate Reviewer approves -> moves to Committee
        $t3 = WorkflowTask::where('instance_id', $inst->json('data.id'))->where('state_id', $stDirectorate->id)->whereNull('closed_at')->value('id');
        $this->postJson("/api/v1/workflows/tasks/{$t3}/decide", ['decision' => 'approve'])->assertStatus(200);

        // Committee review with 3 participants
        $t4 = WorkflowTask::where('instance_id', $inst->json('data.id'))->where('state_id', $stCommittee->id)->whereNull('closed_at')->first();
        $this->postJson("/api/v1/workflows/tasks/{$t4->id}/participants", [
            'participants' => [
                ['user_id' => $this->staffUser1->id, 'role' => 'lead_reviewer'],
                ['user_id' => $this->staffUser2->id, 'role' => 'reviewer'],
                ['user_id' => $this->staffUser3->id, 'role' => 'reviewer'],
            ],
        ])->assertStatus(200);

        // Reviewer 2 approves
        $this->authenticateAs($this->staffUser2);
        $this->postJson("/api/v1/workflows/tasks/{$t4->id}/decide", ['decision' => 'approve'])->assertStatus(202);

        // Reviewer 3 approves -> Majority reached!
        $this->authenticateAs($this->staffUser3);
        $this->postJson("/api/v1/workflows/tasks/{$t4->id}/decide", ['decision' => 'approve'])->assertStatus(200);

        $finalTasks = WorkflowTask::where('instance_id', $inst->json('data.id'))->whereNull('closed_at')->count();
        $this->assertEquals(0, $finalTasks);
    }

    /**
     * Scenario 9: Simple workflow (Administrative Notice without committee overhead).
     */
    public function test_scenario_9_simple_workflow_institutional_notice(): void
    {
        $this->authenticateAs($this->adminUser);

        $noticeDoc = Document::create([
            'reference_number' => 'WU/DOC/2026/333001',
            'title' => 'Campus Holiday Schedule Announcement',
            'summary' => 'Public notice of national holiday observance',
            'category_id' => $this->categoryId,
            'confidentiality_id' => $this->confidentialityId,
            'owner_unit_id' => $this->academicUnit->id,
            'created_by' => $this->adminUser->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        // Direct assignment to IT Department without heavy multi-stage workflow
        $assignNotice = $this->postJson("/api/v1/documents/{$noticeDoc->id}/assignments", [
            'assigned_unit_id' => $this->itUnit->id,
            'instruction' => 'Publish on university internal portal and campus bulletin boards',
        ]);
        $assignNotice->assertStatus(201);
        $assignId = $assignNotice->json('data.id');

        // Department acknowledges / completes notice directive
        $completeRes = $this->postJson("/api/v1/assignments/{$assignId}/events", [
            'action' => 'completed',
            'comment' => 'Notice published on student portal and faculty dashboard',
        ]);
        $completeRes->assertStatus(201);
        $this->assertDatabaseHas('assignment_events', [
            'assignment_id' => $assignId,
            'action' => 'completed',
        ]);
    }

    /**
     * Scenario 10: Delayed scanning (Scenario B) with Version 1 immutability.
     */
    public function test_scenario_10_delayed_scanning_preserves_version_1_immutability(): void
    {
        $this->authenticateAs($this->adminUser);

        // 1. Initial paper registration (Version 1 with 0 files, ingestion_method = manually_registered)
        $doc = Document::create([
            'reference_number' => 'WU/DOC/2026/777001',
            'title' => 'Incoming Historical Land Deed Document',
            'category_id' => $this->categoryId,
            'confidentiality_id' => $this->confidentialityId,
            'owner_unit_id' => $this->academicUnit->id,
            'created_by' => $this->adminUser->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $v1 = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'ingestion_method' => 'manually_registered',
            'created_by' => $this->adminUser->id,
            'change_note' => 'Physical document received, deferred scanning logged',
            'created_at' => now(),
        ]);

        $physOriginal = DB::table('physical_originals')->insertGetId([
            'document_id' => $doc->id,
            'physical_reference' => 'PHYS-DEED-001',
            'retention_requirement' => 'required',
            'created_by' => $this->adminUser->id,
            'created_at' => now(),
        ]);

        // 2. Later: Scan upload via /api/v1/documents/{id}/digitize
        $scanFile = UploadedFile::fake()->create('scanned_land_deed.pdf', 800, 'application/pdf');

        $digitizeResponse = $this->postJson("/api/v1/documents/{$doc->id}/digitize", [
            'file' => $scanFile,
            'physical_original_id' => $physOriginal,
            'verification_note' => 'Original deed scanned on high-resolution flatbed scanner',
        ]);

        $digitizeResponse->assertStatus(201);
        $v2Number = $digitizeResponse->json('data.version_number');
        $this->assertEquals(2, $v2Number);

        // Verify Version 1 was NEVER mutated!
        $v1Check = DocumentVersion::find($v1->id);
        $this->assertEquals('manually_registered', $v1Check->ingestion_method);
        $this->assertEquals(1, $v1Check->version_number);

        // Verify Digitization record links physical original to Version 2
        $this->assertDatabaseHas('digitization_records', [
            'physical_original_id' => $physOriginal,
            'document_version_id' => $digitizeResponse->json('data.id'),
            'verification_status' => 'accepted',
        ]);
    }

    /**
     * Bilingual OCR Text Extraction and Full-Text Search.
     */
    public function test_bilingual_ocr_extraction_and_normalized_amharic_search(): void
    {
        $this->authenticateAs($this->adminUser);

        $doc = Document::create([
            'reference_number' => 'WU/DOC/2026/666001',
            'title' => 'የወሎ ዩኒቨርሲቲ የጥናትና ምርምር መመሪያ',
            'summary' => 'Wollo University Research Guidelines and Ethics Code',
            'category_id' => $this->categoryId,
            'confidentiality_id' => $this->confidentialityId,
            'owner_unit_id' => $this->itUnit->id,
            'created_by' => $this->adminUser->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $ver = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'ingestion_method' => 'scanned_paper',
            'created_by' => $this->adminUser->id,
            'created_at' => now(),
        ]);

        $fileObject = DB::table('file_objects')->insertGetId([
            'storage_disk' => 'local',
            'storage_key' => 'documents/test.pdf',
            'original_filename' => 'guidelines.pdf',
            'detected_mime' => 'application/pdf',
            'byte_size' => 1024,
            'sha256' => hash('sha256', 'test'),
            'uploaded_by' => $this->adminUser->id,
            'uploaded_at' => now(),
            'scan_status' => 'clean',
            'scanned_at' => now(),
            'storage_status' => 'ready',
        ]);

        $ocrResponse = $this->postJson("/api/v1/documents/{$doc->id}/versions/{$ver->id}/extractions", [
            'file_object_id' => $fileObject,
            'raw_text' => 'የአካዳሚክ፡ምርምር፡ሐሳብ Technical Report for Wollo University',
            'language_detected' => 'amh+eng',
            'confidence_score' => 98.5,
        ]);
        $ocrResponse->assertStatus(201);

        $extraction = DB::table('document_text_extractions')->where('document_version_id', $ver->id)->first();
        $this->assertStringContainsString('ሀሳብ', $extraction->searchable_text);
        $this->assertStringNotContainsString('፡', $extraction->searchable_text);

        $searchResponse = $this->getJson('/api/v1/search/documents?q=ሀሳብ');
        $searchResponse->assertStatus(200);
        $items = $searchResponse->json('data');
        $this->assertNotEmpty($items);
        $this->assertEquals($doc->id, $items[0]['id']);
    }

    /**
     * Timeline compilation endpoint.
     */
    public function test_document_timeline_endpoint(): void
    {
        $this->authenticateAs($this->adminUser);

        $doc = Document::create([
            'reference_number' => 'WU/DOC/2026/555001',
            'title' => 'Timeline Inspection Master Document',
            'category_id' => $this->categoryId,
            'confidentiality_id' => $this->confidentialityId,
            'owner_unit_id' => $this->academicUnit->id,
            'created_by' => $this->adminUser->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $timelineResponse = $this->getJson("/api/v1/documents/{$doc->id}/timeline");
        $timelineResponse->assertStatus(200);
        $timeline = $timelineResponse->json('data');

        $this->assertNotEmpty($timeline);
        $this->assertEquals('document_registered', $timeline[0]['event_type']);
    }
}
