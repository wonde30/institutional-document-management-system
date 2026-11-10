<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ArchiveEntry;
use App\Models\Correspondence;
use App\Models\Document;
use App\Models\DocumentMovement;
use App\Models\OrganizationUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Institutional administrative and document management summary report.
     */
    public function summary(Request $request): JsonResponse
    {
        $startDate = $request->get('from_date', now()->subMonths(6)->toDateString());
        $endDate = $request->get('to_date', now()->toDateString());

        // Document volume by category
        $byCategory = Document::join('document_categories', 'documents.category_id', '=', 'document_categories.id')
            ->whereBetween('documents.registered_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->selectRaw('document_categories.name as category, count(*) as count')
            ->groupBy('document_categories.name')
            ->get();

        // Document volume by confidentiality
        $byConfidentiality = Document::join('confidentiality_levels', 'documents.confidentiality_id', '=', 'confidentiality_levels.id')
            ->whereBetween('documents.registered_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->selectRaw('confidentiality_levels.name as confidentiality, count(*) as count')
            ->groupBy('confidentiality_levels.name')
            ->get();

        // Correspondence volume by type
        $byCorrespondenceType = Correspondence::join('correspondence_types', 'correspondences.type_id', '=', 'correspondence_types.id')
            ->whereBetween('correspondences.registered_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->selectRaw('correspondence_types.name as type, count(*) as count')
            ->groupBy('correspondence_types.name')
            ->get();

        // Movements by receiving unit
        $byUnitMovements = DocumentMovement::join('organization_units', 'document_movements.to_unit_id', '=', 'organization_units.id')
            ->whereBetween('document_movements.sent_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->selectRaw('organization_units.name as unit, count(*) as count')
            ->groupBy('organization_units.name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'from' => $startDate,
                    'to' => $endDate,
                ],
                'documents_by_category' => $byCategory,
                'documents_by_confidentiality' => $byConfidentiality,
                'correspondence_by_type' => $byCorrespondenceType,
                'top_movement_destinations' => $byUnitMovements,
                'totals' => [
                    'documents' => Document::count(),
                    'correspondence' => Correspondence::count(),
                    'movements' => DocumentMovement::count(),
                    'archive_entries' => ArchiveEntry::count(),
                ],
            ],
        ]);
    }
}
