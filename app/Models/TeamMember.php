<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamMember extends Model
{
    use HasFactory;

    protected $connection = 'agendae';
    protected $table = 'team_members';

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'phone',
        'role_title',
        'commission_rate',
        'is_active',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(AgendaeUser::class, 'user_id');
    }
}
