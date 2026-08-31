<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use HasFactory;

    protected $connection = 'agendae';
    protected $table = 'appointments';

    protected $fillable = [
        'user_id',
        'client_account_id',
        'service_id',
        'team_member_id',
        'client_name',
        'client_email',
        'client_phone',
        'appointment_date',
        'appointment_time',
        'status',
        'payment_status',
        'payment_id',
        'notes',
    ];

    protected $casts = [
        'service_id' => 'integer',
        'client_account_id' => 'integer',
        'team_member_id' => 'integer',
        'appointment_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(AgendaeUser::class, 'user_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'team_member_id');
    }
}
