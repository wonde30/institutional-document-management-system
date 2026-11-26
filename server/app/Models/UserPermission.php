<?php

namespace App\Models;

use App\Models\Concerns\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPermission extends Model
{
    use HasCompositePrimaryKey;

    public $timestamps = false;
    public $incrementing = false;
    protected $table = 'user_permissions';

    public function getCompositeKey(): array
    {
        return ['user_id', 'permission_id', 'scope_id'];
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
        'permission_id',
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

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_id');
    }

    public function scope(): BelongsTo
    {
        return $this->belongsTo(AuthorizationScope::class, 'scope_id');
    }

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
