<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BackupRun extends Model
{
    use HasFactory;

    protected $table = 'backup_runs';
    public $timestamps = false;

    protected $fillable = [
        'provider_job_reference',
        'started_at',
        'completed_at',
        'status',
        'manifest_reference',
        'verification_status',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
