<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccreditationStandard extends Model
{
    use HasFactory;

    protected $table = 'accreditation_standards';
    public $timestamps = false;

    protected $fillable = [
        'framework_version_id',
        'parent_id',
        'code',
        'title',
        'description',
    ];

    protected function casts(): array
    {
        return [

        ];
    }
    public function frameworkVersion(): BelongsTo
    {
        return $this->belongsTo(AccreditationFrameworkVersion::class, 'framework_version_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(AccreditationStandard::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(AccreditationStandard::class, 'parent_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(AccreditationEvidence::class, 'standard_id');
    }
}
