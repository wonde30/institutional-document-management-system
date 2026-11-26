<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DataExportOperation;
use App\Models\DataImportOperation;
use App\Models\OrganizationUnit;
use App\Services\Authorization\OrganizationalContextService;
use App\Services\DataTransfer\CsvWriter;
use App\Services\DataTransfer\DataTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Dataset import and export.
 *
 * Every operation is declared in the supported operation catalogue. The scope
 * of an operation is decided by the server: an import always applies to the
 * active organizational unit, and an export is always intersected with the
 * records the caller is permitted to reach.
 */
class DataTransferController extends Controller
{
    public function __construct(
        protected DataTransferService $transfers,
        protected OrganizationalContextService $context,
    ) {
    }

    /**
     * The supported import and export datasets.
     */
    public function datasets(Request $request): JsonResponse
    {
        $catalogue = [];

        foreach ($this->transfers->catalogue() as $key => $dataset) {
            $catalogue[] = [
                'key' => $key,
                'label' => $dataset['label'],
                'description' => $dataset['description'],
                'direction' => $dataset['direction'],
                'format' => $dataset['format'],
                'permission' => $dataset['permission'],
                'scope' => $dataset['scope'],
                'fields' => $dataset['direction'] === 'import'
                    ? array_keys($dataset['fields'])
                    : $dataset['fields'],
                'required_headers' => $dataset['required_headers'] ?? [],
                'filters' => $dataset['filters'] ?? [],
                'max_rows' => $dataset['max_rows'] ?? null,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Supported datasets retrieved.',
            'data' => $catalogue,
            'meta' => [
                'max_import_kilobytes' => $this->transfers->maxImportKilobytes(),
            ],
        ]);
    }

    /*
    |----------------------------------------------------------------------
    | Import
    |----------------------------------------------------------------------
    */

    /**
     * The import history of the caller.
     */
    public function imports(Request $request): JsonResponse
    {
        $operations = DataImportOperation::query()
            ->with('scopeUnit')
            ->where('created_by', $request->user()->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (DataImportOperation $operation) => $this->presentOperation($operation));

        return response()->json([
            'success' => true,
            'message' => 'Import history retrieved.',
            'data' => $operations,
        ]);
    }

