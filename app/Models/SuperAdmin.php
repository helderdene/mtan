<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class SuperAdmin extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $connection = 'central';

    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'two_factor_secret',
        'two_factor_enabled',
        'last_login_at',
        'last_login_ip',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'two_factor_enabled' => 'boolean',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

    /**
     * Get the impersonation logs for this super admin
     */
    public function impersonationLogs(): HasMany
    {
        return $this->hasMany(ImpersonationLog::class);
    }

    /**
     * Scope: Filter active super admins
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
