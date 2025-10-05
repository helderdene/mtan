<?php

namespace App\Domain\Attendance\DTOs;

use Carbon\Carbon;

/**
 * Data Transfer Object for Employee Attendance Pattern
 *
 * Represents the analyzed attendance pattern of an employee over the last 30 days.
 */
class EmployeePattern
{
    /**
     * Create a new EmployeePattern instance
     *
     * @param  float|null  $avgCheckInTime  Average check-in time in seconds since midnight (e.g., 32400 = 09:00)
     * @param  float|null  $checkInStdDev  Standard deviation of check-in times in seconds
     * @param  float|null  $avgCheckOutTime  Average check-out time in seconds since midnight
     * @param  float|null  $checkOutStdDev  Standard deviation of check-out times in seconds
     * @param  int  $recordCount  Number of records analyzed (minimum 7 for reliable pattern)
     * @param  Carbon  $analyzedAt  Timestamp when the pattern was calculated
     * @param  bool  $reliable  Whether the pattern is reliable (>= 7 records)
     */
    public function __construct(
        public readonly ?float $avgCheckInTime,
        public readonly ?float $checkInStdDev,
        public readonly ?float $avgCheckOutTime,
        public readonly ?float $checkOutStdDev,
        public readonly int $recordCount,
        public readonly Carbon $analyzedAt,
        public readonly bool $reliable,
    ) {
    }

    /**
     * Get the average check-in time as a Carbon instance
     */
    public function getAvgCheckInTimeAsCarbon(): ?Carbon
    {
        if ($this->avgCheckInTime === null) {
            return null;
        }

        return Carbon::today()->addSeconds((int) $this->avgCheckInTime);
    }

    /**
     * Get the average check-out time as a Carbon instance
     */
    public function getAvgCheckOutTimeAsCarbon(): ?Carbon
    {
        if ($this->avgCheckOutTime === null) {
            return null;
        }

        return Carbon::today()->addSeconds((int) $this->avgCheckOutTime);
    }

    /**
     * Get the check-in time range (avg ± 1 standard deviation)
     *
     * @return array{min: Carbon|null, max: Carbon|null}
     */
    public function getCheckInRange(): array
    {
        if ($this->avgCheckInTime === null || $this->checkInStdDev === null) {
            return ['min' => null, 'max' => null];
        }

        return [
            'min' => Carbon::today()->addSeconds((int) ($this->avgCheckInTime - $this->checkInStdDev)),
            'max' => Carbon::today()->addSeconds((int) ($this->avgCheckInTime + $this->checkInStdDev)),
        ];
    }

    /**
     * Get the check-out time range (avg ± 1 standard deviation)
     *
     * @return array{min: Carbon|null, max: Carbon|null}
     */
    public function getCheckOutRange(): array
    {
        if ($this->avgCheckOutTime === null || $this->checkOutStdDev === null) {
            return ['min' => null, 'max' => null];
        }

        return [
            'min' => Carbon::today()->addSeconds((int) ($this->avgCheckOutTime - $this->checkOutStdDev)),
            'max' => Carbon::today()->addSeconds((int) ($this->avgCheckOutTime + $this->checkOutStdDev)),
        ];
    }

    /**
     * Convert the pattern to an array
     */
    public function toArray(): array
    {
        return [
            'avg_check_in_time' => $this->avgCheckInTime,
            'check_in_std_dev' => $this->checkInStdDev,
            'avg_check_out_time' => $this->avgCheckOutTime,
            'check_out_std_dev' => $this->checkOutStdDev,
            'record_count' => $this->recordCount,
            'analyzed_at' => $this->analyzedAt->toIso8601String(),
            'reliable' => $this->reliable,
        ];
    }

    /**
     * Create an unreliable pattern (< 7 records or new employee)
     */
    public static function unreliable(int $recordCount = 0): self
    {
        return new self(
            avgCheckInTime: null,
            checkInStdDev: null,
            avgCheckOutTime: null,
            checkOutStdDev: null,
            recordCount: $recordCount,
            analyzedAt: Carbon::now(),
            reliable: false,
        );
    }
}
