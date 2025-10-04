<?php

namespace Database\Seeders;

use App\Models\DeviceRegistry;
use App\Models\Tenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DeviceRegistrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all active tenants
        $tenants = Tenant::active()->get();

        if ($tenants->isEmpty()) {
            $this->command->warn('No active tenants found. Skipping device registry seeding.');
            return;
        }

        $this->command->info("Seeding devices for {$tenants->count()} active tenant(s)...");

        // Create devices for each tenant
        foreach ($tenants as $tenant) {
            // Create 2-5 devices per tenant
            $deviceCount = rand(2, 5);

            DeviceRegistry::factory()
                ->count($deviceCount)
                ->create([
                    'tenant_id' => $tenant->id,
                ]);

            $this->command->info("Created {$deviceCount} devices for tenant: {$tenant->company_name}");
        }

        $totalDevices = DeviceRegistry::count();
        $this->command->info("Device registry seeding completed! Total devices: {$totalDevices}");
    }
}
