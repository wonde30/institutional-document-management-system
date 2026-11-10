<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccreditationCycle extends Model
{
    use HasFactory;

    protected $table = 'accreditation_cycles';
    public $timestamps = false;

    protected $fillable = [
        'framework_version_id',
        'unit_id',
        'name',
        'starts_on',
        'ends_on',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }
    public function frameworkVersion(): BelongsTo
    {
        return $this->belongsTo(AccreditationFrameworkVersion::class, 'framework_version_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'unit_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(AccreditationEvidence::class, 'cycle_id');
    }
}
