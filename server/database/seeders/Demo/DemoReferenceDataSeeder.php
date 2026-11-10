<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Models\Permission;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowState;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use Carbon\Carbon;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Database\Seeder;

class DemoReferenceDataSeeder extends Seeder
{
    /**
     * Seed baseline institutional vocabulary, roles, permissions, and workflow state machines.
     */
    public function run(): void
    {
        $now = Carbon::now();

        // 1. Call pure baseline reference vocabulary seeder (roles, permissions, categories, priorities, unit types, etc.)
        $this->call(BaselineReferenceVocabularySeeder::class);

        // 2. Seed Workflow Definition
        $definition = WorkflowDefinition::firstOrCreate(
            ['code' => 'standard_doc_approval'],
            [
                'name' => 'Standard Document Review & Approval',
                'active' => true,
            ]
        );

        // 3. Workflow Version
        $version = WorkflowVersion::firstOrCreate(
            [
                'definition_id' => $definition->id,
                'version_number' => 1,
            ],
            [
                'created_by' => null,
                'created_at' => $now,
                'published_at' => $now,
            ]
        );

        // 4. Workflow States
        $states = [
            'draft' => ['name' => 'Draft', 'is_initial' => true, 'is_terminal' => false],
            'under_review' => ['name' => 'Under Review', 'is_initial' => false, 'is_terminal' => false],
            'approved' => ['name' => 'Approved', 'is_initial' => false, 'is_terminal' => true],
            'rejected' => ['name' => 'Rejected', 'is_initial' => false, 'is_terminal' => true],
        ];

        $stateModels = [];
        foreach ($states as $code => $cfg) {
            $stateModels[$code] = WorkflowState::firstOrCreate(
                [
                    'workflow_version_id' => $version->id,
                    'code' => $code,
                ],
                [
                    'name' => $cfg['name'],
                    'is_initial' => $cfg['is_initial'],
                    'is_terminal' => $cfg['is_terminal'],
                ]
            );
        }

        // 5. Resolve Permissions for Transitions
        $pSubmit = Permission::where('code', 'workflow.submit')->value('id')
            ?? Permission::where('code', 'workflow.review')->value('id');
        $pApprove = Permission::where('code', 'workflow.approve')->value('id')
            ?? Permission::where('code', 'workflow.review')->value('id');
        $pReject = Permission::where('code', 'workflow.reject')->value('id')
            ?? Permission::where('code', 'workflow.review')->value('id');
        $pReview = Permission::where('code', 'workflow.review')->value('id');

        // 6. Workflow Transitions
        $transitions = [
            [
                'from_state_id' => $stateModels['draft']->id,
                'to_state_id' => $stateModels['under_review']->id,
                'action_code' => 'submit',
                'permission_id' => $pSubmit,
                'requires_reason' => false,
                'requires_signature' => false,
                'prohibit_self_approval' => false,
            ],
            [
                'from_state_id' => $stateModels['under_review']->id,
                'to_state_id' => $stateModels['approved']->id,
                'action_code' => 'approve',
                'permission_id' => $pApprove,
                'requires_reason' => false,
                'requires_signature' => false,
                'prohibit_self_approval' => false,
            ],
            [
                'from_state_id' => $stateModels['under_review']->id,
                'to_state_id' => $stateModels['rejected']->id,
                'action_code' => 'reject',
                'permission_id' => $pReject,
                'requires_reason' => true,
                'requires_signature' => false,
                'prohibit_self_approval' => false,
            ],
            [
                'from_state_id' => $stateModels['under_review']->id,
                'to_state_id' => $stateModels['draft']->id,
                'action_code' => 'return',
                'permission_id' => $pReview,
                'requires_reason' => true,
                'requires_signature' => false,
                'prohibit_self_approval' => false,
            ],
        ];

        foreach ($transitions as $t) {
            WorkflowTransition::updateOrInsert(
                [
                    'from_state_id' => $t['from_state_id'],
                    'action_code' => $t['action_code'],
                ],
                [
                    'to_state_id' => $t['to_state_id'],
                    'permission_id' => $t['permission_id'],
                    'requires_reason' => $t['requires_reason'],
                    'requires_signature' => $t['requires_signature'],
                    'prohibit_self_approval' => $t['prohibit_self_approval'],
                ]
            );
        }
    }
}
