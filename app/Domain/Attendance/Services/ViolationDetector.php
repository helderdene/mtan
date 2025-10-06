<?php

namespace App\Domain\Attendance\Services;

use App\Domain\Attendance\Models\AttendanceViolation;
use App\Domain\Attendance\Models\DailyAttendanceSummary;
use App\Domain\Shift\Services\OverrideService;
use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\Employee;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Service for detecting attendance policy violations.
 *
 * Detects and logs violations such as late arrival, early departure,
 * extended breaks, and missing checkouts.
 */
class ViolationDetector
{
    public function __construct(
        private readonly OverrideService $overrideService = new OverrideService()
    ) {
    }

    /**
     * Detect violations from an attendance record.
     *
     * @return Collection<AttendanceViolation>
     */
    public function detectFromRecord(AttendanceRecord $record): Collection
    {
        $violations = collect();
        $employee = $record->employee;
        $date = $record->recorded_at->copy()->startOfDay();

        // Skip detection on holidays/off-days
        if (! $this->isWorkRequired($employee, $date)) {
            return $violations;
        }

        switch ($record->direction) {
            case 'check-in':
                if ($violation = $this->detectLateArrival($record)) {
                    $violations->push($violation);
                }
                break;

            case 'check-out':
                if ($violation = $this->detectEarlyDeparture($record)) {
                    $violations->push($violation);
                }
                break;

            case 'break-end':
                if ($violation = $this->detectExtendedBreak($record)) {
                    $violations->push($violation);
                }
                break;
        }

        return $violations;
    }

    /**
     * Detect late arrival violation.
     */
    public function detectLateArrival(AttendanceRecord $record): ?AttendanceViolation
    {
        $employee = $record->employee;
        $shift = $employee->getShiftForDate($record->recorded_at);

        if (! $shift) {
            return null;
        }

        $effectiveShift = $this->getEffectiveShiftTimes($employee, $record->recorded_at, $shift);
        if (! $effectiveShift) {
            return null;
        }

        $shiftStartTime = Carbon::parse($effectiveShift->startTime);
        $gracePeriod = config('violations.late_arrival_grace_minutes', 15);
        $allowedStartTime = $shiftStartTime->copy()->addMinutes($gracePeriod);

        if ($record->recorded_at->greaterThan($allowedStartTime)) {
            $minutesLate = $shiftStartTime->diffInMinutes($record->recorded_at);

            return AttendanceViolation::create([
                'employee_id' => $employee->id,
                'attendance_record_id' => $record->id,
                'daily_summary_id' => $this->getDailySummaryId($employee, $record->recorded_at),
                'violation_date' => $record->recorded_at->toDateString(),
                'type' => 'late_arrival',
                'severity' => $this->calculateSeverity('late_arrival', $minutesLate),
                'minutes_deviation' => $minutesLate,
                'metadata' => [
                    'shift_start_time' => $shiftStartTime->format('H:i:s'),
                    'actual_check_in_time' => $record->recorded_at->format('H:i:s'),
                    'grace_period_minutes' => $gracePeriod,
                ],
                'status' => 'pending',
            ]);
        }

        return null;
    }

    /**
     * Detect early departure violation.
     */
    public function detectEarlyDeparture(AttendanceRecord $record): ?AttendanceViolation
    {
        $employee = $record->employee;
        $shift = $employee->getShiftForDate($record->recorded_at);

        if (! $shift) {
            return null;
        }

        $effectiveShift = $this->getEffectiveShiftTimes($employee, $record->recorded_at, $shift);
        if (! $effectiveShift) {
            return null;
        }

        $shiftEndTime = Carbon::parse($effectiveShift->endTime);
        $gracePeriod = config('violations.early_departure_grace_minutes', 15);
        $allowedEndTime = $shiftEndTime->copy()->subMinutes($gracePeriod);

        if ($record->recorded_at->lessThan($allowedEndTime)) {
            $minutesEarly = $record->recorded_at->diffInMinutes($shiftEndTime);

            return AttendanceViolation::create([
                'employee_id' => $employee->id,
                'attendance_record_id' => $record->id,
                'daily_summary_id' => $this->getDailySummaryId($employee, $record->recorded_at),
                'violation_date' => $record->recorded_at->toDateString(),
                'type' => 'early_departure',
                'severity' => $this->calculateSeverity('early_departure', $minutesEarly),
                'minutes_deviation' => $minutesEarly,
                'metadata' => [
                    'shift_end_time' => $shiftEndTime->format('H:i:s'),
                    'actual_check_out_time' => $record->recorded_at->format('H:i:s'),
                    'grace_period_minutes' => $gracePeriod,
                ],
                'status' => 'pending',
            ]);
        }

        return null;
    }

