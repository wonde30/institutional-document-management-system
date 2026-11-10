<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRow extends Model
{
    use HasFactory;

    protected $table = 'import_rows';
    public $timestamps = false;

    protected $fillable = [
        'batch_id',
        'source_row_key',
        'target_document_id',
        'status',
        'validation_errors_json',
    ];

    protected function casts(): array
    {
        return [
            'validation_errors_json' => 'array',
        ];
    }
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'batch_id');
    }

    public function targetDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'target_document_id');
    }
}
