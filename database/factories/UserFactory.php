<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => 'tenant_user',
            'tenant_id' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Create a super admin user.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'super_admin',
            'tenant_id' => null,
        ]);
    }

    /**
     * Create a tenant admin user.
     */
    public function tenantAdmin(?string $tenantId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'tenant_admin',
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Create a tenant user.
     */
    public function tenantUser(?string $tenantId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'tenant_user',
            'tenant_id' => $tenantId,
        ]);
    }
}
