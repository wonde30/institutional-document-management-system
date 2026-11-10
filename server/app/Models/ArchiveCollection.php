<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchiveCollection extends Model
{
    use HasFactory;

    protected $table = 'archive_collections';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'code',
        'name',
        'description',
    ];

    protected function casts(): array
    {
        return [

        ];
    }
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ArchiveCollection::class, 'parent_id');
    }
}
