<?php

namespace App\Domain\Attendance\Services;

use App\Domain\Attendance\DTOs\EmployeePattern;
use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Service for analyzing employee attendance patterns
 *
 * This service analyzes the last 30 days of attendance records to calculate:
 * - Average check-in/check-out times
 * - Standard deviation for check-in/check-out times
 * - Pattern reliability (minimum 7 records required)
 *
 * Patterns are cached for 24 hours per employee to optimize performance.
 */
class PatternAnalyzer
{
    /**
     * Minimum number of records required for a reliable pattern
     */
    public const RELIABILITY_THRESHOLD = 7;

    /**
     * Number of days to analyze for pattern calculation
     */
    public const ANALYSIS_WINDOW_DAYS = 30;

    /**
     * Cache duration in seconds (24 hours)
     */
    public const CACHE_DURATION = 86400;

    /**
     * Analyze attendance patterns for an employee
     *
     * Returns cached pattern if available, otherwise calculates fresh.
     *
     * @param  Employee  $employee  The employee to analyze
     * @return EmployeePattern The calculated or cached pattern
     */
    public function analyzePatterns(Employee $employee): EmployeePattern
    {
        // Generate cache key with tenant isolation
        $cacheKey = $this->getCacheKey($employee);

        // Try to get from cache first
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $this->hydrateFromCache($cached);
        }

        // Calculate fresh pattern
        $pattern = $this->calculatePatternFromDatabase($employee);

        // Cache the result
        Cache::put($cacheKey, $pattern->toArray(), self::CACHE_DURATION);

