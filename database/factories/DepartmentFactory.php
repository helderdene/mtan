<?php

namespace Database\Factories;

use App\Models\Tenant\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant\Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'Engineering',
                'Sales',
                'Marketing',
                'Human Resources',
                'Finance',
                'Operations',
                'Customer Support',
                'Product',
                'Design',
                'Legal',
            ]),
            'description' => fake()->sentence(),
        ];
    }
}
