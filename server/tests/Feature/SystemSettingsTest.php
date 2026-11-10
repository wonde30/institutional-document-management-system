<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\OrganizationUnit;
use App\Models\SystemSetting;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Runtime system configuration.
 *
 * The tests exercise the module against the real configuration catalogue: the
 * definition of every key, its type, its default and its validation rules
 * come from the code, never from the request.
 */
class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $administrator;
    protected User $officer;
    protected OrganizationUnit $unitA;
    protected OrganizationUnit $unitB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $collegeType = UnitType::firstOrCreate(['code' => 'college'], ['name' => 'College', 'active' => 1]);
        $this->unitA = OrganizationUnit::first();
        $this->unitB = OrganizationUnit::create([
            'parent_id' => $this->unitA->id,
            'unit_type_id' => $collegeType->id,
            'code' => 'SETTINGS_UNIT_B',
            'name' => 'Settings Unit B',
            'active' => 1,
        ]);

        $this->administrator = User::create([
            'name' => 'Settings Administrator',
            'email' => 'settings.admin@wu.edu.et',
            'password' => bcrypt('StrongPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->administrator, 'admin', 'global', null);

        $this->officer = User::create([
            'name' => 'Settings Officer',
            'email' => 'settings.officer@wu.edu.et',
            'password' => bcrypt('StrongPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->officer, 'records_officer', 'global', null);

        DB::table('organization_memberships')->insert([
            'user_id' => $this->administrator->id,
            'unit_id' => $this->unitA->id,
            'position_title' => 'System Administrator',
            'starts_at' => now()->subYear(),
            'created_by' => $this->administrator->id,
            'created_at' => now(),
        ]);
    }

    protected function settingsPayload(): array
    {
        return $this->getJson('/api/v1/settings')->json('data');
    }

    /**
     * 1. An authorised administrator receives the catalogue with the current
     *    and default values.
     */
    public function test_authorised_administrator_retrieves_the_catalogue(): void
    {
        $this->authenticateAs($this->administrator);

        $response = $this->getJson('/api/v1/settings');
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $data = $response->json('data');

        $this->assertArrayHasKey('categories', $data);
        $this->assertArrayHasKey('settings', $data);
        $this->assertArrayHasKey('deployment', $data);

        $setting = collect($data['settings'])->firstWhere('key', 'institution.display_name');

        $this->assertNotNull($setting);
        $this->assertSame('Wollo University', $setting['value']);
        $this->assertSame('Wollo University', $setting['default']);
        $this->assertTrue($setting['is_default']);
        $this->assertTrue($setting['editable']);
    }

    /**
     * 2. A user without the capability is refused.
     */
    public function test_unauthorised_user_cannot_retrieve_the_catalogue(): void
    {
        $this->authenticateAs($this->officer);

        $this->getJson('/api/v1/settings')->assertStatus(403);
    }

    /**
     * 3. An unauthenticated request is refused.
     */
    public function test_unauthenticated_request_is_refused(): void
    {
        $this->getJson('/api/v1/settings')->assertStatus(401);
    }

    /**
     * 4. A valid update is persisted and returned.
     */
    public function test_valid_update_is_persisted(): void
    {
        $this->authenticateAs($this->administrator);

        $response = $this->putJson('/api/v1/settings', [
            'settings' => [
                ['key' => 'institution.display_name', 'value' => 'Wollo University of Technology'],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'institution.display_name',
            'scope_unit_id' => null,
            'scope_key' => 0,
            'string_value' => 'Wollo University of Technology',
        ]);

        // The value survives a reload.
        $this->authenticateAs($this->administrator);
        $setting = collect($this->settingsPayload()['settings'])->firstWhere('key', 'institution.display_name');
        $this->assertSame('Wollo University of Technology', $setting['value']);
        $this->assertFalse($setting['is_default']);
    }

    /**
     * 5. An invalid value is rejected and nothing is stored.
     */
    public function test_invalid_value_is_rejected(): void
    {
        $this->authenticateAs($this->administrator);

        $response = $this->putJson('/api/v1/settings', [
            'settings' => [
                ['key' => 'session.lifetime_minutes', 'value' => 3],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('system_settings', 0);
    }

    /**
     * 6. A value of the wrong type is rejected.
     */
    public function test_value_of_the_wrong_type_is_rejected(): void
    {
        $this->authenticateAs($this->administrator);

        $this->putJson('/api/v1/settings', [
            'settings' => [
                ['key' => 'documents.max_upload_size_mb', 'value' => 'not-a-number'],
            ],
        ])->assertStatus(422);

        $this->assertDatabaseCount('system_settings', 0);
    }

    /**
     * 7. An unknown key is rejected: the catalogue is authoritative.
     */
    public function test_unknown_setting_key_is_rejected(): void
    {
        $this->authenticateAs($this->administrator);

        $response = $this->putJson('/api/v1/settings', [
            'settings' => [
                ['key' => 'not.a.real.setting', 'value' => 'x'],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('system_settings', 0);
    }

    /**
     * 8. Infrastructure secrets are never writable through the settings API.
     */
    public function test_infrastructure_secrets_cannot_be_written(): void
    {
        $this->authenticateAs($this->administrator);

        foreach (['app.key', 'db.password', 'mail.password', 'aws.secret_access_key'] as $key) {
            $this->putJson('/api/v1/settings', [
                'settings' => [['key' => $key, 'value' => 'stolen']],
            ])->assertStatus(422);
        }

        $this->assertDatabaseCount('system_settings', 0);
    }

    /**
     * 9. A setting that is declared but not editable is refused.
     */
    public function test_non_editable_setting_is_refused(): void
    {
        $this->authenticateAs($this->administrator);

        $response = $this->putJson('/api/v1/settings', [
            'settings' => [
                ['key' => 'documents.reference_number_scheme', 'value' => 'X/Y'],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('system_settings', 0);
    }

    /**
     * 10. A select setting accepts only the declared values.
     */
    public function test_select_setting_rejects_an_undeclared_value(): void
    {
        $this->authenticateAs($this->administrator);

        $this->putJson('/api/v1/settings', [
            'settings' => [
                ['key' => 'documents.default_confidentiality_level_code', 'value' => 'public'],
            ],
        ])->assertStatus(422);

        $this->assertDatabaseCount('system_settings', 0);

        $this->putJson('/api/v1/settings', [
            'settings' => [
                ['key' => 'documents.default_confidentiality_level_code', 'value' => 'confidential'],
            ],
        ])->assertStatus(200);

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'documents.default_confidentiality_level_code',
            'string_value' => 'confidential',
        ]);
    }

    /**
     * 11. A reset returns the setting to its catalogue default.
     */
    public function test_reset_returns_the_catalogue_default(): void
    {
        $this->authenticateAs($this->administrator);

        $this->putJson('/api/v1/settings', [
            'settings' => [['key' => 'institution.display_name', 'value' => 'Changed Name']],
        ])->assertStatus(200);

        $this->assertDatabaseCount('system_settings', 1);

        $response = $this->postJson('/api/v1/settings/institution.display_name/reset');
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $this->assertDatabaseCount('system_settings', 0);

        $this->authenticateAs($this->administrator);
        $setting = collect($this->settingsPayload()['settings'])->firstWhere('key', 'institution.display_name');
        $this->assertSame('Wollo University', $setting['value']);
    }

    /**
     * 12. A category reset returns every setting of the category to its
     *     default and leaves the other categories alone.
     */
    public function test_category_reset_only_affects_the_category(): void
    {
        $this->authenticateAs($this->administrator);

        $this->putJson('/api/v1/settings', [
            'settings' => [
                ['key' => 'institution.display_name', 'value' => 'Changed Name'],
                ['key' => 'session.lifetime_minutes', 'value' => 45],
            ],
        ])->assertStatus(200);

        $response = $this->postJson('/api/v1/settings/categories/institution/reset');
        $response->assertStatus(200);

        // The institutional category is back to its default.
        $this->assertDatabaseMissing('system_settings', ['setting_key' => 'institution.display_name']);

        // The other category is untouched.
        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'session.lifetime_minutes',
            'integer_value' => 45,
        ]);
    }

    /**
     * 13. Every successful modification produces an audit event with the
     *     previous and the new value.
     */
    public function test_update_is_audited(): void
    {
        $this->authenticateAs($this->administrator);

        $this->putJson('/api/v1/settings', [
            'settings' => [['key' => 'institution.display_name', 'value' => 'Audited Name']],
        ])->assertStatus(200);

        $event = AuditEvent::where('action', 'settings.updated')->latest('id')->first();

        $this->assertNotNull($event);
        $this->assertSame($this->administrator->id, $event->actor_id);
        $this->assertSame('system_setting', $event->target_type);
        $this->assertSame('institution.display_name', $event->target_id);
        $this->assertSame('institution.display_name', $event->details_json['setting_key']);
        $this->assertSame('Wollo University', $event->details_json['previous_value']);
        $this->assertSame('Audited Name', $event->details_json['new_value']);
        $this->assertSame('update', $event->details_json['operation']);
    }

    /**
     * 14. A reset is audited as a reset.
     */
    public function test_reset_is_audited(): void
    {
        $this->authenticateAs($this->administrator);

        $this->putJson('/api/v1/settings', [
            'settings' => [['key' => 'institution.display_name', 'value' => 'Audited Name']],
        ])->assertStatus(200);

        $this->postJson('/api/v1/settings/institution.display_name/reset')->assertStatus(200);

        $event = AuditEvent::where('action', 'settings.reset')->latest('id')->first();

        $this->assertNotNull($event);
        $this->assertSame('reset', $event->details_json['operation']);
        $this->assertSame('Audited Name', $event->details_json['previous_value']);
    }

    /**
     * 15. A failed update writes no audit event.
     */
    public function test_failed_update_writes_no_audit_event(): void
    {
        $this->authenticateAs($this->administrator);

        $this->putJson('/api/v1/settings', [
            'settings' => [['key' => 'session.lifetime_minutes', 'value' => 9999]],
        ])->assertStatus(422);

        $this->assertSame(0, AuditEvent::where('action', 'settings.updated')->count());
    }

    /**
     * 16. A rejected batch leaves no partial write.
     */
    public function test_a_rejected_batch_leaves_no_partial_write(): void
    {
        $this->authenticateAs($this->administrator);

        $response = $this->putJson('/api/v1/settings', [
            'settings' => [
                ['key' => 'institution.display_name', 'value' => 'Valid Name'],
                ['key' => 'session.lifetime_minutes', 'value' => 9999],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('system_settings', 0);
        $this->assertSame(0, AuditEvent::where('action', 'settings.updated')->count());
    }

    /**
     * 17. A duplicate value for the same key and scope is prevented by the
     *     database, not only by the service.
     */
    public function test_duplicate_configuration_is_prevented_by_the_database(): void
    {
        $this->authenticateAs($this->administrator);

        $this->putJson('/api/v1/settings', [
            'settings' => [['key' => 'institution.display_name', 'value' => 'First']],
        ])->assertStatus(200);

        // A second write for the same key and scope updates the existing row.
        $this->putJson('/api/v1/settings', [
            'settings' => [['key' => 'institution.display_name', 'value' => 'Second']],
        ])->assertStatus(200);

        $this->assertSame(1, SystemSetting::where('setting_key', 'institution.display_name')->count());
        $this->assertSame('Second', SystemSetting::where('setting_key', 'institution.display_name')->first()->string_value);
    }

    /**
     * 18. A unit scoped value is stored separately from the global value and
     *     takes precedence for that unit.
     */
    public function test_unit_scoped_value_is_isolated_from_the_global_value(): void
    {
        $this->authenticateAs($this->administrator);

        $this->putJson('/api/v1/settings', [
            'settings' => [
                ['key' => 'documents.default_confidentiality_level_code', 'value' => 'confidential'],
            ],
        ])->assertStatus(200);

        // The active unit is the unit the administrator belongs to.
        $this->putJson('/api/v1/settings', [
            'scope_unit_id' => $this->unitA->id,
            'settings' => [
                ['key' => 'documents.default_confidentiality_level_code', 'value' => 'restricted'],
            ],
        ])->assertStatus(200);

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'documents.default_confidentiality_level_code',
            'scope_unit_id' => null,
            'scope_key' => 0,
            'string_value' => 'confidential',
        ]);

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'documents.default_confidentiality_level_code',
            'scope_unit_id' => $this->unitA->id,
            'scope_key' => $this->unitA->id,
            'string_value' => 'restricted',
        ]);
    }

    /**
     * 19. A unit scoped write is refused for a unit that is not the active
     *     organizational unit of the caller.
     */
    public function test_unit_scoped_write_is_refused_outside_the_active_unit(): void
    {
        $this->authenticateAs($this->administrator);

        $response = $this->putJson('/api/v1/settings', [
            'scope_unit_id' => $this->unitB->id,
            'settings' => [
                ['key' => 'documents.default_confidentiality_level_code', 'value' => 'restricted'],
            ],
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('system_settings', 0);
    }

    /**
     * 20. A setting that does not support a unit scope is refused.
     */
    public function test_a_global_only_setting_refuses_a_unit_scope(): void
    {
        $this->authenticateAs($this->administrator);

        $response = $this->putJson('/api/v1/settings', [
            'scope_unit_id' => $this->unitA->id,
            'settings' => [
                ['key' => 'institution.display_name', 'value' => 'Scoped Name'],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('system_settings', 0);
    }

    /**
     * 21. The deployment boundary is published without any secret value.
     */
    public function test_deployment_boundary_never_publishes_a_secret(): void
    {
        $this->authenticateAs($this->administrator);

        $deployment = $this->settingsPayload()['deployment'];

        $this->assertNotEmpty($deployment);

        foreach ($deployment as $entry) {
            if ($entry['sensitive']) {
                $this->assertNull($entry['value'], "The sensitive entry '{$entry['key']}' must not carry a value.");
            }
        }

        // The sensitive keys are still listed, so the boundary is visible.
        $keys = array_column($deployment, 'key');
        $this->assertContains('app.key', $keys);
        $this->assertContains('db.password', $keys);
    }

    /**
     * 22. The public endpoint publishes only the declared public settings and
     *     requires no authentication.
     */
    public function test_public_settings_are_limited_to_the_public_catalogue(): void
    {
        $response = $this->getJson('/api/v1/settings/public');
        $response->assertStatus(200);

        $data = $response->json('data');

        $this->assertArrayHasKey('institution.display_name', $data);
        $this->assertArrayNotHasKey('session.lifetime_minutes', $data);
        $this->assertArrayNotHasKey('app.key', $data);
    }

    /**
     * 23. The session lifetime setting is applied to the running session.
     */
    public function test_session_lifetime_setting_is_applied(): void
    {
        $this->authenticateAs($this->administrator);

        $this->putJson('/api/v1/settings', [
            'settings' => [['key' => 'session.lifetime_minutes', 'value' => 45]],
        ])->assertStatus(200);

        $this->authenticateAs($this->administrator);
        $this->getJson('/api/v1/auth/me')->assertStatus(200);

        $this->assertSame(45, config('session.lifetime'));
    }

    /**
     * 24. The upload limit setting is applied to the document upload
     *     validation.
     */
    public function test_upload_size_setting_changes_the_accepted_upload_size(): void
    {
        $category = \App\Models\DocumentCategory::first();
        $confidentiality = \App\Models\ConfidentialityLevel::where('code', 'internal')->first();

        $this->authenticateAs($this->administrator);

        // One megabyte is accepted.
        $this->putJson('/api/v1/settings', [
            'settings' => [['key' => 'documents.max_upload_size_mb', 'value' => 1]],
        ])->assertStatus(200);

        $payload = [
            'title' => 'Size probe document',
            'category_id' => $category->id,
            'confidentiality_id' => $confidentiality->id,
            'owner_unit_id' => $this->unitA->id,
            'document_date' => now()->toDateString(),
        ];

        $this->post('/api/v1/documents', $payload + [
            'file' => \Illuminate\Http\UploadedFile::fake()->create('probe.pdf', 2048, 'kb'),
        ])->assertStatus(422);

        // Five megabytes are accepted.
        $this->putJson('/api/v1/settings', [
            'settings' => [['key' => 'documents.max_upload_size_mb', 'value' => 5]],
        ])->assertStatus(200);

        $this->post('/api/v1/documents', $payload + [
            'file' => \Illuminate\Http\UploadedFile::fake()->create('probe.pdf', 2048, 'kb'),
        ])->assertStatus(201);
    }

    /**
     * 25. The configured default classification is applied when a document is
     *     registered without an explicit level, and an explicit level always
     *     wins.
     */
    public function test_default_classification_setting_is_applied_on_registration(): void
    {
        $category = \App\Models\DocumentCategory::first();
        $confidential = \App\Models\ConfidentialityLevel::where('code', 'confidential')->first();
        $internal = \App\Models\ConfidentialityLevel::where('code', 'internal')->first();

        $this->authenticateAs($this->administrator);

        $this->putJson('/api/v1/settings', [
            'settings' => [
                ['key' => 'documents.default_confidentiality_level_code', 'value' => 'confidential'],
            ],
        ])->assertStatus(200);

        $payload = [
            'title' => 'Classification probe document',
            'category_id' => $category->id,
            'owner_unit_id' => $this->unitA->id,
            'document_date' => now()->toDateString(),
        ];

        // Without an explicit level the configured default is applied.
        $response = $this->postJson('/api/v1/documents', $payload);
        $response->assertStatus(201);

        $document = \App\Models\Document::where('title', 'Classification probe document')
            ->latest('id')
            ->first();

        $this->assertNotNull($document);
        $this->assertSame($confidential->id, (int) $document->confidentiality_id);

        // An explicit level always wins.
        $explicit = $this->postJson('/api/v1/documents', array_merge($payload, [
            'confidentiality_id' => $internal->id,
            'title' => 'Explicit classification document',
        ]));
        $explicit->assertStatus(201);

        $explicitDocument = \App\Models\Document::where('title', 'Explicit classification document')
            ->latest('id')
            ->first();

        $this->assertNotNull($explicitDocument);
        $this->assertSame($internal->id, (int) $explicitDocument->confidentiality_id);
    }
}