    /**
     * Detect extended break violation.
     */
    public function detectExtendedBreak(AttendanceRecord $breakEndRecord): ?AttendanceViolation
    {
        $employee = $breakEndRecord->employee;
        $date = $breakEndRecord->recorded_at->copy()->startOfDay();

        // Find the corresponding break-start record
        $breakStartRecord = AttendanceRecord::where('employee_id', $employee->id)
            ->whereDate('recorded_at', $date)
            ->where('direction', 'break-start')
            ->where('recorded_at', '<', $breakEndRecord->recorded_at)
            ->orderBy('recorded_at', 'desc')
            ->first();

        if (! $breakStartRecord) {
            return null;
        }

        $breakDuration = $breakStartRecord->recorded_at->diffInMinutes($breakEndRecord->recorded_at);
        $maxBreakMinutes = config('violations.max_break_minutes', 120);

        if ($breakDuration > $maxBreakMinutes) {
            $minutesOver = $breakDuration - $maxBreakMinutes;

            return AttendanceViolation::create([
                'employee_id' => $employee->id,
                'attendance_record_id' => $breakEndRecord->id,
                'daily_summary_id' => $this->getDailySummaryId($employee, $breakEndRecord->recorded_at),
                'violation_date' => $breakEndRecord->recorded_at->toDateString(),
                'type' => 'extended_break',
                'severity' => $this->calculateSeverity('extended_break', $minutesOver),
                'minutes_deviation' => $minutesOver,
                'metadata' => [
                    'break_start_time' => $breakStartRecord->recorded_at->format('H:i:s'),
                    'break_end_time' => $breakEndRecord->recorded_at->format('H:i:s'),
                    'break_duration_minutes' => $breakDuration,
                    'max_break_minutes' => $maxBreakMinutes,
                ],
                'status' => 'pending',
            ]);
        }

        return null;
    }

    /**
     * Detect missing checkout violations for a specific date.
     *
     * @return Collection<AttendanceViolation>
     */
    public function detectMissingCheckouts(Carbon $date): Collection
    {
        $violations = collect();

        // Find all check-ins without corresponding check-outs
        $incompleteRecords = AttendanceRecord::whereDate('recorded_at', $date)
            ->where('direction', 'check-in')
            ->whereDoesntHave('employee', function ($query) use ($date) {
                $query->whereHas('attendanceRecords', function ($q) use ($date) {
                    $q->whereDate('recorded_at', $date)
                        ->where('direction', 'check-out');
                });
            })
            ->with('employee')
            ->get();

        foreach ($incompleteRecords as $record) {
            $employee = $record->employee;

            // Skip if not a work day
            if (! $this->isWorkRequired($employee, $date)) {
                continue;
            }

            $violation = AttendanceViolation::create([
                'employee_id' => $employee->id,
                'attendance_record_id' => null,
                'daily_summary_id' => $this->getDailySummaryId($employee, $date),
                'violation_date' => $date->toDateString(),
                'type' => 'missing_checkout',
                'severity' => 'moderate',
                'minutes_deviation' => 0,
                'metadata' => [
                    'check_in_time' => $record->recorded_at->format('H:i:s'),
                    'detected_at' => now()->format('Y-m-d H:i:s'),
                ],
                'status' => 'pending',
            ]);

            $violations->push($violation);
        }

        return $violations;
    }

    /**
     * Calculate severity level based on violation type and deviation.
     */
    public function calculateSeverity(string $type, int $minutesDeviation): string
    {
        $thresholds = config("violations.severity_thresholds.{$type}", [
            'minor' => 30,
            'moderate' => 60,
        ]);

        if ($minutesDeviation <= $thresholds['minor']) {
            return 'minor';
        }

        if ($minutesDeviation <= $thresholds['moderate']) {
            return 'moderate';
        }

        return 'major';
    }

    /**
     * Check if work is required on the given date.
     */
    private function isWorkRequired(Employee $employee, Carbon $date): bool
    {
        $shift = $employee->getShiftForDate($date);

        if (! $shift) {
            return false;
        }

        // Check for shift overrides (holidays/off-days)
        if ($shift instanceof \App\Domain\Shift\Models\Shift) {
            return $this->overrideService->isWorkRequired($date, $employee);
        }

        return true;
    }

    /**
     * Get effective shift times accounting for overrides.
     */
    private function getEffectiveShiftTimes(Employee $employee, Carbon $date, $shift)
    {
        if ($shift instanceof \App\Domain\Shift\Models\Shift) {
            return $this->overrideService->getEffectiveShiftTimes($date, $shift, $employee);
        }

        // For Tenant\Shift model, return basic shift times
        return (object) [
            'startTime' => $shift->start_time,
            'endTime' => $shift->end_time,
        ];
    }

    /**
     * Get daily summary ID for the date.
     */
    private function getDailySummaryId(Employee $employee, Carbon $date): ?int
    {
        $summary = DailyAttendanceSummary::where('employee_id', $employee->id)
            ->whereDate('date', $date)
            ->first();

        return $summary?->id;
    }
}
