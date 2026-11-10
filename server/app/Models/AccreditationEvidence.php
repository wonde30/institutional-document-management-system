<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AccreditationEvidence extends Model
{
    use HasFactory;

    protected $table = 'accreditation_evidence';
    public $timestamps = false;

    protected $fillable = [
        'cycle_id',
        'standard_id',
        'document_version_id',
        'submitted_by',
        'submitted_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AccreditationCycle::class, 'cycle_id');
    }

    public function standard(): BelongsTo
    {
        return $this->belongsTo(AccreditationStandard::class, 'standard_id');
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(AccreditationReview::class, 'evidence_id');
    }

    public function latestReview(): HasOne
    {
        return $this->hasOne(AccreditationReview::class, 'evidence_id')->latestOfMany('id');
    }
}
