<?php

namespace Tests\Feature;

use App\Models\Correspondence;
use App\Models\CorrespondenceType;
use App\Models\DocumentCategory;
use App\Models\OrganizationUnit;
use App\Models\Priority;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorrespondenceTest extends TestCase
{
    use RefreshDatabase;

    protected User $officer;
    protected User $dean;
    protected OrganizationUnit $centralAdmin;
    protected OrganizationUnit $collegeUnit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $collegeType = UnitType::where('code', 'college')->first();
        $this->centralAdmin = OrganizationUnit::first();
        $this->collegeUnit = OrganizationUnit::create([
            'parent_id' => $this->centralAdmin->id,
            'unit_type_id' => $collegeType->id,
            'code' => 'CMHS',
            'name' => 'College of Medicine & Health Sciences',
            'active' => true,
        ]);

        $this->officer = User::create([
            'name' => 'Central Records Officer',
            'email' => 'officer@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->officer, 'records_officer');

        $this->dean = User::create([
            'name' => 'Dr. Dean Bekele',
            'email' => 'dean.cmhs@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->dean, 'department_head', 'unit', $this->collegeUnit->id);
    }

    public function test_can_register_incoming_correspondence_and_forward_and_assign(): void
    {
        $this->authenticateAs($this->officer);

        $incomingType = CorrespondenceType::where('code', 'incoming')->first();
        $urgentPriority = Priority::where('code', 'urgent')->first() ?? Priority::first();
        $cat = DocumentCategory::first();
        $conf = \App\Models\ConfidentialityLevel::first();

        // 1. Register Incoming Correspondence
        $response = $this->postJson('/api/v1/correspondences', [
            'title' => 'Federal MoE Medical Equipment Directive',
            'summary' => 'Direct transfer of surgical kits and CT consumables.',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $this->centralAdmin->id,
            'type_id' => $incomingType->id,
            'priority_id' => $urgentPriority->id,
            'purpose_code' => 'directive',
            'external_reference' => 'MOE/DIR/2026/8991',
            'correspondence_date' => '2026-10-29',
            'first_receiving_unit_id' => $this->centralAdmin->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $corrId = $response->json('data.id');
        $this->assertDatabaseHas('correspondences', [
            'id' => $corrId,
            'external_reference' => 'MOE/DIR/2026/8991',
        ]);

        // 2. Record Movement (Forward from Central Admin to College)
        $moveRes = $this->postJson("/api/v1/correspondences/{$corrId}/movements", [
            'from_unit_id' => $this->centralAdmin->id,
            'to_unit_id' => $this->collegeUnit->id,
            'instruction' => 'For your office review and action as requested by MoE.',
            'due_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
        ]);

        $moveRes->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('document_movements', [
            'from_unit_id' => $this->centralAdmin->id,
            'to_unit_id' => $this->collegeUnit->id,
        ]);

        // 3. Assign to Dean
        $assignRes = $this->postJson("/api/v1/correspondences/{$corrId}/assignments", [
            'assigned_user_id' => $this->dean->id,
            'assigned_unit_id' => $this->collegeUnit->id,
            'instruction' => 'Oversee distribution to hospital clinics.',
            'due_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
        ]);

        $assignRes->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('document_assignments', [
            'assigned_user_id' => $this->dean->id,
            'assigned_unit_id' => $this->collegeUnit->id,
        ]);
    }

    public function test_correspondences_list_supports_filtering(): void
    {
        $this->authenticateAs($this->officer);

        $incomingType = CorrespondenceType::where('code', 'incoming')->first();
        $urgentPriority = Priority::where('code', 'urgent')->first() ?? Priority::first();
        $cat = DocumentCategory::first();
        $conf = \App\Models\ConfidentialityLevel::first();

        $this->postJson('/api/v1/correspondences', [
            'title' => 'Searchable MoE Letter 9988',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $this->centralAdmin->id,
            'type_id' => $incomingType->id,
            'priority_id' => $urgentPriority->id,
            'purpose_code' => 'directive',
            'external_reference' => 'EXT-FILTER-9988',
            'correspondence_date' => '2026-10-29',
        ]);

        $listRes = $this->getJson('/api/v1/correspondences?search=EXT-FILTER-9988');
        $listRes->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($listRes->json('data')));

        $emptyRes = $this->getJson('/api/v1/correspondences?search=NONEXISTENT_REF');
        $emptyRes->assertStatus(200);
        $this->assertCount(0, $emptyRes->json('data'));
    }

    public function test_unauthorized_user_cannot_register_correspondence(): void
    {
        $unauthorized = User::create([
            'name' => 'Unauthorized Staff',
            'email' => 'unauth@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        $this->authenticateAs($unauthorized);

        $incomingType = CorrespondenceType::first();
        $priority = Priority::first();

        $response = $this->postJson('/api/v1/correspondences', [
            'title' => 'Unauthorized Letter Registration',
            'category_id' => DocumentCategory::first()->id,
            'confidentiality_id' => \App\Models\ConfidentialityLevel::first()->id,
            'owner_unit_id' => $this->centralAdmin->id,
            'type_id' => $incomingType->id,
            'priority_id' => $priority->id,
            'purpose_code' => 'general',
            'correspondence_date' => '2026-10-29',
        ]);

        $response->assertStatus(403);
    }
}
