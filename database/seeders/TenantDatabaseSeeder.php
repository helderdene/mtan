<?php

namespace Database\Seeders;

use App\Models\Device;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TenantDatabaseSeeder extends Seeder
{
    /**
     * Seed the tenant database with default data.
     *
     * This seeder is idempotent - it can be run multiple times safely.
     */
    public function run(): void
    {
        $this->seedDepartments();
        $this->seedShifts();
        $this->seedAdminUser();
        $this->seedDevices();
    }

    /**
     * Seed default departments
     */
    private function seedDepartments(): void
    {
        $departments = [
            ['name' => 'General', 'description' => 'General employees not assigned to specific departments'],
            ['name' => 'Human Resources', 'description' => 'HR and recruitment'],
            ['name' => 'IT', 'description' => 'Information Technology'],
            ['name' => 'Sales', 'description' => 'Sales and marketing'],
            ['name' => 'Operations', 'description' => 'Operations and logistics'],
        ];

        foreach ($departments as $department) {
            // Check if department exists
            $exists = DB::table('departments')
                ->where('name', $department['name'])
                ->exists();

            if (! $exists) {
                DB::table('departments')->insert([
                    'name' => $department['name'],
                    'description' => $department['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Seed default shifts
     */
    private function seedShifts(): void
    {
        $shifts = [
            [
                'name' => 'Day Shift',
                'start_time' => '09:00:00',
                'end_time' => '17:00:00',
                'working_days' => json_encode(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
                'is_default' => true,
            ],
            [
                'name' => 'Night Shift',
                'start_time' => '22:00:00',
                'end_time' => '06:00:00',
                'working_days' => json_encode(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
                'is_default' => false,
            ],
            [
                'name' => 'Flexible',
                'start_time' => '00:00:00',
                'end_time' => '23:59:59',
                'working_days' => json_encode(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']),
                'is_default' => false,
            ],
        ];

        foreach ($shifts as $shift) {
            // Check if shift exists
            $exists = DB::table('shifts')
                ->where('name', $shift['name'])
                ->exists();

            if (! $exists) {
                DB::table('shifts')->insert([
                    'name' => $shift['name'],
                    'start_time' => $shift['start_time'],
                    'end_time' => $shift['end_time'],
                    'working_days' => $shift['working_days'],
                    'is_default' => $shift['is_default'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Seed default admin user
     */
    private function seedAdminUser(): void
    {
        // Check if admin user exists
        $exists = DB::table('users')
            ->where('email', 'admin@tenant.local')
            ->exists();

        if (! $exists) {
            DB::table('users')->insert([
                'name' => 'Admin',
                'email' => 'admin@tenant.local',
                'password' => Hash::make('password'), // Default password: "password"
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Seed default devices
     */
    private function seedDevices(): void
    {
        // Check if devices already exist
        $deviceCount = DB::table('devices')->count();

        if ($deviceCount === 0) {
            // Create 3-5 devices for this tenant
            $count = rand(3, 5);
            Device::factory()->count($count)->create();

            $this->command->info("Created {$count} devices for tenant");
        }
    }
}
