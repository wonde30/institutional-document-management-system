<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignatureVerification extends Model
{
    use HasFactory;

    protected $table = 'signature_verifications';
    public $timestamps = false;

    protected $fillable = [
        'signature_id',
        'verified_at',
        'outcome',
        'certificate_status',
        'timestamp_status',
        'details_json',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'details_json' => 'array',
        ];
    }
    public function signature(): BelongsTo
    {
        return $this->belongsTo(DigitalSignature::class, 'signature_id');
    }
}
