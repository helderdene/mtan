<?php

namespace Database\Factories;

use App\Models\Tenant\StrangerLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant\StrangerLog>
 */
class StrangerLogFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = StrangerLog::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => \App\Models\Tenant\Device::factory(),
            'employee_id' => null,
            'matched_by' => null,
            'detected_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'photo_path' => 'strangers/photos/' . $this->faker->uuid() . '.jpg',
            'match_status' => 'unreviewed',
            'notes' => null,
        ];
    }

    /**
     * Indicate that the stranger has been matched to an employee.
     */
    public function matched(): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => \App\Models\Tenant\Employee::factory(),
            'matched_by' => \App\Models\User::factory(),
            'match_status' => 'matched',
        ]);
    }

    /**
     * Indicate that the stranger is marked as a security issue.
     */
    public function securityIssue(): static
    {
        return $this->state(fn (array $attributes) => [
            'match_status' => 'security_issue',
            'notes' => $this->faker->sentence(),
        ]);
    }
}
