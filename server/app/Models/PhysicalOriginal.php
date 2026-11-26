<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhysicalOriginal extends Model
{
    use HasFactory;

    protected $table = 'physical_originals';
    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'physical_reference',
        'retention_requirement',
        'retention_policy_version_id',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function retentionPolicyVersion(): BelongsTo
    {
        return $this->belongsTo(RetentionPolicyVersion::class, 'retention_policy_version_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function custodyEvents(): HasMany
    {
        return $this->hasMany(PhysicalCustodyEvent::class, 'physical_original_id')->orderByDesc('occurred_at');
    }

    /**
     * @return HasMany<DigitizationRecord, $this>
     */
    public function digitizationRecords(): HasMany
    {
        return $this->hasMany(DigitizationRecord::class, 'physical_original_id');
    }
}
