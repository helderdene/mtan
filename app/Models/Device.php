<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Device extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'device_id',
        'name',
        'location',
        'device_type',
        'ip_address',
        'mac_address',
        'firmware_version',
        'capacity',
        'current_count',
        'is_entry_device',
        'is_exit_device',
        'timezone',
        'settings',
        'is_active',
        'last_sync_at',
        'last_heartbeat_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_entry_device' => 'boolean',
        'is_exit_device' => 'boolean',
        'is_active' => 'boolean',
        'last_sync_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOnline($query)
    {
        return $query->where('last_heartbeat_at', '>=', now()->subMinutes(5));
    }

    public function isOnline(): bool
    {
        return $this->last_heartbeat_at && $this->last_heartbeat_at->diffInMinutes(now()) < 5;
    }

    public function getStatusAttribute(): string
    {
        if (!$this->is_active) {
            return 'inactive';
        }

        return $this->isOnline() ? 'online' : 'offline';
    }
}
