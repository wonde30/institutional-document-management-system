<?php

declare(strict_types=1);

namespace App\Services\DataTransfer;

use App\Models\DataExportOperation;
use App\Models\DataImportOperation;
use App\Models\DataImportRowError;
use App\Models\Document;
use App\Models\OrganizationMembership;
use App\Models\OrganizationUnit;
use App\Models\User;
use App\Models\UnitType;
use App\Services\AuditService;
use App\Services\Settings\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Dataset import and export.
 *
 * The supported operations are declared in config/data_transfer.php. There is
 * no generic table access and no arbitrary model serialization: an operation
 * that is not declared in the catalogue cannot be performed.
 *
 * Imports follow a controlled lifecycle. A file is received, stored under a
 * server controlled name on the private disk, inspected and validated. The
 * inspection creates no business record. The records are created only when
 * the operation is explicitly executed, inside a single transaction.
 */
class DataTransferService
{
    /**
     * How long a generated export file remains retrievable.
     */
    public const EXPORT_TTL_HOURS = 24;

    public function __construct(
        protected SettingsService $settings,
    ) {
    }

    /**
     * The supported operation catalogue.
     *
     * @return array<string, array<string, mixed>>
     */
    public function catalogue(?string $direction = null): array
    {
        $catalogue = (array) config('data_transfer', []);

        if ($direction === null) {
            return $catalogue;
        }

        return array_filter(
            $catalogue,
            fn (array $dataset) => ($dataset['direction'] ?? null) === $direction
        );
    }

    /**
     * A single dataset definition, or null when it is not supported.
     *
     * @return array<string, mixed>|null
     */
    public function dataset(string $key): ?array
    {
        $dataset = $this->catalogue()[$key] ?? null;

        return is_array($dataset) ? $dataset : null;
    }

    /**
     * The maximum size, in kilobytes, accepted for an import file.
     */
    public function maxImportKilobytes(): int
    {
        $megabytes = $this->settings->value('data_imports.max_file_size_mb');

        if (! is_numeric($megabytes) || (int) $megabytes < 1) {
            return 20 * 1024;
        }

        return (int) $megabytes * 1024;
    }

    /*
    |----------------------------------------------------------------------
    | Import
    |----------------------------------------------------------------------
    */

    /**
     * Receive, store and inspect an import file.
     *
     * No business record is created. The operation records the outcome of the
     * inspection and the row level failures.
     */
    public function inspect(
        UploadedFile $file,
        string $datasetKey,
        ?int $scopeUnitId,
        User $actor,
        Request $request
    ): DataImportOperation {
        $dataset = $this->dataset($datasetKey);

        if ($dataset === null || ($dataset['direction'] ?? null) !== 'import') {
            abort(422, "The dataset '{$datasetKey}' is not an importable dataset.");
        }

        $this->assertFileAccepted($file);

        $operation = DataImportOperation::create([
            'dataset' => $datasetKey,
            'status' => 'uploaded',
            'format' => $dataset['format'],
            'original_filename' => Str::limit(basename((string) $file->getClientOriginalName()), 255, ''),
            'scope_unit_id' => $scopeUnitId,
            'created_by' => $actor->id,
        ]);

        // The file is stored under a server controlled name. The name the
        // client supplied is recorded for the operator but never used to build
        // a path.
        $path = 'imports/'.$operation->id.'/'.Str::uuid()->toString().'.csv';

        try {
            Storage::disk('local')->putFileAs(
                dirname($path),
                $file,
                basename($path)
            );
        } catch (\Throwable $exception) {
            $operation->update([
                'status' => 'failed',
                'failure_reason' => 'The uploaded file could not be stored.',
                'completed_at' => now(),
            ]);

            throw $exception;
        }

        $operation->update(['storage_path' => $path]);

        $inspection = $this->inspectFile($operation, $dataset, $scopeUnitId);

        $operation->update([
            'status' => 'validated',
            'total_rows' => $inspection['total'],
            'valid_rows' => $inspection['valid'],
            'invalid_rows' => $inspection['invalid'],
            'duplicate_rows' => $inspection['duplicate'],
        ]);

        foreach ($inspection['errors'] as $error) {
            DataImportRowError::create(array_merge($error, ['operation_id' => $operation->id]));
        }

        AuditService::log(
            action: 'data.import.inspected',
            targetType: 'data_import_operation',
            targetId: (string) $operation->id,
            details: [
                'operation_id' => $operation->id,
                'dataset' => $datasetKey,
                'scope_unit_id' => $scopeUnitId,
                'original_filename' => $operation->original_filename,
                'format' => $operation->format,
                'total_rows' => $inspection['total'],
                'valid_rows' => $inspection['valid'],
                'invalid_rows' => $inspection['invalid'],
                'duplicate_rows' => $inspection['duplicate'],
                'warnings' => $inspection['warnings'],
            ],
            actorId: $actor->id,
            request: $request
        );

        return $operation->refresh();
    }

