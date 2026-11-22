<?php

namespace Tests\Feature;

use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\StampDefinition;
use App\Models\User;
use App\Services\UniversityAssetService;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditStampPermissionResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);
    }

    public function test_user_with_stamps_manage_permission_is_authorized_for_stamp(): void
    {
        $unit = OrganizationUnit::first();
        $user = User::factory()->create(['status' => 'active', 'authz_version' => 1]);

        // Assign stamps.manage permission in unit scope
        $scope = \App\Models\AuthorizationScope::firstOrCreate([
            'kind' => 'unit',
            'unit_id' => $unit->id,
        ]);
        $permission = \App\Models\Permission::where('code', 'stamps.manage')->first();

        \App\Models\UserPermission::create([
            'user_id' => $user->id,
            'permission_id' => $permission->id,
            'scope_id' => $scope->id,
            'granted_by' => 1,
            'granted_at' => now(),
        ]);

        $this->assertTrue($user->hasPermission('stamps.manage', $unit->id));
        $this->assertFalse($user->hasPermission('documents.stamp', $unit->id));
    }
}
