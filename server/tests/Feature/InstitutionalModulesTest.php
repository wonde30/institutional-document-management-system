<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\OrganizationUnit;
use App\Models\ProjectType;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowState;
use App\Models\WorkflowTask;
use App\Models\WorkflowVersion;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstitutionalModulesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $dean;
    protected User $reviewer;
    protected OrganizationUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $this->unit = OrganizationUnit::first();
        $this->admin = User::where('email', 'admin@wu.edu.et')->first();

        $this->dean = User::create([
            'name' => 'Dr. Dean Institutional',
            'email' => 'dean.inst@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->dean, 'department_head');

        $this->reviewer = User::create([
            'name' => 'Dr. Reviewer',
            'email' => 'reviewer@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->reviewer, 'document_controller');
    }

    public function test_research_output_registration_and_publication(): void
    {
        $this->authenticateAs($this->dean);

        // 1. Store research output
        $response = $this->postJson('/api/v1/research/outputs', [
            'title' => 'Geothermal Potential in South Wollo Rift Zone',
            'office_id' => $this->unit->id,
            'completion_date' => '2026-10-29',
            'research_type' => 'applied_geosciences',
            'external_reference' => 'WU-RES-GEOTH-2026',
            'notes' => 'Faculty research funded by national initiative',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $recordId = $response->json('data.id');
        $this->assertDatabaseHas('research_records', ['id' => $recordId]);

        // 2. Toggle publication
        $pubRes = $this->postJson("/api/v1/research/outputs/{$recordId}/toggle-publication", [
            'action' => 'publish',
        ]);

        $pubRes->assertStatus(200)
            ->assertJsonPath('data.publication_status', 'published');

        // 3. List research outputs
        $listRes = $this->getJson('/api/v1/research/outputs');
        $listRes->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_staff_physical_records_and_custody_determination(): void
    {
        $this->authenticateAs($this->admin);

        $response = $this->postJson('/api/v1/staff/records', [
            'title' => 'Faculty Dossier - Original Terminal Credentials',
            'office_id' => $this->unit->id,
            'location_description' => 'Central Archives Safe Vault Room 102',
            'physical_retention' => 'required',
            'physical_reference' => 'HR-VAULT-2026-A1',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('physical_originals', [
            'physical_reference' => 'HR-VAULT-2026-A1',
            'retention_requirement' => 'required',
        ]);

        $listRes = $this->getJson('/api/v1/staff/records');
        $listRes->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_project_proposal_registration_and_listing(): void
    {
        $this->authenticateAs($this->dean);

        $projectType = ProjectType::first();

        $response = $this->postJson('/api/v1/projects', [
            'title' => 'Smart Solar Irrigation for Highland Agriculture',
            'office_id' => $this->unit->id,
            'project_type_id' => $projectType->id,
            'summary' => 'Innovation concept for regional water sustainability',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('project_proposals', [
            'project_type_id' => $projectType->id,
        ]);

        $listRes = $this->getJson('/api/v1/projects');
        $listRes->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_signature_requests_and_stamp_authorizations(): void
    {
        $this->authenticateAs($this->dean);

        $cat = DocumentCategory::first();
        $conf = \App\Models\ConfidentialityLevel::first();

        $doc = Document::create([
            'reference_number' => 'WU/DOC/2026/888801',
            'title' => 'MOU on Academic Exchange',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $this->unit->id,
            'created_by' => $this->dean->id,
            'document_date' => '2026-10-29',
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $ver = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $this->dean->id,
            'change_note' => 'Initial submission for presidential signature',
            'created_at' => now(),
        ]);

        $sigRes = $this->postJson('/api/v1/signatures', [
            'document_version_id' => $ver->id,
            'signer_id' => $this->admin->id,
            'purpose' => 'Presidential Endorsement',
            'expires_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
        ]);

        $sigRes->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('signature_requests', [
            'document_version_id' => $ver->id,
            'signer_id' => $this->admin->id,
        ]);

        $listRes = $this->getJson('/api/v1/signatures');
        $listRes->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_workflow_task_decision_lifecycle(): void
    {
        $this->authenticateAs($this->dean);

        $cat = DocumentCategory::first();
        $conf = \App\Models\ConfidentialityLevel::first();

        $doc = Document::create([
            'reference_number' => 'WU/DOC/2026/777701',
            'title' => 'Curriculum Revision Proposal for Electrical Engineering',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $this->unit->id,
            'created_by' => $this->dean->id,
            'document_date' => '2026-10-29',
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $ver = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $this->dean->id,
            'change_note' => 'First revision draft',
            'created_at' => now(),
        ]);

        $wfDef = WorkflowDefinition::first();
        $wfVer = WorkflowVersion::create([
            'definition_id' => $wfDef->id,
            'version_number' => 1,
            'created_by' => $this->admin->id,
            'published_at' => now(),
        ]);

        $state = WorkflowState::create([
            'workflow_version_id' => $wfVer->id,
            'code' => 'under_review',
            'name' => 'Under Review',
            'is_initial' => 1,
            'is_terminal' => 0,
        ]);

        $instance = WorkflowInstance::create([
            'document_version_id' => $ver->id,
            'workflow_version_id' => $wfVer->id,
            'started_by' => $this->dean->id,
            'started_at' => now(),
        ]);

        $task = WorkflowTask::create([
            'instance_id' => $instance->id,
            'state_id' => $state->id,
            'assigned_user_id' => $this->reviewer->id,
            'assigned_unit_id' => $this->unit->id,
            'due_at' => now()->addDays(3),
            'created_at' => now(),
        ]);

        // Switch to reviewer
        $this->authenticateAs($this->reviewer);

        // List tasks
        $tasksRes = $this->getJson('/api/v1/workflows/tasks?scope=mine');
        $tasksRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // Decide task
        $decideRes = $this->postJson("/api/v1/workflows/tasks/{$task->id}/decide", [
            'decision' => 'approve',
            'reason' => 'Curriculum satisfies university academic senate criteria.',
        ]);

        $decideRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('workflow_task_decisions', [
            'task_id' => $task->id,
            'decision' => 'approve',
        ]);

        $this->assertNotNull($task->fresh()->closed_at);
    }

    public function test_operations_status_and_recovery_drills(): void
    {
        $this->authenticateAs($this->admin);

        $response = $this->getJson('/api/v1/operations/status');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'integrity_checks',
                    'backups',
                    'restore_drills',
                    'storage_driver',
                ],
            ]);
    }
}
