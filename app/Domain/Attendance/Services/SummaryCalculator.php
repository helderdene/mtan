<?php

namespace App\Domain\Attendance\Services;

use App\Domain\Attendance\Models\DailyAttendanceSummary;
use App\Domain\Shift\Services\FlexibleShiftValidator;
use App\Domain\Shift\Services\OverrideService;
use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\Employee;
use Carbon\Carbon;

/**
 * Service for calculating daily attendance summaries.
 *
 * Aggregates attendance records into daily summaries with work hours,
 * break time, overtime, and attendance status calculations.
 */
class SummaryCalculator
{
    /**
     * Default shift duration in minutes (8 hours).
     */
    private const DEFAULT_SHIFT_MINUTES = 480;

    /**
     * Half-day threshold percentage (50% of expected hours).
     */
    private const HALF_DAY_THRESHOLD = 0.5;

    public function __construct(
        private readonly OverrideService $overrideService = new OverrideService(),
        private readonly FlexibleShiftValidator $flexibleShiftValidator = new FlexibleShiftValidator()
    ) {
    }

    /**
     * Calculate or update daily summary for an employee on a specific date.
     *
     * This is the main entry point for summary calculation.
     */
    public function calculateForDate(Employee $employee, Carbon $date): DailyAttendanceSummary
    {
        // Get all attendance records for the date and next day (for overnight shifts)
        // We include next day records to handle check-outs that happen after midnight
        $records = AttendanceRecord::where('employee_id', $employee->id)
            ->where(function ($query) use ($date) {
                $query->whereDate('recorded_at', $date)
                    ->orWhere(function ($q) use ($date) {
                        // Include next day records if they're check-out or break-end
                        $q->whereDate('recorded_at', $date->copy()->addDay())
                          ->whereIn('direction', ['check-out', 'break-end']);
                    });
            })
            ->orderBy('recorded_at')
            ->get();

        // Get employee's shift for this date
        $shift = $employee->current_shift;

        // Calculate all metrics
        $workData = $this->calculateWorkHours($records);
        $breakData = $this->calculateBreakTime($records);
        $overtime = $this->calculateOvertime(
            $workData['total_work_minutes'],
            $breakData['total_break_minutes'],
            $date,
            $shift,
            $employee
        );
        $status = $this->determineStatus(
            $workData['total_work_minutes'],
            $date,
            $shift,
            $employee
        );

        // Upsert summary (create or update)
        $summary = DailyAttendanceSummary::updateOrCreate(
            [
                'employee_id' => $employee->id,
                'date' => $date,
            ],
            [
                'first_check_in' => $workData['first_check_in'],
                'last_check_out' => $workData['last_check_out'],
                'total_work_minutes' => $workData['total_work_minutes'],
                'total_break_minutes' => $breakData['total_break_minutes'],
                'overtime_minutes' => $overtime,
                'status' => $status,
                'is_complete' => $workData['is_complete'],
            ]
        );

        return $summary;
    }

    /**
     * Recalculate summaries for a date range.
     *
     * @return int Number of summaries recalculated
     */
    public function recalculateRange(Employee $employee, Carbon $startDate, Carbon $endDate): int
    {
        $count = 0;
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $this->calculateForDate($employee, $currentDate->copy());
            $count++;
            $currentDate->addDay();
        }

