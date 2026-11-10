<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileIntegrityCheck extends Model
{
    use HasFactory;

    protected $table = 'file_integrity_checks';
    public $timestamps = false;

    protected $fillable = [
        'file_object_id',
        'checked_at',
        'observed_sha256',
        'outcome',
        'error_code',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
        ];
    }
    public function fileObject(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'file_object_id');
    }
}
