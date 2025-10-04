<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeviceRegistry>
 */
class DeviceRegistryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $deviceTypes = ['facial_recognition', 'fingerprint', 'card_reader', 'biometric_hybrid'];
        $locations = ['Main Entrance', 'Side Entrance', 'Lobby', 'Office Floor 1', 'Office Floor 2', 'Warehouse', 'Parking Gate'];

        return [
            'device_id' => 'DEV-' . strtoupper(fake()->unique()->bothify('???###')),
            'device_name' => fake()->randomElement([
                'Main Entrance Scanner',
                'Office Entry Device',
                'Warehouse Access Terminal',
                'Executive Floor Reader',
                'Lobby Biometric Unit',
                'Parking Gate Scanner',
            ]) . ' ' . fake()->numberBetween(1, 99),
            'device_type' => fake()->randomElement($deviceTypes),
            'location' => fake()->randomElement($locations),
            'ip_address' => fake()->localIpv4(),
            'mac_address' => fake()->macAddress(),
            'firmware_version' => fake()->randomElement(['v1.2.3', 'v1.3.0', 'v1.4.1', 'v2.0.0', 'v2.1.0']),
            'is_active' => fake()->boolean(85), // 85% chance of being active
            'last_seen_at' => fake()->boolean(70) ? now()->subMinutes(fake()->numberBetween(1, 120)) : null,
            'registered_at' => now()->subDays(fake()->numberBetween(1, 180)),
            'metadata' => null,
        ];
    }
}
