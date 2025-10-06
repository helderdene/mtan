<?php

namespace Database\Factories\Domain\Attendance\Models;

use App\Domain\Attendance\Models\AttendanceViolation;
use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Attendance\Models\AttendanceViolation>
 */
class AttendanceViolationFactory extends Factory
{
    protected $model = AttendanceViolation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'attendance_record_id' => AttendanceRecord::factory(),
            'violation_date' => $this->faker->date(),
            'type' => $this->faker->randomElement(['late_arrival', 'early_departure', 'extended_break', 'missing_checkout']),
            'severity' => $this->faker->randomElement(['minor', 'moderate', 'major', 'critical']),
            'minutes_deviation' => $this->faker->numberBetween(5, 120),
            'metadata' => [
                'shift_start_time' => '09:00:00',
                'actual_time' => '09:25:00',
                'grace_period_minutes' => 15,
            ],
            'status' => 'pending',
            'notes' => null,
        ];
    }

    /**
     * Indicate that the violation is a late arrival.
     */
    public function lateArrival(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'late_arrival',
            'severity' => 'minor',
            'minutes_deviation' => 20,
        ]);
    }

    /**
     * Indicate that the violation is an early departure.
     */
    public function earlyDeparture(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'early_departure',
            'severity' => 'moderate',
            'minutes_deviation' => 45,
        ]);
    }

    /**
     * Indicate that the violation is an extended break.
     */
    public function extendedBreak(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'extended_break',
            'severity' => 'minor',
            'minutes_deviation' => 10,
        ]);
    }

    /**
     * Indicate that the violation is a missing checkout.
     */
    public function missingCheckout(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'missing_checkout',
            'severity' => 'moderate',
            'attendance_record_id' => null,
        ]);
    }

    /**
     * Indicate that the violation has minor severity.
     */
    public function minor(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => 'minor',
            'minutes_deviation' => $this->faker->numberBetween(5, 30),
        ]);
    }

    /**
     * Indicate that the violation has moderate severity.
     */
    public function moderate(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => 'moderate',
            'minutes_deviation' => $this->faker->numberBetween(31, 60),
        ]);
    }

    /**
     * Indicate that the violation has major severity.
     */
    public function major(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => 'major',
            'minutes_deviation' => $this->faker->numberBetween(61, 180),
        ]);
    }

    /**
     * Indicate that the violation has been acknowledged.
     */
    public function acknowledged(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'acknowledged',
        ]);
    }

    /**
     * Indicate that the violation has been disputed.
     */
    public function disputed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'disputed',
            'notes' => 'Employee disputes the violation',
        ]);
    }
}
