<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ShiftRotationPattern>
 */
class ShiftRotationPatternFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true) . ' Rotation',
            'cycle_type' => fake()->randomElement(['weekly', 'bi_weekly', 'monthly']),
            'rotation_sequence' => [1, 2, 3], // Default shift IDs, override in tests
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * Create a weekly rotation pattern
     */
    public function weekly(): static
    {
        return $this->state(fn (array $attributes) => [
            'cycle_type' => 'weekly',
        ]);
    }

    /**
     * Create a bi-weekly rotation pattern
     */
    public function biWeekly(): static
    {
        return $this->state(fn (array $attributes) => [
            'cycle_type' => 'bi_weekly',
        ]);
    }

    /**
     * Create a monthly rotation pattern
     */
    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'cycle_type' => 'monthly',
        ]);
    }

    /**
     * Create an inactive rotation pattern
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
