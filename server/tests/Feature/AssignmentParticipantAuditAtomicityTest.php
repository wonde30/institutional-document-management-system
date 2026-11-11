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
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The atomicity of an assignment participant removal and its audit records.
 *
 * A database level failure is injected while the audit is written. The removal
 * and both of its audit records must share one transaction, so the removal must
 * be rolled back with them: a failed removal may never leave a state change
 * behind, and a successful removal may never leave an unaudited change.
 *
 * The test uses the migration based database refresh because a trigger cannot
 * be created inside a wrapped transaction: the statement would implicitly
 * commit it.
 */
class AssignmentParticipantAuditAtomicityTest extends TestCase
{
    use DatabaseMigrations;

    protected User $officer;
    protected User $participant;
    protected DocumentAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $collegeType = UnitType::firstOrCreate(['code' => 'college'], ['name' => 'College', 'active' => 1]);
        $unit = OrganizationUnit::first();
        OrganizationUnit::create([
            'parent_id' => $unit->id,
            'unit_type_id' => $collegeType->id,
            'code' => 'ATOMICITY_UNIT_B',
            'name' => 'Atomicity Unit B',
            'active' => 1,
        ]);

        $this->officer = User::create([
            'name' => 'Atomicity Officer',
            'email' => 'assignment.atomicity.officer@wu.edu.et',
            'password' => bcrypt('StrongPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->officer, 'records_officer', 'unit', $unit->id);

        $this->participant = User::create([
            'name' => 'Atomicity Participant',
            'email' => 'assignment.atomicity.participant@wu.edu.et',
            'password' => bcrypt('StrongPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        $document = Document::create([
            'reference_number' => 'WU/DOC/2026/'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'title' => 'Atomicity document',
            'category_id' => DocumentCategory::first()->id,
            'confidentiality_id' => \App\Models\ConfidentialityLevel::where('code', 'internal')->value('id'),
            'owner_unit_id' => $unit->id,
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
            'assigned_unit_id' => $unit->id,
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

    public function test_a_failure_while_the_audit_is_written_rolls_the_removal_back(): void
    {
        $this->authenticateAs($this->officer);

        // The audit write fails at the database level.
        DB::unprepared(
            "CREATE TRIGGER ctx_assignment_audit_failure BEFORE INSERT ON `audit_events` ".
            "FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced audit failure'"
        );

        try {
            $response = $this->deleteJson(
                "/api/v1/assignments/{$this->assignment->id}/participants/{$this->participant->id}"
            );

            $this->assertSame(500, $response->status());
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS ctx_assignment_audit_failure');
        }

        // Nothing was committed: the participant is still a member of the team.
        $this->assertDatabaseHas('assignment_participants', [
            'assignment_id' => $this->assignment->id,
            'user_id' => $this->participant->id,
        ]);

        // No lifecycle event and no audit event survived the failure.
        $this->assertSame(0, AssignmentEvent::where('action', 'participant_removed')->count());
        $this->assertSame(0, DB::table('audit_events')->where('action', 'assignment.participant_removed')->count());
    }
}
