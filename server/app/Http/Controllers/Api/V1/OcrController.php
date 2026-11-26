<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\OcrExtractionJob;
use App\Models\DocumentTextExtraction;
use App\Models\DocumentVersion;
use App\Services\OcrTextExtractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OcrController extends Controller
{
    public function __construct(
        protected OcrTextExtractionService $ocrService
    ) {}

    /**
     * Get OCR subsystem status and engine availability.
     */
    public function status(): JsonResponse
    {
        $binary = $this->ocrService->resolveTesseractBinary();

        return response()->json([
            'success' => true,
            'data' => [
                'engine' => 'tesseract-ocr-5.x',
                'is_available' => $binary !== null,
                'binary_path' => $binary,
                'languages_supported' => ['eng', 'amh'],
                'tessdata_path' => config('ocr.tessdata_path'),
                'setup_script' => 'scripts/windows/setup-tesseract.ps1',
            ],
        ]);
    }

    /**
     * Trigger asynchronous or synchronous OCR extraction on a document version.
     */
    public function process(Request $request, int $id, int $versionId): JsonResponse
    {
        $version = DocumentVersion::with('versionFiles.fileObject')->where('document_id', $id)->findOrFail($versionId);

        $fileObjectId = $request->input('file_object_id');
        $versionFile = $fileObjectId
            ? $version->versionFiles()->where('file_object_id', $fileObjectId)->first()
            : ($version->versionFiles()->where('purpose', 'primary')->first() ?? $version->versionFiles()->first());

        if (! $versionFile || ! $versionFile->fileObject) {
            return response()->json([
                'success' => false,
                'message' => 'No readable file object found attached to this document version.',
            ], 422);
        }

        $validated = $request->validate([
            'languages' => 'nullable|string|in:amh,eng,amh+eng,eng+amh',
        ]);

        $fileObject = $versionFile->fileObject;
        $isSync = $request->boolean('sync', false);

        if ($isSync) {
            $extraction = $this->ocrService->executeExtraction(
                $version,
                $fileObject,
                $validated,
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => $extraction->processing_status === 'completed'
                    ? 'OCR text extracted successfully.'
                    : 'OCR extraction processed (check status).',
                'data' => $extraction,
            ], $extraction->processing_status === 'completed' ? 200 : 202);
        }

        OcrExtractionJob::dispatch($version, $fileObject, $validated, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'OCR extraction job queued for background processing.',
            'data' => [
                'document_version_id' => $version->id,
                'file_object_id' => $fileObject->id,
                'status' => 'queued',
            ],
        ], 202);
    }

    /**
     * Store or run manual OCR text extraction payload on a document version.
     */
    public function extract(Request $request, int $id, int $versionId): JsonResponse
    {
        $version = DocumentVersion::where('document_id', $id)->findOrFail($versionId);

        $validated = $request->validate([
            'raw_text' => 'required|string',
            'file_object_id' => 'nullable|exists:file_objects,id',
            'ocr_engine' => 'nullable|string|max:64',
            'language_detected' => 'nullable|string|max:16',
            'confidence_score' => 'nullable|numeric|min:0|max:100',
            'processing_status' => 'nullable|string|in:pending,processing,completed,failed',
            'error_details' => 'nullable|string',
        ]);

        if (! empty($validated['file_object_id'])) {
            $belongsToOtherVersion = \App\Models\VersionFile::where('file_object_id', (int) $validated['file_object_id'])
                ->where('document_version_id', '!=', $version->id)
                ->exists();
            if ($belongsToOtherVersion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error: The specified file_object_id does not belong to this document version.',
                ], 422);
            }
        }

        $extraction = $this->ocrService->storeExtraction($version, $validated, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Bilingual OCR text extraction stored and indexed successfully.',
            'data' => $extraction,
        ], 201);
    }

    /**
     * List OCR extractions for a document version.
     */
    public function extractions(int $id, int $versionId): JsonResponse
    {
        $version = DocumentVersion::where('document_id', $id)->findOrFail($versionId);

        $extractions = DocumentTextExtraction::with(['verifiedBy', 'fileObject'])
            ->where('document_version_id', $version->id)
            ->orderByDesc('extracted_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $extractions,
        ]);
    }

    /**
     * Human verification and correction of OCR text extraction.
     */
    public function verify(Request $request, int $id, ?int $versionId = null, ?int $extractionId = null): JsonResponse
    {
        $extId = $extractionId ?? ($request->route('extractionId') ? (int) $request->route('extractionId') : $id);
        $extraction = DocumentTextExtraction::with('documentVersion.document')->findOrFail($extId);

        if ($versionId !== null && (int) $extraction->document_version_id !== $versionId) {
            abort(404, 'OCR extraction does not belong to the specified document version.');
        }

        $doc = $extraction->documentVersion?->document;
        if ($extractionId !== null && (! $doc || (int) $doc->id !== $id)) {
            abort(404, 'OCR extraction does not belong to the specified document.');
        }

        $actor = $request->user();
        if ($actor && $doc && ! $doc->allowsUser($actor, 'documents.update')) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to verify OCR extractions for this document.',
            ], 403);
        }

        $validated = $request->validate([
            'verified_text' => 'required|string',
            'notes' => 'nullable|string|max:2000',
        ]);

        $verified = $this->ocrService->verifyExtraction(
            $extraction,
            $validated['verified_text'],
            $request->user(),
            $validated['notes'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'OCR text extraction verified and updated.',
            'data' => $verified->load('verifiedBy'),
        ]);
    }

    /**
     * Retry a failed extraction job.
     */
    public function retry(Request $request, int $id, int $versionId, int $extractionId): JsonResponse
    {
        $version = DocumentVersion::where('document_id', $id)->findOrFail($versionId);
        $extraction = DocumentTextExtraction::with('fileObject')
            ->where('document_version_id', $version->id)
            ->findOrFail($extractionId);

        if (! $extraction->fileObject) {
            return response()->json([
                'success' => false,
                'message' => 'No attached file object to retry extraction.',
            ], 422);
        }

        $isSync = $request->boolean('sync', false);

        if ($isSync) {
            $updated = $this->ocrService->executeExtraction($version, $extraction->fileObject, [], $request->user());

            return response()->json([
                'success' => true,
                'message' => 'OCR extraction retry executed.',
                'data' => $updated,
            ]);
        }

        OcrExtractionJob::dispatch($version, $extraction->fileObject, [], $request->user());

        $extraction->update(['processing_status' => 'queued']);

        return response()->json([
            'success' => true,
            'message' => 'OCR extraction retry queued.',
            'data' => $extraction,
        ], 202);
    }
}
