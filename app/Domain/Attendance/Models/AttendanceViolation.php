<?php

namespace App\Domain\Attendance\Models;

use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\Employee;
use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceViolation extends Model
{
    use HasFactory, UsesTenantConnection;

    protected $connection = 'tenant';

    protected $fillable = [
        'employee_id',
        'attendance_record_id',
        'daily_summary_id',
        'date',
        'type',
        'severity',
        'description',
        'minutes_deviation',
        'metadata',
        'status',
        'notes',
        'acknowledged_at',
        'disputed_at',
        'dispute_reason',
    ];

    protected $casts = [
        'date' => 'date',
        'metadata' => 'array',
        'minutes_deviation' => 'integer',
        'acknowledged_at' => 'datetime',
        'disputed_at' => 'datetime',
    ];

    /**
     * Get the employee that owns the violation.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the attendance record that triggered the violation.
     */
    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    /**
     * Get the daily summary associated with the violation.
     */
    public function dailySummary(): BelongsTo
    {
        return $this->belongsTo(DailyAttendanceSummary::class, 'daily_summary_id');
    }

    /**
     * Scope a query to only include violations for a specific employee.
     */
    public function scopeByEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope a query to only include violations of a specific type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include violations of a specific severity.
     */
    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope a query to only include violations with a specific status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include pending violations.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to filter violations by date range.
     */
    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereBetween('date', [$from, $to]);
    }
}
