<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrespondenceParticipant extends Model
{
    use HasFactory;

    protected $table = 'correspondence_participants';
    public $timestamps = false;

    protected $fillable = [
        'correspondence_id',
        'party_id',
        'participation_type',
    ];

    protected function casts(): array
    {
        return [

        ];
    }
    public function correspondence(): BelongsTo
    {
        return $this->belongsTo(Correspondence::class, 'correspondence_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(CommunicationParty::class, 'party_id');
    }
}
