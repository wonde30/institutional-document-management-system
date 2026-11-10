<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowInstance extends Model
{
    use HasFactory;

    protected $table = 'workflow_instances';
    public $timestamps = false;

    protected $fillable = [
        'document_version_id',
        'workflow_version_id',
        'started_by',
        'started_at',
        'parent_instance_id',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->started_at)) {
                $model->started_at = now()->format('Y-m-d H:i:s.u');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
        ];
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function parentInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'parent_instance_id');
    }

    public function childInstances(): HasMany
    {
        return $this->hasMany(WorkflowInstance::class, 'parent_instance_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(WorkflowTask::class, 'instance_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(WorkflowEvent::class, 'instance_id')->orderBy('sequence_number');
    }
}
