<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $connection = 'central';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'company_name',
        'subdomain',
        'domain',
        'admin_email',
        'admin_password',
        'database_name',
        'database_host',
        'subscription_plan',
        'max_employees',
        'max_devices',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'max_employees' => 'integer',
        'max_devices' => 'integer',
    ];

    /**
     * Relationship: Tenant has many device registries
     */
    public function devices(): HasMany
    {
        return $this->hasMany(DeviceRegistry::class);
    }

    /**
     * Scope: Filter active tenants
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
