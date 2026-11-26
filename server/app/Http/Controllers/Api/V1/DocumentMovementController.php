<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\DocumentMovement;
use App\Models\MovementReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DocumentMovementController extends Controller
{
    /**
     * List movements for a specific document.
     */
    public function index(int $documentId): JsonResponse
    {
        $document = Document::findOrFail($documentId);

        $movements = DocumentMovement::with(['fromUnit', 'toUnit', 'sentBy', 'receipt.acknowledgedBy'])
            ->where('document_id', $document->id)
            ->orderByDesc('sent_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $movements,
        ]);
    }

    /**
     * Record an inter-office document movement / transit.
     */
    public function store(Request $request, int $documentId): JsonResponse
    {
        $document = Document::findOrFail($documentId);

        if ($document->lifecycle !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot record movement for a retired or tombstoned document.',
            ], 422);
        }

        $validated = $request->validate([
            'from_unit_id' => 'required|exists:organization_units,id',
            'to_unit_id' => 'required|exists:organization_units,id|different:from_unit_id',
            'instruction' => 'nullable|string|max:500',
            'due_at' => 'nullable|date|after:now',
        ]);

        $actor = $request->user();
        if ($actor && ! $actor->hasPermission('system.configure') && ! $actor->hasPermission('correspondence.forward', (int) $validated['from_unit_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to forward documents from the specified originating unit.',
            ], 403);
        }
        $now = now()->format('Y-m-d H:i:s.u');

        $movement = DB::transaction(function () use ($document, $validated, $actor, $now) {
            $movement = DocumentMovement::create([
                'document_id' => $document->id,
                'from_unit_id' => $validated['from_unit_id'],
                'to_unit_id' => $validated['to_unit_id'],
                'sent_by' => $actor->id,
                'sent_at' => $now,
                'instruction' => $validated['instruction'] ?? null,
                'due_at' => $validated['due_at'] ?? null,
                'idempotency_key' => (string) Str::uuid(),
            ]);

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'document.movement_sent',
                'target_type' => 'document_movement',
                'target_id' => (string) $movement->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => [
                    'document_id' => $document->id,
                    'from_unit_id' => $validated['from_unit_id'],
                    'to_unit_id' => $validated['to_unit_id'],
                ],
            ]);

            return $movement;
        });

        return response()->json([
            'success' => true,
            'message' => 'Document movement recorded successfully.',
            'data' => $movement->load(['fromUnit', 'toUnit', 'sentBy']),
        ], 201);
    }

    /**
     * Direct movement acknowledgment endpoint.
     */
    public function acknowledge(Request $request, int $id): JsonResponse
    {
        $movement = DocumentMovement::with('document')->findOrFail($id);
        $actor = $request->user();

        $now = now()->format('Y-m-d H:i:s.u');

        $receipt = DB::transaction(function () use ($movement, $actor, $now) {
            DocumentMovement::lockForUpdate()->findOrFail($movement->id);
            $existingReceipt = MovementReceipt::where('movement_id', $movement->id)->first();
            if ($existingReceipt) {
                return null;
            }

            $rcpt = MovementReceipt::create([
                'movement_id' => $movement->id,
                'acknowledged_by' => $actor->id,
                'acknowledged_at' => $now,
            ]);

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'document.movement_acknowledged',
                'target_type' => 'movement_receipt',
                'target_id' => (string) $movement->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => [
                    'document_id' => $movement->document_id,
                    'movement_id' => $movement->id,
                    'acknowledged_by' => $actor->id,
                ],
            ]);

            return $rcpt;
        });

        if (! $receipt) {
            return response()->json([
                'success' => false,
                'message' => 'Movement has already been acknowledged.',
                'data' => MovementReceipt::with('acknowledgedBy')->where('movement_id', $movement->id)->first(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Document movement custody receipt acknowledged successfully.',
            'data' => $receipt->load(['movement', 'acknowledgedBy']),
        ]);
    }
}