        return $count;
    }

    /**
     * Update daily summary when a new attendance event occurs.
     *
     * Called from ProcessAttendanceEvent job for real-time updates.
     */
    public function updateSummaryFromEvent(AttendanceRecord $record): DailyAttendanceSummary
    {
        $date = $record->recorded_at->copy()->startOfDay();

        // For check-out or break-end records, check if there's a check-in from the previous day
        // This handles overnight shifts where check-out happens after midnight
        if (in_array($record->direction, ['check-out', 'break-end'])) {
            $previousDayCheckIn = AttendanceRecord::where('employee_id', $record->employee_id)
                ->whereDate('recorded_at', $date->copy()->subDay())
                ->where('direction', 'check-in')
                ->orderBy('recorded_at', 'desc')
                ->first();

            // If there's a check-in from the previous day without a matching check-out,
            // this check-out belongs to that day's summary
            if ($previousDayCheckIn) {
                $previousDayCheckOut = AttendanceRecord::where('employee_id', $record->employee_id)
                    ->whereDate('recorded_at', $date->copy()->subDay())
                    ->where('direction', 'check-out')
                    ->where('recorded_at', '>', $previousDayCheckIn->recorded_at)
                    ->exists();

                if (! $previousDayCheckOut) {
                    // This check-out belongs to the previous day's summary
                    $date = $date->subDay();
                }
            }
        }

        return $this->calculateForDate($record->employee, $date);
    }

    /**
     * Calculate work hours from attendance records.
     *
     * Returns:
     * - total_work_minutes: Total work time in minutes
     * - first_check_in: Time of first check-in (HH:MM:SS)
     * - last_check_out: Time of last check-out (HH:MM:SS)
     * - is_complete: Whether employee has checked out
     */
    private function calculateWorkHours(iterable $records): array
    {
        $totalWorkMinutes = 0;
        $firstCheckIn = null;
        $lastCheckOut = null;
        $checkInTime = null;
        $isComplete = false;

        foreach ($records as $record) {
            switch ($record->direction) {
                case 'check-in':
                    $checkInTime = $record->recorded_at;
                    if ($firstCheckIn === null) {
                        $firstCheckIn = $record->recorded_at;
                    }
                    break;

                case 'check-out':
                    if ($checkInTime) {
                        $totalWorkMinutes += $checkInTime->diffInMinutes($record->recorded_at);
                        $checkInTime = null;
                        $lastCheckOut = $record->recorded_at;
                        $isComplete = true;
                    }
                    break;

                case 'break-start':
                    // Work time ends when break starts
                    if ($checkInTime) {
                        $totalWorkMinutes += $checkInTime->diffInMinutes($record->recorded_at);
                        $checkInTime = null;
                    }
                    break;

                case 'break-end':
                    // Resume work time tracking after break
                    $checkInTime = $record->recorded_at;
                    break;
            }
        }

        // If still checked in at end of calculation, mark as incomplete
        if ($checkInTime !== null) {
            $isComplete = false;
        }

        return [
            'total_work_minutes' => $totalWorkMinutes,
            'first_check_in' => $firstCheckIn?->format('H:i:s'),
            'last_check_out' => $lastCheckOut?->format('H:i:s'),
            'is_complete' => $isComplete,
        ];
    }

    /**
     * Calculate break time from attendance records.
     *
     * Returns:
     * - total_break_minutes: Total break time in minutes
     */
    private function calculateBreakTime(iterable $records): array
    {
        $totalBreakMinutes = 0;
        $breakStartTime = null;

        foreach ($records as $record) {
            switch ($record->direction) {
                case 'break-start':
                    $breakStartTime = $record->recorded_at;
                    break;

                case 'break-end':
                    if ($breakStartTime) {
                        $totalBreakMinutes += $breakStartTime->diffInMinutes($record->recorded_at);
                        $breakStartTime = null;
                    }
                    break;
            }
        }

        // If still on break, don't count ongoing break time
        return [
            'total_break_minutes' => $totalBreakMinutes,
        ];
    }

    /**
     * Calculate overtime based on shift duration and overrides.
     *
     * Overtime = Work hours - Expected hours (minus break time)
     */
    private function calculateOvertime(
        int $totalWorkMinutes,
        int $totalBreakMinutes,
        Carbon $date,
        $shift,
        Employee $employee
    ): int {
        // Check for shift overrides (only if shift is from Domain\Shift namespace)
        if ($shift && $shift instanceof \App\Domain\Shift\Models\Shift) {
            $effectiveShift = $this->overrideService->getEffectiveShiftTimes($date, $shift, $employee);

            if ($effectiveShift === null) {
                // Holiday or off-day: all work is overtime
                return $totalWorkMinutes;
            }

            // Calculate expected work minutes
            $expectedMinutes = $effectiveShift->getDurationMinutes();
        } elseif ($shift) {
            // Tenant\Shift model - calculate duration directly
            $startTime = Carbon::parse($shift->start_time);
            $endTime = Carbon::parse($shift->end_time);

            // Handle overnight shifts
            if ($endTime->lt($startTime)) {
                $endTime->addDay();
            }

            $expectedMinutes = $startTime->diffInMinutes($endTime);
        } else {
            // No shift assigned: use default 8-hour shift
            $expectedMinutes = self::DEFAULT_SHIFT_MINUTES;
        }

        // Overtime is work beyond expected hours
        $overtimeMinutes = max(0, $totalWorkMinutes - $expectedMinutes);

        return $overtimeMinutes;
    }

    /**
     * Determine attendance status based on work hours and overrides.
     *
     * Status priorities:
     * 1. Check for shift overrides (holiday, off-day)
     * 2. Absent if no work hours
     * 3. Half-day if < 50% of expected hours
     * 4. Present otherwise
     */
    private function determineStatus(
        int $totalWorkMinutes,
        Carbon $date,
        $shift,
        Employee $employee
    ): string {
        // Check for override first (only if shift is from Domain\Shift namespace)
        if ($shift && $shift instanceof \App\Domain\Shift\Models\Shift) {
            $override = $this->overrideService->getActiveOverride($date, $shift, $employee);

            if ($override) {
                if ($override->type === 'holiday') {
                    return 'holiday';
                }
                if ($override->type === 'off-day') {
                    return 'on-leave';
                }
            }
        }

        // Determine based on work hours
        if ($totalWorkMinutes == 0) {
            return 'absent';
        }

        // Get expected work hours
        if ($shift && $shift instanceof \App\Domain\Shift\Models\Shift) {
            $effectiveShift = $this->overrideService->getEffectiveShiftTimes($date, $shift, $employee);
            $expectedMinutes = $effectiveShift?->getDurationMinutes() ?? self::DEFAULT_SHIFT_MINUTES;
        } elseif ($shift) {
            // Tenant\Shift model - calculate duration directly

            // For flexible shifts, use the actual check-in time to calculate expected hours
            if ($shift->isFlexible()) {
                $firstCheckIn = AttendanceRecord::where('employee_id', $employee->id)
                    ->whereDate('recorded_at', $date)
                    ->where('direction', 'check-in')
                    ->orderBy('recorded_at')
                    ->first();

                if ($firstCheckIn) {
                    $expectedHours = $this->flexibleShiftValidator->calculateExpectedHours($shift, $firstCheckIn->recorded_at);
                    $expectedMinutes = $expectedHours * 60;
                } else {
                    // No check-in found, use core hours requirement
                    $expectedMinutes = ($shift->core_hours_required ?? 8) * 60;
                }
            } else {
                // Fixed shift - calculate duration from start/end times
                $startTime = Carbon::parse($shift->start_time);
                $endTime = Carbon::parse($shift->end_time);

                // Handle overnight shifts
                if ($endTime->lt($startTime)) {
                    $endTime->addDay();
                }

                $expectedMinutes = $startTime->diffInMinutes($endTime);
            }
        } else {
            $expectedMinutes = self::DEFAULT_SHIFT_MINUTES;
        }

        // Half day threshold (< 50% of expected hours)
        if ($totalWorkMinutes < $expectedMinutes * self::HALF_DAY_THRESHOLD) {
            return 'half-day';
        }

        return 'present';
    }
}
