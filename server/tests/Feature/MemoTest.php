<?php

namespace Tests\Feature;

use App\Models\CommunicationParty;
use App\Models\Correspondence;
use App\Models\CorrespondenceParticipant;
use App\Models\CorrespondenceType;
use App\Models\DocumentCategory;
use App\Models\OrganizationUnit;
use App\Models\Priority;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemoTest extends TestCase
{
    use RefreshDatabase;

    protected User $sender;
    protected User $recipient;
    protected OrganizationUnit $senderUnit;
    protected OrganizationUnit $receiverUnit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $collegeType = UnitType::where('code', 'college')->first();
        $root = OrganizationUnit::first();

        $this->senderUnit = OrganizationUnit::create([
            'parent_id' => $root->id,
            'unit_type_id' => $collegeType->id,
            'code' => 'CNCS',
            'name' => 'College of Natural & Computational Sciences',
            'active' => true,
        ]);

        $this->receiverUnit = OrganizationUnit::create([
            'parent_id' => $root->id,
            'unit_type_id' => $collegeType->id,
            'code' => 'KIOT',
            'name' => 'Kombolcha Institute of Technology',
            'active' => true,
        ]);

        $this->sender = User::create([
            'name' => 'Dean CNCS',
            'email' => 'dean.cncs@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->sender, 'department_head', 'unit', $this->senderUnit->id);

        $this->recipient = User::create([
            'name' => 'Director KIOT',
            'email' => 'director.kiot@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->recipient, 'department_head', 'unit', $this->receiverUnit->id);
    }

    public function test_can_register_internal_memorandum_between_units(): void
    {
        $this->authenticateAs($this->sender);

        $memoType = CorrespondenceType::where('code', 'internal_memo')->firstOrFail();
        $urgentPriority = Priority::where('code', 'urgent')->first() ?? Priority::first();
        $category = DocumentCategory::first();
        $confidentiality = \App\Models\ConfidentialityLevel::first();

        $response = $this->postJson('/api/v1/correspondences', [
            'title' => 'Inter-College Lab Equipment Sharing Agreement',
            'summary' => 'Arrangement for joint utilization of SEM electron microscopy laboratory.',
            'category_id' => $category->id,
            'confidentiality_id' => $confidentiality->id,
            'owner_unit_id' => $this->senderUnit->id,
            'type_id' => $memoType->id,
            'priority_id' => $urgentPriority->id,
            'purpose_code' => 'inter_college_coordination',
            'correspondence_date' => '2026-10-29',
            'sender_unit_id' => $this->senderUnit->id,
            'to_unit_id' => $this->receiverUnit->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $corrId = $response->json('data.id');
        $this->assertDatabaseHas('correspondences', [
            'id' => $corrId,
            'type_id' => $memoType->id,
            'purpose_code' => 'inter_college_coordination',
        ]);

        // Verify participants
        $this->assertDatabaseHas('correspondence_participants', [
            'correspondence_id' => $corrId,
            'participation_type' => 'sender',
        ]);
        $this->assertDatabaseHas('correspondence_participants', [
            'correspondence_id' => $corrId,
            'participation_type' => 'to',
        ]);
    }

    public function test_inter_office_memorandum_routing_and_forwarding(): void
    {
        $this->authenticateAs($this->sender);

        $memoType = CorrespondenceType::where('code', 'internal_memo')->firstOrFail();
        $priority = Priority::first();
        $category = DocumentCategory::first();
        $confidentiality = \App\Models\ConfidentialityLevel::first();

        $createRes = $this->postJson('/api/v1/correspondences', [
            'title' => 'Annual Joint Research Symposium Planning',
            'category_id' => $category->id,
            'confidentiality_id' => $confidentiality->id,
            'owner_unit_id' => $this->senderUnit->id,
            'type_id' => $memoType->id,
            'priority_id' => $priority->id,
            'purpose_code' => 'symposium_planning',
            'correspondence_date' => '2026-10-29',
        ]);

        $corrId = $createRes->json('data.id');

        // Record movement / forwarding
        $moveRes = $this->postJson("/api/v1/correspondences/{$corrId}/movements", [
            'from_unit_id' => $this->senderUnit->id,
            'to_unit_id' => $this->receiverUnit->id,
            'instruction' => 'Please provide the list of keynote presenters by Friday.',
            'due_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
        ]);

        $moveRes->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('document_movements', [
            'from_unit_id' => $this->senderUnit->id,
            'to_unit_id' => $this->receiverUnit->id,
        ]);
    }

    public function test_office_procedure_governance_rules(): void
    {
        $admin = User::where('email', 'admin@wu.edu.et')->first();
        $this->authenticateAs($admin);

        $memoType = CorrespondenceType::where('code', 'internal_memo')->firstOrFail();

        $response = $this->postJson('/api/v1/procedures', [
            'unit_id' => $this->senderUnit->id,
            'correspondence_type_id' => $memoType->id,
            'purpose_code' => 'lab_sharing',
            'receipt_mode' => 'direct',
            'issue_mode' => 'direct',
            'confirmation_source' => 'Senate Resolution 2026/08',
            'effective_from' => '2026-10-01',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $listRes = $this->getJson('/api/v1/procedures');
        $listRes->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
