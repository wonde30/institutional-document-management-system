<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizationUnitResource;
use App\Models\OrganizationUnit;
use App\Models\UnitType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizationUnitController extends Controller
{
    /**
     * List organization units.
     */
    public function index(Request $request): JsonResponse
    {
        $query = OrganizationUnit::with(['unitType', 'parent']);

        if ($request->has('active')) {
            $query->where('active', filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('unit_type_id')) {
            $query->where('unit_type_id', $request->query('unit_type_id'));
        }

        if ($request->filled('parent_id')) {
            $query->where('parent_id', $request->query('parent_id'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $units = $query->orderBy('name')->paginate($request->query('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => OrganizationUnitResource::collection($units),
            'meta' => [
                'current_page' => $units->currentPage(),
                'last_page' => $units->lastPage(),
                'total' => $units->total(),
            ],
        ]);
    }

    /**
     * Get hierarchical tree of organization units.
     */
    public function tree(): JsonResponse
    {
        $roots = OrganizationUnit::whereNull('parent_id')
            ->with(['children.children.children', 'unitType'])
            ->where('active', 1)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => OrganizationUnitResource::collection($roots),
        ]);
    }

    /**
     * Store new organization unit.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|exists:organization_units,id',
            'unit_type_id' => 'required|exists:unit_types,id',
            'code' => 'required|string|max:64|unique:organization_units,code',
            'name' => 'required|string|max:200',
            'active' => 'nullable|boolean',
        ]);

        $actor = $request->user();
        $parentId = isset($validated['parent_id']) ? (int) $validated['parent_id'] : null;
        if ($actor && ! $actor->hasPermission('system.configure')) {
            if ($parentId === null && ! $actor->hasPermission('organization.manage', null)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: Creating root organization units requires global organization.manage authority.',
                ], 403);
            }
            if ($parentId !== null && ! $actor->hasPermission('organization.manage', $parentId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: You do not have organization.manage authority for the specified parent unit.',
                ], 403);
            }
        }

        $unit = OrganizationUnit::create([
            'parent_id' => $validated['parent_id'] ?? null,
            'unit_type_id' => $validated['unit_type_id'],
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'active' => $validated['active'] ?? true,
        ]);

        $unit->load('unitType');

        return response()->json([
            'success' => true,
            'message' => 'Organization unit created successfully.',
            'data' => new OrganizationUnitResource($unit),
        ], 201);
    }

    /**
     * Show single unit details.
     */
    public function show(int $id): JsonResponse
    {
        $unit = OrganizationUnit::with(['unitType', 'parent', 'children'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new OrganizationUnitResource($unit),
        ]);
    }

    /**
     * Update organization unit.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $unit = OrganizationUnit::findOrFail($id);

        $validated = $request->validate([
            'parent_id' => 'nullable|exists:organization_units,id|not_in:'.$id,
            'unit_type_id' => 'sometimes|required|exists:unit_types,id',
            'name' => 'sometimes|required|string|max:200',
            'active' => 'sometimes|boolean',
        ]);

        // NOTE: Cycle prevention: an organizational unit cannot have its own descendant as a parent.
        if (! empty($validated['parent_id']) && in_array((int) $validated['parent_id'], $unit->getDescendantIds(), true)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot set unit parent to one of its own descendants (cyclic relationship).',
                'errors' => [
                    'parent_id' => ['The selected parent unit is a descendant of this unit.'],
                ],
            ], 422);
        }

        $oldActive = (bool) $unit->active;
        $oldParentId = $unit->parent_id;
        $unit->update($validated);
        $unit->load('unitType');

        if ($oldActive !== (bool) $unit->active || $oldParentId !== $unit->parent_id) {
            $affectedUnitIds = array_values(array_unique(array_merge([$unit->id], $unit->getDescendantIds())));
            $scopeIds = \App\Models\AuthorizationScope::whereIn('unit_id', $affectedUnitIds)->pluck('id')->all();
            if (! empty($scopeIds)) {
                $roleUids = DB::table('user_roles')->whereIn('scope_id', $scopeIds)->pluck('user_id')->all();
                $permUids = DB::table('user_permissions')->whereIn('scope_id', $scopeIds)->pluck('user_id')->all();
                $affectedUsers = array_values(array_unique(array_merge($roleUids, $permUids)));
                if (! empty($affectedUsers)) {
                    \App\Models\User::whereIn('id', $affectedUsers)->increment('authz_version');
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Organization unit updated successfully.',
            'data' => new OrganizationUnitResource($unit),
        ]);
    }

    /**
     * Delete organization unit (leaf units without dependent records only).
     */
    public function destroy(int $id): JsonResponse
    {
        return DB::transaction(function () use ($id) {
            $unit = OrganizationUnit::where('id', $id)->lockForUpdate()->firstOrFail();

            if ($unit->children()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete an organizational unit that contains child units.',
                ], 422);
            }

            if ($unit->documents()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete an organizational unit that owns registered documents.',
                ], 422);
            }

            if ($unit->memberships()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete an organizational unit that has assigned staff members.',
                ], 422);
            }

            if (DB::table('authorization_scopes')->where('unit_id', $id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete an organizational unit associated with active authorization scopes.',
                ], 422);
            }

            if (DB::table('workflow_tasks')->where('assigned_unit_id', $id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete an organizational unit with assigned workflow tasks.',
                ], 422);
            }

            if (DB::table('office_procedure_versions')->where('unit_id', $id)->orWhere('registry_unit_id', $id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete an organizational unit referenced in office procedure versions.',
                ], 422);
            }

            if (DB::table('correspondences')->where('first_receiving_unit_id', $id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete an organizational unit referenced in the correspondence registry.',
                ], 422);
            }

            if (DB::table('communication_parties')->where('unit_id', $id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete an organizational unit referenced as a correspondence communication party.',
                ], 422);
            }

            if (DB::table('document_movements')->where('from_unit_id', $id)->orWhere('to_unit_id', $id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete an organizational unit with recorded document movements.',
                ], 422);
            }

            if (DB::table('document_assignments')->where('assigned_unit_id', $id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete an organizational unit with assigned documents.',
                ], 422);
            }

            if (DB::table('staff_employment_records')->where('unit_id', $id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete an organizational unit associated with staff employment records.',
                ], 422);
            }

            if (DB::table('physical_storage_locations')->where('unit_id', $id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete an organizational unit associated with physical storage locations.',
                ], 422);
            }

            if (DB::table('accreditation_cycles')->where('unit_id', $id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete an organizational unit associated with accreditation cycles.',
                ], 422);
            }

            if (DB::table('stamp_definitions')->where('unit_id', $id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete an organizational unit that has registered official stamp definitions.',
                ], 422);
            }

            if (DB::table('routing_policies')->where(fn ($q) => $q->where('from_unit_id', $id)->orWhere('to_unit_id', $id)->orWhere('escalation_unit_id', $id))->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete an organizational unit configured in active routing policies.',
                ], 422);
            }

            if (DB::table('document_grants')->where('unit_id', $id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete an organizational unit with active document grants.',
                ], 422);
            }

            if (DB::table('document_templates')->where('owner_unit_id', $id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete an organizational unit that owns document templates.',
                ], 422);
            }

            if (DB::table('workflow_states')->where('assigned_unit_id', $id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete an organizational unit assigned to workflow states.',
                ], 422);
            }

            try {
                $unit->delete();
            } catch (\Illuminate\Database\QueryException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete organizational unit due to existing database dependencies.',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Organization unit deleted successfully.',
            ]);
        });
    }

    /**
     * List all unit types.
     */
    public function unitTypes(): JsonResponse
    {
        $types = UnitType::where('active', 1)->orderBy('id')->get();

        return response()->json([
            'success' => true,
            'data' => $types,
        ]);
    }
}
