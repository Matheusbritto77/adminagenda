<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppSession extends Model
{
    protected $table = 'whatsapp_sessions';

    protected $fillable = [
        'tenant_id',
        'status',
        'phone_number',
        'profile_name',
        'qr_code',
        'pairing_code',
        'creds',
        'connected_at',
        'last_activity_at',
        'disconnected_at',
    ];

    protected function casts(): array
    {
        return [
            'connected_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'disconnected_at' => 'datetime',
        ];
    }
}
