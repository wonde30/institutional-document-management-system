<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Publication extends Model
{
    use HasFactory;

    protected $table = 'publications';
    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'doi',
        'publication_type',
        'venue',
        'published_on',
    ];

    protected function casts(): array
    {
        return [
            'published_on' => 'date',
        ];
    }
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }
}
