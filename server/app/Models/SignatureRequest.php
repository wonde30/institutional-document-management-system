<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SignatureRequest extends Model
{
    use HasFactory;

    protected $table = 'signature_requests';
    public $timestamps = false;

    protected $fillable = [
        'document_version_id',
        'requested_by',
        'signer_id',
        'requested_at',
        'expires_at',
        'purpose',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
    /**
     * @return BelongsTo<DocumentVersion, $this>
     */
    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_id');
    }

    /**
     * @return HasOne<DigitalSignature, $this>
     */
    public function digitalSignature(): HasOne
    {
        return $this->hasOne(DigitalSignature::class, 'request_id');
    }
}