        return $pattern;
    }

    /**
     * Calculate pattern from database records
     *
     * @param  Employee  $employee
     * @return EmployeePattern
     */
    protected function calculatePatternFromDatabase(Employee $employee): EmployeePattern
    {
        $fromDate = Carbon::now()->subDays(self::ANALYSIS_WINDOW_DAYS)->startOfDay();

        // Fetch all check-in and check-out records from the last 30 days
        $records = AttendanceRecord::where('employee_id', $employee->id)
            ->where('recorded_at', '>=', $fromDate)
            ->whereIn('direction', ['check-in', 'check-out'])
            ->orderBy('recorded_at', 'asc')
            ->get();

        $recordCount = $records->count();

        // If not enough records, return unreliable pattern
        if ($recordCount < self::RELIABILITY_THRESHOLD) {
            return EmployeePattern::unreliable($recordCount);
        }

        // Separate check-in and check-out records
        $checkIns = $records->where('direction', 'check-in');
        $checkOuts = $records->where('direction', 'check-out');

        // Calculate average check-in time (seconds since midnight)
        $avgCheckInTime = null;
        $checkInStdDev = null;
        if ($checkIns->isNotEmpty()) {
            $checkInTimes = $checkIns->map(function ($record) {
                return $this->timeToSecondsFromMidnight($record->recorded_at);
            });

            $avgCheckInTime = $checkInTimes->avg();
            $checkInStdDev = $this->calculateStandardDeviation($checkInTimes->toArray(), $avgCheckInTime);
        }

        // Calculate average check-out time (seconds since midnight)
        $avgCheckOutTime = null;
        $checkOutStdDev = null;
        if ($checkOuts->isNotEmpty()) {
            $checkOutTimes = $checkOuts->map(function ($record) {
                return $this->timeToSecondsFromMidnight($record->recorded_at);
            });

            $avgCheckOutTime = $checkOutTimes->avg();
            $checkOutStdDev = $this->calculateStandardDeviation($checkOutTimes->toArray(), $avgCheckOutTime);
        }

        return new EmployeePattern(
            avgCheckInTime: $avgCheckInTime,
            checkInStdDev: $checkInStdDev,
            avgCheckOutTime: $avgCheckOutTime,
            checkOutStdDev: $checkOutStdDev,
            recordCount: $recordCount,
            analyzedAt: Carbon::now(),
            reliable: true
        );
    }

    /**
     * Score a direction based on pattern proximity
     *
     * Returns a score from 0-20 based on how close the timestamp is to the
     * employee's historical pattern for the expected direction.
     *
     * Scoring:
     * - Within 1σ (68% of data): 20 points
     * - Within 2σ (95% of data): 15 points
     * - Within 3σ (99.7% of data): 10 points
     * - Outside 3σ: 5 points
     * - Unreliable pattern or new employee: 10 points (neutral)
     * - Wrong direction: 0 points
     *
     * @param  Employee  $employee  The employee
     * @param  Carbon  $timestamp  The event timestamp
     * @param  string  $expectedDirection  Expected direction ('check-in' or 'check-out')
     * @return int Score from 0-20
     */
    public function scorePattern(Employee $employee, Carbon $timestamp, string $expectedDirection): int
    {
        $pattern = $this->analyzePatterns($employee);

        // If pattern is unreliable (< 7 records), return neutral score
        if (!$pattern->reliable) {
            return 10;
        }

        // Get the relevant average and standard deviation
        $avgTime = match ($expectedDirection) {
            'check-in' => $pattern->avgCheckInTime,
            'check-out' => $pattern->avgCheckOutTime,
            default => null,
        };

        $stdDev = match ($expectedDirection) {
            'check-in' => $pattern->checkInStdDev,
            'check-out' => $pattern->checkOutStdDev,
            default => null,
        };

        // If no pattern data for this direction, return neutral score
        if ($avgTime === null || $stdDev === null) {
            return 10;
        }

        // Convert timestamp to seconds from midnight
        $eventTime = $this->timeToSecondsFromMidnight($timestamp);

        // Calculate distance from average in terms of standard deviations
        $distance = abs($eventTime - $avgTime);
        $sigmaDistance = $stdDev > 0 ? $distance / $stdDev : 0;

        // Score based on proximity to pattern
        return match (true) {
            $sigmaDistance <= 1.0 => 20, // Within 1σ (68%) - very consistent
            $sigmaDistance <= 2.0 => 15, // Within 2σ (95%) - consistent
            $sigmaDistance <= 3.0 => 10, // Within 3σ (99.7%) - acceptable
            default => 5,                 // Outside 3σ - unusual
        };
    }

    /**
     * Invalidate pattern cache for an employee
     *
     * Call this after creating new attendance records to ensure
     * patterns are recalculated on next analysis.
     *
     * @param  Employee  $employee
     * @return void
     */
    public function invalidatePatternCache(Employee $employee): void
    {
        $cacheKey = $this->getCacheKey($employee);
        Cache::forget($cacheKey);
    }

    /**
     * Convert Carbon timestamp to seconds from midnight
     *
     * @param  Carbon  $timestamp
     * @return int Seconds from midnight (0-86399)
     */
    protected function timeToSecondsFromMidnight(Carbon $timestamp): int
    {
        return ($timestamp->hour * 3600) + ($timestamp->minute * 60) + $timestamp->second;
    }

    /**
     * Calculate standard deviation of a set of values
     *
     * @param  array  $values
     * @param  float  $mean
     * @return float
     */
    protected function calculateStandardDeviation(array $values, float $mean): float
    {
        if (count($values) === 0) {
            return 0.0;
        }

        $variance = array_reduce($values, function ($carry, $value) use ($mean) {
            return $carry + pow($value - $mean, 2);
        }, 0.0) / count($values);

        return sqrt($variance);
    }

    /**
     * Generate cache key for employee pattern
     *
     * @param  Employee  $employee
     * @return string
     */
    protected function getCacheKey(Employee $employee): string
    {
        // Get tenant ID from current database connection
        // Use a default value for testing environments
        try {
            $tenantId = DB::connection('tenant')->getDatabaseName();
        } catch (\Exception $e) {
            $tenantId = 'test_tenant';
        }

        return "pattern:{$tenantId}:employee:{$employee->id}";
    }

    /**
     * Hydrate EmployeePattern from cached array
     *
     * @param  array  $cached
     * @return EmployeePattern
     */
    protected function hydrateFromCache(array $cached): EmployeePattern
    {
        return new EmployeePattern(
            avgCheckInTime: $cached['avg_check_in_time'] ?? null,
            checkInStdDev: $cached['check_in_std_dev'] ?? null,
            avgCheckOutTime: $cached['avg_check_out_time'] ?? null,
            checkOutStdDev: $cached['check_out_std_dev'] ?? null,
            recordCount: $cached['record_count'] ?? 0,
            analyzedAt: Carbon::parse($cached['analyzed_at']),
            reliable: $cached['reliable'] ?? false
        );
    }
}
