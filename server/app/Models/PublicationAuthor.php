<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicationAuthor extends Model
{
    use HasFactory;

    protected $table = 'publication_authors';
    public $timestamps = false;

    protected $fillable = [
        'publication_id',
        'staff_id',
        'external_name',
        'author_order',
        'is_corresponding',
    ];

    protected function casts(): array
    {
        return [
            'author_order' => 'integer',
            'is_corresponding' => 'boolean',
        ];
    }
    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class, 'publication_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'staff_id');
    }
}
