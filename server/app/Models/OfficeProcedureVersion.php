<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficeProcedureVersion extends Model
{
    use HasFactory;

    protected $table = 'office_procedure_versions';
    public $timestamps = false;

    protected $fillable = [
        'unit_id',
        'correspondence_type_id',
        'purpose_code',
        'version_number',
        'receipt_mode',
        'registry_unit_id',
        'issue_mode',
        'workflow_version_id',
        'confirmed_by',
        'confirmation_source',
        'confirmed_at',
        'effective_from',
        'effective_until',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'confirmed_at' => 'datetime',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
        ];
    }
    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'unit_id');
    }

    public function correspondenceType(): BelongsTo
    {
        return $this->belongsTo(CorrespondenceType::class, 'correspondence_type_id');
    }

    public function registryUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'registry_unit_id');
    }

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