    /**
     * Execute a previously inspected import.
     *
     * The authorization and the business rules are re-evaluated at execution
     * time. The valid records are created inside a single transaction, so the
     * operation either commits every valid record or none of them.
     */
    public function execute(DataImportOperation $operation, User $actor, Request $request): DataImportOperation
    {
        if (! $operation->isExecutable()) {
            abort(422, 'The import operation is not in a state that can be executed.');
        }

        if ($operation->created_by !== $actor->id) {
            abort(403, 'Forbidden: An import operation may only be executed by the officer who uploaded it.');
        }

        // The organizational scope is re-evaluated at execution time.
        $scopeUnitId = $this->assertScopeStillValid($operation, $actor);

        $dataset = $this->dataset($operation->dataset);

        if ($dataset === null) {
            abort(422, 'The dataset of this operation is no longer supported.');
        }

        $operation->update(['status' => 'importing', 'started_at' => now()]);

        $rows = $this->readRows($operation);

        $imported = 0;
        $failed = 0;

        try {
            DB::transaction(function () use ($rows, $dataset, $scopeUnitId, $actor, &$imported, &$failed, $operation) {
                foreach ($rows as $index => $row) {
                    $rowNumber = $index + 2; // The header occupies the first line.

                    $result = $this->validateRow($dataset, $row, $scopeUnitId, $rowNumber, true);

                    if ($result['errors'] !== []) {
                        $failed++;

                        foreach ($result['errors'] as $error) {
                            DataImportRowError::create(array_merge($error, ['operation_id' => $operation->id]));
                        }

                        continue;
                    }

                    $this->createRecord($dataset, $result['record'], $scopeUnitId, $actor, $operation->dataset);

                    $imported++;
                }
            });
        } catch (\Throwable $exception) {
            $operation->update([
                'status' => 'failed',
                'imported_rows' => 0,
                'failed_rows' => $failed,
                'failure_reason' => 'The import was rolled back and no record was created: '.Str::limit($exception->getMessage(), 500, ''),
                'completed_at' => now(),
            ]);

            AuditService::log(
                action: 'data.import.failed',
                targetType: 'data_import_operation',
                targetId: (string) $operation->id,
                details: [
                    'operation_id' => $operation->id,
                    'dataset' => $operation->dataset,
                    'scope_unit_id' => $scopeUnitId,
                    'imported_rows' => 0,
                    'failed_rows' => $failed,
                    'reason' => $exception->getMessage(),
                ],
                actorId: $actor->id,
                request: $request
            );

            return $operation->refresh();
        }

        $status = $failed > 0 ? 'completed_with_errors' : 'completed';

        $operation->update([
            'status' => $status,
            'imported_rows' => $imported,
            'failed_rows' => $failed,
            'completed_at' => now(),
        ]);

        AuditService::log(
            action: 'data.import.executed',
            targetType: 'data_import_operation',
            targetId: (string) $operation->id,
            details: [
                'operation_id' => $operation->id,
                'dataset' => $operation->dataset,
                'scope_unit_id' => $scopeUnitId,
                'total_rows' => $operation->total_rows,
                'imported_rows' => $imported,
                'failed_rows' => $failed,
                'operation' => 'execute',
            ],
            actorId: $actor->id,
            request: $request
        );

        return $operation->refresh();
    }

