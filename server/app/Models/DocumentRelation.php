<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRelation extends Model
{
    use HasFactory;

    protected $table = 'document_relations';
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'source_id',
        'target_id',
        'relation_type_id',
    ];

    protected function casts(): array
    {
        return [

        ];
    }
    public function source(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'source_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'target_id');
    }

    public function relationType(): BelongsTo
    {
        return $this->belongsTo(DocumentRelationType::class, 'relation_type_id');
    }
}
