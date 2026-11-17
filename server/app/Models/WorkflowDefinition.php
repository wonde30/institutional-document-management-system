<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WorkflowDefinition extends Model
{
    use HasFactory;

    protected $table = 'workflow_definitions';
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
            'active' => 'boolean',
            'document_category_id' => 'integer',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WorkflowVersion::class, 'definition_id')->orderByDesc('version_number');
    }

    public function activeVersion(): HasOne
    {
        return $this->hasOne(WorkflowVersion::class, 'definition_id')
            ->whereNotNull('published_at')
            ->latestOfMany('version_number');
    }

    public function documentCategory(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }
}
