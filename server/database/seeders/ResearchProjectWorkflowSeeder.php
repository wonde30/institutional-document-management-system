<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowState;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ResearchProjectWorkflowSeeder extends Seeder
{
    /**
     * Seed Research Approval and Project Approval workflow definitions
     * to close the gap identified in PROPOSAL_IMPLEMENTATION_TRACEABILITY.md:
     * - Requirement 20: Research approval workflow
     * - Requirement 21: Project approval workflow
     */
    public function run(): void
    {
        $now = Carbon::now();
        $adminUser = User::where('email', 'admin@wu.edu.et')->first() ?? User::first();
        $adminId = $adminUser ? $adminUser->id : 1;

        // ========== RESEARCH APPROVAL WORKFLOW ==========
        $this->seedResearchWorkflow($adminId, $now);

        // ========== PROJECT APPROVAL WORKFLOW ==========
        $this->seedProjectWorkflow($adminId, $now);

        $this->command->info('Research and Project approval workflows seeded successfully.');
    }

    private function seedResearchWorkflow(int $adminId, Carbon $now): void
    {
        // 1. Research Workflow Definition
        $researchDefinition = WorkflowDefinition::firstOrCreate(
            ['code' => 'research_departmental_review'],
            [
                'name' => 'Research Departmental Review & Approval',
                'description' => 'Workflow for departmental review and approval of finalized research outputs before publication.',
                'active' => true,
            ]
        );

        // 2. Research Workflow Version
        $researchVersion = WorkflowVersion::firstOrCreate(
            [
                'definition_id' => $researchDefinition->id,
                'version_number' => 1,
            ],
            [
                'created_by' => $adminId,
                'created_at' => $now,
                'published_at' => $now,
            ]
        );

        // 3. Research Workflow States
        $researchStates = [
            'draft' => [
                'name' => 'Draft',
                'is_initial' => true,
                'is_terminal' => false,
                'sla_hours' => null,
            ],
            'submitted' => [
                'name' => 'Submitted for Review',
                'is_initial' => false,
                'is_terminal' => false,
                'sla_hours' => 72,
            ],
            'under_review' => [
                'name' => 'Under Departmental Review',
                'is_initial' => false,
                'is_terminal' => false,
                'sla_hours' => 120,
            ],
            'approved' => [
                'name' => 'Approved for Publication',
                'is_initial' => false,
                'is_terminal' => true,
                'sla_hours' => null,
            ],
            'rejected' => [
                'name' => 'Rejected',
                'is_initial' => false,
                'is_terminal' => true,
                'sla_hours' => null,
            ],
            'returned' => [
                'name' => 'Returned for Revision',
                'is_initial' => false,
                'is_terminal' => false,
                'sla_hours' => 168,
            ],
        ];

        $researchStateModels = [];
        foreach ($researchStates as $code => $cfg) {
            $researchStateModels[$code] = WorkflowState::firstOrCreate(
                [
                    'workflow_version_id' => $researchVersion->id,
                    'code' => $code,
                ],
                [
                    'name' => $cfg['name'],
                    'is_initial' => $cfg['is_initial'],
                    'is_terminal' => $cfg['is_terminal'],
                    'sla_hours' => $cfg['sla_hours'],
                ]
            );
        }

        // 4. Resolve Research Permissions
        $pSubmit = Permission::where('code', 'research.submit')->value('id')
            ?? Permission::where('code', 'research.create')->value('id');
        $pApprove = Permission::where('code', 'research.approve')->value('id')
            ?? Permission::where('code', 'research.publish')->value('id');
        $pReject = Permission::where('code', 'research.reject')->value('id')
            ?? Permission::where('code', 'research.approve')->value('id');
        $pReview = Permission::where('code', 'research.review')->value('id')
            ?? Permission::where('code', 'research.view')->value('id');

        // 5. Research Workflow Transitions
        $researchTransitions = [
            [
                'from_state_id' => $researchStateModels['draft']->id,
                'to_state_id' => $researchStateModels['submitted']->id,
                'action_code' => 'submit',
                'permission_id' => $pSubmit,
                'requires_reason' => false,
                'requires_signature' => false,
                'prohibit_self_approval' => false,
                'approval_policy' => 'single',
            ],
            [
                'from_state_id' => $researchStateModels['submitted']->id,
                'to_state_id' => $researchStateModels['under_review']->id,
                'action_code' => 'start_review',
                'permission_id' => $pReview,
                'requires_reason' => false,
                'requires_signature' => false,
                'prohibit_self_approval' => false,
                'approval_policy' => 'single',
            ],
            [
                'from_state_id' => $researchStateModels['under_review']->id,
                'to_state_id' => $researchStateModels['approved']->id,
                'action_code' => 'approve',
                'permission_id' => $pApprove,
                'requires_reason' => false,
                'requires_signature' => false,
                'prohibit_self_approval' => true,
                'approval_policy' => 'single',
            ],
            [
                'from_state_id' => $researchStateModels['under_review']->id,
                'to_state_id' => $researchStateModels['rejected']->id,
                'action_code' => 'reject',
                'permission_id' => $pReject,
                'requires_reason' => true,
                'requires_signature' => false,
                'prohibit_self_approval' => false,
                'approval_policy' => 'single',
            ],
            [
                'from_state_id' => $researchStateModels['under_review']->id,
                'to_state_id' => $researchStateModels['returned']->id,
                'action_code' => 'return',
                'permission_id' => $pReview,
                'requires_reason' => true,
                'requires_signature' => false,
                'prohibit_self_approval' => false,
                'approval_policy' => 'single',
            ],
            [
                'from_state_id' => $researchStateModels['returned']->id,
                'to_state_id' => $researchStateModels['submitted']->id,
                'action_code' => 'resubmit',
                'permission_id' => $pSubmit,
                'requires_reason' => false,
                'requires_signature' => false,
                'prohibit_self_approval' => false,
                'approval_policy' => 'single',
            ],
        ];

        foreach ($researchTransitions as $transition) {
            WorkflowTransition::firstOrCreate(
                [
                    'from_state_id' => $transition['from_state_id'],
                    'to_state_id' => $transition['to_state_id'],
                    'action_code' => $transition['action_code'],
                ],
                $transition
            );
        }

        $this->command->info("✓ Research Departmental Review workflow created (Definition ID: {$researchDefinition->id})");
    }

    private function seedProjectWorkflow(int $adminId, Carbon $now): void
    {
        // 1. Project Workflow Definition
        $projectDefinition = WorkflowDefinition::firstOrCreate(
            ['code' => 'project_approval'],
            [
                'name' => 'Project Approval Workflow',
                'description' => 'Workflow for approval of project proposals and innovation initiatives.',
                'active' => true,
            ]
        );

        // 2. Project Workflow Version
        $projectVersion = WorkflowVersion::firstOrCreate(
            [
                'definition_id' => $projectDefinition->id,
                'version_number' => 1,
            ],
            [
                'created_by' => $adminId,
                'created_at' => $now,
                'published_at' => $now,
            ]
        );

        // 3. Project Workflow States
        $projectStates = [
            'draft' => [
                'name' => 'Draft Proposal',
                'is_initial' => true,
                'is_terminal' => false,
                'sla_hours' => null,
            ],
            'submitted' => [
                'name' => 'Submitted for Review',
                'is_initial' => false,
                'is_terminal' => false,
                'sla_hours' => 48,
            ],
            'under_review' => [
                'name' => 'Under Committee Review',
                'is_initial' => false,
                'is_terminal' => false,
                'sla_hours' => 168,
            ],
            'approved' => [
                'name' => 'Approved',
                'is_initial' => false,
                'is_terminal' => true,
                'sla_hours' => null,
            ],
            'rejected' => [
                'name' => 'Rejected',
                'is_initial' => false,
                'is_terminal' => true,
                'sla_hours' => null,
            ],
            'returned' => [
                'name' => 'Returned for Revision',
                'is_initial' => false,
                'is_terminal' => false,
                'sla_hours' => 120,
            ],
        ];

        $projectStateModels = [];
        foreach ($projectStates as $code => $cfg) {
            $projectStateModels[$code] = WorkflowState::firstOrCreate(
                [
                    'workflow_version_id' => $projectVersion->id,
                    'code' => $code,
                ],
                [
                    'name' => $cfg['name'],
                    'is_initial' => $cfg['is_initial'],
                    'is_terminal' => $cfg['is_terminal'],
                    'sla_hours' => $cfg['sla_hours'],
                ]
            );
        }

        // 4. Resolve Project Permissions
        $pSubmit = Permission::where('code', 'projects.submit')->value('id')
            ?? Permission::where('code', 'projects.view')->value('id');
        $pApprove = Permission::where('code', 'projects.approve')->value('id')
            ?? Permission::where('code', 'projects.submit')->value('id');
        $pReject = Permission::where('code', 'projects.reject')->value('id')
            ?? Permission::where('code', 'projects.approve')->value('id');
        $pReview = Permission::where('code', 'projects.review')->value('id')
            ?? Permission::where('code', 'projects.view')->value('id');

        // 5. Project Workflow Transitions
        $projectTransitions = [
            [
                'from_state_id' => $projectStateModels['draft']->id,
                'to_state_id' => $projectStateModels['submitted']->id,
                'action_code' => 'submit',
                'permission_id' => $pSubmit,
                'requires_reason' => false,
                'requires_signature' => false,
                'prohibit_self_approval' => false,
                'approval_policy' => 'single',
            ],
            [
                'from_state_id' => $projectStateModels['submitted']->id,
                'to_state_id' => $projectStateModels['under_review']->id,
                'action_code' => 'start_review',
                'permission_id' => $pReview,
                'requires_reason' => false,
                'requires_signature' => false,
                'prohibit_self_approval' => false,
                'approval_policy' => 'single',
            ],
            [
                'from_state_id' => $projectStateModels['under_review']->id,
                'to_state_id' => $projectStateModels['approved']->id,
                'action_code' => 'approve',
                'permission_id' => $pApprove,
                'requires_reason' => false,
                'requires_signature' => false,
                'prohibit_self_approval' => true,
                'approval_policy' => 'single',
            ],
            [
                'from_state_id' => $projectStateModels['under_review']->id,
                'to_state_id' => $projectStateModels['rejected']->id,
                'action_code' => 'reject',
                'permission_id' => $pReject,
                'requires_reason' => true,
                'requires_signature' => false,
                'prohibit_self_approval' => false,
                'approval_policy' => 'single',
            ],
            [
                'from_state_id' => $projectStateModels['under_review']->id,
                'to_state_id' => $projectStateModels['returned']->id,
                'action_code' => 'return',
                'permission_id' => $pReview,
                'requires_reason' => true,
                'requires_signature' => false,
                'prohibit_self_approval' => false,
                'approval_policy' => 'single',
            ],
            [
                'from_state_id' => $projectStateModels['returned']->id,
                'to_state_id' => $projectStateModels['submitted']->id,
                'action_code' => 'resubmit',
                'permission_id' => $pSubmit,
                'requires_reason' => false,
                'requires_signature' => false,
                'prohibit_self_approval' => false,
                'approval_policy' => 'single',
            ],
        ];

        foreach ($projectTransitions as $transition) {
            WorkflowTransition::firstOrCreate(
                [
                    'from_state_id' => $transition['from_state_id'],
                    'to_state_id' => $transition['to_state_id'],
                    'action_code' => $transition['action_code'],
                ],
                $transition
            );
        }

        $this->command->info("✓ Project Approval workflow created (Definition ID: {$projectDefinition->id})");
    }
}
