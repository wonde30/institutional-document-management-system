<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTransition extends Model
{
    use HasFactory;

    protected $table = 'workflow_transitions';
    public $timestamps = false;

    protected $fillable = [
        'from_state_id',
        'to_state_id',
        'action_code',
        'permission_id',
        'requires_reason',
        'requires_signature',
        'prohibit_self_approval',
        'approval_policy',
        'quorum_count',
        'quorum_percentage',
        'join_policy',
        'fork_state_ids',
    ];

    protected function casts(): array
    {
        return [
            'requires_reason' => 'boolean',
            'requires_signature' => 'boolean',
            'prohibit_self_approval' => 'boolean',
            'quorum_count' => 'integer',
            'quorum_percentage' => 'decimal:2',
            'fork_state_ids' => 'array',
        ];
    }

    public function fromState(): BelongsTo
    {
        return $this->belongsTo(WorkflowState::class, 'from_state_id');
    }

    public function toState(): BelongsTo
    {
        return $this->belongsTo(WorkflowState::class, 'to_state_id');
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_id');
    }
}
