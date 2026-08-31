<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentFlowLog extends Model
{
    use HasFactory;

    protected $connection = 'agendae';
    protected $table = 'appointment_flow_logs';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'appointment_id',
        'event_type',
        'level',
        'channel',
        'title',
        'description',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
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
