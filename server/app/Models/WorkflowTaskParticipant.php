<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTaskParticipant extends Model
{
    use HasFactory;

    protected $table = 'workflow_task_participants';
    public $timestamps = false;

    protected $fillable = [
        'task_id',
        'user_id',
        'participation_role',
        'status',
        'assigned_at',
        'responded_at',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->assigned_at)) {
                $model->assigned_at = now()->format('Y-m-d H:i:s.u');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(WorkflowTask::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
