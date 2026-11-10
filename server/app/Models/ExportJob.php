<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportJob extends Model
{
    use HasFactory;

    protected $table = 'export_jobs';
    public $timestamps = false;

    protected $fillable = [
        'requested_by',
        'report_type',
        'filter_snapshot_json',
        'status',
        'output_file_id',
        'requested_at',
        'completed_at',
        'expires_at',
        'error_code',
    ];

    protected function casts(): array
    {
        return [
            'filter_snapshot_json' => 'array',
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function outputFile(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'output_file_id');
    }
}
