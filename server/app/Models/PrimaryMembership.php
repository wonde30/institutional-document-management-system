<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $user_id
 * @property int $membership_id
 * @property \Carbon\Carbon|null $selected_at
 * @property \App\Models\OrganizationMembership|null $membership
 * @property \App\Models\User|null $user
 */
class PrimaryMembership extends Model
{
    use HasFactory;

    protected $table = 'primary_memberships';
    public $timestamps = false;
    protected $primaryKey = 'user_id';

    protected $fillable = [
        'user_id',
        'membership_id',
        'selected_at',
    ];

    protected function casts(): array
    {
        return [
            'selected_at' => 'datetime',
        ];
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(OrganizationMembership::class, 'membership_id');
    }
}
