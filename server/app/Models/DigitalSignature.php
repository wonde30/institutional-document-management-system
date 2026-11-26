<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $request_id
 * @property int $signing_identity_id
 * @property int|null $source_file_id
 * @property int|null $signed_file_id
 * @property string $signature_format
 * @property \Carbon\Carbon|null $signed_at
 * @property string|null $provider_transaction_reference
 * @property string $verification_status
 * @property string $trust_tier
 * @property array|null $certificate_metadata
 * @property \App\Models\SignatureRequest|null $request
 * @property \App\Models\SigningIdentity|null $signingIdentity
 * @property \App\Models\FileObject|null $sourceFile
 * @property \App\Models\FileObject|null $signedFile
 */
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
    /**
     * @return BelongsTo<SignatureRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(SignatureRequest::class, 'request_id');
    }

    /**
     * @return BelongsTo<SigningIdentity, $this>
     */
    public function signingIdentity(): BelongsTo
    {
        return $this->belongsTo(SigningIdentity::class, 'signing_identity_id');
    }

    public function sourceFile(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'source_file_id');
    }

    /**
     * @return BelongsTo<FileObject, $this>
     */
    public function signedFile(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'signed_file_id');
    }

    /**
     * @return HasMany<SignatureVerification, $this>
     */
    public function verifications(): HasMany
    {
        return $this->hasMany(SignatureVerification::class, 'signature_id');
    }
}
