<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Models\TemplateVersion;
use App\Services\TemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function __construct(
        protected TemplateService $templateService
    ) {}

    /**
     * List document templates.
     */
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $query = DocumentTemplate::with(['category', 'ownerUnit', 'versions', 'publishedVersion'])
            ->where('is_active', 1);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }

        if ($request->filled('owner_unit_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('owner_unit_id', $request->query('owner_unit_id'))
                  ->orWhereNull('owner_unit_id');
            });
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $templates = $query->orderBy('name')->paginate($request->query('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $templates->items(),
            'meta' => [
                'current_page' => $templates->currentPage(),
                'last_page' => $templates->lastPage(),
                'total' => $templates->total(),
            ],
        ]);
    }

    /**
     * Store new document template.
     */
    public function store(Request $request): JsonResponse
    {
        if ($request->has('active') && ! $request->has('is_active')) {
            $request->merge(['is_active' => $request->boolean('active')]);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:64|unique:document_templates,code',
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:document_categories,id',
            'owner_unit_id' => 'nullable|exists:organization_units,id',
            'visibility_scope' => 'nullable|in:global,unit_restricted',
            'is_active' => 'nullable|boolean',
        ]);

        $template = $this->templateService->createTemplate($validated, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Document template created successfully.',
            'data' => $template->load(['category', 'ownerUnit']),
        ], 201);
    }

    /**
     * Show template with its versions.
     */
    public function show(int $id): JsonResponse
    {
        $template = DocumentTemplate::with(['category', 'ownerUnit', 'versions.createdBy'])->findOrFail($id);

        if ($template->visibility_scope === 'unit_restricted' && $template->owner_unit_id !== null) {
            $user = request()->user();
            if ($user && ! $user->hasRole('super_admin') && ! $user->hasPermission('documents.view', (int) $template->owner_unit_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: This template is restricted to its owner organization unit.',
                ], 403);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $template,
        ]);
    }

    /**
     * Create a draft template version.
     */
    public function storeVersion(Request $request, int $id): JsonResponse
    {
        $template = DocumentTemplate::findOrFail($id);

        if ($template->owner_unit_id !== null) {
            $user = $request->user();
            if ($user && ! $user->hasRole('super_admin') && ! $user->hasPermission('documents.create', (int) $template->owner_unit_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: You do not have permission to create template versions for this organization unit.',
                ], 403);
            }
        }

        if ($request->filled('template_content') && ! $request->filled('body_template_html')) {
            $request->merge(['body_template_html' => $request->input('template_content')]);
        }

        $validated = $request->validate([
            'schema_definition' => 'required|array',
            'body_template_html' => 'required|string',
            'header_asset_key' => 'nullable|string|max:255',
            'footer_asset_key' => 'nullable|string|max:255',
        ]);

        $version = $this->templateService->createVersion($template, $validated, $request->user());

        return response()->json([
            'success' => true,
            'message' => "Draft template version {$version->version_number} created.",
            'data' => $version,
        ], 201);
    }

    /**
     * Publish a template version.
     */
    public function publishVersion(Request $request, int $id, int $versionId): JsonResponse
    {
        $template = DocumentTemplate::findOrFail($id);
        $version = TemplateVersion::where('template_id', $template->id)->findOrFail($versionId);

        $published = $this->templateService->publishVersion($version, $request->user());

        return response()->json([
            'success' => true,
            'message' => "Template version {$published->version_number} published successfully.",
            'data' => $published,
        ]);
    }

    /**
     * Render and generate a new repository Document from published template.
     */
    public function render(Request $request, int $id): JsonResponse
    {
        $template = DocumentTemplate::findOrFail($id);

        $versionId = $request->input('template_version_id');
        $version = $versionId
            ? TemplateVersion::where('template_id', $template->id)->findOrFail($versionId)
            : TemplateVersion::where('template_id', $template->id)->where('status', 'published')->orderByDesc('version_number')->firstOrFail();

        $validated = $request->validate([
            'template_data' => 'required|array',
            'title' => 'required|string|max:500',
            'summary' => 'nullable|string',
            'category_id' => 'required|exists:document_categories,id',
            'confidentiality_id' => 'required|exists:confidentiality_levels,id',
            'owner_unit_id' => 'required|exists:organization_units,id',
            'document_date' => 'nullable|date',
        ]);

        if (! $request->user()->hasPermission('documents.create', (int) $validated['owner_unit_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to generate documents for the specified organization unit.',
            ], 403);
        }

        $doc = $this->templateService->generateDocument(
            $version,
            $validated['template_data'],
            [
                'title' => $validated['title'],
                'summary' => $validated['summary'] ?? null,
                'category_id' => $validated['category_id'],
                'confidentiality_id' => $validated['confidentiality_id'],
                'owner_unit_id' => $validated['owner_unit_id'],
                'document_date' => $validated['document_date'] ?? now()->toDateString(),
            ],
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Document generated successfully from institutional template.',
            'data' => $doc->load(['versions.versionFiles.fileObject', 'ownerUnit', 'category']),
        ], 201);
    }
}
