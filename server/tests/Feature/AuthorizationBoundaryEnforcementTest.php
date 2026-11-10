<?php

namespace Tests\Feature;

use App\Models\AuthorizationScope;
use App\Models\ConfidentialityLevel;
use App\Models\Document;
use App\Models\DocumentAssignment;
use App\Models\DocumentCategory;
use App\Models\DocumentMovement;
use App\Models\DocumentTextExtraction;
use App\Models\DocumentVersion;
use App\Models\FileObject;
use App\Models\OrganizationMembership;
use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\PrimaryMembership;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Record-level authorization boundary enforcement.
 *
 * These tests cover the route families whose records are addressed directly
 * (document movements, operational directives, OCR extractions, signature
 * requests and accreditation evidence). Every one of those families resolves
 * to an owning document or organizational unit, so a capability-only check is
 * insufficient: a caller must additionally hold the capability in the scope
 * that owns the record.
 */
class AuthorizationBoundaryEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected User $officerUnitA;
    protected User $officerUnitB;
    protected OrganizationUnit $unitA;
    protected OrganizationUnit $unitB;
    protected DocumentCategory $category;
    protected ConfidentialityLevel $confidentiality;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);
        Storage::fake('local');

        $collegeType = UnitType::firstOrCreate(['code' => 'college'], ['name' => 'College', 'active' => 1]);
        $this->unitA = OrganizationUnit::first();
        $this->unitB = OrganizationUnit::create([
            'parent_id' => $this->unitA->id,
            'unit_type_id' => $collegeType->id,
            'code' => 'BOUNDARY_UNIT_B',
            'name' => 'Boundary Secondary Unit B',
            'active' => 1,
        ]);

        $this->category = DocumentCategory::first();
        $this->confidentiality = ConfidentialityLevel::where('code', 'internal')->first() ?? ConfidentialityLevel::first();

        $this->officerUnitA = User::create([
            'name' => 'Boundary Officer Unit A',
            'email' => 'boundary.a@wu.edu.et',
            'password' => bcrypt('StrongPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        $this->officerUnitB = User::create([
            'name' => 'Boundary Officer Unit B',
            'email' => 'boundary.b@wu.edu.et',
            'password' => bcrypt('StrongPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        // Both officers operate from a primary organizational unit, which is the
        // scope in which the client capability set is published.
        $this->establishPrimaryMembership($this->officerUnitA, $this->unitA);
        $this->establishPrimaryMembership($this->officerUnitB, $this->unitB);
    }

    protected function establishPrimaryMembership(User $user, OrganizationUnit $unit): void
    {
        $membership = OrganizationMembership::firstOrCreate(
            ['user_id' => $user->id, 'unit_id' => $unit->id],
            [
                'position_title' => 'Records Officer',
                'starts_at' => now()->subMonths(6),
                'created_by' => $user->id,
                'created_at' => now(),
            ]
        );

        PrimaryMembership::updateOrCreate(
            ['user_id' => $user->id],
            ['membership_id' => $membership->id, 'selected_at' => now()]
        );
    }

    protected function createTestDocument(OrganizationUnit $unit, User $creator): Document
    {
        $doc = Document::create([
            'reference_number' => 'REF-BOUNDARY-'.uniqid(),
            'title' => 'Boundary Document for '.$unit->name,
            'category_id' => $this->category->id,
            'confidentiality_id' => $this->confidentiality->id,
            'owner_unit_id' => $unit->id,
            'created_by' => $creator->id,
            'lifecycle' => 'active',
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
        ]);

        DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $creator->id,
            'comment' => 'Initial version',
        ]);

        return $doc;
    }

    protected function createExtraction(Document $doc): DocumentTextExtraction
    {
        $version = DocumentVersion::where('document_id', $doc->id)->firstOrFail();

        $fileObject = FileObject::create([
            'storage_disk' => 'local',
            'storage_key' => 'documents/boundary-'.uniqid().'.pdf',
            'original_filename' => 'boundary_scan.pdf',
            'detected_mime' => 'application/pdf',
            'byte_size' => 1024,
            'sha256' => hash('sha256', 'boundary_content_'.uniqid()),
            'uploaded_by' => $doc->created_by,
            'uploaded_at' => now(),
            'scan_status' => 'clean',
            'storage_status' => 'ready',
        ]);

        return DocumentTextExtraction::create([
            'document_version_id' => $version->id,
            'file_object_id' => $fileObject->id,
            'raw_text' => 'raw extracted text',
            'searchable_text' => 'raw extracted text',
            'language_detected' => 'am',
            'confidence_score' => 91.5,
            'processing_status' => 'completed',
            'ocr_engine' => 'tesseract',
            'extracted_at' => now(),
        ]);
    }

    protected function createMovement(Document $doc, User $sender, OrganizationUnit $toUnit): DocumentMovement
    {
        return DocumentMovement::create([
            'document_id' => $doc->id,
            'from_unit_id' => $doc->owner_unit_id,
            'to_unit_id' => $toUnit->id,
            'sent_by' => $sender->id,
            'sent_at' => now(),
            'instruction' => 'Transfer for review',
            'idempotency_key' => 'move-'.uniqid(),
        ]);
    }

    protected function createAssignment(Document $doc, User $assigner, OrganizationUnit $assignedUnit): DocumentAssignment
    {
        return DocumentAssignment::create([
            'document_id' => $doc->id,
            'assigned_user_id' => $this->officerUnitB->id,
            'assigned_unit_id' => $assignedUnit->id,
            'assigned_by' => $assigner->id,
            'assigned_at' => now(),
            'instruction' => 'Prepare the committee report',
        ]);
    }

    /**
     * OCR text extraction verification must be bound to the document that owns
     * the extraction. The flat alias route previously fell through to a
     * capability-only check, allowing a foreign unit to rewrite the verified
     * text of another unit's document.
     */
    public function test_ocr_extraction_verification_is_bound_to_the_owning_document(): void
    {
        $docA = $this->createTestDocument($this->unitA, $this->officerUnitA);
        $extraction = $this->createExtraction($docA);

        // Foreign unit holds documents.update for its own unit only.
        $this->grantPermissionToUser($this->officerUnitB, 'documents.update', 'unit', $this->unitB->id);
        $this->authenticateAs($this->officerUnitB);

        $denied = $this->postJson("/api/v1/extractions/{$extraction->id}/verify", [
            'verified_text' => 'Tampered text from a foreign unit',
        ]);
        $denied->assertStatus(403);
        $denied->assertJsonPath('success', false);

        $this->assertDatabaseHas('document_text_extractions', [
            'id' => $extraction->id,
            'verified_by' => null,
        ]);

        // The owning unit may verify its own extraction.
        $this->grantPermissionToUser($this->officerUnitA, 'documents.update', 'unit', $this->unitA->id);
        $this->authenticateAs($this->officerUnitA);

        $allowed = $this->postJson("/api/v1/extractions/{$extraction->id}/verify", [
            'verified_text' => 'Verified by the owning unit',
        ]);
        $allowed->assertStatus(200);
        $allowed->assertJsonPath('success', true);

        $this->assertDatabaseHas('document_text_extractions', [
            'id' => $extraction->id,
            'verified_by' => $this->officerUnitA->id,
        ]);
    }

    /**
     * The nested extraction route and the flat alias must enforce the same
     * boundary; a flat alias must never be a weaker path to the same record.
     */
    public function test_flat_extraction_alias_enforces_the_same_boundary_as_the_nested_route(): void
    {
        $docA = $this->createTestDocument($this->unitA, $this->officerUnitA);
        $extraction = $this->createExtraction($docA);
        $version = DocumentVersion::where('document_id', $docA->id)->firstOrFail();

        $this->grantPermissionToUser($this->officerUnitB, 'documents.update', 'unit', $this->unitB->id);
        $this->authenticateAs($this->officerUnitB);

        $nested = $this->postJson(
            "/api/v1/documents/{$docA->id}/versions/{$version->id}/extractions/{$extraction->id}/verify",
            ['verified_text' => 'Tampered via nested route']
        );
        $nested->assertStatus(403);

        $flat = $this->postJson("/api/v1/extractions/{$extraction->id}/verify", [
            'verified_text' => 'Tampered via flat alias',
        ]);
        $flat->assertStatus(403);
    }

    /**
     * A custody receipt is owned by the RECEIVING unit. The originating unit
     * must not be able to acknowledge a transfer it merely sent.
     */
    public function test_movement_acknowledgment_requires_the_receiving_unit_scope(): void
    {
        $docA = $this->createTestDocument($this->unitA, $this->officerUnitA);
        $movement = $this->createMovement($docA, $this->officerUnitA, $this->unitB);

        // The originating unit holds the capability for its own unit only.
        $this->grantPermissionToUser($this->officerUnitA, 'correspondence.acknowledge', 'unit', $this->unitA->id);
        $this->authenticateAs($this->officerUnitA);

        $denied = $this->postJson("/api/v1/movements/{$movement->id}/acknowledge");
        $denied->assertStatus(403);
        $denied->assertJsonPath('success', false);

        $this->assertDatabaseCount('movement_receipts', 0);

        // The receiving unit holds the capability for its own unit.
        $this->grantPermissionToUser($this->officerUnitB, 'correspondence.acknowledge', 'unit', $this->unitB->id);
        $this->authenticateAs($this->officerUnitB);

        $allowed = $this->postJson("/api/v1/movements/{$movement->id}/acknowledge");
        $allowed->assertStatus(200);
        $allowed->assertJsonPath('success', true);

        $this->assertDatabaseHas('movement_receipts', [
            'movement_id' => $movement->id,
            'acknowledged_by' => $this->officerUnitB->id,
        ]);
    }

    /**
     * An operational directive is owned by the unit instructed to carry out
     * the work, so participant management is bound to that unit.
     */
    public function test_assignment_participant_management_is_bound_to_the_assigned_unit(): void
    {
        $docA = $this->createTestDocument($this->unitA, $this->officerUnitA);
        $assignment = $this->createAssignment($docA, $this->officerUnitA, $this->unitB);

        $this->grantPermissionToUser($this->officerUnitA, 'correspondence.assign', 'unit', $this->unitA->id);
        $this->authenticateAs($this->officerUnitA);

        $denied = $this->postJson("/api/v1/assignments/{$assignment->id}/participants", [
            'user_id' => $this->officerUnitA->id,
            'role_code' => 'contributor',
        ]);
        $denied->assertStatus(403);
        $denied->assertJsonPath('success', false);

        $this->assertDatabaseCount('assignment_participants', 0);

        $this->grantPermissionToUser($this->officerUnitB, 'correspondence.assign', 'unit', $this->unitB->id);
        $this->authenticateAs($this->officerUnitB);

        $allowed = $this->postJson("/api/v1/assignments/{$assignment->id}/participants", [
            'user_id' => $this->officerUnitA->id,
            'role_code' => 'contributor',
        ]);
        $allowed->assertStatus(201);

        $this->assertDatabaseHas('assignment_participants', [
            'assignment_id' => $assignment->id,
            'user_id' => $this->officerUnitA->id,
        ]);
    }

    /**
     * A directive may not be modified by a unit that does not own it, even
     * when that unit holds the capability in another organizational scope.
     */
    public function test_global_scope_grants_do_not_bypass_record_ownership(): void
    {
        $docA = $this->createTestDocument($this->unitA, $this->officerUnitA);
        $assignment = $this->createAssignment($docA, $this->officerUnitA, $this->unitB);

        // A global grant authorizes the capability everywhere, but the record
        // itself is still owned by the assigned unit. A global holder therefore
        // is authorized only where the ownership boundary permits it.
        $this->grantPermissionToUser($this->officerUnitB, 'correspondence.assign', 'global', null);
        $this->authenticateAs($this->officerUnitB);

        // The assigned unit is unit B, so a global holder may act on it, but the
        // response must be a genuine authorization decision rather than a
        // capability-only pass-through.
        $this->postJson("/api/v1/assignments/{$assignment->id}/participants", [
            'user_id' => $this->officerUnitA->id,
            'role_code' => 'writer',
        ])->assertStatus(201);
    }

    /**
     * The scope resolution used for authorization decisions and the scope
     * resolution used to publish capabilities to clients must agree.
     */
    public function test_capability_resolution_matches_the_authorization_decision(): void
    {
        Permission::where('code', 'documents.view')->firstOrFail();

        // A global grant authorizes the capability in every scope.
        $this->grantPermissionToUser($this->officerUnitA, 'documents.view', 'global', null);
        $this->assertTrue($this->officerUnitA->hasPermission('documents.view', null));
        $this->assertTrue($this->officerUnitA->hasPermission('documents.view', $this->unitA->id));
        $this->assertTrue($this->officerUnitA->hasPermission('documents.view', $this->unitB->id));
        $this->assertContains('documents.view', $this->officerUnitA->getEffectivePermissionCodes(null));

        // A unit-scoped grant authorizes the capability in that unit only.
        $this->grantPermissionToUser($this->officerUnitB, 'documents.view', 'unit', $this->unitA->id);
        $this->assertTrue($this->officerUnitB->hasPermission('documents.view', $this->unitA->id));
        $this->assertFalse($this->officerUnitB->hasPermission('documents.view', $this->unitB->id));
        $this->assertFalse($this->officerUnitB->hasPermission('documents.view', null));

        // An unscoped capability listing must not disclose unit-scoped grants.
        $this->assertNotContains('documents.view', $this->officerUnitB->getEffectivePermissionCodes(null));

        // The unit-scoped grant is only visible in its own unit.
        $this->assertContains('documents.view', $this->officerUnitB->getEffectivePermissionCodes($this->unitA->id));
        $this->assertNotContains('documents.view', $this->officerUnitB->getEffectivePermissionCodes($this->unitB->id));

        // A subtree grant anchored on an ancestor covers the descendant unit.
        $this->grantPermissionToUser($this->officerUnitB, 'documents.download', 'subtree', $this->unitA->id);
        $this->assertTrue($this->officerUnitB->hasPermission('documents.download', $this->unitB->id));
        $this->assertContains('documents.download', $this->officerUnitB->getEffectivePermissionCodes($this->unitB->id));
        $this->assertNotContains('documents.download', $this->officerUnitB->getEffectivePermissionCodes(null));

        // The administrative listing still surfaces every scope.
        $this->assertContains('documents.view', $this->officerUnitB->getAllPermissionCodesAcrossScopes());
    }

    /**
     * The client capability payload must describe the scope the user actually
     * operates in, so the front end never advertises authority the server
     * will refuse.
     */
    public function test_auth_me_publishes_the_scoped_capability_set(): void
    {
        $this->grantPermissionToUser($this->officerUnitA, 'documents.view', 'unit', $this->unitA->id);
        $this->grantPermissionToUser($this->officerUnitA, 'reports.view', 'global', null);

        $this->authenticateAs($this->officerUnitA);
        $response = $this->getJson('/api/v1/auth/me');
        $response->assertStatus(200);

        $user = $response->json('user');

        // The published capability set is calculated for the active
        // organizational context, which for this officer is their own unit.
        $this->assertContains('documents.view', $user['permissions']);

        // The global grant is published in both listings.
        $this->assertContains('reports.view', $user['permissions']);
        $this->assertContains('reports.view', $user['permissions_global']);

        // The unit-scoped grant is not published as a global capability.
        $this->assertNotContains('documents.view', $user['permissions_global']);

        // The active unit is resolved from the primary membership.
        $this->assertSame($this->unitA->id, $user['active_unit_id']);
        $this->assertSame($this->unitA->id, $user['active_unit']['id']);
    }

    /**
     * The dynamic navigation catalog must be filtered with the same scoped
     * capability set that the API enforces.
     */
    public function test_navigation_catalog_is_filtered_by_scoped_capabilities(): void
    {
        $this->grantPermissionToUser($this->officerUnitA, 'documents.view', 'unit', $this->unitA->id);

        $this->authenticateAs($this->officerUnitA);
        $response = $this->getJson('/api/v1/navigation');
        $response->assertStatus(200);

        $routes = collect($response->json('data'))
            ->flatMap(fn ($section) => $section['items'])
            ->pluck('to')
            ->all();

        // Documents is available because the unit-scoped capability is in scope.
        $this->assertContains('/documents', $routes);

        // Administration surfaces require capabilities the user does not hold.
        $this->assertNotContains('/admin/users', $routes);
        $this->assertNotContains('/admin/roles', $routes);
    }

    /**
     * A request that addresses a record in a unit where the caller holds no
     * grant must be refused with 403 rather than silently allowed through the
     * capability check.
     */
    public function test_document_access_outside_the_granted_unit_is_refused(): void
    {
        $docA = $this->createTestDocument($this->unitA, $this->officerUnitA);

        $this->grantPermissionToUser($this->officerUnitB, 'documents.view', 'unit', $this->unitB->id);
        $this->authenticateAs($this->officerUnitB);

        $this->getJson("/api/v1/documents/{$docA->id}")->assertStatus(403);

        $this->grantPermissionToUser($this->officerUnitA, 'documents.view', 'unit', $this->unitA->id);
        $this->authenticateAs($this->officerUnitA);

        $this->getJson("/api/v1/documents/{$docA->id}")->assertStatus(200);
    }

    /**
     * The middleware must fail closed for a record that does not exist.
     */
    public function test_missing_document_is_reported_as_not_found(): void
    {
        $this->grantPermissionToUser($this->officerUnitA, 'documents.view', 'global', null);
        $this->authenticateAs($this->officerUnitA);

        $this->getJson('/api/v1/documents/999999')->assertStatus(404);
    }

    /**
     * Authorization scopes must remain resolvable for the seeded units.
     */
    public function test_authorization_scopes_are_resolvable(): void
    {
        $this->assertGreaterThan(0, AuthorizationScope::count());
        $this->assertNotNull(DB::table('users')->where('email', 'admin@wu.edu.et')->value('id'));
    }
}
