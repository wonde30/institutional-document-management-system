<?php

namespace App\Models;

use App\Models\Concerns\GuardsVersionImmutability;
use App\Models\Concerns\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $document_version_id
 * @property int $file_object_id
 * @property string|null $purpose
 * @property int|null $display_order
 * @property int|null $primary_slot
 * @property \App\Models\DocumentVersion $documentVersion
 * @property \App\Models\FileObject|null $fileObject
 */
class VersionFile extends Model
{
    use HasFactory, HasCompositePrimaryKey, GuardsVersionImmutability;

    protected $table = 'version_files';
    public $timestamps = false;

    protected $fillable = [
        'document_version_id',
        'file_object_id',
        'purpose',
        'display_order',
    ];

    /**
     * Get the composite primary key columns.
     *
     * @return array<int, string>
     */
    public function getCompositeKey(): array
    {
        return ['document_version_id', 'file_object_id'];
    }

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'primary_slot' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<DocumentVersion, $this>
     */
    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    /**
     * @return BelongsTo<FileObject, $this>
     */
    public function fileObject(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'file_object_id');
    }
}
