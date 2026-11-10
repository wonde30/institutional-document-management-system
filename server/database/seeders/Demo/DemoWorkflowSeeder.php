<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowState;
use App\Models\WorkflowTask;
use App\Models\WorkflowTaskDecision;
use App\Models\WorkflowVersion;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoWorkflowSeeder extends Seeder
{
    /**
     * Seed active and historical multi-stage approval workflow instances, tasks, and decisions.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $users = User::pluck('id', 'email')->toArray();
        $roles = Role::pluck('id', 'code')->toArray();
        $units = OrganizationUnit::pluck('id', 'code')->toArray();

        $adminId = $users['demo.admin@example.test'] ?? 1;
        $headCsId = $users['demo.head.cs@example.test'] ?? 1;
        $deanEngId = $users['demo.head.eng@example.test'] ?? 1;
        $approverId = $users['demo.approver@example.test'] ?? 1;
        $staffId = $users['demo.staff@example.test'] ?? 1;

        $roleHead = $roles['department_head'] ?? 1;
        $roleAdmin = $roles['admin'] ?? 1;

        $unitCs = $units['CS'] ?? 1;
        $unitCote = $units['COTE'] ?? 1;
        $unitVpaa = $units['VPAA'] ?? 1;

        // 1. Resolve Workflow Definition & States
        $definition = WorkflowDefinition::firstOrCreate(['code' => 'standard_doc_approval'], ['name' => 'Standard Document Review & Approval', 'active' => 1]);
        $version = WorkflowVersion::where('definition_id', $definition->id)->orderByDesc('version_number')->first();
        if (! $version) {
            $version = WorkflowVersion::create([
                'definition_id' => $definition->id,
                'version_number' => 1,
                'created_by' => $adminId,
                'created_at' => $now,
                'published_at' => $now,
            ]);
        }

        $states = WorkflowState::where('workflow_version_id', $version->id)->pluck('id', 'code')->toArray();
        $stateDraft = $states['draft'] ?? 1;
        $stateReview = $states['under_review'] ?? ($states['review'] ?? 2);
        $stateApproved = $states['approved'] ?? 3;
        $stateRejected = $states['rejected'] ?? 4;

        // 2. Scenario 1: Pending Task for Demo Dept Head (CS)
        // Document WU-DOC-2026-0011 (BSc Software Eng Curriculum Modernization)
        $doc1 = Document::where('reference_number', 'WU-DOC-2026-0011')->first();
        if ($doc1) {
            $v1 = DocumentVersion::where('document_id', $doc1->id)->orderByDesc('version_number')->first();
            if ($v1) {
                $instance1 = WorkflowInstance::firstOrCreate(
                    ['document_version_id' => $v1->id],
                    [
                        'workflow_version_id' => $version->id,
                        'started_by' => $staffId,
                        'started_at' => $now->subDays(5),
                    ]
                );

                WorkflowTask::firstOrCreate(
                    [
                        'instance_id' => $instance1->id,
                        'state_id' => $stateReview,
                        'assigned_user_id' => $headCsId,
                    ],
                    [
                        'assigned_unit_id' => $unitCs,
                        'required_role_id' => $roleHead,
                        'created_at' => $now->subDays(5),
                        'due_at' => $now->addDays(5),
                        'closed_at' => null, // ACTIVE & ACTIONABLE!
                    ]
                );
            }
        }

        // 3. Scenario 2: Pending Executive Task for Demo Approver (Academic VP)
        // Document WU-DOC-2026-0012 (MSc Cybersecurity Program Launch)
        $doc2 = Document::where('reference_number', 'WU-DOC-2026-0012')->first();
        if ($doc2) {
            $v2 = DocumentVersion::where('document_id', $doc2->id)->orderByDesc('version_number')->first();
            if ($v2) {
                $instance2 = WorkflowInstance::firstOrCreate(
                    ['document_version_id' => $v2->id],
                    [
                        'workflow_version_id' => $version->id,
                        'started_by' => $headCsId,
                        'started_at' => $now->subDays(3),
                    ]
                );

                WorkflowTask::firstOrCreate(
                    [
                        'instance_id' => $instance2->id,
                        'state_id' => $stateReview,
                        'assigned_user_id' => $approverId,
                    ],
                    [
                        'assigned_unit_id' => $unitVpaa,
                        'required_role_id' => $roleHead,
                        'created_at' => $now->subDays(3),
                        'due_at' => $now->addDays(7),
                        'closed_at' => null, // ACTIVE & ACTIONABLE!
                    ]
                );
            }
        }

        // 4. Scenario 3: Completed Approved Workflow
        // Document WU-DOC-2026-0003 (Revised Academic Policy Framework)
        $doc3 = Document::where('reference_number', 'WU-DOC-2026-0003')->first();
        if ($doc3) {
            $v3 = DocumentVersion::where('document_id', $doc3->id)->orderByDesc('version_number')->first();
            if ($v3) {
                $instance3 = WorkflowInstance::firstOrCreate(
                    ['document_version_id' => $v3->id],
                    [
                        'workflow_version_id' => $version->id,
                        'started_by' => $deanEngId,
                        'started_at' => $now->subDays(20),
                    ]
                );

                $task3 = WorkflowTask::firstOrCreate(
                    [
                        'instance_id' => $instance3->id,
                        'state_id' => $stateApproved,
                    ],
                    [
                        'assigned_user_id' => $approverId,
                        'assigned_unit_id' => $unitVpaa,
                        'required_role_id' => $roleHead,
                        'created_at' => $now->subDays(20),
                        'due_at' => $now->subDays(10),
                        'closed_at' => $now->subDays(12),
                    ]
                );

                WorkflowTaskDecision::firstOrCreate(
                    ['task_id' => $task3->id],
                    [
                        'decided_by' => $approverId,
                        'decision' => 'approve',
                        'reason' => 'Ratified by Academic Council with minor revisions incorporated into Appendix B.',
                        'decided_at' => $now->subDays(12),
                    ]
                );
            }
        }

        // 5. Scenario 4: Completed Rejected Workflow
        // Document WU-DOC-2026-0010 (Revoked Vendor Agreement)
        $doc4 = Document::where('reference_number', 'WU-DOC-2026-0010')->first();
        if ($doc4) {
            $v4 = DocumentVersion::where('document_id', $doc4->id)->orderByDesc('version_number')->first();
            if ($v4) {
                $instance4 = WorkflowInstance::firstOrCreate(
                    ['document_version_id' => $v4->id],
                    [
                        'workflow_version_id' => $version->id,
                        'started_by' => $adminId,
                        'started_at' => $now->subDays(40),
                    ]
                );

                $task4 = WorkflowTask::firstOrCreate(
                    [
                        'instance_id' => $instance4->id,
                        'state_id' => $stateRejected,
                    ],
                    [
                        'assigned_user_id' => $approverId,
                        'assigned_unit_id' => $unitVpaa,
                        'required_role_id' => $roleHead,
                        'created_at' => $now->subDays(40),
                        'due_at' => $now->subDays(30),
                        'closed_at' => $now->subDays(35),
                    ]
                );

                WorkflowTaskDecision::firstOrCreate(
                    ['task_id' => $task4->id],
                    [
                        'decided_by' => $approverId,
                        'decision' => 'reject',
                        'reason' => 'Tender canceled due to non-provision of required bank performance security.',
                        'decided_at' => $now->subDays(35),
                    ]
                );
            }
        }

        // 6. Scenario 5: Pending Task for Dean of Engineering
        // Document WU-DOC-2026-0043 (Solar-Wind Microgrid Proposal)
        $doc5 = Document::where('reference_number', 'WU-DOC-2026-0043')->first();
        if ($doc5) {
            $v5 = DocumentVersion::where('document_id', $doc5->id)->orderByDesc('version_number')->first();
            if ($v5) {
                $instance5 = WorkflowInstance::firstOrCreate(
                    ['document_version_id' => $v5->id],
                    [
                        'workflow_version_id' => $version->id,
                        'started_by' => $headCsId,
                        'started_at' => $now->subDays(2),
                    ]
                );

                WorkflowTask::firstOrCreate(
                    [
                        'instance_id' => $instance5->id,
                        'state_id' => $stateReview,
                        'assigned_user_id' => $deanEngId,
                    ],
                    [
                        'assigned_unit_id' => $unitCote,
                        'required_role_id' => $roleHead,
                        'created_at' => $now->subDays(2),
                        'due_at' => $now->addDays(8),
                        'closed_at' => null, // ACTIVE & ACTIONABLE!
                    ]
                );
            }
        }
    }
}
