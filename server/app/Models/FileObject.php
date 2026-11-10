<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileObject extends Model
{
    use HasFactory;

    protected $table = 'file_objects';
    public $timestamps = false;

    protected $fillable = [
        'storage_disk',
        'storage_key',
        'original_filename',
        'detected_mime',
        'byte_size',
        'sha256',
        'uploaded_by',
        'uploaded_at',
        'scan_status',
        'scanned_at',
        'storage_status',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
            'scanned_at' => 'datetime',
        ];
    }
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
