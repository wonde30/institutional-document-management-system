<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $unit_id
 * @property string $position_title
 * @property \Carbon\Carbon|null $starts_at
 * @property \Carbon\Carbon|null $ends_at
 * @property int|null $created_by
 * @property \Carbon\Carbon|null $created_at
 * @property \App\Models\OrganizationUnit|null $unit
 * @property \App\Models\User|null $user
 */
class OrganizationMembership extends Model
{
    use HasFactory;

    protected $table = 'organization_memberships';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'unit_id',
        'position_title',
        'starts_at',
        'ends_at',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'unit_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
