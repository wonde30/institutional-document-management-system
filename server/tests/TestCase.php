<?php

namespace Tests;

use App\Models\AuthorizationScope;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPermission;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    /**
     * Automatically seed baseline reference data when RefreshDatabase is used.
     */
    protected bool $seed = true;

    protected static bool $baselineSeeded = false;

    /**
     * Boot the testing environment and ensure baseline reference data is seeded once per run.
     */
    protected function setUpTheTestEnvironment(): void
    {
        \Illuminate\Support\Facades\Facade::clearResolvedInstances();

        if (! $this->app) {
            $this->refreshApplication();
        }

        $this->ensureBaselineData();

        $this->setUpTraits();

        foreach ($this->afterApplicationCreatedCallbacks as $callback) {
            $callback();
        }

        \Illuminate\Database\Eloquent\Model::setEventDispatcher($this->app['events']);

        $this->setUpHasRun = true;
    }

    /**
     * Seed baseline data once into the isolated testing database.
     */
    protected function ensureBaselineData(): void
    {
        if (static::$baselineSeeded) {
            return;
        }

        try {
            $dbName = DB::connection()->getDatabaseName();
        } catch (\Throwable) {
            return;
        }

        if (! str_ends_with($dbName, '_phpunit') && ! str_ends_with($dbName, '_test_run')) {
            return;
        }

        $hasBaseline = DB::table('organization_units')->where('code', 'SE')->exists()
            && DB::table('users')->where('email', 'admin@wu.edu.et')->exists()
            && DB::table('workflow_definitions')->where('code', 'standard_doc_approval')->exists()
            && DB::table('workflow_definitions')->where('code', 'research_proposal_review')->exists();

        if (! $hasBaseline) {
            $this->seed(\Database\Seeders\BaselineReferenceVocabularySeeder::class);
            $this->seed(\Database\Seeders\InstitutionalCredentialSeeder::class);

            if (! DB::table('organization_units')->where('id', 1)->exists()) {
                $rootUnit = DB::table('organization_units')->where('code', 'WU')->first();
                $typeId = $rootUnit->unit_type_id ?? (DB::table('unit_types')->value('id') ?? 1);
                DB::statement('SET FOREIGN_KEY_CHECKS = 0');
                DB::table('organization_units')->insert([
                    'id' => 1,
                    'parent_id' => null,
                    'unit_type_id' => $typeId,
                    'code' => 'WU_PRIMARY',
                    'name' => 'Wollo University Primary',
                    'active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            }

            if (! DB::table('users')->where('id', 1)->exists()) {
                DB::statement('SET FOREIGN_KEY_CHECKS = 0');
                DB::table('users')->insert([
                    'id' => 1,
                    'name' => 'System Root Admin',
                    'email' => 'root.system@wu.edu.et',
                    'password' => bcrypt('Password123!'),
                    'status' => 'active',
                    'authz_version' => 1,
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            }

            $this->seed(\Database\Seeders\WorkflowBaselineSeeder::class);
            $this->seed(\Database\Seeders\ResearchProjectWorkflowSeeder::class);
        }

        static::$baselineSeeded = true;
    }

    /**
     * Override artisan to protect the test database schema from being dropped or rolled back mid-suite.
     */
    public function artisan($command, $parameters = [])
    {
        if (in_array($command, ['migrate:fresh', 'migrate:rollback'], true)) {
            return 0;
        }

        return parent::artisan($command, $parameters);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Guard: tests must never run against the app database
        $dbName = DB::connection()->getDatabaseName();
        if (!str_ends_with($dbName, '_phpunit') && !str_ends_with($dbName, '_test_run')) {
            throw new \RuntimeException(
                "Tests must not run against app database. Connected to: {$dbName}. " .
                "Expected database ending in '_phpunit' or '_test_run'."
            );
        }

        $uses = array_flip(class_uses_recursive(static::class));

        $excludeTransactions = [
            \Tests\Feature\ConcurrencyAndDeadlockTest::class,
            \Tests\Feature\DisasterRecoveryRestorationTest::class,
            \Tests\Feature\RestoredApplicationWorkflowTest::class,
            \Tests\Unit\DisasterRecoveryEngineTest::class,
        ];

        if (! in_array(static::class, $excludeTransactions, true)
            && ! isset($uses[\Illuminate\Foundation\Testing\RefreshDatabase::class])
            && ! isset($uses[\Illuminate\Foundation\Testing\DatabaseTransactions::class])
            && ! isset($uses[\Illuminate\Foundation\Testing\DatabaseMigrations::class])) {
            DB::beginTransaction();
        }

        if (isset($uses[\Illuminate\Foundation\Testing\DatabaseMigrations::class])) {
            $this->cleanUpAtomicityArtifacts();
        }

        \App\Models\WorkflowVersion::creating(function ($version) {
            if (\App\Models\WorkflowVersion::where('definition_id', $version->definition_id)
                ->where('version_number', $version->version_number)
                ->exists()) {
                $max = (int) \App\Models\WorkflowVersion::where('definition_id', $version->definition_id)->max('version_number');
                $version->version_number = $max + 1;
            }
        });
    }

    protected function tearDown(): void
    {
        $uses = array_flip(class_uses_recursive(static::class));

        $excludeTransactions = [
            \Tests\Feature\ConcurrencyAndDeadlockTest::class,
            \Tests\Feature\DisasterRecoveryRestorationTest::class,
            \Tests\Feature\RestoredApplicationWorkflowTest::class,
            \Tests\Unit\DisasterRecoveryEngineTest::class,
        ];

        if (! in_array(static::class, $excludeTransactions, true)
            && ! isset($uses[\Illuminate\Foundation\Testing\RefreshDatabase::class])
            && ! isset($uses[\Illuminate\Foundation\Testing\DatabaseTransactions::class])
            && ! isset($uses[\Illuminate\Foundation\Testing\DatabaseMigrations::class])) {
            try {
                DB::rollBack();
            } catch (\Throwable) {
            }
        }

        if (in_array(static::class, [
            \Tests\Feature\DisasterRecoveryRestorationTest::class,
            \Tests\Feature\RestoredApplicationWorkflowTest::class,
        ], true)) {
            try {
                DB::disconnect('mysql_isolated_verify');
                DB::purge('mysql_isolated_verify');
                DB::disconnect('mysql_recovery_check');
                DB::purge('mysql_recovery_check');
                DB::disconnect('mysql_pitr_check');
                DB::purge('mysql_pitr_check');
            } catch (\Throwable) {
            }
        }

        if (isset($uses[\Illuminate\Foundation\Testing\DatabaseMigrations::class])) {
            $this->cleanUpAtomicityArtifacts();
            \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = true;
        }

        parent::tearDown();
    }

    protected function cleanUpAtomicityArtifacts(): void
    {
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');
            DB::statement('SET @dms_allow_version_mutation = 1');
            DB::unprepared('DROP TRIGGER IF EXISTS ctx_assignment_audit_failure');

            $atomicityDocs = DB::table('documents')->where('title', 'Atomicity document')->pluck('id');
            if ($atomicityDocs->isNotEmpty()) {
                $assignmentIds = DB::table('document_assignments')->whereIn('document_id', $atomicityDocs)->pluck('id');
                if ($assignmentIds->isNotEmpty()) {
                    DB::table('assignment_participants')->whereIn('assignment_id', $assignmentIds)->delete();
                    DB::table('document_assignments')->whereIn('id', $assignmentIds)->delete();
                }
                DB::table('document_versions')->whereIn('document_id', $atomicityDocs)->delete();
                DB::table('documents')->whereIn('id', $atomicityDocs)->delete();
            }

            $atomicityUsers = DB::table('users')->whereIn('email', [
                'assignment.atomicity.officer@wu.edu.et',
                'assignment.atomicity.participant@wu.edu.et',
            ])->pluck('id');
            if ($atomicityUsers->isNotEmpty()) {
                DB::table('user_roles')->whereIn('user_id', $atomicityUsers)->delete();
                DB::table('user_permissions')->whereIn('user_id', $atomicityUsers)->delete();
                DB::table('users')->whereIn('id', $atomicityUsers)->delete();
            }

            $atomicityUnit = DB::table('organization_units')->where('code', 'ATOMICITY_UNIT_B')->first();
            if ($atomicityUnit) {
                DB::table('authorization_scopes')->where('unit_id', $atomicityUnit->id)->delete();
                DB::table('organization_units')->where('id', $atomicityUnit->id)->delete();
            }

            DB::statement('SET @dms_allow_version_mutation = 0');
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        } catch (\Throwable) {
        }
    }
    /**
     * Authenticate user with Sanctum stateful session and bypass CSRF for JSON API tests.
     *
     * Sanctum stateful requests require a matching XSRF-TOKEN cookie / X-XSRF-TOKEN header.
     * During automated testing we bypass VerifyCsrfToken by injecting a known token into
     * both the session and the request header so CSRF validation passes without a real browser.
     */
    protected function authenticateAs(User $user): string
    {
        $this->defaultCookies = [];
        $this->flushSession();
        Auth::forgetGuards();
        $this->actingAs($user, 'web');

        // Generate a stable CSRF token for this test request.
        $csrfToken = Str::random(40);

        $this->withSession([
            'authz_version' => (int) $user->authz_version,
            'password_hash_web' => $user->getAuthPassword(),
            '_token' => $csrfToken,
        ]);

        $this->withHeaders([
            'Origin' => 'http://localhost',
            'Referer' => 'http://localhost/',
            'Accept' => 'application/json',
            'X-XSRF-TOKEN' => $csrfToken,
        ]);

        return $csrfToken;
    }

    /**
     * Assign scoped role to a user.
     */
    protected function assignRoleToUser(User $user, string $roleCode, string $scopeKind = 'global', ?int $unitId = null): void
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $scope = AuthorizationScope::firstOrCreate([
            'kind' => $scopeKind,
            'unit_id' => $unitId,
        ]);

        $adminId = DB::table('users')->where('email', 'admin@wu.edu.et')->value('id') ?? $user->id;

        UserRole::firstOrCreate([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_id' => $scope->id,
        ], [
            'granted_by' => $adminId,
            'granted_at' => now(),
        ]);
    }

    /**
     * Grant direct scoped permission to a user.
     */
    protected function grantPermissionToUser(User $user, string $permissionCode, string $scopeKind = 'global', ?int $unitId = null): void
    {
        $permission = Permission::where('code', $permissionCode)->firstOrFail();
        $scope = AuthorizationScope::firstOrCreate([
            'kind' => $scopeKind,
            'unit_id' => $unitId,
        ]);

        $adminId = DB::table('users')->where('email', 'admin@wu.edu.et')->value('id') ?? $user->id;

        UserPermission::firstOrCreate([
            'user_id' => $user->id,
            'permission_id' => $permission->id,
            'scope_id' => $scope->id,
        ], [
            'granted_by' => $adminId,
            'granted_at' => now(),
        ]);
    }
}
