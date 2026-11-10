<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentMovement extends Model
{
    use HasFactory;

    protected $table = 'document_movements';
    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'from_unit_id',
        'to_unit_id',
        'sent_by',
        'sent_at',
        'instruction',
        'due_at',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function fromUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'from_unit_id');
    }

    public function toUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'to_unit_id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function receipt(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(MovementReceipt::class, 'movement_id');
    }
}
