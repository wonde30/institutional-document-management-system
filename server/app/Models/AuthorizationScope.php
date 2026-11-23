<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $kind
 * @property int|null $unit_id
 * @property \App\Models\OrganizationUnit|null $unit
 */
class AuthorizationScope extends Model
{
    use HasFactory;

    protected $table = 'authorization_scopes';
    public $timestamps = false;

    protected $fillable = [
        'kind',
        'unit_id',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'unit_id');
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class, 'scope_id');
    }

    public function userPermissions(): HasMany
    {
        return $this->hasMany(UserPermission::class, 'scope_id');
    }
}
