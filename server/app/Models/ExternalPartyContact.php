<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalPartyContact extends Model
{
    use HasFactory;

    protected $table = 'external_party_contacts';
    public $timestamps = false;

    protected $fillable = [
        'external_party_id',
        'contact_type',
        'value',
        'label',
    ];

    protected function casts(): array
    {
        return [

        ];
    }
    public function externalParty(): BelongsTo
    {
        return $this->belongsTo(ExternalParty::class, 'external_party_id');
    }
}
