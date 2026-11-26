<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Services\OcrTextExtractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __construct(
        protected OcrTextExtractionService $ocrService
    ) {}

    /**
     * Search repository documents using bilingual OCR text and metadata filters.
     */
    public function search(Request $request): JsonResponse
    {
        $actor = $request->user();
        $query = Document::accessibleBy($actor, 'documents.view')
            ->with(['category:id,code,name', 'confidentiality:id,code,name', 'ownerUnit:id,code,name', 'creator:id,name', 'custodian:id,name,email'])
            ->withCount('versions');

        // Scope isolation
        $permittedUnitIds = $actor ? $actor->getPermittedUnitIds('documents.view') : [];
        if ($request->filled('owner_unit_id')) {
            $requestedUnit = (int) $request->query('owner_unit_id');
            if ($permittedUnitIds !== null && ! in_array($requestedUnit, $permittedUnitIds, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: You do not have permission to view documents from this organizational unit.',
                ], 403);
            }
            $query->where('owner_unit_id', $requestedUnit);
        }

        if ($request->filled('lifecycle')) {
            $query->where('lifecycle', $request->query('lifecycle'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('document_date', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('document_date', '<=', $request->query('to_date'));
        }

        $term = $request->query('q') ?: $request->query('search');
        if (! empty($term)) {
            $escapeLike = static fn (string $value): string => str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
            $escapedTerm = $escapeLike((string) $term);
            $normalizedTerm = $escapeLike($this->ocrService->normalizeEthiopicText((string) $term));

            $query->where(function ($q) use ($escapedTerm, $normalizedTerm) {
                $q->where('title', 'like', "%{$escapedTerm}%")
                  ->orWhere('reference_number', 'like', "%{$escapedTerm}%")
                  ->orWhere('summary', 'like', "%{$escapedTerm}%")
                  ->orWhereHas('versions.textExtractions', function ($eq) use ($normalizedTerm, $escapedTerm) {
                      $eq->where('searchable_text', 'like', "%{$normalizedTerm}%")
                         ->orWhere('raw_text', 'like', "%{$escapedTerm}%");
                  });
            });
        }

        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);
        $documents = $query->orderByDesc('registered_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => DocumentResource::collection($documents),
            'meta' => [
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'total' => $documents->total(),
            ],
        ]);
    }
}
