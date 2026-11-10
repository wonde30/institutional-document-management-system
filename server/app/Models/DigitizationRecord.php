<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigitizationRecord extends Model
{
    use HasFactory;

    protected $table = 'digitization_records';
    public $timestamps = false;

    protected $fillable = [
        'physical_original_id',
        'document_version_id',
        'digitized_by',
        'digitized_at',
        'verification_status',
        'verified_by',
        'verified_at',
        'verification_note',
    ];

    protected function casts(): array
    {
        return [
            'digitized_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }
    public function physicalOriginal(): BelongsTo
    {
        return $this->belongsTo(PhysicalOriginal::class, 'physical_original_id');
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    public function digitizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'digitized_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
