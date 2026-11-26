<?php

namespace Tests\Feature;

use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserPermission;
use App\Models\AuthorizationScope;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditWorkflowPermissionResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);
    }

    public function test_workflow_reassign_permission_is_seeded_and_verifiable(): void
    {
        $unit = OrganizationUnit::first();
        $user = User::factory()->create(['status' => 'active', 'authz_version' => 1]);

        $scope = AuthorizationScope::firstOrCreate([
            'kind' => 'unit',
            'unit_id' => $unit->id,
        ]);
        $permission = Permission::where('code', 'workflow.reassign')->first();
        $this->assertNotNull($permission, 'workflow.reassign must exist in seeded permissions.');

        UserPermission::create([
            'user_id' => $user->id,
            'permission_id' => $permission->id,
            'scope_id' => $scope->id,
            'granted_by' => 1,
            'granted_at' => now(),
        ]);

        $this->assertTrue($user->hasPermission('workflow.reassign', $unit->id));
        $this->assertFalse($user->hasPermission('workflow.delegate', $unit->id));
    }
}
