<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Device extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

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
        'capacity' => 'integer',
        'current_count' => 'integer',
        'last_sync_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
    ];

    /**
     * Relationship: Device has many enrollments
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(DeviceEnrollment::class);
    }

    /**
     * Relationship: Device has many attendance records
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * Scope: Filter active devices
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter by location
     */
    public function scopeByLocation($query, string $location)
    {
        return $query->where('location', $location);
    }

    /**
     * Scope: Filter online devices (heartbeat within last 5 minutes)
     */
    public function scopeOnline($query)
    {
        return $query->where('last_heartbeat_at', '>=', now()->subMinutes(5));
    }

    /**
     * Check if device is currently online
     */
    public function isOnline(): bool
    {
        return $this->last_heartbeat_at && $this->last_heartbeat_at->diffInMinutes(now()) < 5;
    }

    /**
     * Get device status
     */
    public function getStatusAttribute(): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }

        return $this->isOnline() ? 'online' : 'offline';
    }
}