    /**
     * Discard an import operation and remove its uploaded file.
     */
    public function discard(DataImportOperation $operation, User $actor, Request $request): DataImportOperation
    {
        if ($operation->created_by !== $actor->id) {
            abort(403, 'Forbidden: An import operation may only be discarded by the officer who uploaded it.');
        }

        if (! $operation->isFinished()) {
            $operation->update([
                'status' => 'discarded',
                'completed_at' => now(),
            ]);
        }

        if ($operation->storage_path) {
            Storage::disk('local')->delete($operation->storage_path);
            $operation->update(['storage_path' => null]);
        }

        AuditService::log(
            action: 'data.import.discarded',
            targetType: 'data_import_operation',
            targetId: (string) $operation->id,
            details: [
                'operation_id' => $operation->id,
                'dataset' => $operation->dataset,
                'operation' => 'discard',
            ],
            actorId: $actor->id,
            request: $request
        );

        return $operation->refresh();
    }

    /*
    |----------------------------------------------------------------------
    | Export
    |----------------------------------------------------------------------
    */

    /**
     * Generate an export file for the authorised records.
     */
    public function generate(
        string $datasetKey,
        array $filters,
        ?int $scopeUnitId,
        User $actor,
        Request $request
    ): DataExportOperation {
        $dataset = $this->dataset($datasetKey);

        if ($dataset === null || ($dataset['direction'] ?? null) !== 'export') {
            abort(422, "The dataset '{$datasetKey}' is not an exportable dataset.");
        }

        $operation = DataExportOperation::create([
            'dataset' => $datasetKey,
            'status' => 'requested',
            'format' => $dataset['format'],
            'filters' => $filters,
            'scope_unit_id' => $scopeUnitId,
            'created_by' => $actor->id,
            'started_at' => now(),
        ]);

        try {
            $recordCount = $this->writeExport($operation, $dataset, $filters, $scopeUnitId, $actor);
        } catch (\Throwable $exception) {
            $operation->update([
                'status' => 'failed',
                'failure_reason' => Str::limit($exception->getMessage(), 500, ''),
                'completed_at' => now(),
            ]);

            AuditService::log(
                action: 'data.export.failed',
                targetType: 'data_export_operation',
                targetId: (string) $operation->id,
                details: [
                    'operation_id' => $operation->id,
                    'dataset' => $datasetKey,
                    'scope_unit_id' => $scopeUnitId,
                    'reason' => $exception->getMessage(),
                ],
                actorId: $actor->id,
                request: $request
            );

            return $operation->refresh();
        }

        $operation->update([
            'status' => 'completed',
            'record_count' => $recordCount,
            'file_size' => (int) (Storage::disk('local')->size($operation->storage_path) ?: 0),
            'completed_at' => now(),
            'expires_at' => now()->addHours(self::EXPORT_TTL_HOURS),
        ]);

        AuditService::log(
            action: 'data.export.generated',
            targetType: 'data_export_operation',
            targetId: (string) $operation->id,
            details: [
                'operation_id' => $operation->id,
                'dataset' => $datasetKey,
                'scope_unit_id' => $scopeUnitId,
                'filters' => $filters,
                'format' => $dataset['format'],
                'record_count' => $recordCount,
                'expires_at' => now()->addHours(self::EXPORT_TTL_HOURS)->toIso8601String(),
            ],
            actorId: $actor->id,
            request: $request
        );

        return $operation->refresh();
    }

    /*
    |----------------------------------------------------------------------
    | Internals
    |----------------------------------------------------------------------
    */

    /**
     * Reject a file that is not an acceptable dataset file.
     */
    protected function assertFileAccepted(UploadedFile $file): void
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if ($extension !== 'csv') {
            abort(422, 'Only CSV dataset files are supported.');
        }

