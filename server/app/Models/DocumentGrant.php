<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentGrant extends Model
{
    use HasFactory;

    protected $table = 'document_grants';
    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'permission_id',
        'user_id',
        'unit_id',
        'subject_key',
        'granted_by',
        'granted_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'unit_id');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
