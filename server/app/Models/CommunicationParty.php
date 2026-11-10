<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationParty extends Model
{
    use HasFactory;

    protected $table = 'communication_parties';
    public $timestamps = false;

    protected $fillable = [
        'unit_id',
        'user_id',
        'external_party_id',
    ];

    protected function casts(): array
    {
        return [

        ];
    }
    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'unit_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function externalParty(): BelongsTo
    {
        return $this->belongsTo(ExternalParty::class, 'external_party_id');
    }
}
