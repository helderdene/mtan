<?php

namespace App\Observers;

use App\Models\DeviceRegistry;
use App\Models\Tenant;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeviceRegistryObserver
{
    /**
     * Handle the DeviceRegistry "created" event.
     * Sync new device to tenant's devices table
     */
    public function created(DeviceRegistry $deviceRegistry): void
    {
        $this->syncDeviceToTenant($deviceRegistry);
    }

    /**
     * Handle the DeviceRegistry "updated" event.
     * Update device in tenant's devices table
     */
    public function updated(DeviceRegistry $deviceRegistry): void
    {
        $this->syncDeviceToTenant($deviceRegistry, true);
    }

    /**
     * Handle the DeviceRegistry "deleted" event.
     * Remove device from tenant's devices table
     */
    public function deleted(DeviceRegistry $deviceRegistry): void
    {
        try {
            $tenant = Tenant::find($deviceRegistry->tenant_id);
            if (! $tenant) {
                return;
            }

            $this->configureTenantConnection($tenant);

            DB::connection('tenant_dynamic')
                ->table('devices')
                ->where('device_id', $deviceRegistry->device_id)
                ->delete();

            Log::info("Device {$deviceRegistry->device_id} removed from tenant {$tenant->company_name}");
        } catch (\Exception $e) {
            Log::error("Failed to delete device from tenant database: {$e->getMessage()}");
        }
    }

    /**
     * Sync device registry entry to tenant database
     */
    private function syncDeviceToTenant(DeviceRegistry $deviceRegistry, bool $isUpdate = false): void
    {
        try {
            $tenant = Tenant::find($deviceRegistry->tenant_id);
            if (! $tenant) {
                Log::warning("Tenant not found for device {$deviceRegistry->device_id}");

                return;
            }

            $this->configureTenantConnection($tenant);

            $deviceData = [
                'device_id' => $deviceRegistry->device_id,
                'name' => $deviceRegistry->device_name,
                'location' => $deviceRegistry->location,
                'device_type' => $deviceRegistry->device_type,
                'ip_address' => $deviceRegistry->ip_address,
                'mac_address' => $deviceRegistry->mac_address,
                'firmware_version' => $deviceRegistry->firmware_version,
                'capacity' => 10000, // Default capacity
                'current_count' => 0,
                'is_entry_device' => true,
                'is_exit_device' => true,
                'timezone' => 'Asia/Ulaanbaatar',
                'settings' => json_encode(['auto_sync' => true]),
                'is_active' => $deviceRegistry->is_active,
                'last_heartbeat_at' => $deviceRegistry->last_seen_at,
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ];

            if ($isUpdate) {
                // Update existing device
                DB::connection('tenant_dynamic')
                    ->table('devices')
                    ->where('device_id', $deviceRegistry->device_id)
                    ->update($deviceData);

                Log::info("Device {$deviceRegistry->device_id} updated in tenant {$tenant->company_name}");
            } else {
                // Create new device
                $deviceData['created_at'] = now()->format('Y-m-d H:i:s');

                DB::connection('tenant_dynamic')
                    ->table('devices')
                    ->insert($deviceData);

                Log::info("Device {$deviceRegistry->device_id} synced to tenant {$tenant->company_name}");
            }
        } catch (\Exception $e) {
            Log::error("Failed to sync device to tenant database: {$e->getMessage()}");
        }
    }

    /**
     * Configure dynamic tenant database connection
     */
    private function configureTenantConnection(Tenant $tenant): void
    {
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
    }
}
