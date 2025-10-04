<?php

namespace App\Console\Commands;

use App\Models\DeviceRegistry;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class SyncDeviceRegistryToTenants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'device-registry:sync {tenant_id? : Specific tenant ID to sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync devices from central registry to tenant databases';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantId = $this->argument('tenant_id');

        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if (! $tenant) {
                $this->error("Tenant with ID {$tenantId} not found.");

                return 1;
            }
            $this->syncTenant($tenant);
        } else {
            $tenants = Tenant::active()->get();
            if ($tenants->isEmpty()) {
                $this->warn('No active tenants found.');

                return 0;
            }

            $this->info("Syncing devices for {$tenants->count()} active tenant(s)...");
            foreach ($tenants as $tenant) {
                $this->syncTenant($tenant);
            }
        }

        $this->info('Device registry sync completed successfully!');

        return 0;
    }

    private function syncTenant(Tenant $tenant): void
    {
        $this->info("Syncing devices for tenant: {$tenant->company_name}");

        // Get all devices for this tenant from central registry
        $devices = DeviceRegistry::where('tenant_id', $tenant->id)->get();

        if ($devices->isEmpty()) {
            $this->warn('  No devices found in registry for this tenant.');

            return;
        }

        // Configure tenant database connection
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

        DB::purge('tenant_dynamic');

        $synced = 0;
        $updated = 0;

        foreach ($devices as $deviceRegistry) {
            $deviceData = [
                'device_id' => $deviceRegistry->device_id,
                'name' => $deviceRegistry->device_name,
                'location' => $deviceRegistry->location,
                'device_type' => $deviceRegistry->device_type,
                'ip_address' => $deviceRegistry->ip_address,
                'mac_address' => $deviceRegistry->mac_address,
                'firmware_version' => $deviceRegistry->firmware_version,
                'capacity' => 10000,
                'current_count' => 0,
                'is_entry_device' => true,
                'is_exit_device' => true,
                'timezone' => 'Asia/Ulaanbaatar',
                'settings' => json_encode(['auto_sync' => true]),
                'is_active' => $deviceRegistry->is_active,
                'last_heartbeat_at' => $deviceRegistry->last_seen_at,
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ];

            // Check if device already exists in tenant database
            $exists = DB::connection('tenant_dynamic')
                ->table('devices')
                ->where('device_id', $deviceRegistry->device_id)
                ->exists();

            if ($exists) {
                DB::connection('tenant_dynamic')
                    ->table('devices')
                    ->where('device_id', $deviceRegistry->device_id)
                    ->update($deviceData);
                $updated++;
            } else {
                $deviceData['created_at'] = now()->format('Y-m-d H:i:s');
                DB::connection('tenant_dynamic')
                    ->table('devices')
                    ->insert($deviceData);
                $synced++;
            }
        }

        $this->info("  Synced {$synced} new device(s), updated {$updated} existing device(s)");
    }
}
