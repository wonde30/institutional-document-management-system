<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicLegalResource;
use Illuminate\Http\JsonResponse;

class PublicLegalController extends Controller
{
    /**
     * Get legal policy content by type ('privacy' or 'terms').
     */
    public function show(string $type): JsonResponse
    {
        $legalCatalogue = config('legal_content', []);

        if (! array_key_exists($type, $legalCatalogue)) {
            abort(404, "Legal document type [{$type}] not found.");
        }

        $document = $legalCatalogue[$type];

        return response()->json([
            'success' => true,
            'message' => 'Legal document content retrieved.',
            'data' => (new PublicLegalResource($document))->resolve(),
        ]);
    }
}
