<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceRegistry extends Model
{
    use HasFactory;

    protected $connection = 'central';

    protected $table = 'device_registry';

    protected $fillable = [
        'device_id',
        'tenant_id',
        'device_name',
        'device_type',
        'location',
        'ip_address',
        'mac_address',
        'firmware_version',
        'is_active',
        'last_seen_at',
        'registered_at',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Relationship: Device registry belongs to a tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope: Filter active devices
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
