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
use Illuminate\Http\JsonResponse;

class MetadataController extends Controller
{
    /**
     * Get all active reference vocabularies in a single request.
     */
    public function all(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'document_categories' => DocumentCategory::where('active', 1)->orderBy('name')->get(),
                'confidentiality_levels' => ConfidentialityLevel::where('active', 1)->orderBy('id')->get(),
                'correspondence_types' => CorrespondenceType::where('active', 1)->orderBy('name')->get(),
                'priorities' => Priority::where('active', 1)->orderBy('id')->get(),
                'dispatch_methods' => DispatchMethod::where('active', 1)->orderBy('name')->get(),
                'document_relation_types' => DocumentRelationType::where('active', 1)->orderBy('name')->get(),
                'tags' => Tag::orderBy('display_name')->get(),
                'institutions' => Institution::where('active', 1)->orderBy('name')->get(),
                'qualification_types' => QualificationType::where('active', 1)->orderBy('name')->get(),
                'hr_record_types' => HrRecordType::where('active', 1)->orderBy('name')->get(),
                'project_types' => ProjectType::where('active', 1)->orderBy('name')->get(),
            ],
        ]);
    }

    public function categories(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => DocumentCategory::where('active', 1)->orderBy('name')->get(),
        ]);
    }

    public function confidentialityLevels(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ConfidentialityLevel::where('active', 1)->orderBy('id')->get(),
        ]);
    }

    public function correspondenceTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => CorrespondenceType::where('active', 1)->orderBy('name')->get(),
        ]);
    }

    public function priorities(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Priority::where('active', 1)->orderBy('id')->get(),
        ]);
    }

    public function dispatchMethods(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => DispatchMethod::where('active', 1)->orderBy('name')->get(),
        ]);
    }
}
