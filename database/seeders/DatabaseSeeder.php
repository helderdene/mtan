<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'tenant_admin',
        ]);

        // Seed device registry for active tenants
        // TODO: Re-enable after tenants are provisioned
        // $this->call([
        //     DeviceRegistrySeeder::class,
        // ]);
    }
}
