<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $stamp_id
 * @property int $version_number
 * @property int $image_file_id
 * @property \Carbon\Carbon|null $effective_from
 * @property \Carbon\Carbon|null $retired_at
 * @property \App\Models\StampDefinition|null $stamp
 * @property \App\Models\FileObject|null $imageFile
 */
class StampVersion extends Model
{
    use HasFactory;

    protected $table = 'stamp_versions';
    public $timestamps = false;

    protected $fillable = [
        'stamp_id',
        'version_number',
        'image_file_id',
        'effective_from',
        'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'effective_from' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }
    /**
     * @return BelongsTo<StampDefinition, $this>
     */
    public function stamp(): BelongsTo
    {
        return $this->belongsTo(StampDefinition::class, 'stamp_id');
    }

    /**
     * @return BelongsTo<FileObject, $this>
     */
    public function imageFile(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'image_file_id');
    }
}
