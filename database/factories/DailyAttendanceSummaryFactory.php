<?php

namespace Database\Factories;

use App\Domain\Attendance\Models\DailyAttendanceSummary;
use App\Models\Tenant\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class DailyAttendanceSummaryFactory extends Factory
{
    protected $model = DailyAttendanceSummary::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'date' => fake()->date(),
            'first_check_in' => '09:00:00',
            'last_check_out' => '18:00:00',
            'total_work_minutes' => 480, // 8 hours
            'total_break_minutes' => 60, // 1 hour
            'overtime_minutes' => 0,
            'status' => 'present',
            'is_complete' => false,
        ];
    }

    /**
     * Indicate that the employee is absent.
     */
    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'first_check_in' => null,
            'last_check_out' => null,
            'total_work_minutes' => 0,
            'total_break_minutes' => 0,
            'overtime_minutes' => 0,
            'status' => 'absent',
            'is_complete' => true, // Absent day is finalized
        ]);
    }

    /**
     * Indicate that the employee worked half-day.
     */
    public function halfDay(): static
    {
        return $this->state(fn (array $attributes) => [
            'first_check_in' => '09:00:00',
            'last_check_out' => '13:00:00',
            'total_work_minutes' => 210, // 3.5 hours (< 4 hours threshold)
            'total_break_minutes' => 30,
            'overtime_minutes' => 0,
            'status' => 'half-day',
            'is_complete' => true,
        ]);
    }

    /**
     * Indicate that the employee is on leave.
     */
    public function onLeave(): static
    {
        return $this->state(fn (array $attributes) => [
            'first_check_in' => null,
            'last_check_out' => null,
            'total_work_minutes' => 0,
            'total_break_minutes' => 0,
            'overtime_minutes' => 0,
            'status' => 'on-leave',
            'is_complete' => true,
        ]);
    }

    /**
     * Indicate that it's a holiday.
     */
    public function holiday(): static
    {
        return $this->state(fn (array $attributes) => [
            'first_check_in' => null,
            'last_check_out' => null,
            'total_work_minutes' => 0,
            'total_break_minutes' => 0,
            'overtime_minutes' => 0,
            'status' => 'holiday',
            'is_complete' => true,
        ]);
    }

    /**
     * Indicate that the employee has worked overtime.
     */
    public function withOvertime(int $overtimeMinutes = 60): static
    {
        return $this->state(fn (array $attributes) => [
            'total_work_minutes' => 540, // 9 hours
            'overtime_minutes' => $overtimeMinutes,
        ]);
    }

    /**
     * Indicate that the day is complete (employee has checked out).
     */
    public function complete(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_complete' => true,
        ]);
    }

    /**
     * Indicate that the day is incomplete (employee still checked in).
     */
    public function incomplete(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_check_out' => null,
            'is_complete' => false,
        ]);
    }
}
