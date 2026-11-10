<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffQualification extends Model
{
    use HasFactory;

    protected $table = 'staff_qualifications';
    public $timestamps = false;

    protected $fillable = [
        'staff_id',
        'qualification_type_id',
        'institution_id',
        'field_of_study',
        'awarded_on',
        'evidence_document_id',
    ];

    protected function casts(): array
    {
        return [
            'awarded_on' => 'date',
        ];
    }
    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'staff_id');
    }

    public function qualificationType(): BelongsTo
    {
        return $this->belongsTo(QualificationType::class, 'qualification_type_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'institution_id');
    }

    public function evidenceDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'evidence_document_id');
    }
}
