<?php

namespace Database\Factories;

use App\Models\Tenant\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant\Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => 'DEV-'.strtoupper(fake()->unique()->bothify('???###')),
            'name' => fake()->randomElement([
                'Main Entrance Scanner',
                'Office Entry Device',
                'Warehouse Access Terminal',
                'Executive Floor Reader',
                'Lobby Biometric Unit',
                'Parking Gate Scanner',
            ]).' '.fake()->numberBetween(1, 99),
            'location' => fake()->randomElement([
                'Main Entrance',
                'Side Entrance',
                'Lobby',
                'Office Floor 1',
                'Office Floor 2',
                'Warehouse',
                'Parking Gate',
            ]),
            'ip_address' => fake()->localIpv4(),
            'capacity' => fake()->numberBetween(1000, 50000),
            'is_active' => fake()->boolean(85),
            'last_heartbeat_at' => fake()->boolean(70) ? now()->subMinutes(fake()->numberBetween(1, 10)) : null,
        ];
    }
}
