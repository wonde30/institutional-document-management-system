<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispositionRequest extends Model
{
    use HasFactory;

    protected $table = 'disposition_requests';
    public $timestamps = false;

    protected $fillable = [
        'archive_entry_id',
        'requested_by',
        'requested_action',
        'requested_at',
        'justification',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
        ];
    }
    public function archiveEntry(): BelongsTo
    {
        return $this->belongsTo(ArchiveEntry::class, 'archive_entry_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decision(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(DispositionDecision::class, 'request_id');
    }

    public function execution(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(DispositionExecution::class, 'request_id');
    }
}
