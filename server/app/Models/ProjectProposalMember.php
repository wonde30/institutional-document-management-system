<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectProposalMember extends Model
{
    use HasFactory;

    protected $table = 'project_proposal_members';
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'proposal_id',
        'staff_id',
        'member_role',
    ];

    protected function casts(): array
    {
        return [

        ];
    }
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(ProjectProposal::class, 'proposal_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'staff_id');
    }
}
