<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdempotencyRecord extends Model
{
    use HasFactory;

    protected $table = 'idempotency_records';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'operation_key',
        'idempotency_key',
        'request_hash',
        'result_reference',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
