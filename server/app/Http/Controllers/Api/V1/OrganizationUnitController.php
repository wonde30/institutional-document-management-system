<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizationUnitResource;
use App\Models\OrganizationUnit;
use App\Models\UnitType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $unit->update($validated);
        $unit->load('unitType');

        return response()->json([
            'success' => true,
            'message' => 'Organization unit updated successfully.',
            'data' => new OrganizationUnitResource($unit),
        ]);
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
