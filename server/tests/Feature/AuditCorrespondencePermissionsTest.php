<?php

namespace Tests\Feature;

use App\Models\AuthorizationScope;
use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserPermission;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditCorrespondencePermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);
    }

    public function test_correspondence_permissions_match_seeded_vocabulary(): void
    {
        $unit = OrganizationUnit::first();
        $user = User::factory()->create(['status' => 'active', 'authz_version' => 1]);

        $scope = AuthorizationScope::firstOrCreate([
            'kind' => 'unit',
            'unit_id' => $unit->id,
        ]);

        $regPerm = Permission::where('code', 'correspondence.register')->first();
        $dispPerm = Permission::where('code', 'correspondence.dispatch')->first();

        $this->assertNotNull($regPerm);
        $this->assertNotNull($dispPerm);

        UserPermission::create([
            'user_id' => $user->id,
            'permission_id' => $regPerm->id,
            'scope_id' => $scope->id,
            'granted_by' => 1,
            'granted_at' => now(),
        ]);

        $this->assertTrue($user->hasPermission('correspondence.register', $unit->id));
        $this->assertFalse($user->hasPermission('correspondence.manage', $unit->id));
    }
}
