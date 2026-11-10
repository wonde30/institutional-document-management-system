<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutboxEvent extends Model
{
    use HasFactory;

    protected $table = 'outbox_events';
    public $timestamps = false;

    protected $fillable = [
        'event_type',
        'aggregate_type',
        'aggregate_id',
        'payload_json',
        'published_at',
        'attempt_count',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'created_at' => 'datetime',
            'published_at' => 'datetime',
            'attempt_count' => 'integer',
        ];
    }
}
