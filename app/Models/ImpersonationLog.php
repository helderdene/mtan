<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImpersonationLog extends Model
{
    use HasFactory;

    protected $connection = 'central';

    protected $fillable = [
        'super_admin_id',
        'tenant_id',
        'user_id',
        'user_email',
        'started_at',
        'ended_at',
        'ip_address',
        'user_agent',
        'exit_reason',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /**
     * Get the super admin who performed the impersonation
     */
    public function superAdmin(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class);
    }

    /**
     * Get the tenant that was impersonated
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope: Get active impersonation sessions (not ended)
     */
    public function scopeActive($query)
    {
        return $query->whereNull('ended_at');
    }

    /**
     * Calculate the duration of the impersonation session in seconds
     */
    public function duration(): ?int
    {
        if (! $this->ended_at) {
            return null;
        }

        return $this->started_at->diffInSeconds($this->ended_at);
    }

    /**
     * Check if the impersonation session is still active
     */
    public function isActive(): bool
    {
        return is_null($this->ended_at);
    }
}
