<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppNotificationQueue extends Model
{
    use HasFactory;

    protected $connection = 'agendae';
    protected $table = 'whatsapp_notification_queue';

    protected $fillable = [
        'user_id',
        'appointment_id',
        'recipient_phone',
        'recipient_name',
        'message_type',
        'message_body',
        'media_url',
        'status',
        'attempts',
        'error_message',
        'scheduled_for',
        'sent_at',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'sent_at' => 'datetime',
        'attempts' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(AgendaeUser::class, 'user_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }
}
