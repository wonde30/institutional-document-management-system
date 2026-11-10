<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    public function stamp(): BelongsTo
    {
        return $this->belongsTo(StampDefinition::class, 'stamp_id');
    }

    public function imageFile(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'image_file_id');
    }
}
