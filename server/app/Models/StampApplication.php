<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StampApplication extends Model
{
    use HasFactory;

    protected $table = 'stamp_applications';
    public $timestamps = false;

    protected $fillable = [
        'stamp_version_id',
        'document_version_id',
        'source_file_id',
        'result_file_id',
        'applied_by',
        'applied_at',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'applied_at' => 'datetime',
        ];
    }
    public function stampVersion(): BelongsTo
    {
        return $this->belongsTo(StampVersion::class, 'stamp_version_id');
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    public function sourceFile(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'source_file_id');
    }

    public function resultFile(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'result_file_id');
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }
}
