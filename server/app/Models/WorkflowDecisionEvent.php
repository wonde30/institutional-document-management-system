<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowDecisionEvent extends Model
{
    use HasFactory;

    protected $table = 'workflow_decision_events';
    public $timestamps = false;
    protected $primaryKey = 'decision_id';
    public $incrementing = false;

    protected $fillable = [
        'decision_id',
        'event_id',
    ];

    protected function casts(): array
    {
        return [

        ];
    }
    public function decision(): BelongsTo
    {
        return $this->belongsTo(WorkflowTaskDecision::class, 'decision_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(WorkflowEvent::class, 'event_id');
    }
}
