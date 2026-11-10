<?php

namespace App\Models;

use App\Models\Concerns\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentResponse extends Model
{
    use HasFactory, HasCompositePrimaryKey;

    protected $table = 'assignment_responses';
    public $timestamps = false;

    protected $fillable = [
        'assignment_id',
        'response_document_id',
        'relationship_role',
        'is_fulfilling',
        'linked_by',
        'linked_at',
    ];

    /**
     * Get the composite primary key columns.
     *
     * @return array<int, string>
     */
    public function getCompositeKey(): array
    {
        return ['assignment_id', 'response_document_id'];
    }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->linked_at)) {
                $model->linked_at = now()->format('Y-m-d H:i:s.u');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_fulfilling' => 'boolean',
            'linked_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(DocumentAssignment::class, 'assignment_id');
    }

    public function responseDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'response_document_id');
    }

    public function linkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }
}
