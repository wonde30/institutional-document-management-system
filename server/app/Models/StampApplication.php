<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $stamp_version_id
 * @property int $document_version_id
 * @property int|null $source_file_id
 * @property int|null $result_file_id
 * @property int|null $applied_by
 * @property \Carbon\Carbon|null $applied_at
 * @property string|null $reason
 * @property \App\Models\StampVersion|null $stampVersion
 * @property \App\Models\DocumentVersion|null $documentVersion
 * @property \App\Models\FileObject|null $sourceFile
 * @property \App\Models\FileObject|null $resultFile
 * @property \App\Models\User|null $appliedBy
 */
class StampApplication extends Model
{
    use HasFactory;

    protected $table = 'stamp_applications';
    public $timestamps = false;

    protected $fillable = [
        'stamp_version_id',
        'document_version_id',
        'source_file_id',
        'result_file_id',
        'applied_by',
        'applied_at',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'applied_at' => 'datetime',
        ];
    }
    /**
     * @return BelongsTo<StampVersion, $this>
     */
    public function stampVersion(): BelongsTo
    {
        return $this->belongsTo(StampVersion::class, 'stamp_version_id');
    }

    /**
     * @return BelongsTo<DocumentVersion, $this>
     */
    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    public function sourceFile(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'source_file_id');
    }

    public function resultFile(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'result_file_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }
}
