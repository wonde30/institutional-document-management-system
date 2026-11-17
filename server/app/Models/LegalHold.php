<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalHold extends Model
{
    use HasFactory;

    protected $table = 'legal_holds';
    public $timestamps = false;

    protected $fillable = [
        'code',
        'reason',
        'placed_by',
        'placed_at',
        'released_by',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'placed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }
    public function placedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'placed_by');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function documents(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'legal_hold_documents', 'hold_id', 'document_id');
    }
}
