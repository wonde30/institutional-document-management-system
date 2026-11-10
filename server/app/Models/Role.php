<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $table = 'roles';
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'code',
        'name',
        'description',
        'active',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'is_system' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class, 'role_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')
            ->withPivot(['scope_id', 'granted_by', 'granted_at', 'expires_at']);
    }
}
