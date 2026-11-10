<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTaskDecision extends Model
{
    use HasFactory;

    protected $table = 'workflow_task_decisions';
    public $timestamps = false;

    protected $fillable = [
        'task_id',
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
    public function task(): BelongsTo
    {
        return $this->belongsTo(WorkflowTask::class, 'task_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
