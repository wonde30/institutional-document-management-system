<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ConfidentialityLevel;
use App\Models\CorrespondenceType;
use App\Models\DispatchMethod;
use App\Models\DocumentCategory;
use App\Models\DocumentRelationType;
use App\Models\HrRecordType;
use App\Models\Institution;
use App\Models\Priority;
use App\Models\ProjectType;
use App\Models\QualificationType;
use App\Models\Tag;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MetadataController extends Controller
{
    /**
     * Map vocabulary technical keys to model classes.
     */
    protected array $vocabularies = [
        'categories' => DocumentCategory::class,
        'confidentiality-levels' => ConfidentialityLevel::class,
        'correspondence-types' => CorrespondenceType::class,
        'priorities' => Priority::class,
        'dispatch-methods' => DispatchMethod::class,
        'relation-types' => DocumentRelationType::class,
    ];

    /**
     * Get all active reference vocabularies in a single request.
     */
    public function all(Request $request): JsonResponse
    {
        $includeInactive = $request->boolean('include_inactive');

        $scopeQuery = fn ($model) => $includeInactive ? $model::query() : $model::where('active', 1);

        return response()->json([
            'success' => true,
            'data' => [
                'document_categories' => $scopeQuery(DocumentCategory::class)->orderBy('name')->get(),
                'confidentiality_levels' => $scopeQuery(ConfidentialityLevel::class)->orderBy('id')->get(),
                'correspondence_types' => $scopeQuery(CorrespondenceType::class)->orderBy('name')->get(),
                'priorities' => $scopeQuery(Priority::class)->orderBy('id')->get(),
                'dispatch_methods' => $scopeQuery(DispatchMethod::class)->orderBy('name')->get(),
                'document_relation_types' => $scopeQuery(DocumentRelationType::class)->orderBy('name')->get(),
                'tags' => Tag::orderBy('display_name')->get(),
                'institutions' => $scopeQuery(Institution::class)->orderBy('name')->get(),
                'qualification_types' => $scopeQuery(QualificationType::class)->orderBy('name')->get(),
                'hr_record_types' => $scopeQuery(HrRecordType::class)->orderBy('name')->get(),
                'project_types' => $scopeQuery(ProjectType::class)->orderBy('name')->get(),
            ],
        ]);
    }

    public function categories(Request $request): JsonResponse
    {
        $query = DocumentCategory::query();
        if (! $request->boolean('include_inactive')) {
            $query->where('active', 1);
        }
        return response()->json([
            'success' => true,
            'data' => $query->orderBy('name')->get(),
        ]);
    }

    public function confidentialityLevels(Request $request): JsonResponse
    {
        $query = ConfidentialityLevel::query();
        if (! $request->boolean('include_inactive')) {
            $query->where('active', 1);
        }
        return response()->json([
            'success' => true,
            'data' => $query->orderBy('id')->get(),
        ]);
    }

    public function correspondenceTypes(Request $request): JsonResponse
    {
        $query = CorrespondenceType::query();
        if (! $request->boolean('include_inactive')) {
            $query->where('active', 1);
        }
        return response()->json([
            'success' => true,
            'data' => $query->orderBy('name')->get(),
        ]);
    }

    public function priorities(Request $request): JsonResponse
    {
        $query = Priority::query();
        if (! $request->boolean('include_inactive')) {
            $query->where('active', 1);
        }
        return response()->json([
            'success' => true,
            'data' => $query->orderBy('id')->get(),
        ]);
    }

    public function dispatchMethods(Request $request): JsonResponse
    {
        $query = DispatchMethod::query();
        if (! $request->boolean('include_inactive')) {
            $query->where('active', 1);
        }
        return response()->json([
            'success' => true,
            'data' => $query->orderBy('name')->get(),
        ]);
    }

    /**
     * Store new vocabulary reference item (guarded by system.configure).
     */
    public function storeItem(Request $request, string $type): JsonResponse
    {
        if (! isset($this->vocabularies[$type])) {
            return response()->json([
                'success' => false,
                'message' => "Invalid reference catalog type '{$type}'.",
            ], 404);
        }

        $modelClass = $this->vocabularies[$type];
        $tableName = (new $modelClass)->getTable();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/', Rule::unique($tableName, 'code')],
            'name' => 'required|string|max:160',
            'description' => 'nullable|string',
            'active' => 'nullable|boolean',
        ]);

        $actor = $request->user();
        $item = $modelClass::create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'active' => $validated['active'] ?? true,
        ]);

        AuditService::recordAuthEvent(
            action: 'metadata.item_created',
            actorId: $actor ? $actor->id : null,
            targetType: $tableName,
            targetId: (string) $item->id,
            details: [
                'type' => $type,
                'code' => $item->code,
                'name' => $item->name,
            ],
            request: $request
        );

        return response()->json([
            'success' => true,
            'message' => 'Reference vocabulary item created successfully.',
            'data' => $item,
        ], 201);
    }

    /**
     * Update existing vocabulary item (guarded by system.configure).
     * Note: Technical code is immutable to preserve historical integrity across documents and workflows.
     */
    public function updateItem(Request $request, string $type, int $id): JsonResponse
    {
        if (! isset($this->vocabularies[$type])) {
            return response()->json([
                'success' => false,
                'message' => "Invalid reference catalog type '{$type}'.",
            ], 404);
        }

        $modelClass = $this->vocabularies[$type];
        $item = $modelClass::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:160',
            'description' => 'nullable|string',
            'active' => 'sometimes|boolean',
        ]);

        $item->update($validated);

        $actor = $request->user();
        AuditService::recordAuthEvent(
            action: 'metadata.item_updated',
            actorId: $actor ? $actor->id : null,
            targetType: $item->getTable(),
            targetId: (string) $item->id,
            details: [
                'type' => $type,
                'code' => $item->code,
                'changes' => $validated,
            ],
            request: $request
        );

        return response()->json([
            'success' => true,
            'message' => 'Reference vocabulary item updated successfully.',
            'data' => $item,
        ]);
    }
}
