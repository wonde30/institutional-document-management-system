<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalHoldDocument extends Model
{
    use HasFactory;

    protected $table = 'legal_hold_documents';
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'hold_id',
        'document_id',
    ];

    protected function casts(): array
    {
        return [

        ];
    }
    public function hold(): BelongsTo
    {
        return $this->belongsTo(LegalHold::class, 'hold_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }
}
