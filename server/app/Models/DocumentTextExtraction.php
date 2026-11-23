<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentTextExtraction extends Model
{
    use HasFactory;

    protected $table = 'document_text_extractions';
    public $timestamps = false;

    protected $fillable = [
        'document_version_id',
        'file_object_id',
        'raw_text',
        'verified_text',
        'searchable_text',
        'language_detected',
        'confidence_score',
        'ocr_engine',
        'extracted_at',
        'verified_by',
        'verified_at',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->extracted_at)) {
                $model->extracted_at = now()->format('Y-m-d H:i:s.u');
            }
            if (empty($model->searchable_text)) {
                $model->searchable_text = $model->verified_text ?? $model->raw_text;
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty('verified_text') && empty($model->searchable_text)) {
                $model->searchable_text = $model->verified_text ?? $model->raw_text;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'confidence_score' => 'decimal:2',
            'extracted_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DocumentVersion, $this>
     */
    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    public function fileObject(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'file_object_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
