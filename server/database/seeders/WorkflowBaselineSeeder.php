<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AccreditationCycle;
use App\Models\AccreditationEvidence;
use App\Models\AccreditationFrameworkVersion;
use App\Models\AccreditationStandard;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Permission;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowState;
use App\Models\WorkflowTask;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkflowBaselineSeeder extends Seeder
{
    /**
     * Seed baseline workflow definition, version, states, transitions,
     * staging tasks, and accreditation baseline data non-destructively.
     */
    public function run(): void
    {
        $now = Carbon::now();
        $adminUser = User::where('email', 'admin@wu.edu.et')->first() ?? User::first();
        $adminId = $adminUser ? $adminUser->id : 1;

        // 1. Workflow Definition
        $definition = WorkflowDefinition::firstOrCreate(
            ['code' => 'standard_doc_approval'],
            [
                'name' => 'Standard Document Review & Approval',
                'active' => true,
            ]
        );

        // 2. Workflow Version
        $version = WorkflowVersion::firstOrCreate(
            [
                'definition_id' => $definition->id,
                'version_number' => 1,
            ],
            [
                'created_by' => $adminId,
                'created_at' => $now,
                'published_at' => $now,
            ]
        );

        // 3. Workflow States
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

        // 4. Resolve Permissions for Transitions
        $pSubmit = Permission::where('code', 'workflow.submit')->value('id')
            ?? Permission::where('code', 'workflow.review')->value('id')
            ?? 32;
        $pApprove = Permission::where('code', 'workflow.approve')->value('id')
            ?? Permission::where('code', 'workflow.review')->value('id')
            ?? 34;
        $pReject = Permission::where('code', 'workflow.reject')->value('id')
            ?? Permission::where('code', 'workflow.review')->value('id')
            ?? 35;
        $pReview = Permission::where('code', 'workflow.review')->value('id')
            ?? 33;

        // 5. Workflow Transitions
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
                'prohibit_self_approval' => true,
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

        // 6. HERQA Accreditation Framework Version & Cycle
        $frameworkVersion = AccreditationFrameworkVersion::first();
        if ($frameworkVersion) {
            $cycle = AccreditationCycle::firstOrCreate(
                [
                    'framework_version_id' => $frameworkVersion->id,
                    'name' => 'HERQA Institutional Quality Audit Cycle (2026-2027)',
                ],
                [
                    'unit_id' => 1,
                    'starts_on' => '2026-01-01',
                    'ends_on' => '2027-12-31',
                ]
            );

            // Document Version for Doc 1
            $doc1 = Document::find(1);
            if ($doc1) {
                $doc1Version = DocumentVersion::firstOrCreate(
                    [
                        'document_id' => $doc1->id,
                        'version_number' => 1,
                    ],
                    [
                        'created_by' => $adminId,
                        'change_note' => 'Initial approved charter version for institutional accreditation compliance',
                        'created_at' => $now,
                    ]
                );

                $firstStandard = AccreditationStandard::where('framework_version_id', $frameworkVersion->id)->first();
                if ($firstStandard && $doc1Version) {
                    AccreditationEvidence::firstOrCreate(
                        [
                            'cycle_id' => $cycle->id,
                            'standard_id' => $firstStandard->id,
                            'document_version_id' => $doc1Version->id,
                        ],
                        [
                            'submitted_by' => $adminId,
                            'submitted_at' => $now,
                            'note' => 'Official Wollo University Academic Senate Charter fulfilling governance and leadership standards.',
                        ]
                    );
                }
            }
        }

        // 7. Workflow Tasks
        // Find or create Document Versions for Workflow Tasks
        $docForTask1 = Document::find(50) ?? Document::first();
        $docForTask2 = Document::find(2) ?? Document::skip(1)->first() ?? $docForTask1;

        if ($docForTask1) {
            $version1 = DocumentVersion::firstOrCreate(
                [
                    'document_id' => $docForTask1->id,
                    'version_number' => 1,
                ],
                [
                    'created_by' => $adminId,
                    'change_note' => 'Version 1 for workflow review',
                    'created_at' => $now,
                ]
            );

            $instance1 = WorkflowInstance::firstOrCreate(
                [
                    'document_version_id' => $version1->id,
                    'workflow_version_id' => $version->id,
                ],
                [
                    'started_by' => $adminId,
                    'started_at' => $now,
                ]
            );

            // Find Dr. EE Head (head.ee@wu.edu.et)
            $eeHead = User::where('email', 'head.ee@wu.edu.et')->first();
            $eeHeadId = $eeHead ? $eeHead->id : 4;
            $eeUnitId = 4; // Department of Electrical Engineering

            // Task 1: Assigned to Dr. EE Head directly (mine)
            WorkflowTask::firstOrCreate(
                [
                    'instance_id' => $instance1->id,
                    'state_id' => $stateModels['under_review']->id,
                    'assigned_user_id' => $eeHeadId,
                ],
                [
                    'assigned_unit_id' => $eeUnitId,
                    'created_at' => $now,
                    'due_at' => (clone $now)->addDays(7),
                    'closed_at' => null,
                ]
            );
        }

        if ($docForTask2) {
            $version2 = DocumentVersion::firstOrCreate(
                [
                    'document_id' => $docForTask2->id,
                    'version_number' => 1,
                ],
                [
                    'created_by' => $adminId,
                    'change_note' => 'Version 1 for department office review',
                    'created_at' => $now,
                ]
            );

            $instance2 = WorkflowInstance::firstOrCreate(
                [
                    'document_version_id' => $version2->id,
                    'workflow_version_id' => $version->id,
                ],
                [
                    'started_by' => $adminId,
                    'started_at' => $now,
                ]
            );

            // Task 2: Assigned to the Department of Electrical Engineering office (office queue)
            WorkflowTask::firstOrCreate(
                [
                    'instance_id' => $instance2->id,
                    'state_id' => $stateModels['under_review']->id,
                    'assigned_user_id' => null,
                    'assigned_unit_id' => 4,
                ],
                [
                    'created_at' => $now,
                    'due_at' => (clone $now)->addDays(5),
                    'closed_at' => null,
                ]
            );
        }
    }
}
