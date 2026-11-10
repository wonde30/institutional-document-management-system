<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DispatchMethod extends Model
{
    use HasFactory;

    protected $table = 'dispatch_methods';
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
