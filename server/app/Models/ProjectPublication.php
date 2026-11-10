<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPublication extends Model
{
    use HasFactory;

    protected $table = 'project_publications';
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'project_id',
        'publication_id',
    ];

    protected function casts(): array
    {
        return [

        ];
    }
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class, 'publication_id');
    }
}
