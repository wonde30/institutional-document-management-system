<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StampDefinition extends Model
{
    use HasFactory;

    protected $table = 'stamp_definitions';
    public $timestamps = false;

    protected $fillable = [
        'unit_id',
        'name',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'unit_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(StampVersion::class, 'stamp_id');
    }
}
