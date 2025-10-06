<?php

namespace App\Domain\Attendance\Models;

use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\Employee;
use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyAttendanceSummary extends Model
{
    use HasFactory, UsesTenantConnection;

    protected $connection = 'tenant';

    protected $fillable = [
        'employee_id',
        'date',
        'first_check_in',
        'last_check_out',
        'total_work_minutes',
        'total_break_minutes',
        'overtime_minutes',
        'status',
        'is_complete',
    ];

    protected $casts = [
        'date' => 'date',
        'total_work_minutes' => 'integer',
        'total_break_minutes' => 'integer',
        'overtime_minutes' => 'integer',
        'is_complete' => 'boolean',
    ];

    /**
     * Get the employee that owns this summary.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get all attendance records for this day.
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'employee_id', 'employee_id')
            ->whereDate('recorded_at', $this->date);
    }

    /**
     * Get total work hours (converted from minutes).
     */
    public function getTotalWorkHoursAttribute(): float
    {
        return round($this->total_work_minutes / 60, 2);
    }

    /**
     * Get total break hours (converted from minutes).
     */
    public function getTotalBreakHoursAttribute(): float
    {
        return round($this->total_break_minutes / 60, 2);
    }

    /**
     * Get overtime hours (converted from minutes).
     */
    public function getOvertimeHoursAttribute(): float
    {
        return round($this->overtime_minutes / 60, 2);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\DailyAttendanceSummaryFactory::new();
    }
}
