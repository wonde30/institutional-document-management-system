<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchiveAction extends Model
{
    use HasFactory;

    protected $table = 'archive_actions';
    public $timestamps = false;

    protected $fillable = [
        'archive_entry_id',
        'action',
        'actor_id',
        'occurred_at',
        'reason',
        'evidence_document_id',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }
    public function archiveEntry(): BelongsTo
    {
        return $this->belongsTo(ArchiveEntry::class, 'archive_entry_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function evidenceDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'evidence_document_id');
    }
}
