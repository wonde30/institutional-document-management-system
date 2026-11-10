<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferenceSequence extends Model
{
    use HasFactory;

    protected $table = 'reference_sequences';
    public $timestamps = false;

    protected $fillable = [
        'sequence_key',
        'period_key',
        'next_value',
    ];

    protected function casts(): array
    {
        return [

        ];
    }
}
