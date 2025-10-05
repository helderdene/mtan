<?php

namespace Database\Factories;

use App\Domain\Shift\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Shift\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_code' => strtoupper($this->faker->unique()->lexify('EMP???')),
            'custom_id' => 'EMP' . $this->faker->unique()->numberBetween(1000, 9999),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'department_id' => null,
            'designation' => $this->faker->jobTitle(),
            'employee_type' => 'full-time',
            'card_number' => null,
            'joining_date' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'leaving_date' => null,
            'reporting_manager_id' => null,
            'is_active' => true,
            'metadata' => null,
        ];
    }

    /**
     * Indicate that the employee is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Disable employee events (job dispatching)
     */
    public function withoutEvents(): static
    {
        return $this->afterMaking(function (Employee $employee) {
            Employee::unsetEventDispatcher();
        });
    }
}
