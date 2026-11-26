<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ArchiveEntry;
use App\Models\Correspondence;
use App\Models\Document;
use App\Models\DocumentMovement;
use App\Models\OrganizationUnit;
use App\Services\AuditService;
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
        $actor = $request->user();
        $startDate = $request->get('from_date', now()->subMonths(6)->toDateString());
        $endDate = $request->get('to_date', now()->toDateString());

        $docAccessible = Document::accessibleBy($actor, 'reports.view');
        $corrAccessible = Correspondence::accessibleBy($actor, 'reports.view');

        // Document volume by category
        $byCategory = (clone $docAccessible)
            ->join('document_categories', 'documents.category_id', '=', 'document_categories.id')
            ->whereBetween('documents.registered_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->selectRaw('document_categories.name as category, count(*) as count')
            ->groupBy('document_categories.name')
            ->get();

        // Document volume by confidentiality
        $byConfidentiality = (clone $docAccessible)
            ->join('confidentiality_levels', 'documents.confidentiality_id', '=', 'confidentiality_levels.id')
            ->whereBetween('documents.registered_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->selectRaw('confidentiality_levels.name as confidentiality, count(*) as count')
            ->groupBy('confidentiality_levels.name')
            ->get();

        // Correspondence volume by type
        $byCorrespondenceType = (clone $corrAccessible)
            ->join('correspondence_types', 'correspondences.type_id', '=', 'correspondence_types.id')
            ->whereBetween('correspondences.registered_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->selectRaw('correspondence_types.name as type, count(*) as count')
            ->groupBy('correspondence_types.name')
            ->get();

        // Movements by receiving unit (filtered through accessible documents)
        $byUnitMovements = DocumentMovement::whereIn('document_id', (clone $docAccessible)->select('documents.id'))
            ->join('organization_units', 'document_movements.to_unit_id', '=', 'organization_units.id')
            ->whereBetween('document_movements.sent_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->selectRaw('organization_units.name as unit, count(*) as count')
            ->groupBy('organization_units.name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $archiveCount = ArchiveEntry::whereIn('document_id', (clone $docAccessible)->select('documents.id'))->count();

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
                    'documents' => (clone $docAccessible)->count(),
                    'correspondence' => (clone $corrAccessible)->count(),
                    'movements' => DocumentMovement::whereIn('document_id', (clone $docAccessible)->select('documents.id'))->count(),
                    'archive_entries' => $archiveCount,
                ],
            ],
        ]);
    }

    /**
     * Export institutional documents report as CSV with chunked streaming,
     * strict formula injection escaping on every text column, row cap,
     * truncation header, and audit logging after export completion.
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $actor = $request->user();
        $startDate = $request->get('from_date', now()->subMonths(6)->toDateString());
        $endDate = $request->get('to_date', now()->toDateString());

        $docAccessible = Document::accessibleBy($actor, 'reports.export');
        $permittedUnitIds = $actor->getPermittedUnitIds('reports.export');

        $query = (clone $docAccessible)
            ->with(['category', 'ownerUnit'])
            ->whereBetween('documents.registered_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        if ($permittedUnitIds !== null) {
            $query->whereIn('documents.owner_unit_id', $permittedUnitIds);
        }

        $rowCap = (int) $request->get('limit', 10000);
        if ($rowCap <= 0 || $rowCap > 10000) {
            $rowCap = 10000;
        }

        $totalMatching = (clone $query)->count();
        $isTruncated = $totalMatching > $rowCap;
        $exportCount = min($totalMatching, $rowCap);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="institutional_documents_report_' . date('Ymd_His') . '.csv"',
            'X-Row-Cap' => (string) $rowCap,
            'X-Row-Count' => (string) $exportCount,
            'X-Row-Cap-Hit' => $isTruncated ? 'true' : 'false',
            'X-Report-Truncated' => $isTruncated ? 'true' : 'false',
        ];

        if ($isTruncated) {
            $headers['X-Warning'] = "Report row cap of {$rowCap} reached; results truncated.";
        }

        $escapeFormula = function (?string $val): string {
            $str = (string) ($val ?? '');
            if ($str !== '' && in_array($str[0], ['=', '+', '-', '@', "\t", "\r", "\n"], true)) {
                return "'" . $str;
            }
            return $str;
        };

        return response()->stream(function () use ($query, $rowCap, $isTruncated, $totalMatching, $escapeFormula, $startDate, $endDate, $actor, $request) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Reference Number', 'Title', 'Category', 'Owner Unit', 'Document Date']);

            $exportedCount = 0;

            // Stream in chunks of 500 records up to rowCap
            $query->orderBy('documents.id', 'asc')
                ->chunk(500, function ($documents) use ($handle, $escapeFormula, &$exportedCount, $rowCap) {
                    foreach ($documents as $doc) {
                        if ($exportedCount >= $rowCap) {
                            return false; // Break chunking
                        }

                        $ref = $escapeFormula($doc->reference_number);
                        $title = $escapeFormula($doc->title);
                        $category = $escapeFormula($doc->category?->name);
                        $ownerUnit = $escapeFormula($doc->ownerUnit?->name);
                        $docDate = (string) ($doc->document_date ?? $doc->registered_at);

                        fputcsv($handle, [$ref, $title, $category, $ownerUnit, $docDate]);
                        $exportedCount++;
                    }
                });

            if ($isTruncated) {
                fputcsv($handle, ["NOTICE: Truncated at row cap of {$rowCap} records", '', '', '', '']);
            }

            // Write audit row after export succeeds, recording the outcome
            AuditService::log(
                action: 'report.exported',
                targetType: 'report',
                targetId: 'documents_export',
                details: [
                    'from_date' => $startDate,
                    'to_date' => $endDate,
                    'rows_exported' => $exportedCount,
                    'total_matched' => $totalMatching,
                    'row_cap' => $rowCap,
                    'truncated' => $isTruncated,
                    'outcome' => 'success',
                ],
                actorId: $actor->id,
                request: $request
            );

            fclose($handle);
        }, 200, $headers);
    }
}
