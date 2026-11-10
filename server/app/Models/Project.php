<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Project extends Model
{
    use HasFactory;

    protected $table = 'projects';
    public $timestamps = false;

    protected $fillable = [
        'proposal_id',
        'code',
        'starts_on',
        'ends_on',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(ProjectProposal::class, 'proposal_id');
    }
}
