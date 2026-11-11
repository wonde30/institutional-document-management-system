<?php

namespace Tests\Feature;

use App\Models\AssignmentEvent;
use App\Models\AssignmentParticipant;
use App\Models\Document;
use App\Models\DocumentAssignment;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\OrganizationUnit;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The audit trail of an assignment participant removal.
 *
 * A participant removal is a change to the delegation of an institutional
 * record, so it must leave both the domain lifecycle trail and the system
 * audit trail, and it must never report a success that did not happen.
 */
class AssignmentParticipantAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $officer;
    protected User $participant;
    protected OrganizationUnit $unitA;
    protected OrganizationUnit $unitB;
    protected DocumentAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $collegeType = UnitType::firstOrCreate(['code' => 'college'], ['name' => 'College', 'active' => 1]);
        $this->unitA = OrganizationUnit::first();
        $this->unitB = OrganizationUnit::create([
            'parent_id' => $this->unitA->id,
            'unit_type_id' => $collegeType->id,
            'code' => 'AUDIT_ASSIGNMENT_UNIT_B',
            'name' => 'Audit Assignment Unit B',
            'active' => 1,
        ]);

        $this->officer = User::create([
            'name' => 'Audit Officer',
            'email' => 'assignment.audit.officer@wu.edu.et',
            'password' => bcrypt('StrongPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->officer, 'records_officer', 'unit', $this->unitA->id);

        $this->participant = User::create([
            'name' => 'Audit Participant',
            'email' => 'assignment.audit.participant@wu.edu.et',
            'password' => bcrypt('StrongPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        $document = Document::create([
            'reference_number' => 'WU/DOC/2026/'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'title' => 'Audit assignment document',
            'category_id' => DocumentCategory::first()->id,
            'confidentiality_id' => \App\Models\ConfidentialityLevel::where('code', 'internal')->value('id'),
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->officer->id,
            'lifecycle' => 'active',
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
        ]);

        DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 1,
            'created_by' => $this->officer->id,
            'comment' => 'Initial version',
        ]);

        $this->assignment = DocumentAssignment::create([
            'document_id' => $document->id,
            'assigned_user_id' => $this->participant->id,
            'assigned_unit_id' => $this->unitA->id,
            'assigned_by' => $this->officer->id,
            'assigned_at' => now(),
            'instruction' => 'Prepare the committee report',
        ]);

        AssignmentParticipant::create([
            'assignment_id' => $this->assignment->id,
            'user_id' => $this->participant->id,
            'role_code' => 'writer',
            'status' => 'accepted',
            'assigned_at' => now()->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function test_a_successful_removal_is_audited(): void
    {
        $this->authenticateAs($this->officer);

        $response = $this->deleteJson("/api/v1/assignments/{$this->assignment->id}/participants/{$this->participant->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.user_id', $this->participant->id);
        $response->assertJsonPath('data.previous_role_code', 'writer');

        // The participant is gone.
        $this->assertDatabaseMissing('assignment_participants', [
            'assignment_id' => $this->assignment->id,
            'user_id' => $this->participant->id,
        ]);

        // The domain lifecycle event is recorded.
        $event = AssignmentEvent::where('assignment_id', $this->assignment->id)
            ->where('action', 'participant_removed')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame($this->officer->id, (int) $event->actor_id);
        $this->assertSame($this->participant->id, (int) $event->target_user_id);
        $this->assertNotNull($event->occurred_at);

        // The system audit trail is recorded with the full detail.
        $audit = DB::table('audit_events')
            ->where('action', 'assignment.participant_removed')
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($this->officer->id, (int) $audit->actor_id);
        $this->assertSame('document_assignment', $audit->target_type);
        $this->assertSame((string) $this->assignment->id, $audit->target_id);
        $this->assertNotNull($audit->occurred_at);

        $details = json_decode($audit->details_json, true);

        $this->assertSame($this->assignment->id, $details['assignment_id']);
        $this->assertSame($this->participant->id, $details['removed_user_id']);
        $this->assertSame($this->unitA->id, $details['scope_unit_id']);
        $this->assertSame('writer', $details['previous_state']['role_code']);
        $this->assertSame('accepted', $details['previous_state']['status']);
        $this->assertNull($details['resulting_state']);
    }

    public function test_removing_a_participant_that_is_not_a_member_is_refused(): void
    {
        $this->authenticateAs($this->officer);

        $other = User::create([
            'name' => 'Not A Participant',
            'email' => 'assignment.audit.stranger@wu.edu.et',
            'password' => bcrypt('StrongPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        $response = $this->deleteJson("/api/v1/assignments/{$this->assignment->id}/participants/{$other->id}");

        $response->assertStatus(404);
        $response->assertJsonPath('success', false);

        // No removal was reported and nothing was audited.
        $this->assertSame(0, AssignmentEvent::where('action', 'participant_removed')->count());
        $this->assertSame(0, DB::table('audit_events')->where('action', 'assignment.participant_removed')->count());

        // The participant is untouched.
        $this->assertDatabaseHas('assignment_participants', [
            'assignment_id' => $this->assignment->id,
            'user_id' => $this->participant->id,
        ]);
    }

    public function test_removing_an_already_removed_participant_is_refused(): void
    {
        $this->authenticateAs($this->officer);

        $url = "/api/v1/assignments/{$this->assignment->id}/participants/{$this->participant->id}";

        $this->deleteJson($url)->assertStatus(200);

        // The second attempt is refused and audits nothing further.
        $this->deleteJson($url)->assertStatus(404);

        $this->assertSame(1, AssignmentEvent::where('action', 'participant_removed')->count());
        $this->assertSame(1, DB::table('audit_events')->where('action', 'assignment.participant_removed')->count());
    }

    public function test_an_unauthorised_removal_is_refused_and_audits_nothing(): void
    {
        $stranger = User::create([
            'name' => 'Unauthorised Officer',
            'email' => 'assignment.audit.stranger@wu.edu.et',
            'password' => bcrypt('StrongPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        $this->authenticateAs($stranger);

        $response = $this->deleteJson("/api/v1/assignments/{$this->assignment->id}/participants/{$this->participant->id}");

        $response->assertStatus(403);

        $this->assertSame(0, AssignmentEvent::where('action', 'participant_removed')->count());
        $this->assertSame(0, DB::table('audit_events')->where('action', 'assignment.participant_removed')->count());
        $this->assertDatabaseHas('assignment_participants', [
            'assignment_id' => $this->assignment->id,
            'user_id' => $this->participant->id,
        ]);
    }

    public function test_a_cross_unit_removal_is_refused(): void
    {
        $stranger = User::create([
            'name' => 'Foreign Unit Officer',
            'email' => 'assignment.audit.foreign@wu.edu.et',
            'password' => bcrypt('StrongPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($stranger, 'records_officer', 'unit', $this->unitB->id);

        $this->authenticateAs($stranger);

        $this->deleteJson("/api/v1/assignments/{$this->assignment->id}/participants/{$this->participant->id}")
            ->assertStatus(403);

        $this->assertSame(0, AssignmentEvent::where('action', 'participant_removed')->count());
        $this->assertDatabaseHas('assignment_participants', [
            'assignment_id' => $this->assignment->id,
            'user_id' => $this->participant->id,
        ]);
    }

    /**
     * The atomicity of the removal and its audit records is proven by
     * AssignmentParticipantAuditAtomicityTest, which injects a database level
     * failure while the audit is written. That test uses the migration based
     * database refresh, because a trigger cannot be created inside a wrapped
     * transaction.
     */
}