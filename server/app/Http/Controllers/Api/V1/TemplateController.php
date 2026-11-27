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

        if ($actor && ! $actor->hasPermission('system.configure')) {
            $permittedUnits = $actor->getPermittedUnitIds('documents.view');
            if ($permittedUnits !== null) {
                $query->where(function ($q) use ($permittedUnits) {
                    $q->where('visibility_scope', 'global')
                      ->orWhereNull('owner_unit_id')
                      ->orWhereIn('owner_unit_id', $permittedUnits ?: [0]);
                });
            }
        }

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

        $actor = $request->user();
        if ($actor && ! $actor->hasPermission('system.configure')) {
            if (empty($validated['owner_unit_id']) && ! $actor->hasPermission('documents.create', null)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: Creating global document templates requires global documents.create or system.configure authority.',
                ], 403);
            }
        }

        $template = $this->templateService->createTemplate($validated, $actor);

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
        $template = DocumentTemplate::with(['category', 'ownerUnit', 'versions.creator'])->findOrFail($id);

        if ($template->visibility_scope === 'unit_restricted' && $template->owner_unit_id !== null) {
            $user = request()->user();
            if ($user && ! $user->hasPermission('system.configure') && ! $user->hasPermission('documents.view', (int) $template->owner_unit_id)) {
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
        $user = $request->user();

        if ($user && ! $user->hasPermission('system.configure')) {
            if ($template->owner_unit_id === null && ! $user->hasPermission('documents.create', null)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: Creating versions on global templates requires global documents.create or system.configure authority.',
                ], 403);
            }
            if ($template->owner_unit_id !== null && ! $user->hasPermission('documents.create', (int) $template->owner_unit_id)) {
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
        $user = $request->user();

        if ($user && ! $user->hasPermission('system.configure')) {
            if ($template->owner_unit_id === null && ! $user->hasPermission('documents.create', null)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: Publishing global template versions requires global documents.create or system.configure authority.',
                ], 403);
            }
            if ($template->owner_unit_id !== null && ! $user->hasPermission('documents.create', (int) $template->owner_unit_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: You do not have permission to publish versions for this organization unit template.',
                ], 403);
            }
        }

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

        if ($template->visibility_scope === 'unit_restricted' && $template->owner_unit_id !== null) {
            $actor = $request->user();
            if ($actor && ! $actor->hasPermission('system.configure') && ! $actor->hasPermission('documents.create', (int) $template->owner_unit_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: This template is restricted to its owner organization unit.',
                ], 403);
            }
        }

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

        // Prevent signature/stamp forgery by stripping reserved verification context keys from caller-supplied template_data
        $safeTemplateData = $validated['template_data'];
        unset(
            $safeTemplateData['document_version_id'],
            $safeTemplateData['document_version'],
            $safeTemplateData['digital_signature'],
            $safeTemplateData['reference_number']
        );

        $standardWf = null;
        if ($request->boolean('submit_for_approval')) {
            $standardWf = \Illuminate\Support\Facades\DB::table('workflow_definitions')->where('code', 'standard_doc_approval')->first();
            $hasVersion = $standardWf
                ? \Illuminate\Support\Facades\DB::table('workflow_versions')
                    ->where('definition_id', $standardWf->id)
                    ->whereNotNull('published_at')
                    ->exists()
                : false;

            if (! $standardWf || ! $hasVersion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot submit for approval: no published standard_doc_approval workflow version is configured.',
                ], 422);
            }
        }

        $doc = \Illuminate\Support\Facades\DB::transaction(function () use ($version, $safeTemplateData, $validated, $request, $standardWf) {
            $doc = $this->templateService->generateDocument(
                $version,
                $safeTemplateData,
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

            if ($request->boolean('submit_for_approval') && $standardWf) {
                $initialVersion = $doc->versions()->firstOrFail();
                $workflowEngine = app(\App\Services\WorkflowEngineService::class);
                $workflowEngine->startInstance(
                    (int) $standardWf->id,
                    (int) $initialVersion->id,
                    null,
                    $request->user()
                );
            }

            return $doc;
        });

        return response()->json([
            'success' => true,
            'message' => 'Document generated successfully from institutional template.',
            'data' => $doc->load(['versions.versionFiles.fileObject', 'ownerUnit', 'category']),
        ], 201);
    }

    /**
     * Get baseline configuration and sample presets for official university letter generation.
     */
    public function baselineConfig(Request $request): JsonResponse
    {
        $actor = $request->user();
        $requestedUnitId = $request->query('unit_id');
        if ($requestedUnitId !== null && $requestedUnitId !== '') {
            $preferredUnitId = (int) $requestedUnitId;
        } else {
            $contextService = app(\App\Services\Authorization\OrganizationalContextService::class);
            $preferredUnitId = $actor ? $contextService->resolveActiveUnitId($actor, $request) : null;
            if ($preferredUnitId === null && $actor) {
                $permittedUnits = $actor->getPermittedUnitIds('documents.create');
                if (is_array($permittedUnits) && ! empty($permittedUnits)) {
                    $preferredUnitId = (int) $permittedUnits[0];
                } else {
                    $preferredUnitId = $actor->getPrimaryUnitId();
                }
            }
        }
        $unit = $preferredUnitId !== null
            ? \App\Models\OrganizationUnit::with('parent')->find($preferredUnitId)
            : null;

        $facultyAm = 'ኮምቦልቻ ቴክኖሎጂ ኢንስቲትዩት';
        $facultyEn = 'KOMBOLCHA INSTITUTE OF TECHNOLOGY';
        $deptAm = 'ኢንፎርሜሽን ቴክኖሎጂ ትምህርት ክፍል';
        $deptEn = 'Information Technology Department';
        $locationAm = 'ኮምቦልቻ';
        $locationEn = 'Kombolcha';

        if ($unit) {
            $deptEn = $unit->name;
            if ($unit->parent) {
                $facultyEn = $unit->parent->name;
            }
        }

        $commonCcOffices = [
            'ለምክትል ሳይንቲፊክ ዳይሬክተር ጽ/ቤት',
            'ለተማሪዎች ጉዳይ ዳይሬክተር ጽ/ቤት',
            'ለሬጅስትራር ጽ/ቤት',
            'ለኢንፎርማቲክስ ኮሌጅ',
            'ለኢንፎርማቲክስ ኮሌጅ ት/ት ጥራት አስተባባሪ ጽ/ቤት',
            'ለኢንፎርማቲክስ ኮሌጅ ምርምር፤ ማህበረሰብ አገልግሎትና ድህረ-ምረቃ ጽ/ቤት',
            'ለርቀትና ተከታታይ ጽ/ቤት',
            'ለኢንፎርሜሽን ቴክኖሎጂ ት/ት ክፍል',
        ];

        $samples = [
            [
                'id' => 'sample-course-offering',
                'name' => 'Reference 1: የ2019 ዓ.ም Course Offering መላክ (Course Offering Dispatch)',
                'ref_no' => 'KIOT/W.U.IT/737/2018',
                'date' => '2026-08-26',
                'ethiopian_date' => '20/12/2018 ዓ.ም',
                'recipient_lead' => 'ለኢንፎርማቲክስ ኮሌጅ',
                'recipient_sub' => '',
                'recipient_location' => 'ኮምቦልቻ',
                'subject' => 'የ2019 ዓ.ም Course offering መላክን ይመለከታል',
                'body' => "ከላይ በርዕሱ ለመጥቀስ እንደተሞከረው በኢንፎርማቲክስ ኮሌጅ ስር በኢንፎርሜሽን ቴክኖሎጂ ትምህርት ክፍል የመደበኛ የ2019 ዓ.ም 1ኛ ወሳነ ትምህርት ኮርስ ምደባ የሰራን መሆኑን እየገለጽኩ መረጃውን ከዚህ ሸኝ ደብዳቤ ጋር 02 ገጽ አባሪ አድርገን የላክን መሆኑን አሳውቃለሁ።",
                'cc_list' => [
                    'ለኢንፎርሜሽን ቴክኖሎጂ ት/ት ክፍል',
                    'ለኢንፎርማቲክስ ኮሌጅ ትምህርት ጥራት ጽ/ቤት',
                ],
                'signatory_name' => 'Tibebu Legesse Molla',
                'signatory_title_am' => 'የኢንፎርሜሽን ቴክኖሎጂ ትምህርት ክፍል ኃላፊ',
                'signatory_title_en' => 'Head Department of Information Technology',
            ],
            [
                'id' => 'sample-delegation',
                'name' => 'Reference 2: ውክልና መስጠት (Delegation of Authority)',
                'ref_no' => 'KIOT/W.U.IT/746/2019',
                'date' => '2026-09-28',
                'ethiopian_date' => '18/01/2019 ዓ.ም',
                'recipient_lead' => 'ለመ/ር መላክ ተሾመ',
                'recipient_sub' => 'ኢንፎርሜሽን ቴክኖሎጂ ት/ት ክፍል',
                'recipient_location' => 'ኮምቦልቻ',
                'subject' => 'ውክልና መስጠትን ይመለከታል',
                'body' => "ከላይ በርዕሱ ለመጥቀስ እንደተሞከረው ከቀን 19/01/2019 ዓ.ም ጀምሮ በግል ጉዳዬ በስራ ገበታዬ ላይ ስላማልኖር እርሶ በእኔ ተክተው የት/ት ክፍሉን ስራ እንዲያከናውኑ ሙሉ ውክልና የተሰጠዎት መሆኑን በትህትና እያሳወቅን ግልባጭ የተደረገላችሁ የስራ ክፍሎች አስፈላጊውን ትብብር እንድታደርጉላቸው እንጠይቃለን።",
                'cc_list' => [
                    'ለምክትል ሳይንቲፊክ ዳይሬክተር ጽ/ቤት',
                    'ለተማሪዎች ጉዳይ ዳይሬክተር ጽ/ቤት',
                    'ለሬጅስትራር ጽ/ቤት',
                    'ለኢንፎርማቲክስ ኮሌጅ',
                    'ለኢንፎርማቲክስ ኮሌጅ ት/ት ጥራት አስተባባሪ ጽ/ቤት',
                    'ለኢንፎርማቲክስ ኮሌጅ ምርምር፤ ማህበረሰብ አገልግሎትና ድህረ-ምረቃ ጽ/ቤት',
                    'ለርቀትና ተከታታይ ጽ/ቤት',
                    'ለኢንፎርሜሽን ቴክኖሎጂ ት/ት ክፍል',
                ],
                'signatory_name' => 'Tibebu Legesse Molla',
                'signatory_title_am' => 'የኢንፎርሜሽን ቴክኖሎጂ ትምህርት ክፍል ኃላፊ',
                'signatory_title_en' => 'Head Department of Information Technology',
            ],
        ];

        $uniAm = 'ወሎ ዩኒቨርሲቲ';
        $uniEn = 'WOLLO UNIVERSITY';
        try {
            $settings = app(\App\Services\Settings\SettingsService::class);
            $uniAm = (string) ($settings->effectiveValue('institution.name_am') ?? $uniAm);
            $rawUniEn = $settings->effectiveValue('institution.display_name');
            $uniEn = $rawUniEn ? strtoupper((string) $rawUniEn) : $uniEn;
        } catch (\Throwable) {
            // Default institutional names
        }

        return response()->json([
            'success' => true,
            'data' => [
                'unit_id' => $preferredUnitId,
                'uni_am' => $uniAm,
                'uni_en' => $uniEn,
                'faculty_am' => $facultyAm,
                'faculty_en' => $facultyEn,
                'dept_am' => $deptAm,
                'dept_en' => $deptEn,
                'location_am' => $locationAm,
                'location_en' => $locationEn,
                'common_cc_offices' => $commonCcOffices,
                'samples' => $samples,
            ],
        ]);
    }

    /**
     * Preview an official institutional letter PDF without persisting.
     */
    public function previewOfficialLetter(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:500',
            'body' => 'required|string',
            'recipient_lead' => 'nullable|string|max:255',
            'recipient_name' => 'nullable|string|max:255',
            'recipient_sub' => 'nullable|string|max:255',
            'recipient_office' => 'nullable|string|max:255',
            'recipient_location' => 'nullable|string|max:255',
            'cc_list' => 'nullable|array',
            'signatory_name' => 'nullable|string|max:255',
            'signatory_title_am' => 'nullable|string|max:255',
            'signatory_title_en' => 'nullable|string|max:255',
            'document_date' => 'nullable|date',
            'ethiopian_date' => 'nullable|string|max:100',
            'reference_number' => 'nullable|string|max:100',
            'has_signature' => 'nullable|boolean',
            'has_stamp' => 'nullable|boolean',
        ]);

        $pdfBinary = $this->templateService->renderOfficialLetterPdf(
            $validated,
            $validated['reference_number'] ?? 'WU/DOC/PREVIEW'
        );

        return response()->json([
            'success' => true,
            'data' => [
                'pdf_base64' => base64_encode($pdfBinary),
                'byte_size' => strlen($pdfBinary),
                'mime_type' => 'application/pdf',
            ],
        ]);
    }

    /**
     * Store and register an official institutional letter document in the repository.
     */
    public function generateOfficialLetter(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:500',
            'body' => 'required|string',
            'recipient_lead' => 'nullable|string|max:255',
            'recipient_name' => 'nullable|string|max:255',
            'recipient_sub' => 'nullable|string|max:255',
            'recipient_office' => 'nullable|string|max:255',
            'recipient_location' => 'nullable|string|max:255',
            'cc_list' => 'nullable|array',
            'signatory_name' => 'nullable|string|max:255',
            'signatory_title_am' => 'nullable|string|max:255',
            'signatory_title_en' => 'nullable|string|max:255',
            'document_date' => 'nullable|date',
            'ethiopian_date' => 'nullable|string|max:100',
            'owner_unit_id' => 'required|exists:organization_units,id',
            'category_id' => 'nullable|exists:document_categories,id',
            'confidentiality_id' => 'nullable|exists:confidentiality_levels,id',
            'submit_for_approval' => 'nullable|boolean',
            'has_signature' => 'nullable|boolean',
            'has_stamp' => 'nullable|boolean',
        ]);

        $actor = $request->user();
        $ownerUnitId = (int) $validated['owner_unit_id'];

        if (! $actor->hasPermission('documents.create', $ownerUnitId)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to generate documents for the specified organization unit.',
            ], 403);
        }

        $letterData = [
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'recipient_lead' => $validated['recipient_lead'] ?? ($validated['recipient_name'] ?? 'ለኢንፎርማቲክስ ኮሌጅ'),
            'recipient_sub' => $validated['recipient_sub'] ?? ($validated['recipient_office'] ?? ''),
            'recipient_location' => $validated['recipient_location'] ?? 'ኮምቦልቻ፤',
            'cc_list' => $validated['cc_list'] ?? [],
            'signatory_name' => $validated['signatory_name'] ?? 'Tibebu Legesse Molla',
            'signatory_title_am' => $validated['signatory_title_am'] ?? 'የኢንፎርሜሽን ቴክኖሎጂ ትምህርት ክፍል ኃላፊ',
            'signatory_title_en' => $validated['signatory_title_en'] ?? 'Head Department of Information Technology',
            'document_date' => $validated['document_date'] ?? now()->toDateString(),
            'ethiopian_date' => $validated['ethiopian_date'] ?? null,
            'owner_unit_id' => $ownerUnitId,
        ];

        $defaultCatId = \Illuminate\Support\Facades\DB::table('document_categories')->where('active', 1)->value('id')
            ?? \Illuminate\Support\Facades\DB::table('document_categories')->value('id')
            ?? 1;
        $defaultConfId = \Illuminate\Support\Facades\DB::table('confidentiality_levels')->where('active', 1)->value('id')
            ?? \Illuminate\Support\Facades\DB::table('confidentiality_levels')->value('id')
            ?? 1;

        $docAttributes = [
            'title' => 'Official Letter: ' . $validated['subject'],
            'summary' => $validated['subject'],
            'category_id' => $validated['category_id'] ?? $defaultCatId,
            'confidentiality_id' => $validated['confidentiality_id'] ?? $defaultConfId,
            'owner_unit_id' => $ownerUnitId,
            'document_date' => $validated['document_date'] ?? now()->toDateString(),
        ];

        $submitForApproval = (bool) ($validated['submit_for_approval'] ?? false);

        $document = $this->templateService->generateOfficialLetter(
            $letterData,
            $docAttributes,
            $actor,
            $submitForApproval
        );

        return response()->json([
            'success' => true,
            'message' => 'Official Wollo University letter generated and recorded successfully.',
            'data' => $document->load(['versions.versionFiles.fileObject', 'ownerUnit', 'category']),
        ], 201);
    }
}
