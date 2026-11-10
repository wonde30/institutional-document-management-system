<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowEvent extends Model
{
    use HasFactory;

    protected $table = 'workflow_events';
    public $timestamps = false;

    protected $fillable = [
        'instance_id',
        'sequence_number',
        'transition_id',
        'actor_id',
        'event_kind',
        'reason',
        'occurred_at',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'sequence_number' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }
    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'instance_id');
    }

    public function transition(): BelongsTo
    {
        return $this->belongsTo(WorkflowTransition::class, 'transition_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
