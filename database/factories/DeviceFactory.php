<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Device>
 */
class DeviceFactory extends Factory
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

        $isEntryDevice = fake()->boolean(60);
        $isExitDevice = fake()->boolean(60);

        return [
            'device_id' => 'DEV-' . strtoupper(fake()->unique()->bothify('???###')),
            'name' => fake()->randomElement([
                'Main Entrance Scanner',
                'Office Entry Device',
                'Warehouse Access Terminal',
                'Executive Floor Reader',
                'Lobby Biometric Unit',
                'Parking Gate Scanner',
            ]) . ' ' . fake()->numberBetween(1, 99),
            'location' => fake()->randomElement($locations),
            'device_type' => fake()->randomElement($deviceTypes),
            'ip_address' => fake()->localIpv4(),
            'mac_address' => fake()->macAddress(),
            'firmware_version' => fake()->randomElement(['v1.2.3', 'v1.3.0', 'v1.4.1', 'v2.0.0', 'v2.1.0']),
            'capacity' => fake()->numberBetween(1000, 50000),
            'current_count' => fake()->numberBetween(0, 500),
            'is_entry_device' => $isEntryDevice,
            'is_exit_device' => $isExitDevice,
            'timezone' => 'Asia/Ulaanbaatar',
            'settings' => [
                'auto_sync' => true,
                'notification_enabled' => fake()->boolean(80),
                'face_quality_threshold' => fake()->numberBetween(70, 95),
            ],
            'is_active' => fake()->boolean(85),
            'last_sync_at' => fake()->boolean(70) ? now()->subMinutes(fake()->numberBetween(1, 120)) : null,
            'last_heartbeat_at' => fake()->boolean(70) ? now()->subMinutes(fake()->numberBetween(1, 10)) : null,
        ];
    }
}
