<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffCertificate extends Model
{
    use HasFactory;

    protected $table = 'staff_certificates';
    public $timestamps = false;

    protected $fillable = [
        'staff_id',
        'title',
        'issuer_id',
        'issued_on',
        'expires_on',
        'evidence_document_id',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'expires_on' => 'date',
        ];
    }
    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'staff_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'issuer_id');
    }

    public function evidenceDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'evidence_document_id');
    }
}
