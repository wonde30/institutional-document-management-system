<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowState extends Model
{
    use HasFactory;

    protected $table = 'workflow_states';
    public $timestamps = false;

    protected $fillable = [
        'workflow_version_id',
        'code',
        'name',
        'is_initial',
        'is_terminal',
        'initial_slot',
    ];

    protected function casts(): array
    {
        return [
            'is_initial' => 'boolean',
            'is_terminal' => 'boolean',
            'initial_slot' => 'integer',
        ];
    }
    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }
}
