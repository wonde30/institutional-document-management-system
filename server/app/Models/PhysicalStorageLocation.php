<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhysicalStorageLocation extends Model
{
    use HasFactory;

    protected $table = 'physical_storage_locations';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'unit_id',
        'code',
        'name',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
    public function parent(): BelongsTo
    {
        return $this->belongsTo(PhysicalStorageLocation::class, 'parent_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'unit_id');
    }
}
