<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Shift extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    /**
     * Get the database connection for the model.
     */
    public function getConnectionName()
    {
        // Use default connection in testing environment
        if (app()->bound('env') && app()->environment('testing')) {
            return config('database.default');
        }

        return $this->connection;
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\ShiftFactory::new();
    }

    protected $fillable = [
        'name',
        'code',
        'start_time',
        'end_time',
        'break_start',
        'break_end',
        'grace_period_minutes',
        'early_departure_threshold_minutes',
        'overtime_threshold_minutes',
        'half_day_threshold_minutes',
        'working_days',
        'shift_type',
        'is_overnight',
        'flexible_checkin_start',
        'flexible_checkin_end',
        'core_hours_required',
        'color_code',
        'is_active',
        'description',
        'metadata',
    ];

    protected $casts = [
        'working_days' => 'array',
        'metadata' => 'array',
        'is_overnight' => 'boolean',
        'is_active' => 'boolean',
        'grace_period_minutes' => 'integer',
        'early_departure_threshold_minutes' => 'integer',
        'overtime_threshold_minutes' => 'integer',
        'half_day_threshold_minutes' => 'integer',
        'core_hours_required' => 'decimal:2',
    ];

    /**
     * Relationship: Shift belongs to many employees (through pivot table)
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_shifts')
            ->withPivot('effective_from', 'effective_to')
            ->withTimestamps();
    }

    /**
     * Check if this is a flexible shift.
     */
    public function isFlexible(): bool
    {
        return $this->shift_type === 'flexible';
    }

    /**
     * Check if this is a rotating shift.
     */
    public function isRotating(): bool
    {
        return $this->shift_type === 'rotating';
    }

    /**
     * Check if this is a fixed shift.
     */
    public function isFixed(): bool
    {
        return $this->shift_type === 'fixed';
    }

    /**
     * Get the flexible check-in window duration in minutes.
     */
    public function getFlexibleWindowMinutes(): ?int
    {
        if (!$this->isFlexible() || !$this->flexible_checkin_start || !$this->flexible_checkin_end) {
            return null;
        }

        $start = \Carbon\Carbon::parse($this->flexible_checkin_start);
        $end = \Carbon\Carbon::parse($this->flexible_checkin_end);

        return $start->diffInMinutes($end);
    }

    /**
     * Check if a timestamp is within the flexible check-in window.
     */
    public function isWithinFlexibleWindow(\Carbon\Carbon $timestamp): bool
    {
        if (!$this->isFlexible() || !$this->flexible_checkin_start || !$this->flexible_checkin_end) {
            return false;
        }

        $timeOnly = $timestamp->format('H:i:s');

        return $timeOnly >= $this->flexible_checkin_start && $timeOnly <= $this->flexible_checkin_end;
    }

    /**
     * Scope: Filter active shifts
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter by shift type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('shift_type', $type);
    }
}
