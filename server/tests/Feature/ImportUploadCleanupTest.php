<?php

namespace Tests\Feature;

use App\Models\DataImportOperation;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The lifecycle of the temporary dataset files of import operations.
 *
 * A file that was inspected but never executed is temporary: once the
 * retention window has passed it is no longer needed and it occupies private
 * storage. A file that produced an audit record is never removed, and a file
 * belonging to an operation that is being executed is never touched.
 */
class ImportUploadCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);
        Storage::fake('local');

        $this->officer = User::create([
            'name' => 'Cleanup Officer',
            'email' => 'cleanup.officer@wu.edu.et',
            'password' => bcrypt('StrongPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
    }

    /**
     * Create an operation with a stored file.
     */
    protected function makeOperation(string $status, string $path, ?\DateTimeInterface $createdAt = null): DataImportOperation
    {
        if ($path !== '') {
            Storage::disk('local')->put($path, "code,name\nA,Unit A\n");
        }

        $operation = DataImportOperation::create([
            'dataset' => 'organization_units',
            'status' => $status,
            'format' => 'csv',
            'original_filename' => 'dataset.csv',
            'storage_path' => $path === '' ? null : $path,
            'scope_unit_id' => null,
            'created_by' => $this->officer->id,
            'total_rows' => 1,
            'valid_rows' => 1,
        ]);

        if ($createdAt) {
            $operation->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }

        return $operation;
    }

    public function test_an_expired_inspected_upload_is_removed(): void
    {
        $path = 'imports/'.DataImportOperation::max('id').'/expired.csv';

        $operation = $this->makeOperation('validated', $path, now()->subDays(10));

        Storage::disk('local')->assertExists($operation->storage_path);

        $this->artisan('dms:cleanup-import-uploads')
            ->expectsOutputToContain('Files removed: 1')
            ->assertSuccessful();

        Storage::disk('local')->assertMissing($operation->storage_path);
        $this->assertNull($operation->fresh()->storage_path);
    }

    public function test_a_recently_uploaded_file_is_retained(): void
    {
        $operation = $this->makeOperation('validated', 'imports/recent/recent.csv', now()->subHours(2));

        $this->artisan('dms:cleanup-import-uploads')
            ->expectsOutputToContain('Files removed: 0')
            ->assertSuccessful();

        Storage::disk('local')->assertExists($operation->storage_path);
        $this->assertNotNull($operation->fresh()->storage_path);
    }

    public function test_the_file_of_an_executing_import_is_never_touched(): void
    {
        $operation = $this->makeOperation('importing', 'imports/executing/executing.csv', now()->subDays(30));

        $this->artisan('dms:cleanup-import-uploads')->assertSuccessful();

        Storage::disk('local')->assertExists($operation->storage_path);
        $this->assertNotNull($operation->fresh()->storage_path);
    }

    public function test_the_file_of_a_completed_import_is_retained(): void
    {
        // The source of an applied batch is the only record of what was
        // submitted, so it is never removed.
        $operation = $this->makeOperation('completed', 'imports/completed/completed.csv', now()->subDays(30));

        $this->artisan('dms:cleanup-import-uploads')->assertSuccessful();

        Storage::disk('local')->assertExists($operation->storage_path);
        $this->assertNotNull($operation->fresh()->storage_path);
    }

    public function test_a_discarded_operation_keeps_its_history_and_loses_its_stale_reference(): void
    {
        $operation = $this->makeOperation('discarded', 'imports/discarded/discarded.csv', now()->subDays(30));

        // The file was already removed when the operation was discarded.
        Storage::disk('local')->delete($operation->storage_path);

        $this->artisan('dms:cleanup-import-uploads')
            ->expectsOutputToContain('Expired references cleared: 1')
            ->assertSuccessful();

        // The operation itself is preserved.
        $this->assertDatabaseHas('data_import_operations', ['id' => $operation->id]);
        $this->assertNull($operation->fresh()->storage_path);
    }

    public function test_a_missing_physical_file_is_handled_safely(): void
    {
        $operation = $this->makeOperation('validated', 'imports/missing/missing.csv', now()->subDays(10));

        // The file has gone, the reference has not.
        Storage::disk('local')->delete($operation->storage_path);

        $this->artisan('dms:cleanup-import-uploads')->assertSuccessful();

        $this->assertNull($operation->fresh()->storage_path);
        $this->assertDatabaseHas('data_import_operations', ['id' => $operation->id]);
    }

    public function test_a_path_outside_the_import_area_is_never_removed(): void
    {
        // A stored path that does not belong to the import area must never be
        // removed, whatever the operation claims.
        $path = 'documents/2026/legitimate.pdf';
        Storage::disk('local')->put($path, 'a registered university document');

        $operation = $this->makeOperation('validated', $path, now()->subDays(30));

        $this->artisan('dms:cleanup-import-uploads')
            ->expectsOutputToContain('Skipped outside the import area: 1')
            ->assertSuccessful();

        Storage::disk('local')->assertExists($path);
        $this->assertNotNull($operation->fresh()->storage_path);
    }

    public function test_the_dry_run_removes_nothing(): void
    {
        $operation = $this->makeOperation('validated', 'imports/dry/dry.csv', now()->subDays(10));

        $this->artisan('dms:cleanup-import-uploads --dry-run')
            ->expectsOutputToContain('Files that would be removed: 1')
            ->assertSuccessful();

        Storage::disk('local')->assertExists($operation->storage_path);
        $this->assertNotNull($operation->fresh()->storage_path);
    }

    public function test_the_cleanup_is_idempotent(): void
    {
        $operation = $this->makeOperation('validated', 'imports/idem/idem.csv', now()->subDays(10));

        $this->artisan('dms:cleanup-import-uploads')->assertSuccessful();
        $this->artisan('dms:cleanup-import-uploads')
            ->expectsOutputToContain('Files removed: 0')
            ->assertSuccessful();

        $this->assertNull($operation->fresh()->storage_path);
    }

    public function test_the_cleanup_is_audited(): void
    {
        $this->makeOperation('validated', 'imports/audited/audited.csv', now()->subDays(10));

        $this->artisan('dms:cleanup-import-uploads')->assertSuccessful();

        $this->assertDatabaseHas('audit_events', ['action' => 'data.import.cleanup_completed']);
    }

    public function test_the_retention_window_is_configurable(): void
    {
        $operation = $this->makeOperation('validated', 'imports/short/short.csv', now()->subHours(2));

        // The default window would not touch it.
        $this->artisan('dms:cleanup-import-uploads')->assertSuccessful();
        $this->assertNotNull($operation->fresh()->storage_path);

        // An operator supplied window does.
        $this->artisan('dms:cleanup-import-uploads --hours=1')
            ->expectsOutputToContain('Files removed: 1')
            ->assertSuccessful();

        $this->assertNull($operation->fresh()->storage_path);
    }

    public function test_the_runtime_setting_controls_the_retention_window(): void
    {
        $operation = $this->makeOperation('validated', 'imports/setting/setting.csv', now()->subDays(2));

        // A configured window of one hour makes the file eligible.
        DB::table('system_settings')->insert([
            'setting_key' => 'data_imports.temporary_file_retention_hours',
            'scope_unit_id' => null,
            'scope_key' => 0,
            'value_type' => 'integer',
            'integer_value' => 1,
            'string_value' => null,
            'boolean_value' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('dms:cleanup-import-uploads')
            ->expectsOutputToContain('Retention window: 1 hour(s).')
            ->assertSuccessful();

        $this->assertNull($operation->fresh()->storage_path);
    }
}
