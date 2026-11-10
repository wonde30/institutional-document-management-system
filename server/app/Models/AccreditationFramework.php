<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccreditationFramework extends Model
{
    use HasFactory;

    protected $table = 'accreditation_frameworks';
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'description',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AccreditationFrameworkVersion::class, 'framework_id');
    }
}
