<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Service extends Model
{
    use HasFactory;

    protected $connection = 'agendae';
    protected $table = 'services';

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'price',
        'duration_minutes',
        'slot_duration_minutes',
        'is_active',
        'image_path',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration_minutes' => 'integer',
        'slot_duration_minutes' => 'integer',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(AgendaeUser::class, 'user_id');
    }
}
