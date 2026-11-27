<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\ConfidentialityLevel;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\LegalHold;
use App\Models\OrganizationUnit;
use App\Models\ProjectProposal;
use App\Models\ProjectType;
use App\Models\ResearchRecord;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditPhase4ReferenceAndLegalHoldTest extends TestCase
{
    use RefreshDatabase;

    protected OrganizationUnit $unitA;

    protected OrganizationUnit $unitB;

    protected User $admin;

    protected DocumentCategory $category;

    protected ConfidentialityLevel $internalConf;

    protected ConfidentialityLevel $confidentialConf;

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
            'code' => 'PH4_B_'.Str::random(4),
            'name' => 'Phase 4 Unit B',
            'active' => true,
        ]);

        $this->category = DocumentCategory::firstOrFail();
        $this->internalConf = ConfidentialityLevel::whereNotIn('code', ['confidential', 'restricted', 'secret', 'top_secret'])->first()
            ?? ConfidentialityLevel::firstOrFail();
        $this->confidentialConf = ConfidentialityLevel::where('code', 'confidential')->first()
            ?? ConfidentialityLevel::create(['code' => 'confidential', 'name' => 'Confidential', 'active' => 1]);
    }

    public function test_reference_allocation_rolls_back_on_failure_and_skips_preexisting_or_archived_references(): void
    {
        $year = date('Y');
        $existingMaxRes = DB::table('documents')
            ->where('reference_number', 'like', "WU/RES/{$year}/%")
            ->pluck('reference_number')
            ->map(fn ($ref) => (int) substr((string) $ref, strlen("WU/RES/{$year}/")))
            ->max() ?? 0;

        $refLow = 'WU/RES/'.$year.'/'.sprintf('%04d', $existingMaxRes + 1);
        $refHigh = 'WU/RES/'.$year.'/'.sprintf('%04d', $existingMaxRes + 4);
        $refExpectedNext = 'WU/RES/'.$year.'/'.sprintf('%04d', $existingMaxRes + 5);

        // Seed existing documents including a retired item and a higher-suffix gap
        $doc1 = Document::create([
            'reference_number' => $refLow,
            'title' => 'Historical Research 1',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->internalConf->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->admin->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'retired',
        ]);
        ResearchRecord::create([
            'document_id' => $doc1->id,
            'research_type' => 'faculty_monograph',
            'completed_on' => now()->toDateString(),
            'recorded_by' => $this->admin->id,
            'recorded_at' => now(),
        ]);

        Document::create([
            'reference_number' => $refHigh,
            'title' => 'Out-of-band Research 4',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->internalConf->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->admin->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $this->authenticateAs($this->admin);

        $beforeSequence = DB::table('reference_sequences')->where('sequence_key', 'RES')->where('period_key', $year)->first();

        // Simulated failure inside storeResearch must roll back sequence & document creation
        AuditEvent::creating(function () {
            throw new \RuntimeException('Simulated audit error during research creation');
        });
        try {
            $this->postJson('/api/v1/research/outputs', [
                'title' => 'Rolled Back Research',
                'office_id' => $this->unitA->id,
                'completion_date' => now()->toDateString(),
            ])->assertStatus(500);
        } finally {
            AuditEvent::flushEventListeners();
        }

        $afterRollbackSequence = DB::table('reference_sequences')->where('sequence_key', 'RES')->where('period_key', $year)->first();
        $this->assertEquals($beforeSequence?->next_value, $afterRollbackSequence?->next_value);
        $this->assertDatabaseMissing('documents', ['title' => 'Rolled Back Research']);

        // Next valid store must start cleanly past existing historical/retired max without reusing or colliding
        $ok = $this->postJson('/api/v1/research/outputs', [
            'title' => 'Preserved Sequence Research',
            'office_id' => $this->unitA->id,
            'completion_date' => now()->toDateString(),
        ])->assertStatus(201);

        $this->assertSame($refExpectedNext, $ok->json('data.document.reference_number'));
        $this->assertSame($refLow, $doc1->fresh()->reference_number);

        // Same gap-safe uniqueness check for PROJ
        $existingMaxProj = DB::table('documents')
            ->where('reference_number', 'like', "WU/PROJ/{$year}/%")
            ->pluck('reference_number')
            ->map(fn ($ref) => (int) substr((string) $ref, strlen("WU/PROJ/{$year}/")))
            ->max() ?? 0;
        $projGap = 'WU/PROJ/'.$year.'/'.sprintf('%04d', $existingMaxProj + 3);
        $projExpected = 'WU/PROJ/'.$year.'/'.sprintf('%04d', $existingMaxProj + 4);

        Document::create([
            'reference_number' => $projGap,
            'title' => 'Existing Project 3',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->internalConf->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->admin->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);
        $projType = ProjectType::first() ?? ProjectType::create(['code' => 'P4', 'name' => 'P4', 'active' => 1]);
        $projRes = $this->postJson('/api/v1/projects', [
            'title' => 'New Atomic Project',
            'office_id' => $this->unitA->id,
            'project_type_id' => $projType->id,
        ])->assertStatus(201);
        $this->assertSame($projExpected, $projRes->json('data.document.reference_number'));
        $this->assertGreaterThanOrEqual(1, ProjectProposal::count());
    }

    public function test_legal_hold_release_edge_cases_across_units_confidentiality_multi_unit_toctou_and_failures(): void
    {
        $docAInternal = Document::create([
            'reference_number' => 'WU/LH4/A1/'.Str::random(5),
            'title' => 'Unit A Internal',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->internalConf->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->admin->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $docAConf = Document::create([
            'reference_number' => 'WU/LH4/AC/'.Str::random(5),
            'title' => 'Unit A Confidential',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentialConf->id,
            'owner_unit_id' => $this->unitA->id,
            'created_by' => $this->admin->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        $docBInternal = Document::create([
            'reference_number' => 'WU/LH4/B1/'.Str::random(5),
            'title' => 'Unit B Internal',
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->internalConf->id,
            'owner_unit_id' => $this->unitB->id,
            'created_by' => $this->admin->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
        ]);

        // Users
        $unauthorizedUnitAUser = User::create([
            'name' => 'Unit A Viewer Only',
            'email' => 'viewer.a.'.Str::random(4).'@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($unauthorizedUnitAUser, 'documents.view', 'unit', $this->unitA->id);

        $unitAArchivist = User::create([
            'name' => 'Unit A Archivist Only',
            'email' => 'arch.a4.'.Str::random(4).'@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($unitAArchivist, 'archive.transfer', 'unit', $this->unitA->id);

        $dualUnitArchivist = User::create([
            'name' => 'Dual Unit Archivist Without Global',
            'email' => 'arch.dual.'.Str::random(4).'@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($dualUnitArchivist, 'archive.transfer', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($dualUnitArchivist, 'archive.transfer', 'unit', $this->unitB->id);

        $globalArchivist = User::create([
            'name' => 'Global Archivist',
            'email' => 'arch.glob.'.Str::random(4).'@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($globalArchivist, 'archive.transfer', 'global');

        // 1. Mixed confidentiality single-unit hold (first internal, second confidential)
        $holdMixedConfA = LegalHold::create([
            'code' => 'LH4-CONF-'.Str::random(4),
            'reason' => 'Unit A hold with confidential attachment',
            'placed_by' => $this->admin->id,
            'placed_at' => now(),
        ]);
        DB::table('legal_hold_documents')->insert([
            ['hold_id' => $holdMixedConfA->id, 'document_id' => $docAInternal->id],
            ['hold_id' => $holdMixedConfA->id, 'document_id' => $docAConf->id],
        ]);

        // 2. Multi-unit hold (Unit A + Unit B)
        $holdMultiUnit = LegalHold::create([
            'code' => 'LH4-MULTI-'.Str::random(4),
            'reason' => 'Cross-unit institutional hold',
            'placed_by' => $this->admin->id,
            'placed_at' => now(),
        ]);
        DB::table('legal_hold_documents')->insert([
            ['hold_id' => $holdMultiUnit->id, 'document_id' => $docAInternal->id],
            ['hold_id' => $holdMultiUnit->id, 'document_id' => $docBInternal->id],
        ]);

        // 3. Global unscoped hold (0 attached documents)
        $holdUnscoped = LegalHold::create([
            'code' => 'LH4-NONE-'.Str::random(4),
            'reason' => 'No documents attached yet',
            'placed_by' => $this->admin->id,
            'placed_at' => now(),
        ]);

        // Unauthorized same-unit user -> 403
        $this->authenticateAs($unauthorizedUnitAUser);
        $this->postJson("/api/v1/archive/legal-holds/{$holdMixedConfA->id}/release")->assertStatus(403);

        // Unit A archivist without staff.view_sensitive blocked on Unit A mixed confidential hold -> 403
        $this->authenticateAs($unitAArchivist);
        $this->postJson("/api/v1/archive/legal-holds/{$holdMixedConfA->id}/release")->assertStatus(403);
        // Also blocked on multi-unit hold, unscoped hold, and nonexistent hold (fail-closed)
        $this->postJson("/api/v1/archive/legal-holds/{$holdMultiUnit->id}/release")->assertStatus(403);
        $this->postJson("/api/v1/archive/legal-holds/{$holdUnscoped->id}/release")->assertStatus(403);
        $this->postJson('/api/v1/archive/legal-holds/999999/release')->assertStatus(403);

        // User holding separate unit grants on both A and B (non-global) blocked on multi-unit hold -> 403
        $this->authenticateAs($dualUnitArchivist);
        $this->postJson("/api/v1/archive/legal-holds/{$holdMultiUnit->id}/release")->assertStatus(403);

        // Global archivist without staff.view_sensitive still blocked on confidential doc -> 403
        $this->authenticateAs($globalArchivist);
        $this->postJson("/api/v1/archive/legal-holds/{$holdMixedConfA->id}/release")->assertStatus(403);
        // Global archivist releases multi-unit non-confidential hold & unscoped hold -> 200
        $this->postJson("/api/v1/archive/legal-holds/{$holdMultiUnit->id}/release")->assertStatus(200);
        // Repeated release of already released hold -> 422 and still only 1 release audit event
        $this->postJson("/api/v1/archive/legal-holds/{$holdMultiUnit->id}/release")->assertStatus(422);
        $this->assertSame(1, AuditEvent::where('action', 'archive.legal_hold_released')->where('target_id', (string) $holdMultiUnit->id)->count());

        $this->postJson("/api/v1/archive/legal-holds/{$holdUnscoped->id}/release")->assertStatus(200);
        $this->assertNotNull($holdUnscoped->fresh()->released_at);

        // Database failure during release must roll back cleanly with zero audit logs
        $holdDbFail = LegalHold::create([
            'code' => 'LH4-DBF-'.Str::random(4),
            'reason' => 'DB update failure test',
            'placed_by' => $this->admin->id,
            'placed_at' => now(),
        ]);
        DB::table('legal_hold_documents')->insert(['hold_id' => $holdDbFail->id, 'document_id' => $docAInternal->id]);

        $this->authenticateAs($unitAArchivist);
        LegalHold::updating(function () {
            throw new \RuntimeException('Simulated DB write error on legal hold');
        });
        try {
            $this->postJson("/api/v1/archive/legal-holds/{$holdDbFail->id}/release")->assertStatus(500);
            $this->assertNull($holdDbFail->fresh()->released_at);
            $this->assertSame(0, AuditEvent::where('action', 'archive.legal_hold_released')->where('target_id', (string) $holdDbFail->id)->count());
        } finally {
            LegalHold::flushEventListeners();
        }

        // TOCTOU safeguard: hold modified (Unit B document attached inside lock resolution window) caught inside locked DB::transaction()
        $holdToctou = LegalHold::create([
            'code' => 'LH4-TOC-'.Str::random(4),
            'reason' => 'TOCTOU re-check hold',
            'placed_by' => $this->admin->id,
            'placed_at' => now(),
        ]);
        DB::table('legal_hold_documents')->insert(['hold_id' => $holdToctou->id, 'document_id' => $docAInternal->id]);

        $injected = false;
        LegalHold::retrieved(function (LegalHold $h) use (&$injected, $holdToctou, $docBInternal) {
            if ($h->id === $holdToctou->id && ! $injected) {
                $injected = true;
                DB::table('legal_hold_documents')->insertOrIgnore([
                    'hold_id' => $holdToctou->id,
                    'document_id' => $docBInternal->id,
                ]);
            }
        });
        try {
            $this->postJson("/api/v1/archive/legal-holds/{$holdToctou->id}/release")->assertStatus(403);
            $this->assertNull($holdToctou->fresh()->released_at);
        } finally {
            LegalHold::flushEventListeners();
        }

        // Nonexistent hold for system.configure -> 404
        $this->authenticateAs($this->admin);
        $this->postJson('/api/v1/archive/legal-holds/999999/release')->assertStatus(404);
    }
}
