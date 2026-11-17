<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\RoutingPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoutingPolicyController extends Controller
{
    /**
     * List all organizational routing policies.
     */
    public function index(Request $request): JsonResponse
    {
        $policies = RoutingPolicy::with([
            'fromUnit:id,code,name',
            'toUnit:id,code,name',
            'correspondenceType:id,code,name',
            'documentCategory:id,code,name',
            'priority:id,code,name',
            'defaultAssigneeRole:id,code,name',
            'escalationUnit:id,code,name',
        ])
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $policies,
        ]);
    }

    /**
     * Store a new organizational routing policy.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:32|unique:routing_policies,code',
            'name' => 'required|string|max:120',
            'from_unit_id' => 'nullable|exists:organization_units,id',
            'to_unit_id' => 'required|exists:organization_units,id',
            'correspondence_type_id' => 'nullable|exists:correspondence_types,id',
            'document_category_id' => 'nullable|exists:document_categories,id',
            'priority_id' => 'nullable|exists:priorities,id',
            'default_assignee_role_id' => 'nullable|exists:roles,id',
            'sla_hours' => 'required|integer|min:1',
            'escalation_unit_id' => 'nullable|exists:organization_units,id',
            'active' => 'nullable|boolean',
            'description' => 'nullable|string|max:1000',
        ]);

        $actor = $request->user();
        $now = now()->format('Y-m-d H:i:s.u');

        $policy = RoutingPolicy::create([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'from_unit_id' => $validated['from_unit_id'] ?? null,
            'to_unit_id' => $validated['to_unit_id'],
            'correspondence_type_id' => $validated['correspondence_type_id'] ?? null,
            'document_category_id' => $validated['document_category_id'] ?? null,
            'priority_id' => $validated['priority_id'] ?? null,
            'default_assignee_role_id' => $validated['default_assignee_role_id'] ?? null,
            'sla_hours' => (int) $validated['sla_hours'],
            'escalation_unit_id' => $validated['escalation_unit_id'] ?? null,
            'active' => (bool) ($validated['active'] ?? true),
            'description' => $validated['description'] ?? null,
        ]);

        AuditEvent::create([
            'actor_id' => $actor->id,
            'action' => 'routing_policy.created',
            'target_type' => 'routing_policy',
            'target_id' => (string) $policy->id,
            'request_id' => (string) Str::uuid(),
            'occurred_at' => $now,
            'details_json' => $validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Routing policy created.',
            'data' => $policy->load([
                'fromUnit', 'toUnit', 'correspondenceType', 'documentCategory', 'priority', 'defaultAssigneeRole', 'escalationUnit',
            ]),
        ], 201);
    }

    /**
     * Show a routing policy.
     */
    public function show(int $id): JsonResponse
    {
        $policy = RoutingPolicy::with([
            'fromUnit', 'toUnit', 'correspondenceType', 'documentCategory', 'priority', 'defaultAssigneeRole', 'escalationUnit',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $policy,
        ]);
    }

    /**
     * Update an existing routing policy.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $policy = RoutingPolicy::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'from_unit_id' => 'nullable|exists:organization_units,id',
            'to_unit_id' => 'required|exists:organization_units,id',
            'correspondence_type_id' => 'nullable|exists:correspondence_types,id',
            'document_category_id' => 'nullable|exists:document_categories,id',
            'priority_id' => 'nullable|exists:priorities,id',
            'default_assignee_role_id' => 'nullable|exists:roles,id',
            'sla_hours' => 'required|integer|min:1',
            'escalation_unit_id' => 'nullable|exists:organization_units,id',
            'active' => 'required|boolean',
            'description' => 'nullable|string|max:1000',
        ]);

        $policy->update([
            'name' => $validated['name'],
            'from_unit_id' => $validated['from_unit_id'] ?? null,
            'to_unit_id' => $validated['to_unit_id'],
            'correspondence_type_id' => $validated['correspondence_type_id'] ?? null,
            'document_category_id' => $validated['document_category_id'] ?? null,
            'priority_id' => $validated['priority_id'] ?? null,
            'default_assignee_role_id' => $validated['default_assignee_role_id'] ?? null,
            'sla_hours' => (int) $validated['sla_hours'],
            'escalation_unit_id' => $validated['escalation_unit_id'] ?? null,
            'active' => (bool) $validated['active'],
            'description' => $validated['description'] ?? null,
        ]);

        AuditEvent::create([
            'actor_id' => $request->user()->id,
            'action' => 'routing_policy.updated',
            'target_type' => 'routing_policy',
            'target_id' => (string) $policy->id,
            'request_id' => (string) Str::uuid(),
            'occurred_at' => now()->format('Y-m-d H:i:s.u'),
            'details_json' => $validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Routing policy updated.',
            'data' => $policy->load([
                'fromUnit', 'toUnit', 'correspondenceType', 'documentCategory', 'priority', 'defaultAssigneeRole', 'escalationUnit',
            ]),
        ]);
    }

    /**
     * Delete a routing policy.
     */
    public function destroy(int $id): JsonResponse
    {
        $policy = RoutingPolicy::findOrFail($id);
        $policy->delete();

        return response()->json([
            'success' => true,
            'message' => 'Routing policy deleted.',
        ]);
    }

    /**
     * Match the most specific active routing policy for given context attributes.
     */
    public function match(Request $request): JsonResponse
    {
        $fromUnitId = $request->input('from_unit_id');
        $corrTypeId = $request->input('correspondence_type_id');
        $docCatId = $request->input('document_category_id');
        $priorityId = $request->input('priority_id');

        $query = RoutingPolicy::with([
            'fromUnit:id,code,name',
            'toUnit:id,code,name',
            'defaultAssigneeRole:id,code,name',
            'escalationUnit:id,code,name',
        ])->where('active', 1);

        if ($fromUnitId) {
            $query->where(function ($q) use ($fromUnitId) {
                $q->where('from_unit_id', $fromUnitId)->orWhereNull('from_unit_id');
            });
        }
        if ($corrTypeId) {
            $query->where(function ($q) use ($corrTypeId) {
                $q->where('correspondence_type_id', $corrTypeId)->orWhereNull('correspondence_type_id');
            });
        }
        if ($docCatId) {
            $query->where(function ($q) use ($docCatId) {
                $q->where('document_category_id', $docCatId)->orWhereNull('document_category_id');
            });
        }
        if ($priorityId) {
            $query->where(function ($q) use ($priorityId) {
                $q->where('priority_id', $priorityId)->orWhereNull('priority_id');
            });
        }

        // Score matches by specificity
        $policies = $query->get()->sortByDesc(function ($p) use ($fromUnitId, $corrTypeId, $docCatId, $priorityId) {
            $score = 0;
            if ($p->from_unit_id && $p->from_unit_id == $fromUnitId) {
                $score += 8;
            }
            if ($p->correspondence_type_id && $p->correspondence_type_id == $corrTypeId) {
                $score += 4;
            }
            if ($p->document_category_id && $p->document_category_id == $docCatId) {
                $score += 2;
            }
            if ($p->priority_id && $p->priority_id == $priorityId) {
                $score += 1;
            }

            return $score;
        });

        $matched = $policies->first();

        return response()->json([
            'success' => true,
            'data' => $matched,
            'has_match' => $matched !== null,
        ]);
    }
}
