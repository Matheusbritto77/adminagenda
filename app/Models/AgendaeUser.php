<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgendaeUser extends Model
{
    use HasFactory;

    protected $connection = 'agendae';
    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'avatar_url',
        'parent_id',
        'role_title',
        'subdomain',
        'custom_domain',
        'active_domain_type',
        'role_permissions',
        'custom_roles',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'parent_id' => 'integer',
        'role_permissions' => 'array',
        'custom_roles' => 'array',
    ];

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'user_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'user_id');
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(TeamMember::class, 'user_id');
    }

    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'user_id');
    }
}
