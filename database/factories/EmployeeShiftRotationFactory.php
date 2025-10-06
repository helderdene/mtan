<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeShiftRotation>
 */
class EmployeeShiftRotationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => \App\Models\Tenant\Employee::factory(),
            'rotation_pattern_id' => \App\Models\ShiftRotationPattern::factory(),
            'start_date' => now()->subDays(rand(1, 30)),
            'current_position' => 0,
            'last_rotated_at' => null,
            'is_active' => true,
        ];
    }

    /**
     * Create an inactive rotation assignment
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a rotation with a specific start position
     */
    public function atPosition(int $position): static
    {
        return $this->state(fn (array $attributes) => [
            'current_position' => $position,
            'last_rotated_at' => now()->subWeek(),
        ]);
    }
}
