<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentAssignment extends Model
{
    use HasFactory;

    protected $table = 'document_assignments';
    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'assigned_user_id',
        'assigned_unit_id',
        'assigned_by',
        'assigned_at',
        'due_at',
        'instruction',
    ];

    protected $appends = [
        'status',
    ];

    public function getStatusAttribute(): string
    {
        $latestEvent = $this->events()->latest('sequence_number')->first();

        return $latestEvent ? $latestEvent->action : 'assigned';
    }

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function assignedUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'assigned_unit_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(AssignmentParticipant::class, 'assignment_id');
    }

    public function participantUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'assignment_participants', 'assignment_id', 'user_id')
            ->withPivot(['role_code', 'status', 'assigned_at', 'responded_at']);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(AssignmentResponse::class, 'assignment_id');
    }

    public function fulfillingResponses(): HasMany
    {
        return $this->hasMany(AssignmentResponse::class, 'assignment_id')
            ->where('is_fulfilling', true);
    }

    public function responseDocuments(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'assignment_responses', 'assignment_id', 'response_document_id')
            ->withPivot(['relationship_role', 'is_fulfilling', 'linked_by', 'linked_at']);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AssignmentEvent::class, 'assignment_id')->orderBy('sequence_number');
    }
}
