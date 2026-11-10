<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrespondenceDispatch extends Model
{
    use HasFactory;

    protected $table = 'correspondence_dispatches';

    public $timestamps = false;

    protected $fillable = [
        'participant_id',
        'document_version_id',
        'method_id',
        'dispatched_by',
        'dispatched_at',
        'tracking_reference',
        'evidence_file_id',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(CorrespondenceParticipant::class, 'participant_id');
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(DispatchMethod::class, 'method_id');
    }

    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function evidenceFile(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'evidence_file_id');
    }
}
