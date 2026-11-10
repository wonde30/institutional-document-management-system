<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispositionExecution extends Model
{
    use HasFactory;

    protected $table = 'disposition_executions';
    public $timestamps = false;

    protected $fillable = [
        'request_id',
        'executed_by',
        'executed_at',
        'evidence_document_id',
        'external_receipt',
    ];

    protected function casts(): array
    {
        return [
            'executed_at' => 'datetime',
        ];
    }
    public function request(): BelongsTo
    {
        return $this->belongsTo(DispositionRequest::class, 'request_id');
    }

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function evidenceDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'evidence_document_id');
    }
}
