<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationUnit extends Model
{
    use HasFactory;

    protected $table = 'organization_units';
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'parent_id',
        'unit_type_id',
        'code',
        'name',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'parent_id');
    }

    /**
     * Get all ancestor organization unit IDs in ascending order to root.
     *
     * @return list<int>
     */
    public function getAncestorIds(): array
    {
        $ancestorIds = [];
        $current = $this->parent;
        $visited = [$this->id];

        while ($current && ! in_array($current->id, $visited, true)) {
            $ancestorIds[] = (int) $current->id;
            $visited[] = (int) $current->id;
            $current = $current->parent;
        }

        return $ancestorIds;
    }

    /**
     * Get all descendant organization unit IDs recursively.
     *
     * @return list<int>
     */
    public function getDescendantIds(): array
    {
        $descendants = [];
        $queue = [$this->id];

        while (! empty($queue)) {
            $parentId = array_shift($queue);
            $childIds = self::where('parent_id', $parentId)->pluck('id')->all();

            foreach ($childIds as $cId) {
                $cIdInt = (int) $cId;
                if (! in_array($cIdInt, $descendants, true)) {
                    $descendants[] = $cIdInt;
                    $queue[] = $cIdInt;
                }
            }
        }

        return $descendants;
    }

    public function children(): HasMany
    {
        return $this->hasMany(OrganizationUnit::class, 'parent_id');
    }

    public function unitType(): BelongsTo
    {
        return $this->belongsTo(UnitType::class, 'unit_type_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class, 'unit_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'owner_unit_id');
    }

    public function authorizationScopes(): HasMany
    {
        return $this->hasMany(AuthorizationScope::class, 'unit_id');
    }
}
