<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ArchiveEntry;
use App\Models\Correspondence;
use App\Models\Document;
use App\Models\DocumentAssignment;
use App\Models\DocumentMovement;
use App\Models\OrganizationUnit;
use App\Models\User;
use App\Models\WorkflowTask;
use App\Models\WorkflowTaskDecision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get aggregated university executive and operational KPIs.
     */
    public function metrics(Request $request): JsonResponse
    {
        $actor = $request->user();
        $isSuperAdmin = $actor ? $actor->hasPermission('system.configure') : false;
        $permittedDocUnits = ($actor && ! $isSuperAdmin) ? $actor->getPermittedUnitIds('documents.view') : null;

        $docBase = Document::accessibleBy($actor, 'documents.view');
        $corrBase = Correspondence::accessibleBy($actor, 'correspondence.view');

        $docCounts = (clone $docBase)
            ->select('lifecycle', DB::raw('count(*) as count'))
            ->groupBy('lifecycle')
            ->pluck('count', 'lifecycle');

        $corrCounts = (clone $corrBase)
            ->join('correspondence_types', 'correspondences.type_id', '=', 'correspondence_types.id')
            ->select('correspondence_types.code', DB::raw('count(*) as count'))
            ->groupBy('correspondence_types.code')
            ->pluck('count', 'correspondence_types.code');

        $totalDocuments = (clone $docBase)->count();
        $totalCorrespondences = (clone $corrBase)->count();

        $movementsQuery = DocumentMovement::query();
        $pendingAssignmentsQuery = DocumentAssignment::query();
        if ($permittedDocUnits !== null) {
            $movementsQuery->where(function ($mq) use ($permittedDocUnits) {
                $mq->whereIn('from_unit_id', $permittedDocUnits)
                   ->orWhereIn('to_unit_id', $permittedDocUnits);
            });
            $pendingAssignmentsQuery->where(function ($aq) use ($permittedDocUnits, $actor) {
                $aq->whereIn('assigned_unit_id', $permittedDocUnits)
                   ->orWhere('assigned_user_id', $actor ? $actor->id : 0);
            });
        }

        $activeUsers = User::where('status', 'active')->count();
        $totalUnits = OrganizationUnit::where('active', 1)->count();
        $totalMovements = $movementsQuery->count();
        $pendingAssignments = $pendingAssignmentsQuery->count();
        $archivedRecords = ArchiveEntry::count();

        // Operational workflow tasks query (scoped to user assignments and authorized units)
        $pendingWorkflowTasksQuery = WorkflowTask::with(['instance.documentVersion.document', 'state', 'assignedUser', 'assignedUnit'])
            ->whereNull('closed_at');
        if ($permittedDocUnits !== null) {
            $pendingWorkflowTasksQuery->where(function ($q) use ($permittedDocUnits, $actor) {
                $q->where('assigned_user_id', $actor ? $actor->id : 0)
                  ->orWhere('delegated_to_user_id', $actor ? $actor->id : 0)
                  ->orWhereIn('assigned_unit_id', $permittedDocUnits);
            });
        }
        $pendingWorkflowTasksCount = (clone $pendingWorkflowTasksQuery)->count();
        $overdueTasksCount = (clone $pendingWorkflowTasksQuery)->where('due_at', '<', now())->count();
        $pendingTasksList = (clone $pendingWorkflowTasksQuery)->orderBy('due_at', 'asc')->limit(5)->get();

        // Outgoing letters awaiting authorized physical or electronic dispatch
        $outgoingAwaitingDispatchQuery = (clone $corrBase)
            ->whereHas('type', fn ($tq) => $tq->where('code', 'outgoing'))
            ->whereDoesntHave('dispatches');
        $outgoingAwaitingDispatchCount = (clone $outgoingAwaitingDispatchQuery)->count();
        $outgoingAwaitingDispatch = (clone $outgoingAwaitingDispatchQuery)
            ->with(['document', 'type', 'priority'])
            ->orderByDesc('registered_at')
            ->limit(5)
            ->get();

        // Documents requiring immediate user action (assigned workflow task or directive)
        $docsRequiringActionQuery = (clone $docBase)
            ->where(function ($q) use ($actor) {
                $q->whereHas('assignments', fn ($aq) => $aq->where('assigned_user_id', $actor ? $actor->id : 0))
                  ->orWhereHas('versions.workflowInstances.tasks', fn ($tq) => $tq->where('assigned_user_id', $actor ? $actor->id : 0)->whereNull('closed_at'));
            });
        $docsRequiringActionCount = (clone $docsRequiringActionQuery)->count();
        $docsRequiringAction = (clone $docsRequiringActionQuery)
            ->with(['category', 'ownerUnit'])
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        // Recently completed decisions by user or within user scope
        $recentDecisionsQuery = WorkflowTaskDecision::with(['task.instance.documentVersion.document', 'decidedBy'])
            ->orderByDesc('decided_at');
        if ($permittedDocUnits !== null) {
            $recentDecisionsQuery->where(function ($q) use ($permittedDocUnits, $actor) {
                $q->where('decided_by', $actor ? $actor->id : 0)
                  ->orWhereHas('task.instance.documentVersion.document', fn ($dq) => $dq->whereIn('owner_unit_id', $permittedDocUnits));
            });
        }
        $recentDecisions = (clone $recentDecisionsQuery)->limit(5)->get();

        $recentDocuments = (clone $docBase)
            ->with(['category', 'ownerUnit', 'creator'])
            ->orderByDesc('registered_at')
            ->limit(5)
            ->get();

        $recentCorrespondences = (clone $corrBase)
            ->with(['document', 'type', 'priority'])
            ->orderByDesc('registered_at')
            ->limit(5)
            ->get();

        // Operational queue metrics
        $myPendingTasksCount = WorkflowTask::whereNull('closed_at')
            ->where(function ($q) use ($actor) {
                $q->where('assigned_user_id', $actor ? $actor->id : 0)
                  ->orWhere('delegated_to_user_id', $actor ? $actor->id : 0);
            })
            ->count();

        $myOfficePendingTasksCount = 0;
        if ($permittedDocUnits !== null && ! empty($permittedDocUnits)) {
            $myOfficePendingTasksCount = WorkflowTask::whereNull('closed_at')
                ->whereNull('assigned_user_id')
                ->whereIn('assigned_unit_id', $permittedDocUnits)
                ->count();
        } elseif ($isSuperAdmin) {
            $myOfficePendingTasksCount = WorkflowTask::whereNull('closed_at')
                ->whereNull('assigned_user_id')
                ->count();
        }

        $recordsAwaitingRetentionReviewCount = ArchiveEntry::whereNotNull('next_review_at')
            ->where('next_review_at', '<=', now())
            ->count();

        $pendingDispositionRequestsCount = \App\Models\DispositionRequest::whereDoesntHave('decision')
            ->count();

        $unreceivedDispatchesCount = \App\Models\CorrespondenceDispatch::whereNull('evidence_file_id')
            ->count();

        $incomingUnreceivedCount = (clone $corrBase)
            ->whereHas('type', fn ($tq) => $tq->where('code', 'incoming'))
            ->whereNull('received_at')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'overview' => [
                    'total_documents' => $totalDocuments,
                    'active_documents' => $docCounts['active'] ?? 0,
                    'retired_documents' => $docCounts['retired'] ?? 0,
                    'tombstoned_documents' => $docCounts['tombstoned'] ?? 0,
                    'total_correspondences' => $totalCorrespondences,
                    'incoming_letters' => $corrCounts['incoming'] ?? 0,
                    'outgoing_letters' => $corrCounts['outgoing'] ?? 0,
                    'internal_memos' => $corrCounts['internal_memo'] ?? 0,
                    'active_users' => $activeUsers,
                    'organization_units' => $totalUnits,
                    'total_movements' => $totalMovements,
                    'pending_assignments' => $pendingAssignments,
                    'archived_records' => $archivedRecords,
                    'pending_workflow_tasks' => $pendingWorkflowTasksCount,
                    'my_pending_tasks' => $myPendingTasksCount,
                    'my_office_pending_tasks' => $myOfficePendingTasksCount,
                    'overdue_tasks' => $overdueTasksCount,
                    'outgoing_awaiting_dispatch' => $outgoingAwaitingDispatchCount,
                    'unreceived_dispatches' => $unreceivedDispatchesCount,
                    'incoming_unreceived' => $incomingUnreceivedCount,
                    'records_awaiting_retention_review' => $recordsAwaitingRetentionReviewCount,
                    'pending_disposition_requests' => $pendingDispositionRequestsCount,
                    'documents_requiring_action' => $docsRequiringActionCount,
                ],
                'pending_tasks' => $pendingTasksList,
                'documents_requiring_action' => $docsRequiringAction,
                'outgoing_awaiting_dispatch' => $outgoingAwaitingDispatch,
                'recent_decisions' => $recentDecisions,
                'recent_documents' => $recentDocuments,
                'recent_correspondences' => $recentCorrespondences,
            ],
        ]);
    }
}
