<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NumberingPolicy extends Model
{
    use HasFactory;

    protected $table = 'numbering_policies';
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'code',
        'name',
        'prefix',
        'include_unit_code',
        'include_category_code',
        'year_format',
        'sequence_length',
        'reset_policy',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'include_unit_code' => 'boolean',
            'include_category_code' => 'boolean',
            'sequence_length' => 'integer',
            'active' => 'boolean',
        ];
    }
}
