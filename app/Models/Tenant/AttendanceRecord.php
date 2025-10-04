<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $fillable = [
        'employee_id',
        'device_id',
        'recorded_at',
        'direction',
        'recognition_score',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'recognition_score' => 'decimal:4',
    ];

    protected $appends = ['date', 'check_in', 'check_out', 'total_hours'];

    /**
     * Relationship: Record belongs to an employee
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Relationship: Record belongs to a device
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Scope: Filter by direction
     */
    public function scopeByDirection($query, string $direction)
    {
        return $query->where('direction', $direction);
    }

    /**
     * Scope: Filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('recorded_at', [$startDate, $endDate]);
    }

    /**
     * Accessor: Get date attribute
     */
    public function getDateAttribute()
    {
        return $this->recorded_at?->format('F j, Y');
    }

    /**
     * Accessor: Get check_in attribute
     */
    public function getCheckInAttribute()
    {
        return $this->direction === 'check-in' ? $this->recorded_at : null;
    }

    /**
     * Accessor: Get check_out attribute
     */
    public function getCheckOutAttribute()
    {
        return $this->direction === 'check-out' ? $this->recorded_at : null;
    }

    /**
     * Accessor: Get total_hours attribute
     */
    public function getTotalHoursAttribute()
    {
        // For individual records, we can't calculate total hours
        // This should be calculated from daily summaries
        return null;
    }
}
