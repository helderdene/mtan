<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class SeedTenantDevices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:seed-devices {tenant_id? : The tenant ID (optional, seeds all if not provided)} {--count=5 : Number of devices per tenant}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed devices for a specific tenant or all tenants';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantId = $this->argument('tenant_id');
        $count = (int) $this->option('count');

        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if (! $tenant) {
                $this->error("Tenant with ID {$tenantId} not found.");

                return 1;
            }
            $this->seedTenantDevices($tenant, $count);
        } else {
            $tenants = Tenant::active()->get();
            if ($tenants->isEmpty()) {
                $this->warn('No active tenants found.');

                return 0;
            }

            $this->info("Seeding devices for {$tenants->count()} active tenant(s)...");
            foreach ($tenants as $tenant) {
                $this->seedTenantDevices($tenant, $count);
            }
        }

        $this->info('Device seeding completed successfully!');

        return 0;
    }

    private function seedTenantDevices(Tenant $tenant, int $count): void
    {
        // Configure dynamic tenant database connection
        Config::set('database.connections.tenant_dynamic', [
            'driver' => 'mysql',
            'host' => $tenant->database_host ?? env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => $tenant->database_name,
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ]);

        // Reconnect to ensure fresh connection
        DB::purge('tenant_dynamic');

        // Check existing devices using DB facade
        $existingCount = DB::connection('tenant_dynamic')->table('devices')->count();

        if ($existingCount > 0) {
            $this->warn("  Tenant '{$tenant->company_name}' already has {$existingCount} device(s). Skipping.");
        } else {
            // Temporarily set default connection for factory
            $originalConnection = config('database.default');
            Config::set('database.default', 'tenant_dynamic');

            // Create devices
            for ($i = 0; $i < $count; $i++) {
                $deviceData = Device::factory()->make()->toArray();
                $deviceData['created_at'] = now()->format('Y-m-d H:i:s');
                $deviceData['updated_at'] = now()->format('Y-m-d H:i:s');

                // JSON encode settings if it's an array
                if (isset($deviceData['settings']) && is_array($deviceData['settings'])) {
                    $deviceData['settings'] = json_encode($deviceData['settings']);
                }

                // Format timestamp fields (they might be Carbon instances or strings)
                if (isset($deviceData['last_sync_at']) && $deviceData['last_sync_at']) {
                    $deviceData['last_sync_at'] = is_string($deviceData['last_sync_at'])
                        ? date('Y-m-d H:i:s', strtotime($deviceData['last_sync_at']))
                        : $deviceData['last_sync_at']->format('Y-m-d H:i:s');
                }
                if (isset($deviceData['last_heartbeat_at']) && $deviceData['last_heartbeat_at']) {
                    $deviceData['last_heartbeat_at'] = is_string($deviceData['last_heartbeat_at'])
                        ? date('Y-m-d H:i:s', strtotime($deviceData['last_heartbeat_at']))
                        : $deviceData['last_heartbeat_at']->format('Y-m-d H:i:s');
                }

                DB::connection('tenant_dynamic')->table('devices')->insert($deviceData);
            }

            // Restore original connection
            Config::set('database.default', $originalConnection);

            $this->info("  Created {$count} devices for tenant: {$tenant->company_name}");
        }
    }
}
