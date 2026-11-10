<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DigitalSignature extends Model
{
    use HasFactory;

    protected $table = 'digital_signatures';
    public $timestamps = false;

    protected $fillable = [
        'request_id',
        'signing_identity_id',
        'source_file_id',
        'signed_file_id',
        'signature_format',
        'signed_at',
        'provider_transaction_reference',
        'verification_status',
        'trust_tier',
        'certificate_metadata',
    ];

    protected function casts(): array
    {
        return [
            'signed_at' => 'datetime',
            'certificate_metadata' => 'array',
        ];
    }
    public function request(): BelongsTo
    {
        return $this->belongsTo(SignatureRequest::class, 'request_id');
    }

    public function signingIdentity(): BelongsTo
    {
        return $this->belongsTo(SigningIdentity::class, 'signing_identity_id');
    }

    public function sourceFile(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'source_file_id');
    }

    public function signedFile(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'signed_file_id');
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(SignatureVerification::class, 'signature_id');
    }
}
