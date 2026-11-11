<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A dataset import operation.
 *
 * The operation moves through a controlled lifecycle: uploaded, validated,
 * importing, completed, completed with errors, failed or discarded. An
 * operation is never reported as completed before its records are committed.
 */
class DataImportOperation extends Model
{
    protected $table = 'data_import_operations';

    protected $fillable = [
        'dataset',
        'status',
        'format',
        'original_filename',
        'storage_path',
        'scope_unit_id',
        'created_by',
        'total_rows',
        'valid_rows',
        'invalid_rows',
        'duplicate_rows',
        'imported_rows',
        'failed_rows',
        'failure_reason',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'scope_unit_id' => 'integer',
            'created_by' => 'integer',
            'total_rows' => 'integer',
            'valid_rows' => 'integer',
            'invalid_rows' => 'integer',
            'duplicate_rows' => 'integer',
            'imported_rows' => 'integer',
            'failed_rows' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function scopeUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'scope_unit_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rowErrors(): HasMany
    {
        return $this->hasMany(DataImportRowError::class, 'operation_id');
    }

    /**
     * Whether the operation may still be executed.
     */
    public function isExecutable(): bool
    {
        return $this->status === 'validated' && $this->valid_rows > 0;
    }

    /**
     * Whether the operation has reached a terminal state.
     */
    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'completed_with_errors', 'failed', 'discarded'], true);
    }
}
