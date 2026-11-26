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

        $totalDocuments = (int) $docCounts->sum();
        $totalCorrespondences = (int) $corrCounts->sum();

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

        $activeUsers = $isSuperAdmin
            ? User::where('status', 'active')->count()
            : User::where('status', 'active')
                ->whereHas('memberships', fn ($mq) => $mq->whereIn('unit_id', $permittedDocUnits ?: [0]))
                ->count();
        $totalUnits = $isSuperAdmin
            ? OrganizationUnit::where('active', 1)->count()
            : count($permittedDocUnits ?? []);
        $totalMovements = $movementsQuery->count();
        $pendingAssignments = $pendingAssignmentsQuery->count();
        $archiveCounts = ($isSuperAdmin
            ? ArchiveEntry::query()
            : ArchiveEntry::whereHas('document', fn ($dq) => $dq->accessibleBy($actor, 'documents.view'))
        )->selectRaw('COUNT(*) as total, SUM(CASE WHEN next_review_at IS NOT NULL AND next_review_at <= ? THEN 1 ELSE 0 END) as awaiting_review', [now()])->first();
        $archivedRecords = (int) ($archiveCounts->total ?? 0);
        $recordsAwaitingRetentionReviewCount = (int) ($archiveCounts->awaiting_review ?? 0);

        // Operational workflow tasks query (scoped to user assignments and authorized units)
        $pendingWorkflowTasksQuery = WorkflowTask::with([
            'instance.documentVersion.document:id,title,reference_number,owner_unit_id,category_id,confidentiality_id',
            'state:id,code,name,is_terminal',
            'assignedUser:id,name,email',
            'assignedUnit:id,code,name',
        ])->whereNull('closed_at');
        if ($permittedDocUnits !== null) {
            $pendingWorkflowTasksQuery->where(function ($q) use ($permittedDocUnits, $actor) {
                $q->where('assigned_user_id', $actor ? $actor->id : 0)
                  ->orWhere('delegated_to_user_id', $actor ? $actor->id : 0)
                  ->orWhereIn('assigned_unit_id', $permittedDocUnits);
            })->where(function ($q) use ($actor) {
                $q->whereDoesntHave('instance.documentVersion.document')
                  ->orWhereHas('instance.documentVersion.document', fn ($dq) => $dq->accessibleBy($actor, 'documents.view'));
            });
        }
        $pendingTasksList = (clone $pendingWorkflowTasksQuery)->orderBy('due_at', 'asc')->limit(5)->get();
        if ($pendingTasksList->count() < 5) {
            $pendingWorkflowTasksCount = $pendingTasksList->count();
            $overdueTasksCount = $pendingTasksList->filter(fn ($t) => $t->due_at && $t->due_at->lt(now()))->count();
        } else {
            $taskCounts = (clone $pendingWorkflowTasksQuery)
                ->selectRaw('COUNT(*) as total, SUM(CASE WHEN due_at < ? THEN 1 ELSE 0 END) as overdue', [now()])
                ->first();
            $pendingWorkflowTasksCount = (int) ($taskCounts->total ?? 0);
            $overdueTasksCount = (int) ($taskCounts->overdue ?? 0);
        }

        // Outgoing letters awaiting authorized physical or electronic dispatch
        $outgoingAwaitingDispatchQuery = (clone $corrBase)
            ->whereHas('type', fn ($tq) => $tq->where('code', 'outgoing'))
            ->whereDoesntHave('dispatches');
        $outgoingAwaitingDispatch = (clone $outgoingAwaitingDispatchQuery)
            ->with([
                'document:id,title,reference_number,owner_unit_id',
                'type:id,code,name',
                'priority:id,code,name',
            ])
            ->orderByDesc('registered_at')
            ->limit(5)
            ->get();
        $outgoingAwaitingDispatchCount = $outgoingAwaitingDispatch->count() < 5
            ? $outgoingAwaitingDispatch->count()
            : (clone $outgoingAwaitingDispatchQuery)->count();

        // Documents requiring immediate user action (assigned workflow task or directive)
        $docsRequiringActionQuery = (clone $docBase)
            ->where(function ($q) use ($actor) {
                $q->whereHas('assignments', fn ($aq) => $aq->where('assigned_user_id', $actor ? $actor->id : 0))
                  ->orWhereHas('versions.workflowInstances.tasks', fn ($tq) => $tq->where('assigned_user_id', $actor ? $actor->id : 0)->whereNull('closed_at'));
            });
        $docsRequiringAction = (clone $docsRequiringActionQuery)
            ->with([
                'category:id,code,name',
                'ownerUnit:id,code,name',
            ])
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();
        $docsRequiringActionCount = $docsRequiringAction->count() < 5
            ? $docsRequiringAction->count()
            : (clone $docsRequiringActionQuery)->count();

        // Recently completed decisions by user or within user scope
        $recentDecisionsQuery = WorkflowTaskDecision::with([
            'task.instance.documentVersion.document:id,title,reference_number',
            'task.state:id,code,name',
            'decidedBy:id,name',
        ])->orderByDesc('decided_at');
        if ($permittedDocUnits !== null) {
            $recentDecisionsQuery->where(function ($q) use ($permittedDocUnits, $actor) {
                $q->where('decided_by', $actor ? $actor->id : 0)
                  ->orWhereHas('task.instance.documentVersion.document', fn ($dq) => $dq->accessibleBy($actor, 'documents.view'));
            });
        }
        $recentDecisions = (clone $recentDecisionsQuery)->limit(5)->get();

        $recentDocuments = (clone $docBase)
            ->with([
                'category:id,code,name',
                'ownerUnit:id,code,name',
                'creator:id,name',
            ])
            ->orderByDesc('registered_at')
            ->limit(5)
            ->get();

        $recentCorrespondences = (clone $corrBase)
            ->with([
                'document:id,title,reference_number',
                'type:id,code,name',
                'priority:id,code,name',
            ])
            ->orderByDesc('registered_at')
            ->limit(5)
            ->get();

        // Operational queue metrics (single combined query)
        $actorId = $actor ? $actor->id : 0;
        $unitPlaceholders = ($permittedDocUnits !== null && ! empty($permittedDocUnits))
            ? implode(',', array_map('intval', $permittedDocUnits))
            : null;
        $officeExpr = $isSuperAdmin
            ? 'SUM(CASE WHEN assigned_user_id IS NULL THEN 1 ELSE 0 END)'
            : ($unitPlaceholders !== null
                ? "SUM(CASE WHEN assigned_user_id IS NULL AND assigned_unit_id IN ({$unitPlaceholders}) THEN 1 ELSE 0 END)"
                : '0');
        $queueCounts = WorkflowTask::whereNull('closed_at')
            ->selectRaw("SUM(CASE WHEN assigned_user_id = ? OR delegated_to_user_id = ? THEN 1 ELSE 0 END) as my_pending, {$officeExpr} as office_pending", [$actorId, $actorId])
            ->first();
        $myPendingTasksCount = (int) ($queueCounts->my_pending ?? 0);
        $myOfficePendingTasksCount = (int) ($queueCounts->office_pending ?? 0);

        $pendingDispositionRequestsCount = $archivedRecords === 0
            ? 0
            : ($isSuperAdmin
            ? \App\Models\DispositionRequest::whereDoesntHave('decision')->count()
            : \App\Models\DispositionRequest::whereDoesntHave('decision')
                ->whereHas('archiveEntry.document', fn ($dq) => $dq->accessibleBy($actor, 'documents.view'))->count());

        $unreceivedDispatchesCount = $isSuperAdmin
            ? \App\Models\CorrespondenceDispatch::whereNull('evidence_file_id')->count()
            : \App\Models\CorrespondenceDispatch::whereNull('evidence_file_id')
                ->whereHas('documentVersion.document', fn ($dq) => $dq->accessibleBy($actor, 'documents.view'))->count();

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
