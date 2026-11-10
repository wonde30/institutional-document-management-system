<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StampAuthorization extends Model
{
    use HasFactory;

    protected $table = 'stamp_authorizations';
    public $timestamps = false;

    protected $fillable = [
        'stamp_id',
        'user_id',
        'granted_by',
        'valid_from',
        'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
        ];
    }
    public function stamp(): BelongsTo
    {
        return $this->belongsTo(StampDefinition::class, 'stamp_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
