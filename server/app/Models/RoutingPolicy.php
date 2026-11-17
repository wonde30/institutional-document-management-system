<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutingPolicy extends Model
{
    use HasFactory;

    protected $table = 'routing_policies';
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'code',
        'name',
        'from_unit_id',
        'to_unit_id',
        'correspondence_type_id',
        'document_category_id',
        'priority_id',
        'default_assignee_role_id',
        'sla_hours',
        'escalation_unit_id',
        'active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'from_unit_id' => 'integer',
            'to_unit_id' => 'integer',
            'sla_hours' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function fromUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'from_unit_id');
    }

    public function toUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'to_unit_id');
    }

    public function correspondenceType(): BelongsTo
    {
        return $this->belongsTo(CorrespondenceType::class, 'correspondence_type_id');
    }

    public function documentCategory(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(Priority::class, 'priority_id');
    }

    public function defaultAssigneeRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'default_assignee_role_id');
    }

    public function escalationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'escalation_unit_id');
    }
}
