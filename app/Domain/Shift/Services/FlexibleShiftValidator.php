<?php

namespace App\Domain\Shift\Services;

use App\Models\Tenant\Shift;
use Carbon\Carbon;

class FlexibleShiftValidator
{
    /**
     * Validate if a check-in time is within the flexible window.
     *
     * @param Shift $shift
     * @param Carbon $checkInTime
     * @return bool
     */
    public function isValidCheckIn(Shift $shift, Carbon $checkInTime): bool
    {
        if (!$shift->isFlexible()) {
            return true; // Not a flexible shift, no validation needed
        }

        if (!$shift->flexible_checkin_start || !$shift->flexible_checkin_end) {
            return false; // Flexible shift not properly configured
        }

        return $shift->isWithinFlexibleWindow($checkInTime);
    }

    /**
     * Calculate expected work hours for a flexible shift based on check-in time.
     *
     * @param Shift $shift
     * @param Carbon $checkInTime
     * @return float Expected work hours
     */
    public function calculateExpectedHours(Shift $shift, Carbon $checkInTime): float
    {
        if (!$shift->isFlexible()) {
            // For fixed shifts, calculate normal expected hours
            return $this->calculateFixedShiftHours($shift);
        }

        // For flexible shifts, use core_hours_required
        return $shift->core_hours_required ?? $this->calculateFixedShiftHours($shift);
    }

    /**
     * Calculate expected end time for a flexible shift based on check-in.
     *
     * @param Shift $shift
     * @param Carbon $checkInTime
     * @return Carbon
     */
    public function calculateExpectedEndTime(Shift $shift, Carbon $checkInTime): Carbon
    {
        $expectedHours = $this->calculateExpectedHours($shift, $checkInTime);

        // Add break time if configured
        $breakMinutes = 0;
        if ($shift->break_start && $shift->break_end) {
            $breakStart = Carbon::parse($shift->break_start);
            $breakEnd = Carbon::parse($shift->break_end);
            $breakMinutes = $breakStart->diffInMinutes($breakEnd);
        }

        $totalMinutes = ($expectedHours * 60) + $breakMinutes;

        return $checkInTime->copy()->addMinutes($totalMinutes);
    }

    /**
     * Get validation result with details.
     *
     * @param Shift $shift
     * @param Carbon $checkInTime
     * @return array
     */
    public function validate(Shift $shift, Carbon $checkInTime): array
    {
        $isValid = $this->isValidCheckIn($shift, $checkInTime);

        if (!$shift->isFlexible()) {
            return [
                'valid' => true,
                'type' => 'fixed',
                'message' => 'Fixed shift - no flexible validation required',
            ];
        }

        if (!$isValid) {
            return [
                'valid' => false,
                'type' => 'flexible',
                'message' => sprintf(
                    'Check-in time %s is outside the flexible window (%s - %s)',
                    $checkInTime->format('H:i'),
                    $shift->flexible_checkin_start,
                    $shift->flexible_checkin_end
                ),
                'flexible_window' => [
                    'start' => $shift->flexible_checkin_start,
                    'end' => $shift->flexible_checkin_end,
                ],
            ];
        }

        $expectedEndTime = $this->calculateExpectedEndTime($shift, $checkInTime);

        return [
            'valid' => true,
            'type' => 'flexible',
            'message' => 'Check-in within flexible window',
            'flexible_window' => [
                'start' => $shift->flexible_checkin_start,
                'end' => $shift->flexible_checkin_end,
            ],
            'expected_hours' => $this->calculateExpectedHours($shift, $checkInTime),
            'expected_end_time' => $expectedEndTime->format('H:i:s'),
        ];
    }

    /**
     * Calculate fixed shift hours (for non-flexible shifts).
     *
     * @param Shift $shift
     * @return float
     */
    private function calculateFixedShiftHours(Shift $shift): float
    {
        $start = Carbon::parse($shift->start_time);
        $end = Carbon::parse($shift->end_time);

        // Handle overnight shifts
        if ($shift->is_overnight && $end->lessThan($start)) {
            $end->addDay();
        }

        $totalMinutes = $start->diffInMinutes($end);

        // Subtract break time
        if ($shift->break_start && $shift->break_end) {
            $breakStart = Carbon::parse($shift->break_start);
            $breakEnd = Carbon::parse($shift->break_end);
            $totalMinutes -= $breakStart->diffInMinutes($breakEnd);
        }

        return $totalMinutes / 60;
    }

    /**
     * Get the earliest valid check-in time for a flexible shift.
     *
     * @param Shift $shift
     * @param Carbon $date
     * @return Carbon|null
     */
    public function getEarliestCheckInTime(Shift $shift, Carbon $date): ?Carbon
    {
        if (!$shift->isFlexible() || !$shift->flexible_checkin_start) {
            return null;
        }

        return Carbon::parse($date->format('Y-m-d') . ' ' . $shift->flexible_checkin_start);
    }

    /**
     * Get the latest valid check-in time for a flexible shift.
     *
     * @param Shift $shift
     * @param Carbon $date
     * @return Carbon|null
     */
    public function getLatestCheckInTime(Shift $shift, Carbon $date): ?Carbon
    {
        if (!$shift->isFlexible() || !$shift->flexible_checkin_end) {
            return null;
        }

        return Carbon::parse($date->format('Y-m-d') . ' ' . $shift->flexible_checkin_end);
    }
}
