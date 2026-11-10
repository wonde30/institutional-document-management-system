<?php

namespace App\Models;

use App\Models\Concerns\GuardsVersionImmutability;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentVersion extends Model
{
    use HasFactory, GuardsVersionImmutability;

    protected $table = 'document_versions';
    public $timestamps = false;

    protected $attributes = [
        'ingestion_method' => 'digital_upload',
    ];

    protected $fillable = [
        'document_id',
        'version_number',
        'ingestion_method',
        'template_version_id',
        'template_data',
        'created_by',
        'change_note',
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
            'template_data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class, 'template_version_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versionFiles(): HasMany
    {
        return $this->hasMany(VersionFile::class, 'document_version_id');
    }

    public function fileObjects(): BelongsToMany
    {
        return $this->belongsToMany(FileObject::class, 'version_files', 'document_version_id', 'file_object_id')
            ->withPivot(['purpose', 'display_order', 'primary_slot']);
    }

    public function textExtractions(): HasMany
    {
        return $this->hasMany(DocumentTextExtraction::class, 'document_version_id');
    }

    public function workflowInstances(): HasMany
    {
        return $this->hasMany(WorkflowInstance::class, 'document_version_id');
    }
}
