<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QualificationType extends Model
{
    use HasFactory;

    protected $table = 'qualification_types';
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'description',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
