<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccreditationFrameworkVersion extends Model
{
    use HasFactory;

    protected $table = 'accreditation_framework_versions';
    public $timestamps = false;

    protected $fillable = [
        'framework_id',
        'version_label',
        'effective_from',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
        ];
    }
    public function framework(): BelongsTo
    {
        return $this->belongsTo(AccreditationFramework::class, 'framework_id');
    }

    public function standards(): HasMany
    {
        return $this->hasMany(AccreditationStandard::class, 'framework_version_id');
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(AccreditationCycle::class, 'framework_version_id');
    }
}
