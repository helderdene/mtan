<?php

namespace Database\Factories;

use App\Models\Tenant\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Shift',
            'code' => strtoupper(fake()->unique()->lexify('SH???')),
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start' => null,
            'break_end' => null,
            'grace_period_minutes' => 15,
            'early_departure_threshold_minutes' => 15,
            'overtime_threshold_minutes' => 30,
            'half_day_threshold_minutes' => 240,
            'working_days' => [1, 2, 3, 4, 5], // Monday-Friday
            'shift_type' => 'fixed',
            'is_overnight' => false,
            'color_code' => '#3498db',
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the shift has a break
     */
    public function withBreak(string $breakStart = '12:00:00', string $breakEnd = '13:00:00'): static
    {
        return $this->state(fn (array $attributes) => [
            'break_start' => $breakStart,
            'break_end' => $breakEnd,
        ]);
    }

    /**
     * Indicate that this is an overnight shift
     */
    public function overnight(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
        ]);
    }

    /**
     * Indicate that this is the default shift
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * Indicate that this is a flexible shift
     */
    public function flexible(string $windowStart = '08:00:00', string $windowEnd = '10:00:00', float $coreHours = 8.0): static
    {
        return $this->state(fn (array $attributes) => [
            'shift_type' => 'flexible',
            'flexible_checkin_start' => $windowStart,
            'flexible_checkin_end' => $windowEnd,
            'core_hours_required' => $coreHours,
        ]);
    }

    /**
     * Indicate that this is a rotating shift
     */
    public function rotating(): static
    {
        return $this->state(fn (array $attributes) => [
            'shift_type' => 'rotating',
        ]);
    }
}
