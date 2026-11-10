<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TemplateVersion extends Model
{
    use HasFactory;

    protected $table = 'template_versions';
    public $timestamps = false;

    protected $fillable = [
        'template_id',
        'version_number',
        'schema_definition',
        'body_template_html',
        'header_asset_key',
        'footer_asset_key',
        'status',
        'published_at',
        'published_by',
        'created_by',
        'created_at',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->created_at)) {
                $model->created_at = now()->format('Y-m-d H:i:s.u');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'schema_definition' => 'array',
            'published_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documentVersions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class, 'template_version_id');
    }
}
