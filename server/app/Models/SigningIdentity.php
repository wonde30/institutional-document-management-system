<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SigningIdentity extends Model
{
    use HasFactory;

    protected $table = 'signing_identities';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'provider_code',
        'provider_identity_reference',
        'public_certificate_file_id',
        'valid_from',
        'valid_until',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function publicCertificateFile(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'public_certificate_file_id');
    }
}
