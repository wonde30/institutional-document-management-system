<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * List audit events from the immutable baseline ledger.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditEvent::with('actor');

        if ($request->filled('actor_id')) {
            $query->where('actor_id', $request->query('actor_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->query('action'));
        }

        if ($request->filled('target_type')) {
            $query->where('target_type', $request->query('target_type'));
        }

        if ($request->filled('from_date')) {
            $query->where('occurred_at', '>=', $request->query('from_date'));
        }

        $events = $query->orderByDesc('occurred_at')->paginate($request->query('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $events,
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'total' => $events->total(),
            ],
        ]);
    }
}
