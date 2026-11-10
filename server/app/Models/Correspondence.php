<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Correspondence extends Model
{
    use HasFactory;

    protected $table = 'correspondences';
    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'type_id',
        'priority_id',
        'purpose_code',
        'external_reference',
        'correspondence_date',
        'first_receiving_unit_id',
        'received_at',
        'requested_due_at',
        'registered_by',
        'registered_at',
    ];

    protected function casts(): array
    {
        return [
            'correspondence_date' => 'date',
            'received_at' => 'datetime',
            'requested_due_at' => 'datetime',
            'registered_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(CorrespondenceType::class, 'type_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(Priority::class, 'priority_id');
    }

    public function firstReceivingUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'first_receiving_unit_id');
    }

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(CorrespondenceParticipant::class, 'correspondence_id');
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(CorrespondenceDispatch::class, 'correspondence_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(DocumentMovement::class, 'document_id', 'document_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DocumentAssignment::class, 'document_id', 'document_id');
    }
}
