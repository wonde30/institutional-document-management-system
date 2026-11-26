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
        $actor = $request->user();
        $query = AuditEvent::with('actor');

        if ($actor && ! $actor->hasPermission('system.configure')) {
            $permittedUnits = $actor->getPermittedUnitIds('audit.view');
            if ($permittedUnits !== null) {
                $query->where(function ($q) use ($actor, $permittedUnits) {
                    $q->where('actor_id', $actor->id);
                    if (! empty($permittedUnits)) {
                        $q->orWhere(function ($dq) use ($permittedUnits) {
                            $dq->where('target_type', 'document')
                               ->whereIn('target_id', \App\Models\Document::whereIn('owner_unit_id', $permittedUnits)->selectRaw('CAST(id AS CHAR)'));
                        });
                    }
                });
            }
        }

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

        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);
        $events = $query->orderByDesc('occurred_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $events->items(),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'total' => $events->total(),
            ],
        ]);
    }
}
