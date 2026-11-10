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
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get aggregated university executive and operational KPIs.
     */
    public function metrics(): JsonResponse
    {
        $docCounts = Document::select('lifecycle', DB::raw('count(*) as count'))
            ->groupBy('lifecycle')
            ->pluck('count', 'lifecycle');

        $corrCounts = Correspondence::join('correspondence_types', 'correspondences.type_id', '=', 'correspondence_types.id')
            ->select('correspondence_types.code', DB::raw('count(*) as count'))
            ->groupBy('correspondence_types.code')
            ->pluck('count', 'correspondence_types.code');

        $activeUsers = User::where('status', 'active')->count();
        $totalUnits = OrganizationUnit::where('active', 1)->count();
        $totalMovements = DocumentMovement::count();
        $pendingAssignments = DocumentAssignment::count();
        $archivedRecords = ArchiveEntry::count();

        $recentDocuments = Document::with(['category', 'ownerUnit', 'creator'])
            ->orderByDesc('registered_at')
            ->limit(5)
            ->get();

        $recentCorrespondences = Correspondence::with(['document', 'type', 'priority'])
            ->orderByDesc('registered_at')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'overview' => [
                    'total_documents' => Document::count(),
                    'active_documents' => $docCounts['active'] ?? 0,
                    'retired_documents' => $docCounts['retired'] ?? 0,
                    'tombstoned_documents' => $docCounts['tombstoned'] ?? 0,
                    'total_correspondences' => Correspondence::count(),
                    'incoming_letters' => $corrCounts['incoming'] ?? 0,
                    'outgoing_letters' => $corrCounts['outgoing'] ?? 0,
                    'internal_memos' => $corrCounts['internal_memo'] ?? 0,
                    'active_users' => $activeUsers,
                    'organization_units' => $totalUnits,
                    'total_movements' => $totalMovements,
                    'pending_assignments' => $pendingAssignments,
                    'archived_records' => $archivedRecords,
                ],
                'recent_documents' => $recentDocuments,
                'recent_correspondences' => $recentCorrespondences,
            ],
        ]);
    }
}
