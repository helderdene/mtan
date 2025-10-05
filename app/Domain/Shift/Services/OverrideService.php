<?php

namespace App\Domain\Shift\Services;

use App\Domain\Shift\DTOs\EffectiveShift;
use App\Domain\Shift\Models\Employee;
use App\Domain\Shift\Models\Shift;
use App\Domain\Shift\Models\ShiftOverride;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Service for managing shift overrides and calculating effective shift times.
 *
 * Handles company-wide holidays, employee-specific off-days, half-day shifts,
 * and custom shift modifications.
 */
class OverrideService
{
    /**
     * Cache duration in seconds (24 hours).
     */
    private const CACHE_TTL = 86400;

    /**
     * Get the active override for a given date, shift, and employee.
     *
     * Priority order (highest to lowest):
     * 1. Employee-specific override for this shift
     * 2. Employee-specific off-day (no shift specified)
     * 3. Company-wide override for this shift
     * 4. No override (returns null)
     */
    public function getActiveOverride(
        Carbon $date,
        ?Shift $shift,
        ?Employee $employee
    ): ?ShiftOverride {
        $dateString = $date->format('Y-m-d');
        $cacheKey = $this->getCacheKey($dateString, $shift?->id, $employee?->id);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($dateString, $shift, $employee) {
            // Priority 1: Employee-specific shift override
            if ($employee && $shift) {
                $override = ShiftOverride::whereDate('override_date', $dateString)
                    ->where('shift_id', $shift->id)
                    ->where('employee_id', $employee->id)
                    ->first();

                if ($override) {
                    return $override;
                }
            }

            // Priority 2: Employee-specific off-day (no shift)
            if ($employee) {
                $override = ShiftOverride::whereDate('override_date', $dateString)
                    ->where('employee_id', $employee->id)
                    ->whereNull('shift_id')
                    ->first();

                if ($override) {
                    return $override;
                }
            }

            // Priority 3: Company-wide override for this shift
            if ($shift) {
                $override = ShiftOverride::whereDate('override_date', $dateString)
                    ->where('shift_id', $shift->id)
                    ->whereNull('employee_id')
                    ->first();

                if ($override) {
                    return $override;
                }
            }

            // No override found
            return null;
        });
    }

    /**
     * Check if work is required on a given date for an employee.
     *
     * Returns false if there's a holiday or off-day override.
     */
    public function isWorkRequired(Carbon $date, Employee $employee): bool
    {
        $dateString = $date->format('Y-m-d');

        // Check for employee-specific off-day (no shift)
        $override = ShiftOverride::whereDate('override_date', $dateString)
            ->where('employee_id', $employee->id)
            ->whereNull('shift_id')
            ->first();

        if ($override && in_array($override->type, ['holiday', 'off-day'])) {
            return false;
        }

        // Check for any company-wide holiday (any shift or no shift)
        $override = ShiftOverride::whereDate('override_date', $dateString)
            ->whereNull('employee_id')
            ->where('type', 'holiday')
            ->first();

        if ($override) {
            return false;
        }

        return true;
    }

    /**
     * Get the effective shift times for a date, accounting for overrides.
     *
     * Returns null if no work is required (holiday/off-day).
     * Returns modified times for half-day or custom-shift overrides.
     * Returns regular shift times otherwise.
     */
    public function getEffectiveShiftTimes(
        Carbon $date,
        Shift $shift,
        ?Employee $employee
    ): ?EffectiveShift {
        $override = $this->getActiveOverride($date, $shift, $employee);

        // No work required for holidays and off-days
        if ($override && in_array($override->type, ['holiday', 'off-day'])) {
            return null;
        }

        // Modified shift times for half-day and custom-shift
        if ($override && $override->hasCustomTimes()) {
            return EffectiveShift::fromOverride($override, $shift, $date);
        }

        // Regular shift times (no override or override without custom times)
        return EffectiveShift::fromShift($shift, $date);
    }

    /**
     * Invalidate cache for a specific override.
     */
    public function invalidateCache(ShiftOverride $override): void
    {
        $dateString = $override->override_date->format('Y-m-d');
        $cacheKey = $this->getCacheKey($dateString, $override->shift_id, $override->employee_id);
        Cache::forget($cacheKey);

        // Also invalidate related cache keys
        // Invalidate without employee_id (company-wide lookup)
        $cacheKeyCompanyWide = $this->getCacheKey($dateString, $override->shift_id, null);
        Cache::forget($cacheKeyCompanyWide);

        // Invalidate without shift_id (off-day lookup)
        $cacheKeyOffDay = $this->getCacheKey($dateString, null, $override->employee_id);
        Cache::forget($cacheKeyOffDay);
    }

    /**
     * Invalidate all caches for a specific date.
     */
    public function invalidateCacheForDate(Carbon $date): void
    {
        $dateString = $date->format('Y-m-d');

        // Get all overrides for this date
        $overrides = ShiftOverride::where('override_date', $dateString)->get();

        foreach ($overrides as $override) {
            $this->invalidateCache($override);
        }
    }

    /**
     * Generate cache key for override lookups.
     */
    private function getCacheKey(string $date, ?int $shiftId, ?int $employeeId): string
    {
        // TODO: Add tenant ID when multi-tenancy is implemented
        // $tenantId = tenancy()->tenant?->id ?? 'default';
        // return "tenant:{$tenantId}:override:{$date}:{$shiftId}:{$employeeId}";

        return "override:{$date}:{$shiftId}:{$employeeId}";
    }
}
