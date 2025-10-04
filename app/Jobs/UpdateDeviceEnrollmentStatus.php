<?php

namespace App\Jobs;

use App\DTOs\Tenant as TenantDTO;
use App\Models\Tenant as TenantModel;
use App\Models\Tenant\Device;
use App\Models\Tenant\DeviceEnrollment;
use App\Models\Tenant\Employee;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class UpdateDeviceEnrollmentStatus implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $deviceId,
        public array $acknowledgmentData,
        public string $operator  // 'AddPerson', 'EditPerson', 'DeletePerson'
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
            // Step 1: Find tenant by device_id in central database
            $deviceRegistry = \DB::connection(config('database.default'))
                ->table('device_registry')
                ->where('device_id', $this->deviceId)
                ->first();

            if (!$deviceRegistry) {
                Log::channel('mqtt')->warning('Device not found in registry', [
                    'device_id' => $this->deviceId,
                ]);
                return;
            }

            // Step 2: Get tenant
            $tenant = TenantModel::on(config('database.default'))
                ->where('id', $deviceRegistry->tenant_id)
                ->where('is_active', true)
                ->first();

            if (!$tenant) {
                Log::channel('mqtt')->warning('Tenant not found or inactive', [
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
                Log::channel('mqtt')->warning('Device not found in tenant database', [
                    'device_id' => $this->deviceId,
                    'tenant_id' => $tenant->id,
                ]);
                return;
            }

            // Step 5: Find employee by customId
            $customId = $this->acknowledgmentData['customId'] ?? null;

            if (!$customId) {
                Log::channel('mqtt')->error('Missing customId in acknowledgment', [
                    'device_id' => $this->deviceId,
                    'operator' => $this->operator,
                ]);
                return;
            }

            $employee = Employee::on('tenant')
                ->where('custom_id', $customId)
                ->first();

            if (!$employee) {
                Log::channel('mqtt')->warning('Employee not found', [
                    'custom_id' => $customId,
                    'device_id' => $this->deviceId,
                ]);
                return;
            }

            // Step 6: Update enrollment status based on operator
            $success = $this->acknowledgmentData['success'] ?? false;

            if ($this->operator === 'DeletePerson') {
                // For delete, remove the enrollment record
                if ($success) {
                    DeviceEnrollment::on('tenant')
                        ->where('employee_id', $employee->id)
                        ->where('device_id', $device->id)
                        ->delete();

                    Log::channel('mqtt')->info('Employee enrollment deleted', [
                        'employee_id' => $employee->id,
                        'device_id' => $device->device_id,
                    ]);
                } else {
                    // Mark as failed
                    DeviceEnrollment::on('tenant')->updateOrCreate(
                        [
                            'employee_id' => $employee->id,
                            'device_id' => $device->id,
                        ],
                        [
                            'enrollment_status' => 'failed',
                        ]
                    );
                }
            } else {
                // For AddPerson/EditPerson, update enrollment status
                $status = $success ? 'synced' : 'failed';
                $syncedAt = $success ? now() : null;

                DeviceEnrollment::on('tenant')->updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'device_id' => $device->id,
                    ],
                    [
                        'enrollment_status' => $status,
                        'synced_at' => $syncedAt,
                    ]
                );

                Log::channel('mqtt')->info('Employee enrollment status updated', [
                    'employee_id' => $employee->id,
                    'device_id' => $device->device_id,
                    'status' => $status,
                    'operator' => $this->operator,
                ]);

                // Store notification in cache for UI polling
                $cacheKey = "sync_notification:{$employee->id}:{$device->id}";
                $deviceName = $device->device_name ?: "Device {$device->device_id}";
                $message = $status === 'synced'
                    ? "Successfully synced {$employee->full_name} to {$deviceName}"
                    : "Failed to sync {$employee->full_name} to {$deviceName}";

                $notificationData = [
                    'status' => $status,
                    'message' => $message,
                    'employee_id' => $employee->id,
                    'device_id' => $device->id,
                ];

                \Cache::put($cacheKey, $notificationData, now()->addMinutes(5));

                Log::channel('mqtt')->info('Sync notification cached', [
                    'cache_key' => $cacheKey,
                    'notification' => $notificationData,
                ]);
            }

        } catch (\Exception $e) {
            Log::channel('mqtt')->error('Failed to update enrollment status', [
                'device_id' => $this->deviceId,
                'operator' => $this->operator,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
