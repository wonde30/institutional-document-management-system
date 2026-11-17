<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditEvent extends Model
{
    use HasFactory;

    protected $table = 'audit_events';
    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Audit events are immutable and cannot be modified.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Audit events are permanent and cannot be deleted.');
        });
    }

    protected $fillable = [
        'actor_id',
        'action',
        'target_type',
        'target_id',
        'request_id',
        'occurred_at',
        'ip_address',
        'user_agent',
        'details_json',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'details_json' => 'array',
        ];
    }
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
