<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ConfidentialityLevel;
use App\Models\CorrespondenceType;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\OrganizationUnit;
use App\Models\SignatureRequest;
use App\Models\UnitType;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditRemediation20261126Test extends TestCase
{
    use RefreshDatabase;

    protected OrganizationUnit $unitA;

    protected OrganizationUnit $unitB;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $this->admin = User::where('email', 'admin@wu.edu.et')->firstOrFail();
        $this->unitA = OrganizationUnit::firstOrFail();
        $deptType = UnitType::where('code', 'department')->first()
            ?? UnitType::create(['code' => 'department', 'name' => 'Department', 'active' => 1]);

        $this->unitB = OrganizationUnit::create([
            'parent_id' => $this->unitA->id,
            'unit_type_id' => $deptType->id,
            'code' => 'AUD_REM_B_'.Str::random(4),
            'name' => 'Audit Remediation Unit B',
            'active' => true,
        ]);
    }

    public function test_roles_view_only_user_cannot_create_office_procedure(): void
    {
        $viewer = User::create([
            'name' => 'Roles Viewer',
            'email' => 'roles.viewer.'.Str::random(5).'@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($viewer, 'roles.view', 'global');
        $this->authenticateAs($viewer);

        $type = CorrespondenceType::firstOrFail();

        $res = $this->postJson('/api/v1/procedures', [
            'unit_id' => $this->unitB->id,
            'correspondence_type_id' => $type->id,
            'purpose_code' => 'general_correspondence',
            'receipt_mode' => 'direct_office',
            'issue_mode' => 'direct_issue',
            'confirmation_source' => 'Unauthorized Directive',
            'effective_from' => now()->toDateString(),
        ]);

        $res->assertStatus(403);
    }

    public function test_workflow_configure_user_can_create_workflow_issue_procedure_in_own_unit_but_not_foreign_unit(): void
    {
        $wfDef = WorkflowDefinition::firstOrFail();
        WorkflowVersion::firstOrCreate(
            ['definition_id' => $wfDef->id, 'version_number' => 1],
            ['created_by' => $this->admin->id, 'created_at' => now(), 'published_at' => now()]
        );

        $unitAConfigUser = User::create([
            'name' => 'Unit A Workflow Configurator',
            'email' => 'wf.cfg.'.Str::random(5).'@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($unitAConfigUser, 'workflow.configure', 'unit', $this->unitA->id);
        $this->authenticateAs($unitAConfigUser);

        $type = CorrespondenceType::firstOrFail();

        // Cross-unit target must be denied
        $forbidden = $this->postJson('/api/v1/procedures', [
            'unit_id' => $this->unitB->id,
            'correspondence_type_id' => $type->id,
            'purpose_code' => 'cross_unit_test',
            'receipt_mode' => 'direct_office',
            'issue_mode' => 'workflow_review',
            'confirmation_source' => 'Cross Unit Attempt',
            'effective_from' => now()->toDateString(),
        ]);
        $forbidden->assertStatus(403);

        // Own-unit target with workflow_review must persist issue_mode = 'workflow' (not 'blocked')
        $allowed = $this->postJson('/api/v1/procedures', [
            'unit_id' => $this->unitA->id,
            'correspondence_type_id' => $type->id,
            'purpose_code' => 'wf_mode_test_'.Str::random(4),
            'receipt_mode' => 'designated_registry',
            'issue_mode' => 'workflow_review',
            'confirmation_source' => 'Senate Resolution 2026/11',
            'effective_from' => now()->toDateString(),
        ]);

        $allowed->assertStatus(201)
            ->assertJsonPath('data.receipt_mode', 'via_registry')
            ->assertJsonPath('data.issue_mode', 'workflow');
        $this->assertNotNull($allowed->json('data.workflow_version_id'));
    }

    public function test_non_superadmin_with_users_deactivate_cannot_suspend_super_admin_even_when_another_admin_exists(): void
    {
        $secondAdmin = User::create([
            'name' => 'Second Super Admin',
            'email' => 'admin2.'.Str::random(5).'@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($secondAdmin, 'super_admin', 'global');

        $hrOperator = User::create([
            'name' => 'HR Status Operator',
            'email' => 'hr.status.'.Str::random(5).'@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($hrOperator, 'users.deactivate', 'global');
        $this->grantPermissionToUser($hrOperator, 'users.update', 'global');

        $this->authenticateAs($hrOperator);

        $statusRes = $this->patchJson("/api/v1/users/{$secondAdmin->id}/status", [
            'status' => 'locked',
        ]);
        $statusRes->assertStatus(403);
        $this->assertSame('active', $secondAdmin->fresh()->status);

        $membershipRes = $this->postJson("/api/v1/users/{$secondAdmin->id}/memberships", [
            'unit_id' => $this->unitB->id,
            'position_title' => 'Tampered Membership',
        ]);
        $membershipRes->assertStatus(403);
    }

    public function test_signatures_listing_is_scoped_to_authorized_units_and_participants(): void
    {
        $cat = DocumentCategory::firstOrFail();
        $conf = ConfidentialityLevel::where('code', 'internal')->first() ?? ConfidentialityLevel::firstOrFail();

        $unitAOfficer = User::create([
            'name' => 'Unit A Signer',
            'email' => 'sig.a.'.Str::random(5).'@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($unitAOfficer, 'signatures.request', 'unit', $this->unitA->id);

        $unitBOfficer = User::create([
            'name' => 'Unit B Signer',
            'email' => 'sig.b.'.Str::random(5).'@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($unitBOfficer, 'signatures.request', 'unit', $this->unitB->id);

        $docA = Document::create([
            'reference_number' => 'WU/SIG/A/'.Str::random(6),
            'title' => 'Unit A Signature Document',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $unitAOfficer->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);
        $verA = DocumentVersion::create(['document_id' => $docA->id, 'version_number' => 1, 'created_by' => $unitAOfficer->id, 'created_at' => now()]);
        $reqA = SignatureRequest::create([
            'document_version_id' => $verA->id,
            'requested_by' => $unitAOfficer->id,
            'signer_id' => $unitAOfficer->id,
            'requested_at' => now(),
            'purpose' => 'Unit A Endorsement',
        ]);

        $docB = Document::create([
            'reference_number' => 'WU/SIG/B/'.Str::random(6),
            'title' => 'Unit B Confidential Signature Document',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $this->unitB->id,
            'created_by' => $unitBOfficer->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);
        $verB = DocumentVersion::create(['document_id' => $docB->id, 'version_number' => 1, 'created_by' => $unitBOfficer->id, 'created_at' => now()]);
        $reqB = SignatureRequest::create([
            'document_version_id' => $verB->id,
            'requested_by' => $unitBOfficer->id,
            'signer_id' => $unitBOfficer->id,
            'requested_at' => now(),
            'purpose' => 'Unit B Secret Endorsement',
        ]);

        $this->authenticateAs($unitAOfficer);
        $res = $this->getJson('/api/v1/signatures');
        $res->assertStatus(200);

        $returnedIds = collect($res->json('data.signatures'))->pluck('id')->all();
        $this->assertContains($reqA->id, $returnedIds);
        $this->assertNotContains($reqB->id, $returnedIds);
    }

    public function test_template_render_with_submit_for_approval_fails_explicitly_when_standard_workflow_unpublished(): void
    {
        $cat = DocumentCategory::firstOrFail();
        $conf = ConfidentialityLevel::firstOrFail();

        $this->authenticateAs($this->admin);

        $tplRes = $this->postJson('/api/v1/templates', [
            'code' => 'TPL_AUD_'.Str::random(5),
            'name' => 'Audit Template Approval Test',
            'category_id' => $cat->id,
            'owner_unit_id' => $this->unitA->id,
            'visibility_scope' => 'unit_restricted',
            'is_active' => true,
        ]);
        $tplRes->assertStatus(201);
        $templateId = (int) $tplRes->json('data.id');

        $verRes = $this->postJson("/api/v1/templates/{$templateId}/versions", [
            'schema_definition' => ['fields' => [['name' => 'subject', 'type' => 'string', 'required' => true]]],
            'body_template_html' => '<p>{{subject}}</p>',
        ]);
        $verRes->assertStatus(201);
        $versionId = (int) $verRes->json('data.id');

        $pubRes = $this->postJson("/api/v1/templates/{$templateId}/versions/{$versionId}/publish");
        $pubRes->assertStatus(200);

        $wfDef = WorkflowDefinition::where('code', 'standard_doc_approval')->first();
        if ($wfDef) {
            DB::table('workflow_versions')
                ->where('definition_id', $wfDef->id)
                ->update(['published_at' => null]);
        }

        $docTitle = 'Should Not Be Saved Orphan '.Str::random(6);
        $renderRes = $this->postJson("/api/v1/templates/{$templateId}/render", [
            'template_data' => ['subject' => 'Test Subject'],
            'title' => $docTitle,
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $this->unitA->id,
            'submit_for_approval' => true,
        ]);

        $renderRes->assertStatus(422);
        $this->assertDatabaseMissing('documents', ['title' => $docTitle]);
    }
}
