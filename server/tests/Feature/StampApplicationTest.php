<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\ConfidentialityLevel;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\FileObject;
use App\Models\OrganizationUnit;
use App\Models\StampApplication;
use App\Models\StampAuthorization;
use App\Models\StampDefinition;
use App\Models\StampVersion;
use App\Models\User;
use App\Models\VersionFile;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StampApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $authorizedUser;
    protected User $unauthorizedUser;
    protected OrganizationUnit $unit;
    protected OrganizationUnit $otherUnit;
    protected StampDefinition $stamp;
    protected StampVersion $stampVersion;
    protected DocumentVersion $docVersion;
    protected FileObject $sourceFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        // Create users
        $this->admin = User::where('email', 'admin@wu.edu.et')->first();
        
        $this->authorizedUser = User::create([
            'name' => 'Dr. Authorized Officer',
            'email' => 'auth.officer@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        
        $this->unauthorizedUser = User::create([
            'name' => 'Dr. Unauthorized User',
            'email' => 'unauth.user@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        // Create organization units
        $this->unit = OrganizationUnit::first();
        $this->otherUnit = OrganizationUnit::create([
            'code' => 'OTHER-DEPT',
            'name' => 'Other Department',
            'unit_type' => 'department',
            'parent_id' => $this->unit->id,
            'active' => true,
        ]);

        // Create stamp image file
        Storage::fake('local');
        Storage::disk('local')->put('stamps/official-stamp.png', 'STAMP_IMAGE_DATA');
        
        $stampImageFile = FileObject::create([
            'storage_disk' => 'local',
            'storage_key' => 'stamps/official-stamp.png',
            'original_filename' => 'official-stamp.png',
            'detected_mime' => 'image/png',
            'byte_size' => 1024,
            'sha256' => hash('sha256', 'STAMP_IMAGE_DATA'),
            'uploaded_by' => $this->admin->id,
            'uploaded_at' => now()->format('Y-m-d H:i:s.u'),
            'scan_status' => 'clean',
            'storage_status' => 'ready',
        ]);

        // Create stamp definition and version
        $this->stamp = StampDefinition::create([
            'unit_id' => $this->unit->id,
            'name' => 'Official Department Stamp',
            'active' => true,
        ]);

        $this->stampVersion = StampVersion::create([
            'stamp_id' => $this->stamp->id,
            'version_number' => 1,
            'image_file_id' => $stampImageFile->id,
            'effective_from' => now()->subDays(30)->format('Y-m-d H:i:s.u'),
            'retired_at' => null,
        ]);

        // Create stamp authorization for authorized user
        StampAuthorization::create([
            'stamp_id' => $this->stamp->id,
            'user_id' => $this->authorizedUser->id,
            'granted_by' => $this->admin->id,
            'valid_from' => now()->subDays(30)->format('Y-m-d H:i:s.u'),
            'valid_until' => null,
        ]);

        // Create document and version
        $cat = DocumentCategory::first();
        $conf = ConfidentialityLevel::first();

        $doc = Document::create([
            'reference_number' => 'WU/TEST/2026/001',
            'title' => 'Test Document for Stamping',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $this->unit->id,
            'created_by' => $this->authorizedUser->id,
            'document_date' => now()->format('Y-m-d'),
            'registered_at' => now()->format('Y-m-d H:i:s.u'),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ]);

        $this->docVersion = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $this->authorizedUser->id,
            'created_at' => now()->format('Y-m-d H:i:s.u'),
            'change_note' => 'Initial version',
        ]);

        // Create source file for document version
        Storage::disk('local')->put('documents/test-doc.pdf', 'TEST_DOCUMENT_CONTENT');
        
        $this->sourceFile = FileObject::create([
            'storage_disk' => 'local',
            'storage_key' => 'documents/test-doc.pdf',
            'original_filename' => 'test-doc.pdf',
            'detected_mime' => 'application/pdf',
            'byte_size' => 2048,
            'sha256' => hash('sha256', 'TEST_DOCUMENT_CONTENT'),
            'uploaded_by' => $this->authorizedUser->id,
            'uploaded_at' => now()->format('Y-m-d H:i:s.u'),
            'scan_status' => 'clean',
            'storage_status' => 'ready',
        ]);

        VersionFile::create([
            'version_id' => $this->docVersion->id,
            'file_object_id' => $this->sourceFile->id,
            'purpose' => 'primary',
            'display_order' => 1,
        ]);
    }

    /**
     * Test 1: Authorized user can successfully apply stamp
     */
    public function test_authorized_user_can_apply_stamp_successfully(): void
    {
        $this->authenticateAs($this->authorizedUser);

        $response = $this->postJson('/api/v1/stamps/apply', [
            'document_version_id' => $this->docVersion->id,
            'stamp_id' => $this->stamp->id,
            'reason' => 'Official departmental approval for release',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Official institutional stamp applied and verified successfully.');

        // Verify stamp application record created
        $this->assertDatabaseHas('stamp_applications', [
            'document_version_id' => $this->docVersion->id,
            'stamp_version_id' => $this->stampVersion->id,
            'applied_by' => $this->authorizedUser->id,
            'reason' => 'Official departmental approval for release',
        ]);

        // Verify audit event created
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $this->authorizedUser->id,
            'action' => 'stamp.applied',
            'target_type' => 'stamp_application',
        ]);

        // Verify result file was created
        $application = StampApplication::where('document_version_id', $this->docVersion->id)->first();
        $this->assertNotNull($application);
        $this->assertNotNull($application->result_file_id);
        $this->assertNotEquals($application->source_file_id, $application->result_file_id);
    }

    /**
     * Test 2: Unauthorized user is rejected when applying stamp
     */
    public function test_unauthorized_user_cannot_apply_stamp(): void
    {
        $this->authenticateAs($this->unauthorizedUser);

        $response = $this->postJson('/api/v1/stamps/apply', [
            'document_version_id' => $this->docVersion->id,
            'stamp_id' => $this->stamp->id,
            'reason' => 'Attempting unauthorized stamp application',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden: You are not authorized to apply this institutional stamp.');

        // Verify no stamp application was created
        $this->assertDatabaseMissing('stamp_applications', [
            'document_version_id' => $this->docVersion->id,
            'applied_by' => $this->unauthorizedUser->id,
        ]);
    }

    /**
     * Test 3: Cannot apply inactive stamp
     */
    public function test_cannot_apply_inactive_stamp(): void
    {
        $this->authenticateAs($this->authorizedUser);

        // Deactivate stamp
        $this->stamp->update(['active' => false]);

        $response = $this->postJson('/api/v1/stamps/apply', [
            'document_version_id' => $this->docVersion->id,
            'stamp_id' => $this->stamp->id,
            'reason' => 'Attempting to use inactive stamp',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'The selected stamp definition is inactive or not approved.');

        $this->assertDatabaseMissing('stamp_applications', [
            'document_version_id' => $this->docVersion->id,
        ]);
    }

    /**
     * Test 4: Cannot apply stamp with no active version
     */
    public function test_cannot_apply_stamp_with_no_active_version(): void
    {
        $this->authenticateAs($this->authorizedUser);

        // Retire stamp version
        $this->stampVersion->update(['retired_at' => now()->subDays(1)->format('Y-m-d H:i:s.u')]);

        $response = $this->postJson('/api/v1/stamps/apply', [
            'document_version_id' => $this->docVersion->id,
            'stamp_id' => $this->stamp->id,
            'reason' => 'Attempting to use retired stamp version',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No active, effective stamp version is available for this stamp definition.');

        $this->assertDatabaseMissing('stamp_applications', [
            'document_version_id' => $this->docVersion->id,
        ]);
    }

    /**
     * Test 5: Cannot apply stamp from different unit without cross-unit permission
     */
    public function test_cannot_apply_cross_unit_stamp_without_permission(): void
    {
        // Create stamp for other unit
        $otherStamp = StampDefinition::create([
            'unit_id' => $this->otherUnit->id,
            'name' => 'Other Department Official Stamp',
            'active' => true,
        ]);

        $stampImageFile = FileObject::first();
        StampVersion::create([
            'stamp_id' => $otherStamp->id,
            'version_number' => 1,
            'image_file_id' => $stampImageFile->id,
            'effective_from' => now()->subDays(30)->format('Y-m-d H:i:s.u'),
            'retired_at' => null,
        ]);

        $this->authenticateAs($this->authorizedUser);

        $response = $this->postJson('/api/v1/stamps/apply', [
            'document_version_id' => $this->docVersion->id,
            'stamp_id' => $otherStamp->id,
            'reason' => 'Attempting cross-unit stamp application',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden: You are not authorized to apply this institutional stamp.');

        $this->assertDatabaseMissing('stamp_applications', [
            'stamp_version_id' => $otherStamp->id,
        ]);
    }

    /**
     * Test 6: Expired authorization is rejected
     */
    public function test_expired_authorization_is_rejected(): void
    {
        // Create user with expired authorization
        $expiredUser = User::create([
            'name' => 'Dr. Expired Authorization',
            'email' => 'expired@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        StampAuthorization::create([
            'stamp_id' => $this->stamp->id,
            'user_id' => $expiredUser->id,
            'granted_by' => $this->admin->id,
            'valid_from' => now()->subDays(60)->format('Y-m-d H:i:s.u'),
            'valid_until' => now()->subDays(1)->format('Y-m-d H:i:s.u'), // Expired yesterday
        ]);

        $this->authenticateAs($expiredUser);

        $response = $this->postJson('/api/v1/stamps/apply', [
            'document_version_id' => $this->docVersion->id,
            'stamp_id' => $this->stamp->id,
            'reason' => 'Attempting to use expired authorization',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden: You are not authorized to apply this institutional stamp.');

        $this->assertDatabaseMissing('stamp_applications', [
            'applied_by' => $expiredUser->id,
        ]);
    }

    /**
     * Test 7: Validation errors for missing required fields
     */
    public function test_validation_errors_for_missing_fields(): void
    {
        $this->authenticateAs($this->authorizedUser);

        // Missing document_version_id
        $response1 = $this->postJson('/api/v1/stamps/apply', [
            'stamp_id' => $this->stamp->id,
            'reason' => 'Test reason',
        ]);
        $response1->assertStatus(422)
            ->assertJsonValidationErrors(['document_version_id']);

        // Missing stamp_id
        $response2 = $this->postJson('/api/v1/stamps/apply', [
            'document_version_id' => $this->docVersion->id,
            'reason' => 'Test reason',
        ]);
        $response2->assertStatus(422)
            ->assertJsonValidationErrors(['stamp_id']);

        // Missing reason
        $response3 = $this->postJson('/api/v1/stamps/apply', [
            'document_version_id' => $this->docVersion->id,
            'stamp_id' => $this->stamp->id,
        ]);
        $response3->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    /**
     * Test 8: Invalid document version ID is rejected
     */
    public function test_invalid_document_version_id_is_rejected(): void
    {
        $this->authenticateAs($this->authorizedUser);

        $response = $this->postJson('/api/v1/stamps/apply', [
            'document_version_id' => 99999, // Non-existent ID
            'stamp_id' => $this->stamp->id,
            'reason' => 'Testing invalid document version',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['document_version_id']);
    }

    /**
     * Test 9: Multiple stamp applications on same document version
     */
    public function test_multiple_stamp_applications_on_same_document(): void
    {
        $this->authenticateAs($this->authorizedUser);

        // First application
        $response1 = $this->postJson('/api/v1/stamps/apply', [
            'document_version_id' => $this->docVersion->id,
            'stamp_id' => $this->stamp->id,
            'reason' => 'First stamp application',
        ]);
        $response1->assertStatus(200);

        // Second application (should succeed - multiple stamps allowed)
        $response2 = $this->postJson('/api/v1/stamps/apply', [
            'document_version_id' => $this->docVersion->id,
            'stamp_id' => $this->stamp->id,
            'reason' => 'Second stamp application',
        ]);
        $response2->assertStatus(200);

        // Verify both applications exist
        $applications = StampApplication::where('document_version_id', $this->docVersion->id)->get();
        $this->assertCount(2, $applications);
    }

    /**
     * Test 10: Admin user can apply any stamp with system.configure permission
     */
    public function test_admin_can_apply_any_stamp_with_system_configure(): void
    {
        // Admin should have system.configure permission
        $this->authenticateAs($this->admin);

        $response = $this->postJson('/api/v1/stamps/apply', [
            'document_version_id' => $this->docVersion->id,
            'stamp_id' => $this->stamp->id,
            'reason' => 'Admin applying stamp with system.configure permission',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('stamp_applications', [
            'document_version_id' => $this->docVersion->id,
            'applied_by' => $this->admin->id,
        ]);
    }

    /**
     * Test 11: Audit trail contains complete information
     */
    public function test_audit_trail_contains_complete_information(): void
    {
        $this->authenticateAs($this->authorizedUser);

        $response = $this->postJson('/api/v1/stamps/apply', [
            'document_version_id' => $this->docVersion->id,
            'stamp_id' => $this->stamp->id,
            'reason' => 'Testing complete audit trail',
        ]);

        $response->assertStatus(200);

        $auditEvent = AuditEvent::where('action', 'stamp.applied')
            ->where('actor_id', $this->authorizedUser->id)
            ->latest()
            ->first();

        $this->assertNotNull($auditEvent);
        $this->assertEquals('stamp_application', $auditEvent->target_type);
        
        $details = $auditEvent->details_json;
        $this->assertArrayHasKey('stamp_id', $details);
        $this->assertArrayHasKey('stamp_version_id', $details);
        $this->assertArrayHasKey('version_number', $details);
        $this->assertArrayHasKey('document_version_id', $details);
        $this->assertArrayHasKey('reason', $details);
        $this->assertEquals($this->stamp->id, $details['stamp_id']);
    }

    /**
     * Test 12: Future-dated authorization not yet valid
     */
    public function test_future_dated_authorization_not_yet_valid(): void
    {
        $futureUser = User::create([
            'name' => 'Dr. Future Authorization',
            'email' => 'future@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        StampAuthorization::create([
            'stamp_id' => $this->stamp->id,
            'user_id' => $futureUser->id,
            'granted_by' => $this->admin->id,
            'valid_from' => now()->addDays(5)->format('Y-m-d H:i:s.u'), // Valid in future
            'valid_until' => null,
        ]);

        $this->authenticateAs($futureUser);

        $response = $this->postJson('/api/v1/stamps/apply', [
            'document_version_id' => $this->docVersion->id,
            'stamp_id' => $this->stamp->id,
            'reason' => 'Attempting to use future authorization',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('stamp_applications', [
            'applied_by' => $futureUser->id,
        ]);
    }

    /**
     * Helper method to authenticate as a user
     */
    protected function authenticateAs(User $user): string
    {
        return parent::authenticateAs($user);
    }

    /**
     * Helper to assign role to user
     */
    protected function assignRoleToUser(User $user, string $roleCode, string $scopeKind = 'global', ?int $unitId = null): void
    {
        $role = \App\Models\Role::where('code', $roleCode)->first();
        if ($role) {
            \App\Models\UserRole::create([
                'user_id' => $user->id,
                'role_id' => $role->id,
                'assigned_by' => $this->admin->id,
                'assigned_at' => now()->format('Y-m-d H:i:s.u'),
            ]);
        }
    }
}
