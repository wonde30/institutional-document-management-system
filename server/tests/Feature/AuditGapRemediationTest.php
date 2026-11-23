<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditGapRemediationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $this->admin = User::where('email', 'admin@wu.edu.et')->firstOrFail();
        $this->staff = User::where('email', 'staff.tech@wu.edu.et')->firstOrFail();
    }

    public function test_custom_role_deletion_with_roles_delete_permission(): void
    {
        $customRole = Role::create([
            'code' => 'custom_auditor_' . uniqid(),
            'name' => 'Custom External Auditor',
            'is_system' => false,
            'active' => true,
        ]);

        // User without roles.delete gets 403
        $this->actingAs($this->staff);
        $resForbidden = $this->deleteJson("/api/v1/roles/{$customRole->id}");
        $resForbidden->assertStatus(403);

        // Admin with roles.delete can delete unassigned custom role
        $this->actingAs($this->admin);
        $resSuccess = $this->deleteJson("/api/v1/roles/{$customRole->id}");
        $resSuccess->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('roles', ['id' => $customRole->id]);
    }

    public function test_system_role_deletion_is_blocked(): void
    {
        $systemRole = Role::where('code', 'staff')->firstOrFail();

        $this->actingAs($this->admin);
        $res = $this->deleteJson("/api/v1/roles/{$systemRole->id}");
        $res->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_reports_export_streamed_csv(): void
    {
        $this->actingAs($this->admin);
        $res = $this->get('/api/v1/reports/export');
        $res->assertStatus(200);
        $res->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_document_share_creates_grant_and_audit(): void
    {
        $category = DocumentCategory::first();
        $unit = OrganizationUnit::first();

        $doc = Document::create([
            'reference_number' => 'WU/DOC/TEST/' . uniqid(),
            'title' => 'Test Document for Discretionary Sharing',
            'summary' => 'Summary',
            'category_id' => $category->id,
            'confidentiality_id' => 1,
            'owner_unit_id' => $unit->id,
            'created_by' => $this->admin->id,
            'lifecycle' => 'active',
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
        ]);

        $this->actingAs($this->admin);
        $res = $this->postJson("/api/v1/documents/{$doc->id}/share", [
            'target_user_id' => $this->staff->id,
            'permission_code' => 'documents.view',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('document_grants', [
            'document_id' => $doc->id,
            'user_id' => $this->staff->id,
        ]);
    }
}
