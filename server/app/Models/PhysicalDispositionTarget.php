<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhysicalDispositionTarget extends Model
{
    use HasFactory;

    protected $table = 'physical_disposition_targets';
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'request_id',
        'physical_original_id',
    ];

    protected function casts(): array
    {
        return [

        ];
    }
    public function request(): BelongsTo
    {
        return $this->belongsTo(DispositionRequest::class, 'request_id');
    }

    public function physicalOriginal(): BelongsTo
    {
        return $this->belongsTo(PhysicalOriginal::class, 'physical_original_id');
    }
}
