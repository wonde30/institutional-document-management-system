<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchAuthor extends Model
{
    use HasFactory;

    protected $table = 'research_authors';
    public $timestamps = false;

    protected $fillable = [
        'research_id',
        'staff_id',
        'external_name',
        'author_order',
        'contribution',
    ];

    protected function casts(): array
    {
        return [
            'author_order' => 'integer',
        ];
    }
    public function research(): BelongsTo
    {
        return $this->belongsTo(ResearchRecord::class, 'research_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'staff_id');
    }
}
