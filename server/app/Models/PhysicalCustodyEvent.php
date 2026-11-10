<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhysicalCustodyEvent extends Model
{
    use HasFactory;

    protected $table = 'physical_custody_events';
    public $timestamps = false;

    protected $fillable = [
        'physical_original_id',
        'location_id',
        'custodian_id',
        'action',
        'actor_id',
        'occurred_at',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }
    public function physicalOriginal(): BelongsTo
    {
        return $this->belongsTo(PhysicalOriginal::class, 'physical_original_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(PhysicalStorageLocation::class, 'location_id');
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custodian_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
