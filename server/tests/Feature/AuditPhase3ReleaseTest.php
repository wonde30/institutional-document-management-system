<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\ConfidentialityLevel;
use App\Models\DigitizationRecord;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\LegalHold;
use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\PhysicalOriginal;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditPhase3ReleaseTest extends TestCase
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
            'code' => 'PH3_B_'.Str::random(4),
            'name' => 'Phase 3 Unit B',
            'active' => true,
        ]);
    }

    public function test_release_legal_hold_enforces_document_unit_scope_and_records_audit_atomically(): void
    {
        $cat = DocumentCategory::firstOrFail();
        $conf = ConfidentialityLevel::whereNotIn('code', ['confidential', 'restricted', 'secret', 'top_secret'])->first()
            ?? ConfidentialityLevel::firstOrFail();

        $docA = Document::create([
            'reference_number' => 'WU/LH/A/'.Str::random(5),
            'title' => 'Unit A Legal Record',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->admin->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $docB = Document::create([
            'reference_number' => 'WU/LH/B/'.Str::random(5),
            'title' => 'Unit B Legal Record',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $this->unitB->id,
            'created_by' => $this->admin->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $holdUnitB = LegalHold::create([
            'code' => 'LH-B-'.Str::random(5),
            'reason' => 'Unit B audit hold',
            'placed_by' => $this->admin->id,
            'placed_at' => now(),
        ]);
        DB::table('legal_hold_documents')->insert(['hold_id' => $holdUnitB->id, 'document_id' => $docB->id]);

        $holdUnscoped = LegalHold::create([
            'code' => 'LH-GLOB-'.Str::random(5),
            'reason' => 'Institution-wide unscoped hold',
            'placed_by' => $this->admin->id,
            'placed_at' => now(),
        ]);

        $holdUnitA = LegalHold::create([
            'code' => 'LH-A-'.Str::random(5),
            'reason' => 'Unit A audit hold',
            'placed_by' => $this->admin->id,
            'placed_at' => now(),
        ]);
        DB::table('legal_hold_documents')->insert(['hold_id' => $holdUnitA->id, 'document_id' => $docA->id]);

        $unitAArchivist = User::create([
            'name' => 'Unit A Archivist',
            'email' => 'arch.a.'.Str::random(5).'@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($unitAArchivist, 'archive.transfer', 'unit', $this->unitA->id);
        $this->authenticateAs($unitAArchivist);

        // Unit A user cannot release Unit B hold or unscoped institutional hold
        $this->postJson("/api/v1/archive/legal-holds/{$holdUnitB->id}/release")->assertStatus(403);
        $this->assertNull($holdUnitB->fresh()->released_at);

        $this->postJson("/api/v1/archive/legal-holds/{$holdUnscoped->id}/release")->assertStatus(403);
        $this->assertNull($holdUnscoped->fresh()->released_at);

        // Audit failure must roll back release of own-unit hold
        AuditEvent::creating(function () {
            throw new \RuntimeException('Simulated audit failure on legal hold release');
        });
        try {
            $this->postJson("/api/v1/archive/legal-holds/{$holdUnitA->id}/release")->assertStatus(500);
            $this->assertNull($holdUnitA->fresh()->released_at);
        } finally {
            AuditEvent::flushEventListeners();
        }

        // Valid release on own-unit hold succeeds and writes AuditEvent
        $this->postJson("/api/v1/archive/legal-holds/{$holdUnitA->id}/release")
            ->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertNotNull($holdUnitA->fresh()->released_at);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'archive.legal_hold_released',
            'target_type' => 'legal_hold',
            'target_id' => (string) $holdUnitA->id,
        ]);
    }

    public function test_permission_update_and_staff_scan_verification_roll_back_on_audit_failure(): void
    {
        $cat = DocumentCategory::firstOrFail();
        $conf = ConfidentialityLevel::whereNotIn('code', ['confidential', 'restricted', 'secret', 'top_secret'])->first()
            ?? ConfidentialityLevel::firstOrFail();
        $perm = Permission::where('code', 'documents.view')->firstOrFail();
        $initialActive = (bool) $perm->active;

        $doc = Document::create([
            'reference_number' => 'WU/HR/RB/'.Str::random(5),
            'title' => 'Rollback Staff Scan Record',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->admin->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);
        $ver = DocumentVersion::create(['document_id' => $doc->id, 'version_number' => 1, 'created_by' => $this->admin->id, 'created_at' => now()]);
        $orig = PhysicalOriginal::create([
            'document_id' => $doc->id,
            'physical_reference' => 'PHY-RB-'.Str::random(4),
            'retention_requirement' => 'required',
            'created_by' => $this->admin->id,
            'created_at' => now(),
        ]);
        $dig = DigitizationRecord::create([
            'physical_original_id' => $orig->id,
            'document_version_id' => $ver->id,
            'digitized_by' => $this->admin->id,
            'digitized_at' => now(),
            'verification_status' => 'pending',
        ]);

        $this->authenticateAs($this->admin);
        AuditEvent::creating(function () {
            throw new \RuntimeException('Simulated audit failure on metadata/scan mutation');
        });

        try {
            $this->patchJson("/api/v1/permissions/{$perm->id}", [
                'active' => ! $initialActive,
            ])->assertStatus(500);
            $this->assertSame($initialActive, (bool) $perm->fresh()->active);

            $this->postJson("/api/v1/staff/records/{$orig->id}/verify-scan", [
                'verification_status' => 'accepted',
            ])->assertStatus(500);
            $this->assertSame('pending', $dig->fresh()->verification_status);
        } finally {
            AuditEvent::flushEventListeners();
        }
    }
}
