<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $document_id
 * @property int|null $collection_id
 * @property int|null $retention_policy_version_id
 * @property int|null $archived_by
 * @property \Carbon\Carbon|null $archived_at
 * @property \Carbon\Carbon|null $trigger_occurred_at
 * @property \Carbon\Carbon|null $next_review_at
 * @property \App\Models\Document $document
 * @property \App\Models\ArchiveCollection|null $collection
 * @property \App\Models\RetentionPolicyVersion|null $retentionPolicyVersion
 * @property \App\Models\User|null $archivedBy
 */
class ArchiveEntry extends Model
{
    use HasFactory;

    protected $table = 'archive_entries';
    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'collection_id',
        'retention_policy_version_id',
        'archived_by',
        'archived_at',
        'trigger_occurred_at',
        'next_review_at',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
            'trigger_occurred_at' => 'datetime',
            'next_review_at' => 'datetime',
        ];
    }
    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(ArchiveCollection::class, 'collection_id');
    }

    public function retentionPolicyVersion(): BelongsTo
    {
        return $this->belongsTo(RetentionPolicyVersion::class, 'retention_policy_version_id');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function dispositionRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DispositionRequest::class, 'archive_entry_id');
    }
}
