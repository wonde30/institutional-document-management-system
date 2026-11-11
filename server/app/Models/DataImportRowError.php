<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A row level validation failure recorded during the inspection of an import.
 */
class DataImportRowError extends Model
{
    protected $table = 'data_import_row_errors';

    protected $fillable = [
        'operation_id',
        'row_number',
        'column_name',
        'code',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'operation_id' => 'integer',
            'row_number' => 'integer',
        ];
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(DataImportOperation::class, 'operation_id');
    }
}
