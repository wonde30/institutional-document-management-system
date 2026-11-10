<?php

namespace Tests\Feature;

use App\Models\AssignmentEvent;
use App\Models\AssignmentParticipant;
use App\Models\AssignmentResponse;
use App\Models\ConfidentialityLevel;
use App\Models\Document;
use App\Models\DocumentAssignment;
use App\Models\DocumentCategory;
use App\Models\DocumentRelation;
use App\Models\DocumentRelationType;
use App\Models\DocumentTemplate;
use App\Models\DocumentTextExtraction;
use App\Models\DocumentVersion;
use App\Models\FileObject;
use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\PhysicalOriginal;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Models\VersionFile;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowState;
use App\Models\WorkflowTask;
use App\Models\WorkflowTaskParticipant;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SchemaAndRelationalInvariantsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $staffUser;
    protected User $secondStaffUser;
    protected OrganizationUnit $unit;
    protected DocumentCategory $category;
    protected ConfidentialityLevel $confidentiality;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $this->unit = OrganizationUnit::first();
        $this->category = DocumentCategory::first();
        $this->confidentiality = ConfidentialityLevel::first();

        $this->adminUser = User::where('email', 'admin@wu.edu.et')->first();

        $this->staffUser = User::create([
            'name' => 'Abebe Kebede',
            'email' => 'abebe.kebede@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->staffUser, 'staff');

        $this->secondStaffUser = User::create([
            'name' => 'Almaz Worku',
            'email' => 'almaz.worku@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->secondStaffUser, 'staff');
    }

    private function createDummyDocument(string $title = 'Test Document'): Document
    {
        return Document::create([
            'reference_number' => 'DOC-' . uniqid(),
            'title' => $title,
            'summary' => 'Summary for ' . $title,
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $this->unit->id,
            'custodian_id' => $this->staffUser->id,
            'created_by' => $this->adminUser->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 1,
        ]);
    }

    private function createDummyFileObject(): FileObject
    {
        return FileObject::create([
            'storage_disk' => 'local',
            'storage_key' => 'files/' . uniqid() . '.pdf',
            'original_filename' => 'sample.pdf',
            'detected_mime' => 'application/pdf',
            'byte_size' => 1024,
            'sha256' => hash('sha256', uniqid()),
            'uploaded_by' => $this->adminUser->id,
            'uploaded_at' => now(),
            'scan_status' => 'clean',
            'storage_status' => 'ready',
        ]);
    }

    public function test_document_template_and_template_version_lifecycle(): void
    {
        $template = DocumentTemplate::create([
            'code' => 'TPL-OFFICIAL-MEMO',
            'name' => 'Official Institutional Memorandum',
            'description' => 'Standard template for inter-office memos',
            'category_id' => $this->category->id,
            'owner_unit_id' => $this->unit->id,
            'visibility_scope' => 'unit_restricted',
            'is_active' => true,
            'created_by' => $this->adminUser->id,
        ]);

        $this->assertDatabaseHas('document_templates', ['code' => 'TPL-OFFICIAL-MEMO']);
        $this->assertEquals($this->unit->id, $template->ownerUnit->id);
        $this->assertEquals($this->category->id, $template->category->id);
        $this->assertEquals($this->adminUser->id, $template->creator->id);

        $v1 = TemplateVersion::create([
            'template_id' => $template->id,
            'version_number' => 1,
            'schema_definition' => [
                'fields' => [
                    ['name' => 'to', 'type' => 'string', 'required' => true],
                    ['name' => 'subject', 'type' => 'string', 'required' => true],
                ],
            ],
            'body_template_html' => '<div><p>To: {{to}}</p><p>Subject: {{subject}}</p></div>',
            'status' => 'draft',
            'created_by' => $this->staffUser->id,
        ]);

        $v2 = TemplateVersion::create([
            'template_id' => $template->id,
            'version_number' => 2,
            'schema_definition' => [
                'fields' => [
                    ['name' => 'to', 'type' => 'string', 'required' => true],
                    ['name' => 'from', 'type' => 'string', 'required' => true],
                    ['name' => 'subject', 'type' => 'string', 'required' => true],
                ],
            ],
            'body_template_html' => '<div><p>To: {{to}}</p><p>From: {{from}}</p><p>Subject: {{subject}}</p></div>',
            'status' => 'published',
            'published_at' => now(),
            'published_by' => $this->adminUser->id,
            'created_by' => $this->staffUser->id,
        ]);

        $this->assertCount(2, $template->versions);
        $this->assertEquals(2, $template->currentVersion->version_number);
        $this->assertEquals(2, $template->publishedVersion->version_number);
        $this->assertIsArray($v1->schema_definition);
        $this->assertEquals('published', $v2->status);
        $this->assertEquals($this->adminUser->id, $v2->publisher->id);

        // Test unique constraint on (template_id, version_number)
        $this->expectException(QueryException::class);
        TemplateVersion::create([
            'template_id' => $template->id,
            'version_number' => 1,
            'schema_definition' => [],
            'body_template_html' => '<p>Duplicate</p>',
            'status' => 'draft',
            'created_by' => $this->staffUser->id,
        ]);
    }

    public function test_document_version_template_binding_and_ingestion_method(): void
    {
        $template = DocumentTemplate::create([
            'code' => 'TPL-DIRECTIVE',
            'name' => 'Presidential Directive',
            'category_id' => $this->category->id,
            'visibility_scope' => 'global',
            'created_by' => $this->adminUser->id,
        ]);

        $tplVersion = TemplateVersion::create([
            'template_id' => $template->id,
            'version_number' => 1,
            'schema_definition' => ['directives' => 'array'],
            'body_template_html' => '<div>Directives</div>',
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $this->adminUser->id,
        ]);

        $doc = $this->createDummyDocument('Directive on Academic Calendar');

        $docVersion = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'ingestion_method' => 'template_generated',
            'template_version_id' => $tplVersion->id,
            'template_data' => ['directives' => ['Directive 1', 'Directive 2']],
            'created_by' => $this->adminUser->id,
            'change_note' => 'Initial template generation',
        ]);

        $this->assertEquals('template_generated', $docVersion->ingestion_method);
        $this->assertEquals($tplVersion->id, $docVersion->templateVersion->id);
        $this->assertCount(1, $tplVersion->documentVersions);
        $this->assertEquals(['Directive 1', 'Directive 2'], $docVersion->template_data['directives']);

        // Default ingestion_method verification
        $defaultVersion = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 2,
            'created_by' => $this->adminUser->id,
            'change_note' => 'Standard upload',
        ]);
        $this->assertEquals('digital_upload', $defaultVersion->ingestion_method);
    }

    public function test_document_text_extraction_ocr_and_fulltext(): void
    {
        $doc = $this->createDummyDocument('OCR Processed Report');
        $docVersion = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $this->adminUser->id,
        ]);
        $fileObject = $this->createDummyFileObject();

        $extraction = DocumentTextExtraction::create([
            'document_version_id' => $docVersion->id,
            'file_object_id' => $fileObject->id,
            'raw_text' => 'Wollo University senate meeting held on Tahsas 15 2018.',
            'language_detected' => 'amh',
            'confidence_score' => 96.50,
            'ocr_engine' => 'tesseract-5.3',
            'verified_by' => $this->staffUser->id,
            'verified_at' => now(),
        ]);

        $this->assertDatabaseHas('document_text_extractions', [
            'document_version_id' => $docVersion->id,
            'file_object_id' => $fileObject->id,
            'language_detected' => 'amh',
        ]);
        $this->assertEquals('Wollo University senate meeting held on Tahsas 15 2018.', $extraction->searchable_text);
        $this->assertEquals($docVersion->id, $extraction->documentVersion->id);
        $this->assertEquals($fileObject->id, $extraction->fileObject->id);
        $this->assertEquals($this->staffUser->id, $extraction->verifiedBy->id);
        $this->assertCount(1, $docVersion->textExtractions);

        // Verify unique constraint on (document_version_id, file_object_id)
        $this->expectException(QueryException::class);
        DocumentTextExtraction::create([
            'document_version_id' => $docVersion->id,
            'file_object_id' => $fileObject->id,
            'raw_text' => 'Duplicate extraction attempt',
        ]);
    }

    public function test_multi_staff_assignment_participants(): void
    {
        $doc = $this->createDummyDocument('Assignment Directive Document');
        $assignment = DocumentAssignment::create([
            'document_id' => $doc->id,
            'assigned_user_id' => $this->staffUser->id,
            'assigned_unit_id' => $this->unit->id,
            'assigned_by' => $this->adminUser->id,
            'assigned_at' => now(),
            'due_at' => now()->addDays(7),
            'instruction' => 'Conduct curriculum review collaboratively',
        ]);

        $lead = AssignmentParticipant::create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->staffUser->id,
            'role_code' => 'lead',
            'status' => 'accepted',
            'responded_at' => now(),
        ]);

        $collab = AssignmentParticipant::create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->secondStaffUser->id,
            'role_code' => 'writer',
            'status' => 'pending',
        ]);

        $this->assertCount(2, $assignment->participants);
        $this->assertCount(2, $assignment->participantUsers);
        $this->assertEquals($assignment->id, $lead->assignment->id);
        $this->assertEquals($this->staffUser->id, $lead->user->id);

        // Verify unique constraint on (assignment_id, user_id)
        $this->expectException(QueryException::class);
        AssignmentParticipant::create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->staffUser->id,
            'role_code' => 'contributor',
        ]);
    }

    public function test_assignment_response_and_fulfillment_linkage(): void
    {
        $incomingDoc = $this->createDummyDocument('Inquiry Letter from Ministry');
        $replyDoc = $this->createDummyDocument('Official Response to Ministry Inquiry');

        $assignment = DocumentAssignment::create([
            'document_id' => $incomingDoc->id,
            'assigned_user_id' => $this->staffUser->id,
            'assigned_unit_id' => $this->unit->id,
            'assigned_by' => $this->adminUser->id,
            'assigned_at' => now(),
            'instruction' => 'Draft and dispatch official university response',
        ]);

        $link = AssignmentResponse::create([
            'assignment_id' => $assignment->id,
            'response_document_id' => $replyDoc->id,
            'relationship_role' => 'full_reply',
            'is_fulfilling' => true,
            'linked_by' => $this->staffUser->id,
        ]);

        $this->assertCount(1, $assignment->responses);
        $this->assertCount(1, $assignment->fulfillingResponses);
        $this->assertCount(1, $assignment->responseDocuments);
        $this->assertEquals($replyDoc->id, $assignment->responseDocuments->first()->id);

        // Reverse relationships from Document
        $this->assertCount(1, $replyDoc->assignmentResponses);
        $this->assertCount(1, $replyDoc->fulfilledAssignments);
        $this->assertEquals($assignment->id, $replyDoc->fulfilledAssignments->first()->id);
        $this->assertEquals('full_reply', $link->relationship_role);
        $this->assertTrue($link->is_fulfilling);
    }

    public function test_assignment_events_with_target_user_and_new_actions(): void
    {
        $doc = $this->createDummyDocument('Document for Assignment Events');
        $assignment = DocumentAssignment::create([
            'document_id' => $doc->id,
            'assigned_user_id' => $this->staffUser->id,
            'assigned_unit_id' => $this->unit->id,
            'assigned_by' => $this->adminUser->id,
            'assigned_at' => now(),
            'instruction' => 'Review and process',
        ]);

        $event1 = AssignmentEvent::create([
            'assignment_id' => $assignment->id,
            'sequence_number' => 1,
            'action' => 'assigned',
            'actor_id' => $this->adminUser->id,
            'occurred_at' => now(),
        ]);

        $event2 = AssignmentEvent::create([
            'assignment_id' => $assignment->id,
            'sequence_number' => 2,
            'action' => 'delegated',
            'actor_id' => $this->staffUser->id,
            'target_user_id' => $this->secondStaffUser->id,
            'occurred_at' => now(),
            'comment' => 'Delegating technical analysis to Almaz',
        ]);

        $this->assertCount(2, $assignment->events);
        $this->assertEquals($this->secondStaffUser->id, $event2->targetUser->id);
        $this->assertEquals('delegated', $event2->action);

        // Verify action check constraint rejects invalid action
        $this->expectException(QueryException::class);
        AssignmentEvent::create([
            'assignment_id' => $assignment->id,
            'sequence_number' => 3,
            'action' => 'invalid_unsupported_action',
            'actor_id' => $this->staffUser->id,
            'occurred_at' => now(),
        ]);
    }

    public function test_workflow_task_delegation_and_participants(): void
    {
        $doc = $this->createDummyDocument('Workflow Managed Policy Document');
        $docVersion = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $this->adminUser->id,
        ]);

        $def = WorkflowDefinition::create([
            'code' => 'WF-POLICY-REVIEW',
            'name' => 'Policy Review Workflow',
            'is_active' => true,
            'created_by' => $this->adminUser->id,
        ]);

        $wfVersion = WorkflowVersion::create([
            'definition_id' => $def->id,
            'version_number' => 1,
            'created_by' => $this->adminUser->id,
        ]);

        $state = WorkflowState::create([
            'workflow_version_id' => $wfVersion->id,
            'code' => 'STATE-REVIEW',
            'name' => 'Department Review',
            'state_type' => 'in_progress',
        ]);

        $instance = WorkflowInstance::create([
            'document_version_id' => $docVersion->id,
            'workflow_version_id' => $wfVersion->id,
            'started_by' => $this->adminUser->id,
            'started_at' => now(),
        ]);

        $task = WorkflowTask::create([
            'instance_id' => $instance->id,
            'state_id' => $state->id,
            'assigned_user_id' => $this->staffUser->id,
            'delegated_to_user_id' => $this->secondStaffUser->id,
            'delegated_by' => $this->staffUser->id,
            'delegation_reason' => 'Annual leave delegation',
            'created_at' => now(),
        ]);

        $this->assertEquals($this->secondStaffUser->id, $task->delegatedTo->id);
        $this->assertEquals($this->staffUser->id, $task->delegatedBy->id);
        $this->assertEquals('Annual leave delegation', $task->delegation_reason);

        $participant = WorkflowTaskParticipant::create([
            'task_id' => $task->id,
            'user_id' => $this->secondStaffUser->id,
            'participation_role' => 'reviewer',
            'status' => 'pending',
        ]);

        $this->assertCount(1, $task->participants);
        $this->assertEquals($task->id, $participant->task->id);
        $this->assertEquals($this->secondStaffUser->id, $participant->user->id);

        // Test workflow instance parent/child hierarchy
        $subInstance = WorkflowInstance::create([
            'document_version_id' => $docVersion->id,
            'workflow_version_id' => $wfVersion->id,
            'started_by' => $this->adminUser->id,
            'parent_instance_id' => $instance->id,
            'started_at' => now(),
        ]);

        $this->assertEquals($instance->id, $subInstance->parentInstance->id);
        $this->assertCount(1, $instance->childInstances);
    }

    public function test_workflow_transition_quorum_and_approval_policy(): void
    {
        $def = WorkflowDefinition::create([
            'code' => 'WF-COMMITTEE-APPROVAL',
            'name' => 'Senate Committee Approval',
            'is_active' => true,
            'created_by' => $this->adminUser->id,
        ]);

        $wfVersion = WorkflowVersion::create([
            'definition_id' => $def->id,
            'version_number' => 1,
            'created_by' => $this->adminUser->id,
        ]);

        $s1 = WorkflowState::create([
            'workflow_version_id' => $wfVersion->id,
            'code' => 'STATE-VOTING',
            'name' => 'Voting in Progress',
            'state_type' => 'in_progress',
        ]);

        $s2 = WorkflowState::create([
            'workflow_version_id' => $wfVersion->id,
            'code' => 'STATE-APPROVED',
            'name' => 'Approved by Committee',
            'state_type' => 'terminal',
        ]);

        $permission = Permission::first();

        $transition = WorkflowTransition::create([
            'from_state_id' => $s1->id,
            'to_state_id' => $s2->id,
            'action_code' => 'APPROVE_QUORUM',
            'permission_id' => $permission->id,
            'approval_policy' => 'quorum',
            'quorum_count' => 5,
            'quorum_percentage' => 66.67,
        ]);

        $this->assertEquals('quorum', $transition->approval_policy);
        $this->assertEquals(5, $transition->quorum_count);
        $this->assertEquals('66.67', (string) $transition->quorum_percentage);
    }

    public function test_eloquent_level_immutability_on_document_version(): void
    {
        $doc = $this->createDummyDocument('Version Immutability Test Document');
        $version = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'change_note' => 'Initial note',
            'created_by' => $this->adminUser->id,
        ]);

        // 1. Eloquent update must throw RuntimeException
        try {
            $version->change_note = 'Tampered note';
            $version->save();
            $this->fail('Expected RuntimeException on updating DocumentVersion was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }

        // 2. Eloquent delete must throw RuntimeException
        try {
            $version->delete();
            $this->fail('Expected RuntimeException on deleting DocumentVersion was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }

        // 3. allowMutation bypass must allow controlled maintenance
        DocumentVersion::allowMutation(function () use ($version) {
            $version->change_note = 'Authorized maintenance note';
            $version->save();
        });

        $this->assertEquals('Authorized maintenance note', $version->fresh()->change_note);
    }

    public function test_eloquent_level_immutability_on_version_file(): void
    {
        $doc = $this->createDummyDocument('Version File Immutability Document');
        $version = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $this->adminUser->id,
        ]);
        $file = $this->createDummyFileObject();

        $versionFile = VersionFile::create([
            'document_version_id' => $version->id,
            'file_object_id' => $file->id,
            'purpose' => 'primary',
            'display_order' => 1,
        ]);

        // 1. Eloquent update must throw RuntimeException
        try {
            $versionFile->purpose = 'attachment';
            $versionFile->save();
            $this->fail('Expected RuntimeException on updating VersionFile was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }

        // 2. Eloquent delete must throw RuntimeException
        try {
            $versionFile->delete();
            $this->fail('Expected RuntimeException on deleting VersionFile was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }

        // 3. allowMutation bypass must allow controlled maintenance
        VersionFile::allowMutation(function () use ($versionFile) {
            $versionFile->display_order = 2;
            $versionFile->save();
        });

        $reloaded = VersionFile::where('document_version_id', $version->id)
            ->where('file_object_id', $file->id)
            ->first();
        $this->assertEquals(2, $reloaded->display_order);
    }

    public function test_mysql_trigger_immutability_on_document_version(): void
    {
        $doc = $this->createDummyDocument('MySQL Trigger Document');
        $version = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'change_note' => 'Trigger test initial',
            'created_by' => $this->adminUser->id,
        ]);

        // Direct raw SQL UPDATE must be rejected by MySQL trigger
        try {
            DB::statement('UPDATE `document_versions` SET `change_note` = ? WHERE `id` = ?', ['Direct SQL Tampering', $version->id]);
            $this->fail('Expected QueryException from MySQL trigger on UPDATE was not thrown');
        } catch (QueryException $e) {
            $this->assertStringContainsString('45000', $e->getMessage());
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        // Direct raw SQL DELETE must be rejected by MySQL trigger
        try {
            DB::statement('DELETE FROM `document_versions` WHERE `id` = ?', [$version->id]);
            $this->fail('Expected QueryException from MySQL trigger on DELETE was not thrown');
        } catch (QueryException $e) {
            $this->assertStringContainsString('45000', $e->getMessage());
            $this->assertStringContainsString('cannot be deleted', $e->getMessage());
        }

        // Verify bypass using @dms_allow_version_mutation session variable
        DB::statement('SET @dms_allow_version_mutation = 1');
        DB::statement('UPDATE `document_versions` SET `change_note` = ? WHERE `id` = ?', ['Authorized SQL Bypass', $version->id]);
        DB::statement('SET @dms_allow_version_mutation = 0');

        $this->assertEquals('Authorized SQL Bypass', $version->fresh()->change_note);
    }

    public function test_mysql_trigger_immutability_on_version_file(): void
    {
        $doc = $this->createDummyDocument('MySQL Trigger Version File Document');
        $version = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $this->adminUser->id,
        ]);
        $file = $this->createDummyFileObject();

        VersionFile::create([
            'document_version_id' => $version->id,
            'file_object_id' => $file->id,
            'purpose' => 'primary',
            'display_order' => 1,
        ]);

        // Direct raw SQL UPDATE must be rejected by MySQL trigger
        try {
            DB::statement('UPDATE `version_files` SET `display_order` = 99 WHERE `document_version_id` = ? AND `file_object_id` = ?', [$version->id, $file->id]);
            $this->fail('Expected QueryException from MySQL trigger on UPDATE was not thrown');
        } catch (QueryException $e) {
            $this->assertStringContainsString('45000', $e->getMessage());
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        // Direct raw SQL DELETE must be rejected by MySQL trigger
        try {
            DB::statement('DELETE FROM `version_files` WHERE `document_version_id` = ? AND `file_object_id` = ?', [$version->id, $file->id]);
            $this->fail('Expected QueryException from MySQL trigger on DELETE was not thrown');
        } catch (QueryException $e) {
            $this->assertStringContainsString('45000', $e->getMessage());
            $this->assertStringContainsString('cannot be deleted', $e->getMessage());
        }

        // Verify bypass using @dms_allow_version_mutation session variable
        DB::statement('SET @dms_allow_version_mutation = 1');
        DB::statement('UPDATE `version_files` SET `display_order` = 5 WHERE `document_version_id` = ? AND `file_object_id` = ?', [$version->id, $file->id]);
        DB::statement('SET @dms_allow_version_mutation = 0');

        $this->assertDatabaseHas('version_files', [
            'document_version_id' => $version->id,
            'file_object_id' => $file->id,
            'display_order' => 5,
        ]);
    }

    public function test_document_relation_responds_to_normalization(): void
    {
        $sourceDoc = $this->createDummyDocument('Incoming University Directive');
        $responseDoc = $this->createDummyDocument('Faculty Response to Directive');

        $relationType = DocumentRelationType::where('code', 'responds_to')->first();
        $this->assertNotNull($relationType, "DocumentRelationType 'responds_to' must exist in seed data");

        $relation = DocumentRelation::create([
            'source_id' => $responseDoc->id,
            'target_id' => $sourceDoc->id,
            'relation_type_id' => $relationType->id,
        ]);

        $this->assertDatabaseHas('document_relations', [
            'source_id' => $responseDoc->id,
            'target_id' => $sourceDoc->id,
            'relation_type_id' => $relationType->id,
        ]);
        $this->assertEquals($sourceDoc->id, $relation->target->id);
        $this->assertEquals($responseDoc->id, $relation->source->id);
    }

    public function test_document_physical_originals_relationship(): void
    {
        $doc = $this->createDummyDocument('Original Paper Deed Document');

        $physicalOriginal = PhysicalOriginal::create([
            'document_id' => $doc->id,
            'physical_reference' => 'ARCH-BOX-42-SHELF-3',
            'retention_requirement' => 'required',
            'created_by' => $this->adminUser->id,
            'created_at' => now(),
        ]);

        $this->assertCount(1, $doc->physicalOriginals);
        $this->assertEquals('ARCH-BOX-42-SHELF-3', $doc->physicalOriginals->first()->physical_reference);
        $this->assertEquals($doc->id, $physicalOriginal->document->id);
    }

    public function test_version_file_composite_key_lifecycle(): void
    {
        $doc = $this->createDummyDocument('Composite Key Version File Doc');
        $version = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $this->adminUser->id,
        ]);
        $file = $this->createDummyFileObject();

        $vf = VersionFile::create([
            'document_version_id' => $version->id,
            'file_object_id' => $file->id,
            'purpose' => 'primary',
            'display_order' => 1,
        ]);

        // 1. getKey returns composite array
        $this->assertEquals([
            'document_version_id' => $version->id,
            'file_object_id' => $file->id,
        ], $vf->getKey());

        // 2. findComposite static finder
        $found = VersionFile::findComposite([
            'document_version_id' => $version->id,
            'file_object_id' => $file->id,
        ]);
        $this->assertNotNull($found);
        $this->assertEquals('primary', $found->purpose);

        // 3. fresh() reloads by composite key
        $fresh = $vf->fresh();
        $this->assertNotNull($fresh);
        $this->assertEquals(1, $fresh->display_order);

        // 4. refresh() in-place reload
        $vf->refresh();
        $this->assertEquals(1, $vf->display_order);

        // 5. Deletion via allowMutation using composite keys
        VersionFile::allowMutation(function () use ($vf) {
            $vf->delete();
        });
        $this->assertNull(VersionFile::findComposite([
            'document_version_id' => $version->id,
            'file_object_id' => $file->id,
        ]));
    }

    public function test_assignment_response_composite_key_lifecycle(): void
    {
        $incomingDoc = $this->createDummyDocument('Source Directive Doc');
        $replyDoc = $this->createDummyDocument('Response Doc For Lifecycle');

        $assignment = DocumentAssignment::create([
            'document_id' => $incomingDoc->id,
            'assigned_user_id' => $this->staffUser->id,
            'assigned_unit_id' => $this->unit->id,
            'assigned_by' => $this->adminUser->id,
            'assigned_at' => now(),
            'instruction' => 'Test composite key lifecycle',
        ]);

        $ar = AssignmentResponse::create([
            'assignment_id' => $assignment->id,
            'response_document_id' => $replyDoc->id,
            'relationship_role' => 'full_reply',
            'is_fulfilling' => true,
            'linked_by' => $this->staffUser->id,
        ]);

        // 1. getKey
        $this->assertEquals([
            'assignment_id' => $assignment->id,
            'response_document_id' => $replyDoc->id,
        ], $ar->getKey());

        // 2. findComposite
        $found = AssignmentResponse::findComposite([
            'assignment_id' => $assignment->id,
            'response_document_id' => $replyDoc->id,
        ]);
        $this->assertNotNull($found);
        $this->assertTrue($found->is_fulfilling);

        // 3. fresh()
        $fresh = $ar->fresh();
        $this->assertNotNull($fresh);
        $this->assertEquals('full_reply', $fresh->relationship_role);

        // 4. refresh()
        $ar->refresh();
        $this->assertEquals('full_reply', $ar->relationship_role);

        // 5. Update
        $ar->relationship_role = 'interim_reply';
        $ar->save();
        $this->assertEquals('interim_reply', $ar->fresh()->relationship_role);

        // 6. Delete
        $ar->delete();
        $this->assertNull(AssignmentResponse::findComposite([
            'assignment_id' => $assignment->id,
            'response_document_id' => $replyDoc->id,
        ]));
    }

    public function test_immutability_nested_execution_and_exception_safety(): void
    {
        $doc = $this->createDummyDocument('Nested Immutability Doc');
        $version = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'change_note' => 'Initial note',
            'created_by' => $this->adminUser->id,
        ]);

        // Verify nested execution
        DocumentVersion::allowMutation(function () use ($version) {
            $version->change_note = 'Level 1 mutation';
            $version->save();

            DocumentVersion::allowMutation(function () use ($version) {
                $version->change_note = 'Level 2 nested mutation';
                $version->save();
            });

            // After nested completes, outer scope must still allow mutation
            $version->change_note = 'Level 1 resumed mutation';
            $version->save();
        });

        $this->assertEquals('Level 1 resumed mutation', $version->fresh()->change_note);

        // Verify exception safety: error inside block must reset session variable
        try {
            DocumentVersion::allowMutation(function () use ($version) {
                $version->change_note = 'Before crash';
                $version->save();
                throw new \Exception('Simulated failure during mutation');
            });
        } catch (\Exception $e) {
            $this->assertEquals('Simulated failure during mutation', $e->getMessage());
        }

        // After exception, immutability must be strictly restored
        $this->assertFalse(DocumentVersion::isMutationAllowed());

        $sessionVar = DB::select("SELECT @dms_allow_version_mutation as val")[0]->val;
        $this->assertTrue($sessionVar === '0' || $sessionVar === 0 || $sessionVar === null);

        // Direct update must fail
        $this->expectException(\RuntimeException::class);
        $version->change_note = 'Unauthorized post-crash tamper';
        $version->save();
    }

    public function test_template_deletion_is_restricted_when_versions_exist(): void
    {
        $template = DocumentTemplate::create([
            'code' => 'TPL-RESTRICT-TEST',
            'name' => 'Template for Restrict Test',
            'category_id' => $this->category->id,
            'created_by' => $this->adminUser->id,
        ]);

        TemplateVersion::create([
            'template_id' => $template->id,
            'version_number' => 1,
            'schema_definition' => [],
            'body_template_html' => '<p>Version 1</p>',
            'status' => 'draft',
            'created_by' => $this->adminUser->id,
        ]);

        // Attempting to delete the parent document template must be blocked by ON DELETE RESTRICT
        $this->expectException(QueryException::class);
        $template->delete();
    }

    public function test_assignment_response_document_deletion_is_restricted(): void
    {
        $incomingDoc = $this->createDummyDocument('Directive For Response Protection');
        $replyDoc = $this->createDummyDocument('Fulfilling Response Protected Doc');

        $assignment = DocumentAssignment::create([
            'document_id' => $incomingDoc->id,
            'assigned_user_id' => $this->staffUser->id,
            'assigned_unit_id' => $this->unit->id,
            'assigned_by' => $this->adminUser->id,
            'assigned_at' => now(),
            'instruction' => 'Fulfill directive',
        ]);

        AssignmentResponse::create([
            'assignment_id' => $assignment->id,
            'response_document_id' => $replyDoc->id,
            'relationship_role' => 'full_reply',
            'is_fulfilling' => true,
            'linked_by' => $this->staffUser->id,
        ]);

        // Attempting to delete the response document must be blocked by ON DELETE RESTRICT
        $this->expectException(QueryException::class);
        $replyDoc->delete();
    }
}
