<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectProposalWorkflow extends Model
{
    use HasFactory;

    protected $table = 'project_proposal_workflows';
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'proposal_id',
        'workflow_instance_id',
    ];

    protected function casts(): array
    {
        return [

        ];
    }
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(ProjectProposal::class, 'proposal_id');
    }

    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }
}
