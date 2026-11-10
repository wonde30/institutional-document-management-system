<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrespondenceProcedure extends Model
{
    use HasFactory;

    protected $table = 'correspondence_procedures';
    public $timestamps = false;
    protected $primaryKey = 'correspondence_id';

    protected $fillable = [
        'correspondence_id',
        'procedure_version_id',
        'applied_by',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'applied_at' => 'datetime',
        ];
    }
    public function correspondence(): BelongsTo
    {
        return $this->belongsTo(Correspondence::class, 'correspondence_id');
    }

    public function procedureVersion(): BelongsTo
    {
        return $this->belongsTo(OfficeProcedureVersion::class, 'procedure_version_id');
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }
}
