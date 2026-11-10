<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileDerivative extends Model
{
    use HasFactory;

    protected $table = 'file_derivatives';
    public $timestamps = false;

    protected $fillable = [
        'source_file_id',
        'result_file_id',
        'kind',
        'generator_version',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
        ];
    }
    public function sourceFile(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'source_file_id');
    }

    public function resultFile(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'result_file_id');
    }
}
