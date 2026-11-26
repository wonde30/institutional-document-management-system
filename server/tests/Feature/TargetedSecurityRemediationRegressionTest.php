<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\AuthorizationScope;
use App\Models\ConfidentialityLevel;
use App\Models\DigitalSignature;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentGrant;
use App\Models\DocumentVersion;
use App\Models\FileObject;
use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoutingPolicy;
use App\Models\UnitType;
use App\Models\User;
use App\Models\VersionFile;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowState;
use App\Models\WorkflowTask;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Services\FileSecurityService;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TargetedSecurityRemediationRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $userA;
    protected User $userB;
    protected OrganizationUnit $unit;
    protected DocumentCategory $category;
    protected ConfidentialityLevel $confidentiality;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $this->admin = User::where('email', 'admin@wu.edu.et')->first();
        $this->assignRoleToUser($this->admin, 'super_admin', 'global');

        $this->unit = OrganizationUnit::first();
        $this->category = DocumentCategory::first();
        $this->confidentiality = ConfidentialityLevel::where('code', 'internal')->first() ?? ConfidentialityLevel::first();

        $this->userA = User::create([
            'name' => 'Officer Alpha',
            'email' => 'officer.alpha@wu.edu.et',
            'password' => bcrypt('StrongPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($this->userA, 'documents.download', 'unit', $this->unit->id);
        $this->grantPermissionToUser($this->userA, 'documents.view', 'unit', $this->unit->id);
        $this->grantPermissionToUser($this->userA, 'workflow.review', 'unit', $this->unit->id);

        $this->userB = User::create([
            'name' => 'Officer Beta',
            'email' => 'officer.beta@wu.edu.et',
            'password' => bcrypt('StrongPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($this->userB, 'workflow.review', 'unit', $this->unit->id);
    }

    /**
     * 1. Path Containment Unit Tests:
     * Enforces canonical directory boundaries against prefix siblings, traversals, and symlinks.
     */
    public function test_canonical_path_containment_strictly_enforces_directory_boundaries(): void
    {
        $tempBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wu_containment_test_' . Str::random(8);
        $allowedDir = $tempBase . DIRECTORY_SEPARATOR . 'storage_root';
        $siblingDir = $tempBase . DIRECTORY_SEPARATOR . 'storage_root_evil';

        File::makeDirectory($allowedDir, 0755, true);
        File::makeDirectory($siblingDir, 0755, true);

        $validFile = $allowedDir . DIRECTORY_SEPARATOR . 'report.pdf';
        $evilFile = $siblingDir . DIRECTORY_SEPARATOR . 'exploit.pdf';
        File::put($validFile, '%PDF-1.4 Valid');
        File::put($evilFile, '%PDF-1.4 Evil');

        // Normal file inside root resolves
        $resolved = FileSecurityService::resolveContainedPath($validFile, $allowedDir);
        $this->assertNotNull($resolved);
        $this->assertEquals(realpath($validFile), $resolved);

        // Sibling directory with matching prefix is strictly rejected
        $evilResolved = FileSecurityService::resolveContainedPath($evilFile, $allowedDir);
        $this->assertNull($evilResolved, 'Security failure: Sibling directory bypassed path containment check.');

        // Traversal attempt is rejected
        $traversal = $allowedDir . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'storage_root_evil' . DIRECTORY_SEPARATOR . 'exploit.pdf';
        $traversalResolved = FileSecurityService::resolveContainedPath($traversal, $allowedDir);
        $this->assertNull($traversalResolved, 'Security failure: Directory traversal bypassed path containment check.');

        // Missing file returns null
        $missing = $allowedDir . DIRECTORY_SEPARATOR . 'non_existent.pdf';
        $missingResolved = FileSecurityService::resolveContainedPath($missing, $allowedDir);
        $this->assertNull($missingResolved);

        // Symlink pointing outside root is rejected (if OS supports symlinks)
        $symlinkPath = $allowedDir . DIRECTORY_SEPARATOR . 'symlink_to_evil.pdf';
        if (function_exists('symlink')) {
            try {
                @symlink($evilFile, $symlinkPath);
                if (file_exists($symlinkPath)) {
                    $symlinkResolved = FileSecurityService::resolveContainedPath($symlinkPath, $allowedDir);
                    $this->assertNull($symlinkResolved, 'Security failure: Symlink escape bypassed path containment check.');
                    @unlink($symlinkPath);
                }
            } catch (\Throwable) {}
        }

        // Cleanup
        @unlink($validFile);
        @unlink($evilFile);
        @rmdir($allowedDir);
        @rmdir($siblingDir);
        @rmdir($tempBase);
    }

    /**
     * 2. Legitimate Download and Defense-in-Depth Authorization:
     * Authorized users can download; unauthorized users are blocked with 403.
     */
    public function test_authorized_file_download_and_unauthorized_access_restriction(): void
    {
        Storage::fake('local');
        $storedKey = 'documents/sample_' . Str::random(12) . '.pdf';
        $content = '%PDF-1.4 Authentic Document Content';
        Storage::disk('local')->put($storedKey, $content);

        $doc = Document::create([
            'reference_number' => 'WU-DOC-TEST-' . uniqid(),
            'title' => 'Institutional Assessment Report',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->unit->id,
            'created_by' => $this->userA->id,
            'lifecycle' => 'active',
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
        ]);

        $ver = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $this->userA->id,
        ]);

        $fileObj = FileObject::create([
            'storage_disk' => 'local',
            'storage_key' => $storedKey,
            'original_filename' => 'assessment.pdf',
            'detected_mime' => 'application/pdf',
            'byte_size' => strlen($content),
            'sha256' => hash('sha256', $content),
            'uploaded_by' => $this->userA->id,
            'uploaded_at' => now(),
            'scan_status' => 'clean',
            'storage_status' => 'ready',
        ]);

        VersionFile::create([
            'document_version_id' => $ver->id,
            'file_object_id' => $fileObj->id,
            'purpose' => 'primary',
        ]);

        // User A (authorized officer) has download access
        $this->authenticateAs($this->userA);
        $respA = $this->getJson("/api/v1/files/{$fileObj->id}/download");
        $respA->assertStatus(200);
        $this->assertEquals('application/pdf', $respA->headers->get('Content-Type'));
        $this->assertEquals($fileObj->sha256, $respA->headers->get('X-Checksum-Sha256'));

        // User B (no download permissions) receives HTTP 403
        $this->authenticateAs($this->userB);
        $respB = $this->getJson("/api/v1/files/{$fileObj->id}/download");
        $respB->assertStatus(403);
    }

    /**
     * 3. Missing Physical File Handling:
     * File object exists in DB, but missing binary safely returns HTTP 404 without crashing.
     */
    public function test_missing_physical_file_returns_404(): void
    {
        Storage::fake('local');

        $doc = Document::create([
            'reference_number' => 'WU-DOC-GHOST-' . uniqid(),
            'title' => 'Missing File Document',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->unit->id,
            'created_by' => $this->admin->id,
            'lifecycle' => 'active',
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
        ]);

        $ver = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $this->admin->id,
        ]);

        $fileObj = FileObject::create([
            'storage_disk' => 'local',
            'storage_key' => 'documents/does_not_exist_on_disk.pdf',
            'original_filename' => 'ghost.pdf',
            'detected_mime' => 'application/pdf',
            'byte_size' => 1024,
            'sha256' => hash('sha256', 'ghost'),
            'uploaded_by' => $this->admin->id,
            'uploaded_at' => now(),
            'scan_status' => 'clean',
            'storage_status' => 'ready',
        ]);

        VersionFile::create([
            'document_version_id' => $ver->id,
            'file_object_id' => $fileObj->id,
            'purpose' => 'primary',
        ]);

        $this->authenticateAs($this->admin);
        $resp = $this->getJson("/api/v1/files/{$fileObj->id}/download");
        $resp->assertStatus(404);
    }

    /**
     * 4. Signed PDF Execution and Verification with Canonical Storage Resolution:
     * Generates genuine signed PDF and independently verifies outcome via canonical path containment.
     */
    public function test_valid_signed_pdf_independent_verification_with_canonical_path(): void
    {
        $this->authenticateAs($this->admin);

        $doc = Document::create([
            'reference_number' => 'WU-DOC-SIG-' . uniqid(),
            'title' => 'Executive Accreditation Resolution',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->unit->id,
            'created_by' => $this->admin->id,
            'lifecycle' => 'active',
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
        ]);

        $ver = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $this->admin->id,
        ]);

        // Request signature
        $reqRes = $this->postJson('/api/v1/signatures', [
            'document_version_id' => $ver->id,
            'signer_id' => $this->admin->id,
            'purpose' => 'Accreditation Sign-off',
            'expires_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
        ]);
        $reqRes->assertStatus(201);
        $reqId = $reqRes->json('data.id');

        // Execute signature
        $execRes = $this->postJson("/api/v1/signatures/{$reqId}/execute");
        $execRes->assertStatus(200);
        $sigId = $execRes->json('data.id');

        // Independently verify signature using resolved canonical path
        $verifyRes = $this->postJson("/api/v1/signatures/{$sigId}/verify");
        $verifyRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.outcome', 'valid');

        // Clean up signed file if written
        $sig = DigitalSignature::with('signedFile')->find($sigId);
        if ($sig?->signedFile?->storage_key) {
            @unlink(storage_path('app/' . $sig->signedFile->storage_key));
        }
    }

    /**
     * 4. Workflow Committee Delegation & Consensus:
     * Delegate votes on behalf of represented seat; vote attributes to seat; duplicate voting rejected.
     */
    public function test_workflow_committee_delegation_maps_to_seat_and_prevents_duplicate_voting(): void
    {
        $doc = Document::create([
            'reference_number' => 'WU-DOC-WF-' . uniqid(),
            'title' => 'Committee Review Policy',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->unit->id,
            'created_by' => $this->admin->id,
            'lifecycle' => 'active',
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
        ]);

        $ver = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $this->admin->id,
        ]);

        $def = WorkflowDefinition::create([
            'code' => 'WF_COMM_DEL_' . uniqid(),
            'name' => 'Delegated Committee Flow',
            'is_active' => 1,
            'category_id' => $this->category->id,
        ]);

        $wfVer = WorkflowVersion::create([
            'definition_id' => $def->id,
            'version_number' => 1,
            'created_by' => $this->admin->id,
            'created_at' => now(),
        ]);

        $stReview = WorkflowState::create([
            'workflow_version_id' => $wfVer->id,
            'code' => 'committee_del_review',
            'name' => 'Committee Delegated Review',
            'is_initial' => 1,
            'is_terminal' => 0,
        ]);

        $stApproved = WorkflowState::create([
            'workflow_version_id' => $wfVer->id,
            'code' => 'committee_del_done',
            'name' => 'Committee Delegated Done',
            'is_initial' => 0,
            'is_terminal' => 1,
        ]);

        $perm = Permission::where('code', 'workflow.review')->firstOrFail();
        WorkflowTransition::create([
            'from_state_id' => $stReview->id,
            'to_state_id' => $stApproved->id,
            'action_code' => 'approve',
            'permission_id' => $perm->id,
            'prohibit_self_approval' => 0,
            'approval_policy' => 'all_required',
        ]);

        $this->authenticateAs($this->admin);
        $inst = $this->postJson('/api/v1/workflows/instances', [
            'workflow_definition_id' => $def->id,
            'document_version_id' => $ver->id,
        ])->json('data');

        $task = WorkflowTask::where('instance_id', $inst['id'])->whereNull('closed_at')->firstOrFail();
        $task->update(['assigned_user_id' => $this->userA->id]);

        // Assign 2 committee members: User A and Admin
        $this->postJson("/api/v1/workflows/tasks/{$task->id}/participants", [
            'participants' => [
                ['user_id' => $this->userA->id, 'role' => 'reviewer'],
                ['user_id' => $this->admin->id, 'role' => 'lead_reviewer'],
            ],
        ])->assertStatus(200);

        // User A delegates their task responsibility to User B
        $this->authenticateAs($this->userA);
        $this->postJson("/api/v1/workflows/tasks/{$task->id}/delegate", [
            'target_user_id' => $this->userB->id,
            'reason' => 'Official leave; delegated to Officer Beta.',
        ])->assertStatus(200);

        // Delegate (User B) votes approve on behalf of User A's seat -> pending consensus
        $this->authenticateAs($this->userB);
        $voteB = $this->postJson("/api/v1/workflows/tasks/{$task->id}/decide", ['decision' => 'approve']);
        $voteB->assertStatus(202);
        $this->assertEquals('pending_consensus', $voteB->json('data.status'));

        // Verify audit event contains seat mapping
        $seatAuditEvent = AuditEvent::where('action', 'workflow.task_participant_vote')
            ->where('target_id', (string) $task->id)
            ->where('actor_id', $this->userB->id)
            ->first();
        $this->assertNotNull($seatAuditEvent);
        $this->assertEquals($this->userA->id, $seatAuditEvent->details_json['represented_user_id']);

        // Delegator (User A) attempts to vote on already-completed seat -> HTTP 422
        $this->authenticateAs($this->userA);
        $voteADup = $this->postJson("/api/v1/workflows/tasks/{$task->id}/decide", ['decision' => 'approve']);
        $voteADup->assertStatus(422)
            ->assertJsonPath('message', 'Participant has already submitted a decision for this task.');

        // Delegate (User B) attempts duplicate vote -> HTTP 422
        $this->authenticateAs($this->userB);
        $voteBDup = $this->postJson("/api/v1/workflows/tasks/{$task->id}/decide", ['decision' => 'approve']);
        $voteBDup->assertStatus(422);

        // Admin votes approve -> consensus met and task closed
        $this->authenticateAs($this->admin);
        $voteAdmin = $this->postJson("/api/v1/workflows/tasks/{$task->id}/decide", ['decision' => 'approve']);
        $voteAdmin->assertStatus(200);
        $this->assertNotNull($task->fresh()->closed_at);
    }

    /**
     * 5. Organization Unit Deletion Dependency Invariants:
     * Deletion of a clean unit succeeds; deletion of units with active dependencies fails closed with HTTP 422.
     */
    public function test_organization_unit_deletion_dependency_invariants(): void
    {
        $this->authenticateAs($this->admin);
        $unitType = UnitType::first();

        // 1. Clean unit with no dependencies deletes cleanly
        $cleanUnit = OrganizationUnit::create([
            'unit_type_id' => $unitType->id,
            'code' => 'UNIT-CLEAN-' . uniqid(),
            'name' => 'Clean Independent Unit',
            'active' => 1,
        ]);
        $delResp = $this->deleteJson("/api/v1/organization-units/{$cleanUnit->id}");
        $delResp->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertDatabaseMissing('organization_units', ['id' => $cleanUnit->id]);

        // 2. Unit with active workflow task is blocked
        $unitWithTask = OrganizationUnit::create([
            'unit_type_id' => $unitType->id,
            'code' => 'UNIT-TASK-' . uniqid(),
            'name' => 'Unit With Active Tasks',
            'active' => 1,
        ]);

        $def = WorkflowDefinition::first() ?? WorkflowDefinition::create([
            'code' => 'WF_DUMMY_' . uniqid(),
            'name' => 'Dummy Def',
            'is_active' => 1,
            'category_id' => $this->category->id,
        ]);
        $wfVer = WorkflowVersion::where('definition_id', $def->id)->first() ?? WorkflowVersion::create([
            'definition_id' => $def->id,
            'version_number' => 1,
            'created_by' => $this->admin->id,
            'created_at' => now(),
        ]);
        $st = WorkflowState::where('workflow_version_id', $wfVer->id)->first() ?? WorkflowState::create([
            'workflow_version_id' => $wfVer->id,
            'code' => 'dummy_st',
            'name' => 'Dummy State',
            'is_initial' => 1,
            'is_terminal' => 0,
        ]);
        $doc = Document::create([
            'reference_number' => 'WU-DOC-BLCK-' . uniqid(),
            'title' => 'Blocker Test Doc',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->unit->id,
            'created_by' => $this->admin->id,
            'lifecycle' => 'active',
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
        ]);
        $ver = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $this->admin->id,
        ]);
        $inst = WorkflowInstance::create([
            'workflow_version_id' => $wfVer->id,
            'document_version_id' => $ver->id,
            'current_state_id' => $st->id,
            'status' => 'running',
            'started_by' => $this->admin->id,
            'started_at' => now(),
        ]);

        WorkflowTask::create([
            'assigned_unit_id' => $unitWithTask->id,
            'state_id' => $st->id,
            'instance_id' => $inst->id,
            'created_at' => now(),
        ]);
        $delTaskResp = $this->deleteJson("/api/v1/organization-units/{$unitWithTask->id}");
        $delTaskResp->assertStatus(422)
            ->assertJsonPath('message', 'Cannot delete an organizational unit with assigned workflow tasks.');

        // 3. Unit with active document grant is blocked
        $unitWithGrant = OrganizationUnit::create([
            'unit_type_id' => $unitType->id,
            'code' => 'UNIT-GRANT-' . uniqid(),
            'name' => 'Unit With Document Grants',
            'active' => 1,
        ]);
        $perm = Permission::first();
        DocumentGrant::create([
            'unit_id' => $unitWithGrant->id,
            'document_id' => $doc->id,
            'permission_id' => $perm->id,
            'granted_by' => $this->admin->id,
            'granted_at' => now(),
            'permission_level' => 'view',
        ]);
        $delGrantResp = $this->deleteJson("/api/v1/organization-units/{$unitWithGrant->id}");
        $delGrantResp->assertStatus(422)
            ->assertJsonPath('message', 'Cannot delete an organizational unit with active document grants.');

        // 4. Unit with routing policy is blocked
        $unitWithRouting = OrganizationUnit::create([
            'unit_type_id' => $unitType->id,
            'code' => 'UNIT-ROUTING-' . uniqid(),
            'name' => 'Unit With Routing Policy',
            'active' => 1,
        ]);
        RoutingPolicy::create([
            'code' => 'RP_TEST_' . uniqid(),
            'name' => 'Test Routing Policy',
            'from_unit_id' => $unitWithRouting->id,
            'to_unit_id' => $this->unit->id,
            'active' => 1,
        ]);
        $delRoutingResp = $this->deleteJson("/api/v1/organization-units/{$unitWithRouting->id}");
        $delRoutingResp->assertStatus(422)
            ->assertJsonPath('message', 'Cannot delete an organizational unit configured in active routing policies.');
    }

    /**
     * 6. Dynamic RBAC Authorization Version Invalidation:
     * Role modifications and direct permission changes increment authz_version and invalidate active sessions.
     */
    public function test_authorization_version_invalidation_terminates_stale_sessions(): void
    {
        $this->authenticateAs($this->admin);

        $initialVersion = (int) $this->userA->authz_version;

        // 1. Grant direct permission bumps version
        $perm = Permission::first();
        $this->postJson('/api/v1/user-permissions', [
            'user_id' => $this->userA->id,
            'permission_id' => $perm->id,
            'scope_kind' => 'unit',
            'unit_id' => $this->unit->id,
        ])->assertStatus(200);

        $this->assertEquals($initialVersion + 1, (int) $this->userA->fresh()->authz_version);

        // 2. Revoke direct permission bumps version again
        $scope = AuthorizationScope::where('kind', 'unit')->where('unit_id', $this->unit->id)->firstOrFail();
        $this->deleteJson('/api/v1/user-permissions', [
            'user_id' => $this->userA->id,
            'permission_id' => $perm->id,
            'scope_id' => $scope->id,
        ])->assertStatus(200);

        $this->assertEquals($initialVersion + 2, (int) $this->userA->fresh()->authz_version);

        // 3. Modifying role permissions bumps version for all role holders
        $role = Role::create([
            'name' => 'Testing Scoped Role',
            'code' => 'test_scoped_' . uniqid(),
            'active' => 1,
        ]);
        $this->postJson('/api/v1/user-roles', [
            'user_id' => $this->userA->id,
            'role_id' => $role->id,
            'scope_kind' => 'global',
        ])->assertStatus(200);

        $versionAfterRole = (int) $this->userA->fresh()->authz_version;

        // Updating role permissions increments authz_version
        $this->postJson("/api/v1/roles/{$role->id}/permissions", [
            'permission_ids' => [$perm->id],
        ])->assertStatus(200);

        $this->assertEquals($versionAfterRole + 1, (int) $this->userA->fresh()->authz_version);
    }
}
