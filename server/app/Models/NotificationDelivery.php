<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDelivery extends Model
{
    use HasFactory;

    protected $table = 'notification_deliveries';
    public $timestamps = false;

    protected $fillable = [
        'notification_id',
        'channel',
        'status',
        'attempt_count',
        'delivered_at',
        'last_error_code',
    ];

    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'delivered_at' => 'datetime',
        ];
    }
    public function notification(): BelongsTo
    {
        return $this->belongsTo(UserNotification::class, 'notification_id');
    }
}
