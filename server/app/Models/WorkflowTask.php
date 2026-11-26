<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WorkflowTask extends Model
{
    use HasFactory;

    protected $table = 'workflow_tasks';
    public $timestamps = false;

    protected $fillable = [
        'instance_id',
        'state_id',
        'assigned_user_id',
        'assigned_unit_id',
        'required_role_id',
        'delegated_to_user_id',
        'delegated_by',
        'delegation_reason',
        'branch_key',
        'created_at',
        'due_at',
        'closed_at',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->created_at)) {
                $model->created_at = now()->format('Y-m-d H:i:s.u');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'due_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WorkflowInstance, $this>
     */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'instance_id');
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(WorkflowState::class, 'state_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function assignedUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'assigned_unit_id');
    }

    public function requiredRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'required_role_id');
    }

    public function delegatedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegated_to_user_id');
    }

    public function delegatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegated_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(WorkflowTaskParticipant::class, 'task_id');
    }

    public function participantUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workflow_task_participants', 'task_id', 'user_id')
            ->withPivot(['participation_role', 'status', 'assigned_at', 'responded_at']);
    }

    public function decision(): HasOne
    {
        return $this->hasOne(WorkflowTaskDecision::class, 'task_id');
    }
}
