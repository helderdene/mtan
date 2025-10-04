<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceEnrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'device_id',
        'enrollment_status',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];

    /**
     * Relationship: Enrollment belongs to an employee
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Relationship: Enrollment belongs to a device
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Scope: Filter by enrollment status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('enrollment_status', $status);
    }
}
