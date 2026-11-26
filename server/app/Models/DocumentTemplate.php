<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int|null $category_id
 * @property int|null $owner_unit_id
 * @property string $visibility_scope
 * @property bool $is_active
 * @property bool $active
 */
class DocumentTemplate extends Model
{
    use HasFactory;

    protected $table = 'document_templates';
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'code',
        'name',
        'description',
        'category_id',
        'owner_unit_id',
        'visibility_scope',
        'is_active',
        'created_by',
    ];

    protected $appends = ['active'];

    public function getActiveAttribute(): bool
    {
        return (bool) $this->is_active;
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'category_id');
    }

    public function ownerUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'owner_unit_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(TemplateVersion::class, 'template_id')->orderByDesc('version_number');
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(TemplateVersion::class, 'template_id')->ofMany('version_number', 'max');
    }

    public function publishedVersion(): HasOne
    {
        return $this->hasOne(TemplateVersion::class, 'template_id')
            ->ofMany(['version_number' => 'max'], function ($query) {
                $query->where('status', 'published');
            });
    }
}
