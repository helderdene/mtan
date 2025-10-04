<?php

namespace App\Jobs;

use App\DTOs\Tenant as TenantDTO;
use App\Models\Tenant as TenantModel;
use App\Models\Tenant\Device;
use App\Services\MQTT\MQTTClient;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class HandleDeviceOnlineStatus implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $facesluiceId,
        public array $deviceInfo
    ) {
        // Set the queue for this job
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(MQTTClient $mqttClient, TenantDatabaseManager $manager): void
    {
        try {
            // Step 1: Find tenant by facesluiceId in central database
            $deviceRegistry = \DB::connection(config('database.default'))
                ->table('device_registry')
                ->where('device_id', $this->facesluiceId)
                ->first();

            if (! $deviceRegistry) {
                Log::channel('mqtt')->warning('Device not found in registry for online status', [
                    'facesluiceId' => $this->facesluiceId,
                ]);

                return;
            }

            // Step 2: Get tenant
            $tenant = TenantModel::on(config('database.default'))
                ->where('id', $deviceRegistry->tenant_id)
                ->where('is_active', true)
                ->first();

            if (! $tenant) {
                Log::channel('mqtt')->warning('Tenant not found or inactive for online status', [
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
                ->where('device_id', $this->facesluiceId)
                ->first();

            if (! $device) {
                Log::channel('mqtt')->warning('Device not found in tenant database for online status', [
                    'facesluiceId' => $this->facesluiceId,
                    'tenant_id' => $tenant->id,
                ]);

                return;
            }

            // Step 5: Check if device was offline (no heartbeat in last 2 minutes)
            $wasOffline = ! $device->last_heartbeat_at ||
                          $device->last_heartbeat_at->lt(now()->subMinutes(2));

            // Update device status and last heartbeat
            $device->update([
                'last_heartbeat_at' => now(),
                'ip_address' => $this->deviceInfo['ip'] ?? $this->deviceInfo['wifiIp'] ?? null,
            ]);

            // Only log when device comes back online after being offline
            if ($wasOffline) {
                Log::channel('mqtt')->info('Device came online', [
                    'facesluiceId' => $this->facesluiceId,
                    'device_name' => $device->name,
                    'ip_address' => $device->ip_address,
                    'wifi_ip' => $this->deviceInfo['wifiIp'] ?? null,
                    'tenant_id' => $tenant->id,
                ]);
            }

            // Step 6: Send acknowledgment back to device
            $ackPayload = json_encode([
                'operator' => 'Online-Ack',
                'info' => [
                    'facesluiceId' => $this->facesluiceId,
                    'result' => 'ok',
                ],
            ]);

            // Publish to basic topic only
            $basicTopic = 'mqtt/face/basic';
            $mqttClient->publish($basicTopic, $ackPayload, 1);

        } catch (\Exception $e) {
            Log::channel('mqtt')->error('Failed to handle device online status', [
                'facesluiceId' => $this->facesluiceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
