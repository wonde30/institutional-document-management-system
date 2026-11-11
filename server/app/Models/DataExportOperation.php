<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dataset export operation and the artefact it produced.
 */
class DataExportOperation extends Model
{
    protected $table = 'data_export_operations';

    protected $fillable = [
        'dataset',
        'status',
        'format',
        'storage_path',
        'file_size',
        'record_count',
        'filters',
        'scope_unit_id',
        'created_by',
        'failure_reason',
        'started_at',
        'completed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'file_size' => 'integer',
            'record_count' => 'integer',
            'scope_unit_id' => 'integer',
            'created_by' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
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

    /**
     * Whether the generated artefact may still be retrieved.
     */
    public function isRetrievable(): bool
    {
        return $this->status === 'completed'
            && $this->storage_path !== null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
