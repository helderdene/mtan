<?php

namespace App\Domain\Shift\Services;

use App\Models\EmployeeShiftRotation;
use App\Models\ShiftRotationPattern;
use App\Models\Tenant\Employee;
use App\Models\Tenant\Shift;
use Carbon\Carbon;

class RotationScheduler
{
    /**
     * Calculate the current shift for an employee based on their rotation pattern.
     *
     * @param Employee $employee
     * @param Carbon $date
     * @return Shift|null
     */
    public function getCurrentShift(Employee $employee, Carbon $date): ?Shift
    {
        $rotation = EmployeeShiftRotation::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->with('rotationPattern')
            ->first();

        if (!$rotation) {
            return null;
        }

        $shiftId = $this->calculateCurrentShiftId($rotation, $date);

        if (!$shiftId) {
            return null;
        }

        return Shift::find($shiftId);
    }

    /**
     * Calculate the current shift ID based on rotation pattern and date.
     *
     * @param EmployeeShiftRotation $rotation
     * @param Carbon $date
     * @return int|null
     */
    public function calculateCurrentShiftId(EmployeeShiftRotation $rotation, Carbon $date): ?int
    {
        $pattern = $rotation->rotationPattern;

        if (!$pattern || empty($pattern->rotation_sequence)) {
            return null;
        }

        // Calculate how many cycles have passed since start_date
        $daysSinceStart = $rotation->start_date->diffInDays($date);

        // Determine cycle length based on cycle type
        $cycleDays = match ($pattern->cycle_type) {
            'weekly' => 7,
            'bi_weekly' => 14,
            'monthly' => 30,
            default => 7,
        };

        // Calculate how many full cycles have passed
        $cyclesPassed = intdiv($daysSinceStart, $cycleDays);

        // Calculate current position in rotation sequence
        $rotationLength = count($pattern->rotation_sequence);
        $position = ($rotation->current_position + $cyclesPassed) % $rotationLength;

        return $pattern->rotation_sequence[$position] ?? null;
    }

    /**
     * Get the shift schedule for an employee for the next N days.
     *
     * @param Employee $employee
     * @param int $days
     * @return array Array of ['date' => Carbon, 'shift_id' => int]
     */
    public function getSchedulePreview(Employee $employee, int $days = 30): array
    {
        $rotation = EmployeeShiftRotation::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->with('rotationPattern')
            ->first();

        if (!$rotation) {
            return [];
        }

        $schedule = [];
        $startDate = now()->startOfDay();

        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);
            $shiftId = $this->calculateCurrentShiftId($rotation, $date);

            $schedule[] = [
                'date' => $date,
                'shift_id' => $shiftId,
            ];
        }

        return $schedule;
    }

    /**
     * Check if a rotation needs to be advanced based on the current date.
     *
     * @param EmployeeShiftRotation $rotation
     * @param Carbon $currentDate
     * @return bool
     */
    public function shouldAdvanceRotation(EmployeeShiftRotation $rotation, Carbon $currentDate): bool
    {
        $pattern = $rotation->rotationPattern;

        if (!$pattern) {
            return false;
        }

        // If never rotated, check if it's time for first rotation
        if (!$rotation->last_rotated_at) {
            $cycleDays = match ($pattern->cycle_type) {
                'weekly' => 7,
                'bi_weekly' => 14,
                'monthly' => 30,
                default => 7,
            };

            return $rotation->start_date->copy()->addDays($cycleDays)->lte($currentDate);
        }

        // Check if cycle period has passed since last rotation
        $cycleDays = match ($pattern->cycle_type) {
            'weekly' => 7,
            'bi_weekly' => 14,
            'monthly' => 30,
            default => 7,
        };

        return $rotation->last_rotated_at->copy()->addDays($cycleDays)->lte($currentDate);
    }

    /**
     * Advance all eligible rotations for the current date.
     *
     * @param Carbon|null $date
     * @return int Number of rotations advanced
     */
    public function advanceEligibleRotations(?Carbon $date = null): int
    {
        $date = $date ?? now();
        $count = 0;

        $rotations = EmployeeShiftRotation::where('is_active', true)
            ->with('rotationPattern')
            ->get();

        foreach ($rotations as $rotation) {
            if ($this->shouldAdvanceRotation($rotation, $date)) {
                $rotation->advanceRotation();
                $count++;
            }
        }

        return $count;
    }
}
