<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppLog extends Model
{
    use HasFactory;

    protected $connection = 'agendae';
    protected $table = 'whatsapp_logs';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'direction',
        'phone',
        'status',
        'message_id',
        'message_body',
        'error_message',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(AgendaeUser::class, 'tenant_id');
    }
}
