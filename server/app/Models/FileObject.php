<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $storage_disk
 * @property string $storage_key
 * @property string $original_filename
 * @property string|null $detected_mime
 * @property string|null $mime_type
 * @property string|null $storage_path
 * @property string|null $storage_driver
 * @property int $byte_size
 * @property string $sha256
 * @property int|null $uploaded_by
 * @property \Carbon\Carbon|null $uploaded_at
 * @property string $scan_status
 * @property \Carbon\Carbon|null $scanned_at
 * @property string $storage_status
 */
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
