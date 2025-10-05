<?php

namespace Database\Factories;

use App\Models\Tenant\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    protected $connection = 'tenant';

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true).' Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start' => null,
            'break_end' => null,
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
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
}
