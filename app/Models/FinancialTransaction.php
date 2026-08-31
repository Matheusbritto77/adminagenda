<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialTransaction extends Model
{
    use HasFactory;

    protected $connection = 'agendae';
    protected $table = 'financial_transactions';

    protected $fillable = [
        'user_id',
        'appointment_id',
        'team_member_id',
        'type', // income, expense
        'category',
        'description',
        'amount',
        'payment_method',
        'status',
        'transaction_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(AgendaeUser::class, 'user_id');
    }
}
