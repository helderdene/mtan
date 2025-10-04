<?php

namespace App\Jobs;

use App\DTOs\Tenant as TenantDTO;
use App\Models\Tenant as TenantModel;
use App\Models\Tenant\Device;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class UpdateDeviceHeartbeat implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $deviceId,
        public array $heartbeatData = []
    ) {
        // Set the queue for this job
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(TenantDatabaseManager $manager): void
    {
        try {
            // Step 1: Find tenant by facesluiceId in central database
            // The deviceId we receive is actually the facesluiceId from the device
            $deviceRegistry = \DB::connection(config('database.default'))
                ->table('device_registry')
                ->where('device_id', $this->deviceId)
                ->first();

            if (!$deviceRegistry) {
                Log::channel('mqtt')->warning('Device not found in registry for heartbeat', [
                    'facesluiceId' => $this->deviceId,
                ]);
                return;
            }

            // Step 2: Get tenant
            $tenant = TenantModel::on(config('database.default'))
                ->where('id', $deviceRegistry->tenant_id)
                ->where('is_active', true)
                ->first();

            if (!$tenant) {
                Log::channel('mqtt')->warning('Tenant not found or inactive for heartbeat', [
                    'tenant_id' => $deviceRegistry->tenant_id,
                ]);
                return;
            }

            // Step 3: Setup tenant database connection
            $tenantDto = new TenantDTO(
                id: $tenant->id,
                company_name: $tenant->company_name,
                subdomain: $tenant->subdomain,
                domain: $tenant->domain,
                database_name: $tenant->database_name,
                database_host: $tenant->database_host,
                subscription_plan: $tenant->subscription_plan,
                max_employees: $tenant->max_employees,
                max_devices: $tenant->max_devices,
                is_active: $tenant->is_active,
            );

            $manager->setupTenantConnection($tenantDto);

            // Step 4: Find device in tenant database
            $device = Device::on('tenant')
                ->where('device_id', $this->deviceId)
                ->first();

            if (!$device) {
                Log::channel('mqtt')->warning('Device not found in tenant database for heartbeat', [
                    'device_id' => $this->deviceId,
                    'tenant_id' => $tenant->id,
                ]);
                return;
            }

            // Step 5: Update last heartbeat timestamp
            $device->update([
                'last_heartbeat_at' => now(),
            ]);


        } catch (\Exception $e) {
            Log::channel('mqtt')->error('Failed to update device heartbeat', [
                'device_id' => $this->deviceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
