<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Resources\CorrespondenceResource;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\UserResource;
use App\Models\ArchiveCollection;
use App\Models\ArchiveEntry;
use App\Models\ConfidentialityLevel;
use App\Models\Correspondence;
use App\Models\CorrespondenceType;
use App\Models\DigitalSignature;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentMovement;
use App\Models\DocumentTemplate;
use App\Models\DocumentVersion;
use App\Models\FileObject;
use App\Models\MovementReceipt;
use App\Models\OrganizationMembership;
use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\PhysicalOriginal;
use App\Models\PrimaryMembership;
use App\Models\Priority;
use App\Models\ProjectProposal;
use App\Models\ProjectType;
use App\Models\Publication;
use App\Models\ResearchRecord;
use App\Models\RetentionPolicy;
use App\Models\RetentionPolicyVersion;
use App\Models\SignatureRequest;
use App\Models\SigningIdentity;
use App\Models\StaffProfile;
use App\Models\TemplateVersion;
use App\Models\UnitType;
use App\Models\User;
use App\Models\VersionFile;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowState;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Services\DocumentTimelineService;
use App\Services\Pki\CertificateService;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Database\Seeders\Demo\DemoReferenceDataSeeder;
use Database\Seeders\ResearchProjectWorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinalAuditRemediationTest extends TestCase
{
    use RefreshDatabase;

    protected OrganizationUnit $unitA;
    protected OrganizationUnit $unitB;
    protected DocumentCategory $category;
    protected ConfidentialityLevel $internalConf;
    protected ConfidentialityLevel $confidentialConf;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);
        $this->seed(ResearchProjectWorkflowSeeder::class);

        $this->admin = User::where('email', 'admin@wu.edu.et')->firstOrFail();

        $deptType = UnitType::where('code', 'department')->first() ?? UnitType::firstOrFail();
        $rootUnit = OrganizationUnit::firstOrFail();

        $this->unitA = OrganizationUnit::create([
            'parent_id' => $rootUnit->id,
            'unit_type_id' => $deptType->id,
            'code' => 'FINAL_UNIT_A_' . Str::random(4),
            'name' => 'Department of Software Engineering (Audit)',
            'active' => true,
        ]);

        $this->unitB = OrganizationUnit::create([
            'parent_id' => $rootUnit->id,
            'unit_type_id' => $deptType->id,
            'code' => 'FINAL_UNIT_B_' . Str::random(4),
            'name' => 'Department of Civil Engineering (Audit)',
            'active' => true,
        ]);

        $this->category = DocumentCategory::firstOrFail();
        $this->internalConf = ConfidentialityLevel::where('code', 'internal')->first() ?? ConfidentialityLevel::firstOrFail();
        $this->confidentialConf = ConfidentialityLevel::where('code', 'confidential')->first() ?? $this->internalConf;
    }

    protected function createUser(string $label): User
    {
        return User::create([
            'name' => $label,
            'email' => Str::slug($label) . '.' . Str::random(6) . '@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
    }

    protected function createDocumentForUnit(OrganizationUnit $unit, User $creator, ?ConfidentialityLevel $conf = null): Document
    {
        return Document::create([
            'reference_number' => 'WU/AUDIT/' . Str::random(8),
            'title' => 'Audit Document ' . Str::random(5),
            'summary' => 'Audit verification record',
            'category_id' => $this->category->id,
            'confidentiality_id' => ($conf ?? $this->internalConf)->id,
            'owner_unit_id' => $unit->id,
            'custodian_id' => $creator->id,
            'created_by' => $creator->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);
    }

    // ------------------------------------------------------------------
    // 4A. TemplateController relationship & baselineConfig unit resolution
    // ------------------------------------------------------------------

    public function test_template_show_eager_loads_versions_creator_and_baseline_config_uses_canonical_unit_context(): void
    {
        $user = $this->createUser('Template Author');
        $this->grantPermissionToUser($user, 'documents.view', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($user, 'documents.create', 'unit', $this->unitA->id);

        $membership = OrganizationMembership::create([
            'user_id' => $user->id,
            'unit_id' => $this->unitA->id,
            'position_title' => 'Department Coordinator',
            'starts_at' => now()->subDay(),
            'created_by' => $this->admin->id,
            'created_at' => now(),
        ]);
        PrimaryMembership::create([
            'user_id' => $user->id,
            'membership_id' => $membership->id,
            'selected_at' => now(),
        ]);

        $template = DocumentTemplate::create([
            'code' => 'TPL_AUDIT_' . Str::random(5),
            'name' => 'Audit Verification Template',
            'category_id' => $this->category->id,
            'owner_unit_id' => $this->unitA->id,
            'visibility_scope' => 'unit_restricted',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        TemplateVersion::create([
            'template_id' => $template->id,
            'version_number' => 1,
            'schema_definition' => ['fields' => [['key' => 'title', 'type' => 'text']]],
            'body_template_html' => '<p>{{title}}</p>',
            'status' => 'published',
            'published_by' => $user->id,
            'published_at' => now(),
            'created_by' => $user->id,
        ]);

        $this->authenticateAs($user);

        // GET /api/v1/templates/{id} with existing versions must not throw RelationNotFoundException
        $showRes = $this->getJson("/api/v1/templates/{$template->id}");
        $showRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.versions.0.creator.id', $user->id);

        // Unauthorized cross-unit user is blocked
        $otherUser = $this->createUser('Other Unit User');
        $this->grantPermissionToUser($otherUser, 'documents.view', 'unit', $this->unitB->id);
        $this->authenticateAs($otherUser);
        $this->getJson("/api/v1/templates/{$template->id}")->assertStatus(403);

        // GET /api/v1/official-letters/baseline-config without unit_id resolves canonical primary unit (not 12)
        $this->authenticateAs($user);
        $configRes = $this->getJson('/api/v1/official-letters/baseline-config');
        $configRes->assertStatus(200)
            ->assertJsonPath('data.unit_id', $this->unitA->id)
            ->assertJsonPath('data.dept_en', $this->unitA->name);
    }

    // ------------------------------------------------------------------
    // 4B. Resource & service DB-column compatibility
    // ------------------------------------------------------------------

    public function test_resources_and_timeline_use_actual_database_columns_without_phantom_attributes(): void
    {
        $officer = $this->createUser('Records Officer');
        $this->grantPermissionToUser($officer, 'documents.view', 'unit', $this->unitA->id);

        $doc = $this->createDocumentForUnit($this->unitA, $officer);
        $corrType = CorrespondenceType::firstOrFail();
        $priority = Priority::firstOrFail();

        $corr = Correspondence::create([
            'document_id' => $doc->id,
            'type_id' => $corrType->id,
            'priority_id' => $priority->id,
            'purpose_code' => 'official_memo',
            'correspondence_date' => now()->toDateString(),
            'registered_by' => $officer->id,
            'registered_at' => now(),
        ]);

        $m1 = DocumentMovement::create([
            'document_id' => $doc->id,
            'from_unit_id' => $this->unitA->id,
            'to_unit_id' => $this->unitB->id,
            'sent_by' => $officer->id,
            'sent_at' => now()->subMinutes(10),
            'instruction' => 'First movement',
            'idempotency_key' => (string) Str::uuid(),
        ]);
        MovementReceipt::create([
            'movement_id' => $m1->id,
            'acknowledged_by' => $officer->id,
            'acknowledged_at' => now()->subMinutes(5),
        ]);

        $m2 = DocumentMovement::create([
            'document_id' => $doc->id,
            'from_unit_id' => $this->unitB->id,
            'to_unit_id' => $this->unitA->id,
            'sent_by' => $officer->id,
            'sent_at' => now()->subMinutes(2),
            'instruction' => 'Return movement',
            'idempotency_key' => (string) Str::uuid(),
        ]);
        MovementReceipt::create([
            'movement_id' => $m2->id,
            'acknowledged_by' => $officer->id,
            'acknowledged_at' => now()->subMinute(),
        ]);

        $collection = ArchiveCollection::firstOrFail();
        $retentionVer = RetentionPolicyVersion::firstOrFail();
        ArchiveEntry::create([
            'document_id' => $doc->id,
            'collection_id' => $collection->id,
            'retention_policy_version_id' => $retentionVer->id,
            'archived_by' => $officer->id,
            'archived_at' => now(),
        ]);

        $loadedDoc = Document::with(['movements.receipt.acknowledgedBy', 'movements.fromUnit', 'movements.toUnit', 'archiveEntry.collection'])->findOrFail($doc->id);
        $req = Request::create('/api/v1/documents/' . $doc->id, 'GET');
        $req->setUserResolver(fn () => $officer);

        $docPayload = (new DocumentResource($loadedDoc))->toArray($req);
        $this->assertArrayNotHasKey('dispatch_method', $docPayload['movements'][0]);
        $this->assertArrayNotHasKey('id', $docPayload['movements'][0]['receipt']);
        $this->assertArrayNotHasKey('status', $docPayload['movements'][0]['receipt']);
        $this->assertArrayNotHasKey('remarks', $docPayload['movements'][0]['receipt']);
        $this->assertSame($m1->id, $docPayload['movements'][0]['receipt']['movement_id']);
        $this->assertSame($doc->reference_number, $docPayload['archive_entry']['archive_reference']);

        $loadedCorr = Correspondence::with(['document', 'movements.receipt.acknowledgedBy', 'movements.fromUnit', 'movements.toUnit'])->findOrFail($corr->id);
        $corrPayload = (new CorrespondenceResource($loadedCorr))->toArray($req);
        $this->assertArrayNotHasKey('id', $corrPayload['movements'][0]['receipt']);
        $this->assertSame($m1->id, $corrPayload['movements'][0]['receipt']['movement_id']);

        $timeline = collect(app(DocumentTimelineService::class)->getTimeline($loadedDoc));
        $receiptEvents = $timeline->where('event_type', 'movement_acknowledged')->values();
        $this->assertCount(2, $receiptEvents);
        $this->assertSame("movement-rcpt-{$m1->id}", $receiptEvents[0]['id']);
        $this->assertSame("movement-rcpt-{$m2->id}", $receiptEvents[1]['id']);

        // UserResource & getPermissionLineage with direct UserPermission (composite PK, no id column)
        $perm = Permission::where('code', 'documents.view')->firstOrFail();
        $userWithPerm = User::with(['userPermissions.permission', 'userPermissions.scope', 'userRoles.role.permissions', 'userRoles.scope', 'primaryMembershipRecord.membership.unit'])->findOrFail($officer->id);
        $userPayload = (new UserResource($userWithPerm))->toArray($req);
        $this->assertNotEmpty($userPayload['direct_permissions']);
        $this->assertStringContainsString(":{$perm->id}:", (string) $userPayload['direct_permissions'][0]['id']);

        $lineage = $userWithPerm->getPermissionLineage($this->unitA->id);
        $directEntry = collect($lineage['lineage'])->firstWhere('source_type', 'direct');
        $this->assertNotNull($directEntry);
        $this->assertSame($perm->id, $directEntry['source_id']);
    }

    // ------------------------------------------------------------------
    // 4C. Signature verification authorization (DigitalSignature vs SignatureRequest ID)
    // ------------------------------------------------------------------

    public function test_signature_verify_resolves_digital_signature_id_not_signature_request_id(): void
    {
        Storage::fake('local');
        config(['dms.pki_master_key' => 'base64:' . base64_encode(str_repeat('K', 32))]);

        $unitAOfficer = $this->createUser('Unit A Sig Officer');
        $unitBOfficer = $this->createUser('Unit B Sig Officer');
        $this->grantPermissionToUser($unitAOfficer, 'signatures.request', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($unitBOfficer, 'signatures.request', 'unit', $this->unitB->id);

        // Request 1 belongs to Unit A (NOT executed yet, so DigitalSignature ID #1 will come from Request 2!)
        $docA = $this->createDocumentForUnit($this->unitA, $unitAOfficer);
        $verA = DocumentVersion::create(['document_id' => $docA->id, 'version_number' => 1, 'created_by' => $unitAOfficer->id, 'created_at' => now()]);
        $reqA = SignatureRequest::create([
            'document_version_id' => $verA->id,
            'requested_by' => $unitAOfficer->id,
            'signer_id' => $unitAOfficer->id,
            'requested_at' => now(),
            'purpose' => 'Unit A Signature Request',
        ]);

        // Request 2 belongs to Unit B
        $docB = $this->createDocumentForUnit($this->unitB, $unitBOfficer);
        $verB = DocumentVersion::create(['document_id' => $docB->id, 'version_number' => 1, 'created_by' => $unitBOfficer->id, 'created_at' => now()]);
        $pdfContent = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";
        $path = 'signatures/test_b_' . Str::random(6) . '.pdf';
        Storage::disk('local')->put($path, $pdfContent);
        $fileB = FileObject::create([
            'storage_disk' => 'local',
            'storage_key' => $path,
            'original_filename' => 'unit_b_contract.pdf',
            'detected_mime' => 'application/pdf',
            'byte_size' => strlen($pdfContent),
            'sha256' => hash('sha256', $pdfContent),
            'uploaded_by' => $unitBOfficer->id,
            'uploaded_at' => now(),
            'scan_status' => 'clean',
            'storage_status' => 'ready',
        ]);
        VersionFile::create([
            'document_version_id' => $verB->id,
            'file_object_id' => $fileB->id,
            'purpose' => 'primary',
            'display_order' => 1,
        ]);

        $reqB = SignatureRequest::create([
            'document_version_id' => $verB->id,
            'requested_by' => $unitBOfficer->id,
            'signer_id' => $unitBOfficer->id,
            'requested_at' => now(),
            'purpose' => 'Unit B Signature Request',
        ]);

        // Execute reqB first -> creates DigitalSignature with id != reqB->id (often id == reqA->id!)
        $this->authenticateAs($unitBOfficer);
        $execRes = $this->postJson("/api/v1/signatures/{$reqB->id}/execute");
        $execRes->assertStatus(200);

        $digitalSig = DigitalSignature::where('request_id', $reqB->id)->firstOrFail();
        $this->assertNotSame($reqB->id, $digitalSig->id);

        // Unauthenticated -> 401
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->postJson("/api/v1/signatures/{$digitalSig->id}/verify")->assertStatus(401);

        // Authorized Unit B officer verifies Unit B's DigitalSignature -> MUST succeed (200)
        $this->authenticateAs($unitBOfficer);
        $this->postJson("/api/v1/signatures/{$digitalSig->id}/verify")->assertStatus(200);

        // Unauthorized Unit A officer (even though reqA->id may equal digitalSig->id!) -> MUST be 403
        $this->authenticateAs($unitAOfficer);
        $this->postJson("/api/v1/signatures/{$digitalSig->id}/verify")->assertStatus(403);
    }

    // ------------------------------------------------------------------
    // 4D. CheckBaselinePermission & RouteScopeResolver security parity
    // ------------------------------------------------------------------

    public function test_project_research_staff_and_retention_authorization_boundaries(): void
    {
        $projectType = ProjectType::firstOrFail();

        // 1. Unauthenticated -> 401
        $this->getJson('/api/v1/projects')->assertStatus(401);
        $this->getJson('/api/v1/admin/retention-policies')->assertStatus(401);

        // 2. Missing permission -> 403
        $noPermUser = $this->createUser('Plain User');
        $this->authenticateAs($noPermUser);
        $this->getJson('/api/v1/projects')->assertStatus(403);
        $this->getJson('/api/v1/research/outputs')->assertStatus(403);
        $this->getJson('/api/v1/staff/records')->assertStatus(403);
        $this->getJson('/api/v1/admin/retention-policies')->assertStatus(403);

        // 3. Project high-confidentiality enforcement via RouteScopeResolver -> Document::allowsUser
        $projSubmitter = $this->createUser('Project Submitter');
        $this->grantPermissionToUser($projSubmitter, 'projects.submit', 'unit', $this->unitA->id);

        $projReviewerNoClearance = $this->createUser('Project Reviewer No Clearance');
        $this->grantPermissionToUser($projReviewerNoClearance, 'projects.review', 'unit', $this->unitA->id);

        $projReviewerCleared = $this->createUser('Project Reviewer Cleared');
        $this->grantPermissionToUser($projReviewerCleared, 'projects.review', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($projReviewerCleared, 'staff.view_sensitive', 'unit', $this->unitA->id);

        $crossUnitReviewer = $this->createUser('Project Cross Unit Reviewer');
        $this->grantPermissionToUser($crossUnitReviewer, 'projects.review', 'unit', $this->unitB->id);
        $this->grantPermissionToUser($crossUnitReviewer, 'staff.view_sensitive', 'unit', $this->unitB->id);

        $confDoc = $this->createDocumentForUnit($this->unitA, $projSubmitter, $this->confidentialConf);
        $proposal = ProjectProposal::create([
            'document_id' => $confDoc->id,
            'project_type_id' => $projectType->id,
            'workflow_status' => 'submitted',
            'submitted_by' => $projSubmitter->id,
            'submitted_at' => now(),
            'recorded_by' => $projSubmitter->id,
            'recorded_at' => now(),
        ]);

        // Cross-unit reviewer -> 403
        $this->authenticateAs($crossUnitReviewer);
        $this->postJson("/api/v1/projects/{$proposal->id}/review")->assertStatus(403);

        // Same-unit reviewer WITHOUT staff.view_sensitive on confidential project -> 403
        $this->authenticateAs($projReviewerNoClearance);
        $this->postJson("/api/v1/projects/{$proposal->id}/review")->assertStatus(403);

        // Same-unit reviewer WITH staff.view_sensitive -> 200
        $this->authenticateAs($projReviewerCleared);
        $this->postJson("/api/v1/projects/{$proposal->id}/review")->assertStatus(200);

        // 4. Cross-unit published vs unpublished vs confidential research
        $resAuthor = $this->createUser('Research Author A');
        $this->grantPermissionToUser($resAuthor, 'research.view', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($resAuthor, 'research.publish', 'unit', $this->unitA->id);

        $resUnitBReader = $this->createUser('Research Reader B');
        $this->grantPermissionToUser($resUnitBReader, 'research.view', 'unit', $this->unitB->id);

        $pubInternalDoc = $this->createDocumentForUnit($this->unitA, $resAuthor, $this->internalConf);
        $pubResearch = ResearchRecord::create([
            'document_id' => $pubInternalDoc->id,
            'research_type' => 'journal_article',
            'recorded_by' => $resAuthor->id,
            'recorded_at' => now(),
        ]);

        $unpubDoc = $this->createDocumentForUnit($this->unitA, $resAuthor, $this->internalConf);
        $unpubResearch = ResearchRecord::create([
            'document_id' => $unpubDoc->id,
            'research_type' => 'monograph',
            'recorded_by' => $resAuthor->id,
            'recorded_at' => now(),
        ]);

        $confResDoc = $this->createDocumentForUnit($this->unitA, $resAuthor, $this->confidentialConf);
        $confPubResearch = ResearchRecord::create([
            'document_id' => $confResDoc->id,
            'research_type' => 'classified_study',
            'recorded_by' => $resAuthor->id,
            'recorded_at' => now(),
        ]);

        // Publish pubResearch & confPubResearch
        $pub1 = Publication::create([
            'document_id' => $pubInternalDoc->id,
            'publication_type' => 'journal_article',
            'venue' => 'WU Journal',
            'published_on' => now()->toDateString(),
        ]);
        DB::table('research_publications')->insert(['research_id' => $pubResearch->id, 'publication_id' => $pub1->id]);
        $pub2 = Publication::create([
            'document_id' => $confResDoc->id,
            'publication_type' => 'technical_report',
            'venue' => 'WU Senate',
            'published_on' => now()->toDateString(),
        ]);
        DB::table('research_publications')->insert(['research_id' => $confPubResearch->id, 'publication_id' => $pub2->id]);

        $this->authenticateAs($resUnitBReader);
        // Unit B reader can view published non-confidential research from Unit A
        $this->getJson("/api/v1/research/outputs/{$pubResearch->id}")->assertStatus(200);
        // Unit B reader is blocked from unpublished Unit A research
        $this->getJson("/api/v1/research/outputs/{$unpubResearch->id}")->assertStatus(403);
        // Unit B reader is blocked from published HIGH-confidentiality Unit A research
        $this->getJson("/api/v1/research/outputs/{$confPubResearch->id}")->assertStatus(403);

        $listIds = collect($this->getJson('/api/v1/research/outputs')->assertStatus(200)->json('data'))->pluck('id')->all();
        $this->assertContains($pubResearch->id, $listIds);
        $this->assertNotContains($unpubResearch->id, $listIds);
        $this->assertNotContains($confPubResearch->id, $listIds);

        // Withdraw permission (research.withdraw without research.publish)
        $withdrawUser = $this->createUser('Research Withdrawer');
        $this->grantPermissionToUser($withdrawUser, 'research.withdraw', 'unit', $this->unitA->id);
        $this->authenticateAs($withdrawUser);
        $this->postJson("/api/v1/research/outputs/{$pubResearch->id}/toggle-publication", ['action' => 'withdraw'])
            ->assertStatus(200)
            ->assertJsonPath('data.publication_status', 'withdrawn');

        // 5. Staff confidential record access requires staff.view_sensitive
        $hrCreator = $this->createUser('HR Creator');
        $this->grantPermissionToUser($hrCreator, 'staff.update', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($hrCreator, 'staff.view', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($hrCreator, 'staff.view_sensitive', 'unit', $this->unitA->id);

        $this->authenticateAs($hrCreator);
        $staffCreateRes = $this->postJson('/api/v1/staff/records', [
            'title' => 'Confidential HR Contract Dossier',
            'office_id' => $this->unitA->id,
            'physical_retention' => 'required',
            'has_scan' => true,
        ])->assertStatus(201);
        $staffOrigId = (int) $staffCreateRes->json('data.id');

        $staffNoClearance = $this->createUser('HR No Clearance');
        $this->grantPermissionToUser($staffNoClearance, 'staff.view', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($staffNoClearance, 'staff.update', 'unit', $this->unitA->id);

        $this->authenticateAs($staffNoClearance);
        $visibleStaffIds = collect($this->getJson('/api/v1/staff/records')->assertStatus(200)->json('data'))->pluck('id')->all();
        $this->assertNotContains($staffOrigId, $visibleStaffIds);
        $this->postJson("/api/v1/staff/records/{$staffOrigId}/verify-scan", ['verification_status' => 'accepted'])
            ->assertStatus(403);

        // 6. Archive retention policy authorization (unit-scoped archivist role + manage_retention)
        $archivist = $this->createUser('Unit A Archivist');
        $this->assignRoleToUser($archivist, 'archivist', 'unit', $this->unitA->id);

        $this->authenticateAs($archivist);
        $createPolRes = $this->postJson('/api/v1/admin/retention-policies', [
            'code' => 'RET_AUD_' . Str::random(5),
            'name' => 'Archivist Retention Policy',
            'trigger_code' => 'date_archived',
            'duration_months' => 36,
            'disposition_action' => 'review',
        ]);
        $createPolRes->assertStatus(201);
        $polId = (int) $createPolRes->json('data.policy.id');

        $this->getJson('/api/v1/admin/retention-policies')->assertStatus(200);
        $this->getJson("/api/v1/admin/retention-policies/{$polId}")->assertStatus(200);

        // Archive entries listing must not bypass confidentiality clearance for archive.manage_retention holders
        $confArchiveDoc = $this->createDocumentForUnit($this->unitB, $this->admin, $this->confidentialConf);
        ArchiveEntry::create([
            'document_id' => $confArchiveDoc->id,
            'collection_id' => ArchiveCollection::firstOrFail()->id,
            'retention_policy_version_id' => RetentionPolicyVersion::firstOrFail()->id,
            'archived_by' => $this->admin->id,
            'archived_at' => now(),
        ]);
        $archiveEntryDocIds = collect($this->getJson('/api/v1/archive/entries')->assertStatus(200)->json('data'))->pluck('document_id')->all();
        $this->assertNotContains($confArchiveDoc->id, $archiveEntryDocIds);
    }

    // ------------------------------------------------------------------
    // 4E. Research author constraint validation & return permission (*.review | *.approve)
    // ------------------------------------------------------------------

    public function test_store_research_enforces_author_xor_and_return_accepts_approve_permission(): void
    {
        $creator = $this->createUser('Research Creator');
        $this->grantPermissionToUser($creator, 'research.create', 'unit', $this->unitA->id);

        $staffUser = $this->createUser('Staff Faculty');
        $staffProfile = StaffProfile::create([
            'user_id' => $staffUser->id,
            'employee_number' => 'WU-STF-' . Str::random(5),
        ]);

        $this->authenticateAs($creator);

        // Invalid: both staff_id and external name provided -> 422 (no SQL CHECK 500)
        $this->postJson('/api/v1/research/outputs', [
            'title' => 'Invalid Dual Author Research',
            'office_id' => $this->unitA->id,
            'completion_date' => '2026-10-01',
            'authors' => [
                ['staff_id' => $staffProfile->id, 'name' => 'Duplicate External Name'],
            ],
        ])->assertStatus(422);

        // Invalid: duplicate staff_id -> 422
        $this->postJson('/api/v1/research/outputs', [
            'title' => 'Duplicate Staff Author Research',
            'office_id' => $this->unitA->id,
            'completion_date' => '2026-10-01',
            'authors' => [
                ['staff_id' => $staffProfile->id],
                ['staff_id' => $staffProfile->id],
            ],
        ])->assertStatus(422);

        // Valid: one staff_id author + one external name author
        $validRes = $this->postJson('/api/v1/research/outputs', [
            'title' => 'Valid Mixed Authors Research',
            'office_id' => $this->unitA->id,
            'completion_date' => '2026-10-01',
            'authors' => [
                ['staff_id' => $staffProfile->id, 'contribution' => 'Lead Investigator'],
                ['name' => 'Dr. External Collaborator', 'contribution' => 'Co-Author'],
            ],
        ]);
        $validRes->assertStatus(201);
        $researchId = (int) $validRes->json('data.id');

        $this->assertDatabaseHas('research_authors', [
            'research_id' => $researchId,
            'staff_id' => $staffProfile->id,
            'external_name' => null,
        ]);
        $this->assertDatabaseHas('research_authors', [
            'research_id' => $researchId,
            'staff_id' => null,
            'external_name' => 'Dr. External Collaborator',
        ]);

        // Submit research as separate submitter
        $submitter = $this->createUser('Research Submitter');
        $this->grantPermissionToUser($submitter, 'research.submit', 'unit', $this->unitA->id);
        $this->authenticateAs($submitter);
        $this->postJson("/api/v1/research/outputs/{$researchId}/submit")->assertStatus(200);

        // User with research.approve ONLY (no research.review) can return research
        $approverOnly = $this->createUser('Research Approver Only');
        $this->grantPermissionToUser($approverOnly, 'research.approve', 'unit', $this->unitA->id);
        $this->authenticateAs($approverOnly);
        $this->postJson("/api/v1/research/outputs/{$researchId}/return", [
            'remarks' => 'Please clarify methodology.',
        ])->assertStatus(200)->assertJsonPath('data.workflow_status', 'returned');

        // User with neither research.review nor research.approve is blocked with 403
        $unrelated = $this->createUser('Unrelated Reviewer');
        $this->grantPermissionToUser($unrelated, 'research.view', 'unit', $this->unitA->id);
        $this->authenticateAs($unrelated);
        $this->postJson("/api/v1/research/outputs/{$researchId}/return", [
            'remarks' => 'Unauthorized return',
        ])->assertStatus(403);

        // Project return with projects.approve ONLY (no projects.review)
        $projSubmitter = $this->createUser('Proj Submitter');
        $this->grantPermissionToUser($projSubmitter, 'projects.submit', 'unit', $this->unitA->id);
        $projApproverOnly = $this->createUser('Proj Approver Only');
        $this->grantPermissionToUser($projApproverOnly, 'projects.approve', 'unit', $this->unitA->id);

        $this->authenticateAs($projSubmitter);
        $projRes = $this->postJson('/api/v1/projects', [
            'title' => 'Approver Return Project Test',
            'office_id' => $this->unitA->id,
            'project_type_id' => ProjectType::firstOrFail()->id,
        ])->assertStatus(201);
        $proposalId = (int) $projRes->json('data.id');
        $this->postJson("/api/v1/projects/{$proposalId}/submit")->assertStatus(200);

        $this->authenticateAs($projApproverOnly);
        $this->postJson("/api/v1/projects/{$proposalId}/return", [
            'remarks' => 'Budget breakdown required.',
        ])->assertStatus(200)->assertJsonPath('data.workflow_status', 'returned');
    }

    // ------------------------------------------------------------------
    // 4F. DemoReferenceDataSeeder prohibit_self_approval = true & enforcement
    // ------------------------------------------------------------------

    public function test_demo_reference_data_seeder_enforces_prohibit_self_approval_on_approve_transition(): void
    {
        $this->seed(DemoReferenceDataSeeder::class);

        $def = WorkflowDefinition::where('code', 'standard_doc_approval')->firstOrFail();
        $latestVer = WorkflowVersion::where('definition_id', $def->id)->orderByDesc('version_number')->firstOrFail();
        $underReviewState = WorkflowState::where('workflow_version_id', $latestVer->id)->where('code', 'under_review')->firstOrFail();

        $approveTransition = WorkflowTransition::where('from_state_id', $underReviewState->id)
            ->where('action_code', 'approve')
            ->firstOrFail();

        $this->assertTrue((bool) $approveTransition->prohibit_self_approval);

        $submitter = $this->createUser('Self Approver Attempt');
        $this->grantPermissionToUser($submitter, 'workflow.submit', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($submitter, 'workflow.review', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($submitter, 'workflow.approve', 'unit', $this->unitA->id);

        $approver = $this->createUser('Independent Approver');
        $this->grantPermissionToUser($approver, 'workflow.review', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($approver, 'workflow.approve', 'unit', $this->unitA->id);

        $doc = $this->createDocumentForUnit($this->unitA, $submitter);
        $docVer = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $submitter->id,
            'created_at' => now(),
        ]);

        // Start workflow in draft, submit to under_review, then attempt self-approval
        $this->authenticateAs($submitter);
        $startRes = $this->postJson('/api/v1/workflows/instances', [
            'workflow_definition_id' => $def->id,
            'document_version_id' => $docVer->id,
        ])->assertStatus(201);

        $instanceId = (int) $startRes->json('data.id');
        $draftTask = DB::table('workflow_tasks')->where('instance_id', $instanceId)->whereNull('closed_at')->first();
        $this->assertNotNull($draftTask);

        $this->postJson("/api/v1/workflows/tasks/{$draftTask->id}/decide", [
            'decision' => 'submit',
            'reason' => 'Submitting for approval',
        ])->assertStatus(200);

        $reviewTask = DB::table('workflow_tasks')->where('instance_id', $instanceId)->whereNull('closed_at')->first();
        $this->assertNotNull($reviewTask);

        // Submitter/author attempting self-approval -> MUST be blocked (422)
        $this->postJson("/api/v1/workflows/tasks/{$reviewTask->id}/decide", [
            'decision' => 'approve',
            'reason' => 'Self-approval attempt',
        ])->assertStatus(422);

        // Independent approver -> 200
        $this->authenticateAs($approver);
        $this->postJson("/api/v1/workflows/tasks/{$reviewTask->id}/decide", [
            'decision' => 'approve',
            'reason' => 'Independent approval',
        ])->assertStatus(200);
    }
}
