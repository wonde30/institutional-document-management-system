<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    /**
     * @return BelongsTo<Document, $this>
     */
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

    public function dispatches(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            CorrespondenceDispatch::class,
            CorrespondenceParticipant::class,
            'correspondence_id',
            'participant_id'
        );
    }

    public function movements(): HasMany
    {
        return $this->hasMany(DocumentMovement::class, 'document_id', 'document_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DocumentAssignment::class, 'document_id', 'document_id');
    }

    /**
     * Scope a query to only include correspondence accessible by the given user for a specific capability.
     */
    public function scopeAccessibleBy(Builder $query, ?User $user, string $permission = 'correspondence.view'): Builder
    {
        if (! $user || $user->status !== 'active') {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasPermission('system.configure')) {
            return $query;
        }

        $permittedUnitIds = $user->getPermittedUnitIds($permission);
        $userId = $user->id;

        return $query->where(function (Builder $q) use ($permittedUnitIds, $user, $userId) {
            // Accessible through the underlying document's accessibility scope
            $q->whereHas('document', fn ($dq) => $dq->accessibleBy($user, 'documents.view'))
              // Or direct correspondence participation/movement/assignment in user's permitted units
              ->orWhere(function (Builder $cq) use ($permittedUnitIds, $userId) {
                  if ($permittedUnitIds === null) {
                      $cq->whereRaw('1 = 1');
                  } else {
                      $cq->whereIn('correspondences.first_receiving_unit_id', $permittedUnitIds)
                         ->orWhereHas('movements', fn ($mq) => $mq->whereIn('to_unit_id', $permittedUnitIds)->orWhereIn('from_unit_id', $permittedUnitIds))
                         ->orWhereHas('assignments', fn ($aq) => $aq->whereIn('assigned_unit_id', $permittedUnitIds)->orWhere('assigned_user_id', $userId))
                         ->orWhereHas('participants.party', fn ($pq) => $pq->where('user_id', $userId)->orWhereIn('unit_id', $permittedUnitIds));
                  }
              });
        });
    }
}
