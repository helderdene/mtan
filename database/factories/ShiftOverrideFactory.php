<?php

namespace Database\Factories;

use App\Domain\Shift\Models\Employee;
use App\Domain\Shift\Models\Shift;
use App\Domain\Shift\Models\ShiftOverride;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Shift\Models\ShiftOverride>
 */
class ShiftOverrideFactory extends Factory
{
    protected $model = ShiftOverride::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['holiday', 'off-day', 'half-day', 'custom-shift']);

        return [
            'shift_id' => Shift::factory(),
            'employee_id' => null, // Company-wide by default
            'override_date' => $this->faker->dateTimeBetween('now', '+30 days'),
            'type' => $type,
            'custom_start_time' => in_array($type, ['half-day', 'custom-shift']) ? '09:00:00' : null,
            'custom_end_time' => in_array($type, ['half-day', 'custom-shift']) ? '13:00:00' : null,
            'reason' => $this->faker->sentence(),
        ];
    }

    /**
     * Indicate that this is a holiday override (company-wide).
     */
    public function holiday(string $reason = 'Public Holiday'): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'holiday',
            'shift_id' => null,
            'employee_id' => null,
            'custom_start_time' => null,
            'custom_end_time' => null,
            'reason' => $reason,
        ]);
    }

    /**
     * Indicate that this is an employee-specific off-day.
     */
    public function offDay(?int $employeeId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'off-day',
            'shift_id' => null,
            'employee_id' => $employeeId ?? Employee::factory(),
            'custom_start_time' => null,
            'custom_end_time' => null,
        ]);
    }

    /**
     * Indicate that this is a half-day override.
     */
    public function halfDay(string $startTime = '09:00:00', string $endTime = '13:00:00'): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'half-day',
            'custom_start_time' => $startTime,
            'custom_end_time' => $endTime,
        ]);
    }

    /**
     * Indicate that this is a custom shift override.
     */
    public function customShift(string $startTime, string $endTime): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'custom-shift',
            'custom_start_time' => $startTime,
            'custom_end_time' => $endTime,
        ]);
    }

    /**
     * Indicate that this is for a specific employee.
     */
    public function forEmployee(?int $employeeId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => $employeeId ?? Employee::factory(),
        ]);
    }

    /**
     * Indicate that this is company-wide.
     */
    public function companyWide(): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => null,
        ]);
    }
}