    /**
     * Receive and inspect an import file.
     */
    public function storeImport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dataset' => 'required|string|max:64',
            'file' => 'required|file',
            'scope_unit_id' => 'nullable|integer|exists:organization_units,id',
        ]);

        $dataset = $this->transfers->dataset($validated['dataset']);

        if ($dataset === null || ($dataset['direction'] ?? null) !== 'import') {
            abort(422, "The dataset '{$validated['dataset']}' is not an importable dataset.");
        }

        $scopeUnitId = $this->resolveImportScope($request, $validated['scope_unit_id'] ?? null);

        $this->assertDatasetPermission($request, $dataset, $scopeUnitId);

        $operation = $this->transfers->inspect(
            $request->file('file'),
            $validated['dataset'],
            $scopeUnitId,
            $request->user(),
            $request
        );

        return response()->json([
            'success' => true,
            'message' => 'The file was inspected. No record has been created.',
            'data' => $this->presentOperation($operation, true),
        ], 201);
    }

    /**
     * The detail of an import operation, including its row level failures.
     */
    public function showImport(Request $request, int $id): JsonResponse
    {
        $operation = $this->findOwnOperation($request, $id);

        return response()->json([
            'success' => true,
            'message' => 'Import operation retrieved.',
            'data' => $this->presentOperation($operation, true),
        ]);
    }

    /**
     * Execute a previously inspected import.
     */
    public function executeImport(Request $request, int $id): JsonResponse
    {
        $operation = $this->findOwnOperation($request, $id);

        $dataset = $this->transfers->dataset($operation->dataset);

        if ($dataset === null) {
            abort(422, 'The dataset of this operation is no longer supported.');
        }

        // The scope of the operation is the scope it was inspected in, not the
        // scope that happens to be active now.
        $this->assertDatasetPermission($request, $dataset, $operation->scope_unit_id);

        $operation = $this->transfers->execute($operation, $request->user(), $request);

        return response()->json([
            'success' => $operation->status === 'failed' ? false : true,
            'message' => match ($operation->status) {
                'completed' => 'The import completed. Every valid record was created.',
                'completed_with_errors' => 'The import completed with errors. The valid records were created and the failures are listed in the error report.',
                default => 'The import failed. No record was created.',
            },
            'data' => $this->presentOperation($operation, true),
        ], $operation->status === 'failed' ? 422 : 200);
    }

    /**
     * The structured validation error report of an import.
     */
    public function importErrors(Request $request, int $id)
    {
        $operation = $this->findOwnOperation($request, $id);

        $path = 'imports/'.$operation->id.'/errors-'.Str::uuid()->toString().'.csv';

        $fullPath = Storage::disk('local')->path($path);
        Storage::disk('local')->makeDirectory(dirname($path));

        $writer = CsvWriter::open($fullPath);

        try {
            $writer->writeHeader(['row_number', 'column_name', 'code', 'message']);

            foreach ($operation->rowErrors()->orderBy('row_number')->get() as $error) {
                $writer->writeRow([
                    'row_number' => $error->row_number,
                    'column_name' => $error->column_name,
                    'code' => $error->code,
                    'message' => $error->message,
                ]);
            }
        } finally {
            $writer->close();
        }

        return response()->download($fullPath, 'import-errors-'.$operation->id.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Discard an import operation.
     */
    public function destroyImport(Request $request, int $id): JsonResponse
    {
        $operation = $this->findOwnOperation($request, $id);

        $operation = $this->transfers->discard($operation, $request->user(), $request);

        return response()->json([
            'success' => true,
            'message' => 'The import operation was discarded.',
            'data' => $this->presentOperation($operation),
        ]);
    }

    /*
    |----------------------------------------------------------------------
    | Export
    |----------------------------------------------------------------------
    */

    /**
     * The export history of the caller.
     */
    public function exports(Request $request): JsonResponse
    {
        $operations = DataExportOperation::query()
            ->with('scopeUnit')
            ->where('created_by', $request->user()->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (DataExportOperation $operation) => $this->presentExport($operation));

        return response()->json([
            'success' => true,
            'message' => 'Export history retrieved.',
            'data' => $operations,
        ]);
    }

    /**
     * Generate an export file.
     */
    public function storeExport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dataset' => 'required|string|max:64',
            'filters' => 'nullable|array',
            'scope_unit_id' => 'nullable|integer|exists:organization_units,id',
        ]);

        $dataset = $this->transfers->dataset($validated['dataset']);

        if ($dataset === null || ($dataset['direction'] ?? null) !== 'export') {
            abort(422, "The dataset '{$validated['dataset']}' is not an exportable dataset.");
        }

        $this->assertDatasetPermission($request, $dataset);

        $filters = $this->sanitizeFilters($dataset, (array) ($validated['filters'] ?? []));
        $scopeUnitId = $this->resolveExportScope($request, $validated['scope_unit_id'] ?? null);

        $operation = $this->transfers->generate(
            $validated['dataset'],
            $filters,
            $scopeUnitId,
            $request->user(),
            $request
        );

        return response()->json([
            'success' => $operation->status === 'completed',
            'message' => $operation->status === 'completed'
                ? 'The export file was generated.'
                : 'The export could not be generated.',
            'data' => $this->presentExport($operation),
        ], $operation->status === 'completed' ? 201 : 422);
    }

    /**
     * Download a generated export file.
     *
     * The authorization is re-evaluated at the moment of the download, so a
     * revoked capability stops the retrieval immediately.
     */
    public function downloadExport(Request $request, int $id)
    {
        $operation = DataExportOperation::query()->findOrFail($id);
        $actor = $request->user();

        if (! $actor || ((int) $operation->created_by !== (int) $actor->id && ! $actor->hasPermission('system.configure'))) {
            abort(403, 'Forbidden: You are not authorized to download this export operation.');
        }

        if (! $operation->isRetrievable()) {
            abort(404, 'The export file is no longer available.');
        }

        $dataset = $this->transfers->dataset($operation->dataset);

        if ($dataset === null) {
            abort(404, 'The export file is no longer available.');
        }

        $this->assertDatasetPermission($request, $dataset, $operation->scope_unit_id ? (int) $operation->scope_unit_id : null);

        $path = Storage::disk('local')->path((string) $operation->storage_path);

        if (! is_readable($path)) {
            abort(404, 'The export file is no longer available.');
        }

        \App\Services\AuditService::log(
            action: 'data.export.downloaded',
            targetType: 'data_export_operation',
            targetId: (string) $operation->id,
            details: [
                'operation_id' => $operation->id,
                'dataset' => $operation->dataset,
                'record_count' => $operation->record_count,
            ],
            actorId: $request->user()->id,
            request: $request
        );

        return response()->download($path, 'export-'.$operation->dataset.'-'.$operation->id.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /*
    |----------------------------------------------------------------------
    | Internals
    |----------------------------------------------------------------------
    */

    /**
     * The capability that governs the content of a dataset.
     *
     * The check is scope aware. An import is judged in the organizational unit
     * the operation applies to. An export is judged against the units the
     * caller may reach, because the export covers exactly those records.
     */
    protected function assertDatasetPermission(Request $request, array $dataset, ?int $scopeUnitId = null): void
    {
        $capability = (string) $dataset['permission'];
        $actor = $request->user();

        if (($dataset['direction'] ?? null) === 'import') {
            if (! $actor->hasPermission($capability, $scopeUnitId)) {
                abort(403, "Forbidden: Missing required capability [{$capability}] in the active organizational unit.");
            }

            return;
        }

        $permitted = $actor->getPermittedUnitIds($capability);

        if ($permitted !== null && $permitted === []) {
            abort(403, "Forbidden: Missing required capability [{$capability}].");
        }
    }

    /**
     * The organizational scope of an import. An import always applies to the
     * active organizational unit.
     */
    protected function resolveImportScope(Request $request, ?int $requested): int
    {
        $activeUnitId = $this->context->resolveActiveUnitId($request->user(), $request);

        if ($activeUnitId === null) {
            abort(422, 'An active organizational unit is required before a dataset can be imported.');
        }

        if ($requested !== null && (int) $requested !== $activeUnitId) {
            abort(403, 'Forbidden: The requested import scope is not the active organizational unit.');
        }

        $unit = OrganizationUnit::find($activeUnitId);

        if (! $unit || ! $unit->active) {
            abort(422, 'The active organizational unit is not available for an import.');
        }

        return $activeUnitId;
    }

    /**
     * The organizational scope of an export. The requested scope narrows the
     * result; it can never broaden it, because the exported records are always
     * intersected with the caller's permitted records.
     */
    protected function resolveExportScope(Request $request, ?int $requested): ?int
    {
        if ($requested === null) {
            return null;
        }

        $unit = OrganizationUnit::find($requested);

        if (! $unit || ! $unit->active) {
            abort(422, 'The requested export scope is not available.');
        }

        $permitted = $request->user()->getPermittedUnitIds('documents.view');

        if ($permitted !== null && ! in_array((int) $requested, $permitted, true)) {
            abort(403, 'Forbidden: The requested export scope exceeds your permitted organizational units.');
        }

        return (int) $requested;
    }

    /**
     * Keep only the filters the dataset declares.
     */
    protected function sanitizeFilters(array $dataset, array $filters): array
    {
        $permitted = $dataset['filters'] ?? [];
        $clean = [];

        foreach ($permitted as $filter) {
            $value = $filters[$filter] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $clean[$filter] = is_string($value) ? Str::limit(trim($value), 120, '') : $value;
        }

        return $clean;
    }

    /**
     * An import operation of the caller.
     */
    protected function findOwnOperation(Request $request, int $id): DataImportOperation
    {
        $operation = DataImportOperation::query()->with('rowErrors')->findOrFail($id);

        if ($operation->created_by !== $request->user()->id) {
            abort(403, 'Forbidden: This import operation belongs to another officer.');
        }

        return $operation;
    }

    /**
     * Present an import operation.
     */
    protected function presentOperation(DataImportOperation $operation, bool $withErrors = false): array
    {
        $payload = [
            'id' => $operation->id,
            'dataset' => $operation->dataset,
            'status' => $operation->status,
            'format' => $operation->format,
            'original_filename' => $operation->original_filename,
            'scope_unit_id' => $operation->scope_unit_id,
            'scope_unit' => $operation->scopeUnit?->name,
            'total_rows' => $operation->total_rows,
            'valid_rows' => $operation->valid_rows,
            'invalid_rows' => $operation->invalid_rows,
            'duplicate_rows' => $operation->duplicate_rows,
            'imported_rows' => $operation->imported_rows,
            'failed_rows' => $operation->failed_rows,
            'failure_reason' => $operation->failure_reason,
            'started_at' => $operation->started_at?->toIso8601String(),
            'completed_at' => $operation->completed_at?->toIso8601String(),
            'created_at' => $operation->created_at?->toIso8601String(),
            'executable' => $operation->isExecutable(),
        ];

        if ($withErrors) {
            $payload['errors'] = $operation->rowErrors()
                ->orderBy('row_number')
                ->limit(500)
                ->get()
                ->map(fn ($error) => [
                    'row_number' => $error->row_number,
                    'column_name' => $error->column_name,
                    'code' => $error->code,
                    'message' => $error->message,
                ])->all();
        }

        return $payload;
    }

    /**
     * Present an export operation.
     */
    protected function presentExport(DataExportOperation $operation): array
    {
        return [
            'id' => $operation->id,
            'dataset' => $operation->dataset,
            'status' => $operation->status,
            'format' => $operation->format,
            'filters' => $operation->filters,
            'scope_unit_id' => $operation->scope_unit_id,
            'scope_unit' => $operation->scopeUnit?->name,
            'record_count' => $operation->record_count,
            'file_size' => $operation->file_size,
            'failure_reason' => $operation->failure_reason,
            'started_at' => $operation->started_at?->toIso8601String(),
            'completed_at' => $operation->completed_at?->toIso8601String(),
            'expires_at' => $operation->expires_at?->toIso8601String(),
            'created_at' => $operation->created_at?->toIso8601String(),
            'retrievable' => $operation->isRetrievable(),
        ];
    }
}
