<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentTag extends Model
{
    use HasFactory;

    protected $table = 'document_tags';
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'document_id',
        'tag_id',
    ];

    protected function casts(): array
    {
        return [

        ];
    }
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'tag_id');
    }
}
