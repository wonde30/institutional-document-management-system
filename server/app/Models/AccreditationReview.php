<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccreditationReview extends Model
{
    use HasFactory;

    protected $table = 'accreditation_reviews';
    public $timestamps = false;

    protected $fillable = [
        'evidence_id',
        'reviewer_id',
        'decision',
        'comment',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }
    public function evidence(): BelongsTo
    {
        return $this->belongsTo(AccreditationEvidence::class, 'evidence_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