        $mime = (string) $file->getMimeType();

        $acceptable = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'text/x-csv'];

        if (! in_array($mime, $acceptable, true)) {
            abort(422, "The uploaded file is not a readable CSV file (detected '{$mime}').");
        }

        if ($file->getSize() === false || $file->getSize() <= 0) {
            abort(422, 'The uploaded file is empty.');
        }

        if ($file->getSize() > $this->maxImportKilobytes() * 1024) {
            abort(422, 'The uploaded file exceeds the permitted size for a dataset file.');
        }
    }

    /**
     * Read and validate the uploaded file.
     *
     * @return array{total: int, valid: int, invalid: int, duplicate: int, errors: list<array<string, mixed>>, warnings: list<string>}
     */
    protected function inspectFile(DataImportOperation $operation, array $dataset, ?int $scopeUnitId): array
    {
        $path = Storage::disk('local')->path((string) $operation->storage_path);

        if (! is_readable($path)) {
            abort(422, 'The uploaded file could not be read.');
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            abort(422, 'The uploaded file could not be opened.');
        }

        $headers = fgetcsv($handle);

        if ($headers === false || $headers === [null]) {
            fclose($handle);

            abort(422, 'The uploaded file has no header row.');
        }

        $headers = array_map(fn ($header) => trim((string) $header), $headers);
        $headers[0] = (string) preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);

        $known = array_keys($dataset['fields']);
        $required = $dataset['required_headers'] ?? [];

        $warnings = [];
        $unrecognised = array_values(array_diff($headers, $known));

        if ($unrecognised !== []) {
            $warnings[] = 'Columns that are not part of the catalogue were ignored: '.implode(', ', $unrecognised).'.';
        }

        $missing = array_values(array_diff($required, $headers));

        if ($missing !== []) {
            fclose($handle);

            abort(422, 'The uploaded file is missing required columns: '.implode(', ', $missing).'.');
        }

        $maxRows = (int) ($dataset['max_rows'] ?? 2000);

        $total = 0;
        $valid = 0;
        $invalid = 0;
        $duplicate = 0;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || $row === []) {
                continue;
            }

            $total++;

            if ($total > $maxRows) {
                fclose($handle);

                abort(422, "The uploaded file carries more than the permitted {$maxRows} records.");
            }

            $record = array_combine(
                $headers,
                array_pad(array_slice($row, 0, count($headers)), count($headers), null)
            ) ?: [];

            $result = $this->validateRow($dataset, $record, $scopeUnitId, $total + 1, false);

            if ($result['errors'] !== []) {
                $invalid++;

                foreach ($result['errors'] as $error) {
                    if (($error['code'] ?? null) === 'duplicate') {
                        $duplicate++;
                    }
                }

                $errors = array_merge($errors, $result['errors']);

                continue;
            }

            $valid++;
        }

        fclose($handle);

        return compact('total', 'valid', 'invalid', 'duplicate', 'errors', 'warnings');
    }

    /**
     * Validate one row against the dataset specification.
     *
     * @return array{record: array<string, mixed>, errors: list<array<string, mixed>>}
     */
    protected function validateRow(
        array $dataset,
        array $record,
        ?int $scopeUnitId,
        int $rowNumber,
        bool $revalidate
    ): array {
        $errors = [];
        $normalised = [];

        foreach ($dataset['fields'] as $field => $spec) {
            $raw = $record[$field] ?? null;
            $value = is_string($raw) ? trim($raw) : $raw;

            if ($value === '' || $value === null) {
                if ($spec['required'] ?? false) {
                    $errors[] = [
                        'row_number' => $rowNumber,
                        'column_name' => $field,
                        'code' => 'required',
                        'message' => 'The '.$field.' column is required.',
                    ];

                    continue;
                }

                $normalised[$field] = $spec['default'] ?? null;

                continue;
            }

            $type = $spec['type'] ?? 'string';

            if ($type === 'string') {
                $value = (string) $value;

                if (isset($spec['max']) && mb_strlen($value) > (int) $spec['max']) {
                    $errors[] = [
                        'row_number' => $rowNumber,
                        'column_name' => $field,
                        'code' => 'too_long',
                        'message' => 'The '.$field.' column accepts at most '.$spec['max'].' characters.',
                    ];

                    continue;
                }
            } elseif ($type === 'integer') {
                if (! is_numeric($value)) {
                    $errors[] = [
                        'row_number' => $rowNumber,
                        'column_name' => $field,
                        'code' => 'type',
                        'message' => 'The '.$field.' column must be a number.',
                    ];

                    continue;
                }

                $value = (int) $value;
            } elseif ($type === 'boolean') {
                $value = in_array(strtolower((string) $value), ['1', 'true', 'yes', 'y'], true);
            } elseif ($type === 'date') {
                $timestamp = strtotime((string) $value);

                if ($timestamp === false) {
                    $errors[] = [
                        'row_number' => $rowNumber,
                        'column_name' => $field,
                        'code' => 'type',
                        'message' => 'The '.$field.' column must be a valid date.',
                    ];

                    continue;
                }

                $value = date('Y-m-d H:i:s', $timestamp);
            }

            $normalised[$field] = $value;
        }

        // Dataset specific rules.
        foreach ($this->datasetRules($dataset, $normalised, $scopeUnitId) as $error) {
            $errors[] = array_merge(['row_number' => $rowNumber], $error);
        }

        return ['record' => $normalised, 'errors' => $errors];
    }

    /**
     * The rules that depend on the data rather than on its type.
     *
     * @return list<array<string, mixed>>
     */
    protected function datasetRules(array $dataset, array $record, ?int $scopeUnitId): array
    {
        $errors = [];
        $key = $dataset['fields'] ? array_key_first($dataset['fields']) : null;

        if (($dataset['direction'] ?? null) === 'import' && $key === 'code') {
            // The unit code must not already exist.
            if (! empty($record['code']) && OrganizationUnit::where('code', $record['code'])->exists()) {
                $errors[] = [
                    'column_name' => 'code',
                    'code' => 'duplicate',
                    'message' => 'An organizational unit with the code "'.$record['code'].'" already exists.',
                ];
            }
        }

        if (($dataset['direction'] ?? null) === 'import' && isset($record['unit_type_code'])) {
            if (! UnitType::where('code', $record['unit_type_code'])->where('active', 1)->exists()) {
                $errors[] = [
                    'column_name' => 'unit_type_code',
                    'code' => 'invalid_reference',
                    'message' => 'The organizational unit type "'.$record['unit_type_code'].'" is not registered.',
                ];
            }
        }

        if (($dataset['direction'] ?? null) === 'import' && isset($record['parent_code']) && $record['parent_code'] !== '') {
            $parent = OrganizationUnit::where('code', $record['parent_code'])->first();

            if (! $parent) {
                $errors[] = [
                    'column_name' => 'parent_code',
                    'code' => 'invalid_reference',
                    'message' => 'The parent unit "'.$record['parent_code'].'" does not exist.',
                ];
            } elseif ($scopeUnitId !== null && ! $this->isWithinScope((int) $parent->id, $scopeUnitId)) {
                $errors[] = [
                    'column_name' => 'parent_code',
                    'code' => 'out_of_scope',
                    'message' => 'The parent unit "'.$record['parent_code'].'" is outside the active organizational unit.',
                ];
            }
        }

        if (($dataset['direction'] ?? null) === 'import' && isset($record['user_email'])) {
            $account = User::where('email', strtolower((string) $record['user_email']))->first();

            if (! $account) {
                $errors[] = [
                    'column_name' => 'user_email',
                    'code' => 'invalid_reference',
                    'message' => 'No institutional account matches "'.$record['user_email'].'".',
                ];
            } elseif ($account->status !== 'active') {
                $errors[] = [
                    'column_name' => 'user_email',
                    'code' => 'invalid_reference',
                    'message' => 'The account "'.$record['user_email'].'" is not active.',
                ];
            } elseif ($scopeUnitId !== null && OrganizationMembership::where('user_id', $account->id)
                ->where('unit_id', $scopeUnitId)->exists()) {
                $errors[] = [
                    'column_name' => 'user_email',
                    'code' => 'duplicate',
                    'message' => 'The account "'.$record['user_email'].'" is already a member of the active unit.',
                ];
            }
        }

        if (isset($record['starts_at'], $record['ends_at'])
            && $record['starts_at'] !== null && $record['ends_at'] !== null
            && strtotime((string) $record['ends_at']) < strtotime((string) $record['starts_at'])) {
            $errors[] = [
                'column_name' => 'ends_at',
                'code' => 'invalid_range',
                'message' => 'The membership end date must not precede its start date.',
            ];
        }

        return $errors;
    }

    /**
     * Whether a unit is the scope unit itself or a descendant of it.
     */
    protected function isWithinScope(int $unitId, int $scopeUnitId): bool
    {
        if ($unitId === $scopeUnitId) {
            return true;
        }

        $unit = OrganizationUnit::find($unitId);

        if (! $unit) {
            return false;
        }

        return in_array($scopeUnitId, $unit->getAncestorIds(), true);
    }

    /**
     * Create the business record described by a validated row.
     */
    protected function createRecord(array $dataset, array $record, ?int $scopeUnitId, User $actor, string $datasetKey): void
    {
        if ($datasetKey === 'organization_units') {
            $parent = null;

            if (! empty($record['parent_code'])) {
                $parent = OrganizationUnit::where('code', $record['parent_code'])->first();
            }

            $unitType = UnitType::where('code', $record['unit_type_code'])->first();

            OrganizationUnit::create([
                'parent_id' => $parent?->id ?? $scopeUnitId,
                'unit_type_id' => $unitType?->id,
                'code' => $record['code'],
                'name' => $record['name'],
                'active' => (int) ($record['is_active'] ?? true),
            ]);

            return;
        }

        if ($datasetKey === 'organization_memberships') {
            $account = User::where('email', strtolower((string) $record['user_email']))->first();

            if (! $account) {
                return;
            }

            OrganizationMembership::create([
                'user_id' => $account->id,
                'unit_id' => $scopeUnitId,
                'position_title' => $record['position_title'] ?? null,
                'starts_at' => $record['starts_at'] ?? now(),
                'ends_at' => $record['ends_at'] ?? null,
                'created_by' => $actor->id,
            ]);

            return;
        }
    }

    /**
     * Read the rows of the uploaded file.
     *
     * @return list<array<string, mixed>>
     */
    protected function readRows(DataImportOperation $operation): array
    {
        if (! $operation->storage_path) {
            abort(422, 'The uploaded file is no longer available.');
        }

        $path = Storage::disk('local')->path((string) $operation->storage_path);

        if (! is_readable($path)) {
            abort(422, 'The uploaded file is no longer available.');
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            abort(422, 'The uploaded file could not be opened.');
        }

        $headers = fgetcsv($handle);
        $headers = array_map(fn ($header) => trim((string) $header), (array) $headers);
        $headers[0] = (string) preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);

        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || $row === []) {
                continue;
            }

            $rows[] = array_combine(
                $headers,
                array_pad(array_slice($row, 0, count($headers)), count($headers), null)
            ) ?: [];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Write the export file and return the number of records written.
     */
    protected function writeExport(
        DataExportOperation $operation,
        array $dataset,
        array $filters,
        ?int $scopeUnitId,
        User $actor
    ): int {
        $path = 'exports/'.$operation->id.'/'.Str::uuid()->toString().'.csv';

        $fullPath = Storage::disk('local')->path($path);

        if (! is_dir(dirname($fullPath))) {
            Storage::disk('local')->makeDirectory(dirname($path));
        }

        $writer = CsvWriter::open($fullPath);
        $count = 0;

        try {
            $writer->writeHeader($dataset['fields']);

            foreach ($this->exportRows($dataset, $filters, $scopeUnitId, $actor, $operation->dataset) as $row) {
                $writer->writeRow($row);
                $count++;
            }
        } finally {
            $writer->close();
        }

        $operation->update(['storage_path' => $path]);

        return $count;
    }

    /**
     * The authorised rows of an export, as a generator so the memory use stays
     * bounded.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    protected function exportRows(array $dataset, array $filters, ?int $scopeUnitId, User $actor, string $datasetKey): \Generator
    {
        yield from match ($datasetKey) {
            'document_catalog' => $this->documentExportRows($dataset, $filters, $scopeUnitId, $actor),
            'correspondence_registry' => $this->correspondenceExportRows($dataset, $filters, $scopeUnitId, $actor),
            'organization_directory' => $this->organizationExportRows($dataset, $filters, $scopeUnitId, $actor),
            'audit_events' => $this->auditExportRows($dataset, $filters),
            default => [],
        };
    }

    /**
     * Document metadata. Every record is authorised individually with the
     * record level policy, so a confidential document is only exported by an
     * account that carries the required clearance.
     */
    protected function documentExportRows(array $dataset, array $filters, ?int $scopeUnitId, User $actor): \Generator
    {
        $permitted = $actor->getPermittedUnitIds('documents.view');
        $maxRows = (int) ($dataset['max_rows'] ?? 10000);

        $query = Document::query()
            ->with(['category', 'confidentiality', 'ownerUnit'])
            ->when($permitted !== null, fn ($q) => $q->whereIn('owner_unit_id', $permitted))
            ->when($scopeUnitId !== null, fn ($q) => $q->where('owner_unit_id', $scopeUnitId))
            ->when(! empty($filters['lifecycle']), fn ($q) => $q->where('lifecycle', $filters['lifecycle']))
            ->when(! empty($filters['date_from']), fn ($q) => $q->whereDate('document_date', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($q) => $q->whereDate('document_date', '<=', $filters['date_to']))
            ->orderBy('id');

        if (! empty($filters['category_code'])) {
            $query->whereHas('category', fn ($q) => $q->where('code', $filters['category_code']));
        }

        if (! empty($filters['confidentiality_code'])) {
            $query->whereHas('confidentiality', fn ($q) => $q->where('code', $filters['confidentiality_code']));
        }

        $emitted = 0;
        foreach ($query->cursor() as $document) {
            if (! $document->allowsUser($actor, 'documents.view')) {
                continue;
            }

            yield [
                'reference_number' => $document->reference_number,
                'title' => $document->title,
                'summary' => $document->summary,
                'category' => $document->category?->name,
                'confidentiality' => $document->confidentiality?->name,
                'owner_unit' => $document->ownerUnit?->name,
                'document_date' => $document->document_date,
                'registered_at' => $document->registered_at,
                'lifecycle' => $document->lifecycle,
            ];

            if (++$emitted > $maxRows) {
                break;
            }
        }
    }

    /**
     * Correspondence metadata, authorised through the related document.
     */
    protected function correspondenceExportRows(array $dataset, array $filters, ?int $scopeUnitId, User $actor): \Generator
    {
        $permitted = $actor->getPermittedUnitIds('correspondence.view');
        $maxRows = (int) ($dataset['max_rows'] ?? 10000);

        $query = \App\Models\Correspondence::query()
            ->with(['document.ownerUnit', 'type', 'priority'])
            ->when($permitted !== null, fn ($q) => $q->whereHas('document', fn ($d) => $d->whereIn('owner_unit_id', $permitted)))
            ->when($scopeUnitId !== null, fn ($q) => $q->whereHas('document', fn ($d) => $d->where('owner_unit_id', $scopeUnitId)))
            ->when(! empty($filters['purpose_code']), fn ($q) => $q->where('purpose_code', $filters['purpose_code']))
            ->when(! empty($filters['date_from']), fn ($q) => $q->whereDate('correspondence_date', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($q) => $q->whereDate('correspondence_date', '<=', $filters['date_to']))
            ->orderBy('id');

        if (! empty($filters['type_code'])) {
            $query->whereHas('type', fn ($q) => $q->where('code', $filters['type_code']));
        }

        $emitted = 0;
        foreach ($query->cursor() as $correspondence) {
            if ($correspondence->document && ! $correspondence->document->allowsUser($actor, 'correspondence.view')) {
                continue;
            }

            yield [
                'reference_number' => $correspondence->document?->reference_number,
                'purpose_code' => $correspondence->purpose_code,
                'type' => $correspondence->type?->name,
                'priority' => $correspondence->priority?->name,
                'external_reference' => $correspondence->external_reference,
                'correspondence_date' => $correspondence->correspondence_date,
                'receiving_unit' => $correspondence->firstReceivingUnit?->name,
                'received_at' => $correspondence->received_at,
                'requested_due_at' => $correspondence->requested_due_at,
            ];

            if (++$emitted > $maxRows) {
                break;
            }
        }
    }

    /**
     * The organizational directory and its members.
     */
    protected function organizationExportRows(array $dataset, array $filters, ?int $scopeUnitId, User $actor): \Generator
    {
        $permitted = $actor->getPermittedUnitIds('organization.view');

        $query = OrganizationUnit::query()
            ->with(['unitType', 'parent'])
            ->when($permitted !== null, fn ($q) => $q->whereIn('id', $permitted))
            ->when(! empty($filters['unit_type_code']), fn ($q) => $q->whereHas('unitType', fn ($t) => $t->where('code', $filters['unit_type_code'])))
            ->orderBy('id')
            ->limit((int) ($dataset['max_rows'] ?? 10000) + 1);

        foreach ($query->cursor() as $unit) {
            $members = OrganizationMembership::query()
                ->with(['user', 'unit'])
                ->where('unit_id', $unit->id)
                ->get();

            if ($members->isEmpty()) {
                yield [
                    'unit_code' => $unit->code,
                    'unit_name' => $unit->name,
                    'unit_type' => $unit->unitType?->name,
                    'parent_unit_code' => $unit->parent?->code,
                    'member_name' => null,
                    'member_email' => null,
                    'member_position' => null,
                ];

                continue;
            }

            foreach ($members as $membership) {
                yield [
                    'unit_code' => $unit->code,
                    'unit_name' => $unit->name,
                    'unit_type' => $unit->unitType?->name,
                    'parent_unit_code' => $unit->parent?->code,
                    'member_name' => $membership->user?->name,
                    'member_email' => $membership->user?->email,
                    'member_position' => $membership->position_title,
                ];
            }
        }
    }

    /**
     * The security audit ledger. The free form detail payload is never
     * exported.
     */
    protected function auditExportRows(array $dataset, array $filters): \Generator
    {
        $query = \App\Models\AuditEvent::query()
            ->with('actor')
            ->when(! empty($filters['action']), fn ($q) => $q->where('action', $filters['action']))
            ->when(! empty($filters['date_from']), fn ($q) => $q->whereDate('occurred_at', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($q) => $q->whereDate('occurred_at', '<=', $filters['date_to']))
            ->orderBy('id')
            ->limit((int) ($dataset['max_rows'] ?? 10000) + 1);

        foreach ($query->cursor() as $event) {
            yield [
                'occurred_at' => $event->occurred_at,
                'action' => $event->action,
                'target_type' => $event->target_type,
                'target_id' => $event->target_id,
                'actor_name' => $event->actor?->name,
                'ip_address' => $event->ip_address,
            ];
        }
    }

    /**
     * Re-check that the organizational scope of an operation is still valid.
     */
    protected function assertScopeStillValid(DataImportOperation $operation, User $actor): ?int
    {
        $scopeUnitId = $operation->scope_unit_id;

        if ($scopeUnitId === null) {
            return null;
        }

        $scope = OrganizationUnit::find($scopeUnitId);

        if (! $scope || ! $scope->active) {
            abort(403, 'Forbidden: The organizational unit of this operation is no longer active.');
        }

        return $scopeUnitId;
    }
}
