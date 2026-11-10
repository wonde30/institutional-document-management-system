<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchPublication extends Model
{
    use HasFactory;

    protected $table = 'research_publications';
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'research_id',
        'publication_id',
    ];

    protected function casts(): array
    {
        return [

        ];
    }
    public function research(): BelongsTo
    {
        return $this->belongsTo(ResearchRecord::class, 'research_id');
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class, 'publication_id');
    }
}
