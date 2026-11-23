<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResearchRecord extends Model
{
    use HasFactory;

    protected $table = 'research_records';
    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'research_type',
        'completed_on',
        'external_selection_reference',
        'workflow_status',
        'submitted_by',
        'reviewed_by',
        'approved_by',
        'rejected_by',
        'submitted_at',
        'decision_remarks',
        'recorded_by',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_on' => 'date',
            'recorded_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function authors(): HasMany
    {
        return $this->hasMany(ResearchAuthor::class, 'research_id')->orderBy('author_order');
    }

    public function publications(): BelongsToMany
    {
        return $this->belongsToMany(Publication::class, 'research_publications', 'research_id', 'publication_id');
    }
}
