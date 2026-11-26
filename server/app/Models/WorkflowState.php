<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $workflow_version_id
 * @property string $code
 * @property string $name
 * @property bool $is_initial
 * @property bool $is_terminal
 * @property int|null $assigned_role_id
 * @property int|null $assigned_unit_id
 * @property int|null $sla_hours
 */
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
        'assigned_role_id',
        'assigned_unit_id',
        'sla_hours',
    ];

    protected function casts(): array
    {
        return [
            'is_initial' => 'boolean',
            'is_terminal' => 'boolean',
            'initial_slot' => 'integer',
            'assigned_role_id' => 'integer',
            'assigned_unit_id' => 'integer',
            'sla_hours' => 'integer',
        ];
    }

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }

    public function assignedRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'assigned_role_id');
    }

    public function assignedUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'assigned_unit_id');
    }

    public function outgoingTransitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class, 'from_state_id');
    }

    public function incomingTransitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class, 'to_state_id');
    }
}
