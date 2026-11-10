<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentEvent extends Model
{
    use HasFactory;

    protected $table = 'assignment_events';
    public $timestamps = false;

    protected $fillable = [
        'assignment_id',
        'sequence_number',
        'action',
        'actor_id',
        'target_user_id',
        'occurred_at',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'sequence_number' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(DocumentAssignment::class, 'assignment_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
