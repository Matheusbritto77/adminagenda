<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientAccount extends Model
{
    use HasFactory;

    protected $connection = 'agendae';
    protected $table = 'client_accounts';

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'phone',
        'avatar_path',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(AgendaeUser::class, 'user_id');
    }
}
