<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentComment extends Model
{
    use HasFactory;

    protected $table = 'document_comments';
    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'parent_id',
        'author_id',
        'body',
        'created_at',
        'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'edited_at' => 'datetime',
        ];
    }
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(DocumentComment::class, 'parent_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
