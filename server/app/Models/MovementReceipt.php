<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovementReceipt extends Model
{
    use HasFactory;

    protected $table = 'movement_receipts';
    public $timestamps = false;
    protected $primaryKey = 'movement_id';

    protected $fillable = [
        'movement_id',
        'acknowledged_by',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'acknowledged_at' => 'datetime',
        ];
    }
    public function movement(): BelongsTo
    {
        return $this->belongsTo(DocumentMovement::class, 'movement_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
