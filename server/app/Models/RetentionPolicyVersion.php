<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetentionPolicyVersion extends Model
{
    use HasFactory;

    protected $table = 'retention_policy_versions';
    public $timestamps = false;

    protected $fillable = [
        'policy_id',
        'version_number',
        'trigger_code',
        'duration_months',
        'disposition_action',
        'approved_by',
        'approved_at',
        'effective_from',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'duration_months' => 'integer',
            'approved_at' => 'datetime',
            'effective_from' => 'date',
        ];
    }
    public function policy(): BelongsTo
    {
        return $this->belongsTo(RetentionPolicy::class, 'policy_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
