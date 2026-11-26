<?php

namespace App\Models;

use App\Models\Concerns\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $user_id
 * @property int $role_id
 * @property int $scope_id
 * @property int|null $granted_by
 * @property \Carbon\Carbon|null $granted_at
 * @property \Carbon\Carbon|null $expires_at
 * @property \App\Models\User|null $user
 * @property \App\Models\Role|null $role
 * @property \App\Models\AuthorizationScope|null $scope
 * @property \App\Models\User|null $grantedBy
 */
class UserRole extends Model
{
    use HasCompositePrimaryKey, HasFactory;

    protected $table = 'user_roles';
    public $timestamps = false;
    public $incrementing = false;

    public function getCompositeKey(): array
    {
        return ['user_id', 'role_id', 'scope_id'];
    }

    protected static function booted(): void
    {
        static::saved(function ($model) {
            User::clearUserGrantsCache($model->user_id);
        });
        static::deleted(function ($model) {
            User::clearUserGrantsCache($model->user_id);
        });
    }

    protected $fillable = [
        'user_id',
        'role_id',
        'scope_id',
        'granted_by',
        'granted_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function scope(): BelongsTo
    {
        return $this->belongsTo(AuthorizationScope::class, 'scope_id');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
