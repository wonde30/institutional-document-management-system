<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $participant_id
 * @property int $document_version_id
 * @property int|null $method_id
 * @property int|null $dispatched_by
 * @property \Carbon\Carbon|null $dispatched_at
 * @property string|null $tracking_reference
 * @property int|null $evidence_file_id
 * @property string|null $idempotency_key
 * @property \App\Models\FileObject|null $evidenceFile
 * @property \App\Models\DocumentVersion|null $documentVersion
 * @property \App\Models\CorrespondenceParticipant|null $participant
 */
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

    /**
     * @return BelongsTo<CorrespondenceParticipant, $this>
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(CorrespondenceParticipant::class, 'participant_id');
    }

    /**
     * @return BelongsTo<DocumentVersion, $this>
     */
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

    /**
     * @return BelongsTo<FileObject, $this>
     */
    public function evidenceFile(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'evidence_file_id');
    }
}
