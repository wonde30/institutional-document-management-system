<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispositionDecision extends Model
{
    use HasFactory;

    protected $table = 'disposition_decisions';
    public $timestamps = false;

    protected $fillable = [
        'request_id',
        'decided_by',
        'decision',
        'reason',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }
    public function request(): BelongsTo
    {
        return $this->belongsTo(DispositionRequest::class, 'request_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
