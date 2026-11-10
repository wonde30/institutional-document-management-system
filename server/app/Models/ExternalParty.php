<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExternalParty extends Model
{
    use HasFactory;

    protected $table = 'external_parties';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'party_type',
        'external_identifier',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
