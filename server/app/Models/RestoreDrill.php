<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestoreDrill extends Model
{
    use HasFactory;

    protected $table = 'restore_drills';
    public $timestamps = false;

    protected $fillable = [
        'backup_run_id',
        'started_at',
        'completed_at',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
    public function backupRun(): BelongsTo
    {
        return $this->belongsTo(BackupRun::class, 'backup_run_id');
    }
}
