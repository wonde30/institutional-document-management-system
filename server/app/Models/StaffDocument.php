<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffDocument extends Model
{
    use HasFactory;

    protected $table = 'staff_documents';
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'staff_id',
        'document_id',
        'record_type_id',
    ];

    protected function casts(): array
    {
        return [

        ];
    }
    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'staff_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function recordType(): BelongsTo
    {
        return $this->belongsTo(HrRecordType::class, 'record_type_id');
    }
}
