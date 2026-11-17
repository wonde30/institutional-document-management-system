<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RetentionPolicy extends Model
{
    use HasFactory;

    protected $table = 'retention_policies';
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'description',
        'document_category_id',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'document_category_id' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(RetentionPolicyVersion::class, 'policy_id')->orderByDesc('version_number');
    }

    public function activeVersion(): HasOne
    {
        return $this->hasOne(RetentionPolicyVersion::class, 'policy_id')->latestOfMany('version_number');
    }

    public function documentCategory(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }
}
