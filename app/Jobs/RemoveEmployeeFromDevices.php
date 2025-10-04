<?php

namespace App\Jobs;

use App\DTOs\Tenant as TenantDTO;
use App\Models\Tenant as TenantModel;
use App\Models\Tenant\Device;
use App\Models\Tenant\DeviceEnrollment;
use App\Models\Tenant\Employee;
use App\Services\MQTT\MQTTClient;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RemoveEmployeeFromDevices implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $employeeId,
        public string $tenantId
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
            // Step 1: Get tenant from central database
            $tenant = TenantModel::on(config('database.default'))
                ->where('id', $this->tenantId)
                ->where('is_active', true)
                ->first();

            if (! $tenant) {
                Log::channel('mqtt')->warning('Tenant not found or inactive', [
                    'tenant_id' => $this->tenantId,
                ]);

                return;
            }

            // Step 2: Setup tenant database connection
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

            // Step 3: Get employee from tenant database (use withTrashed in case it's soft deleted)
            $employee = Employee::on('tenant')
                ->where('id', $this->employeeId)
                ->first();

            if (! $employee) {
                Log::channel('mqtt')->warning('Employee not found', [
                    'employee_id' => $this->employeeId,
                    'tenant_id' => $this->tenantId,
                ]);

                return;
            }

            // Step 4: Get all device enrollments for this employee
            $enrollments = DeviceEnrollment::on('tenant')
                ->where('employee_id', $employee->id)
                ->with('device')
                ->get();

            if ($enrollments->isEmpty()) {
                Log::channel('mqtt')->info('No device enrollments found for employee', [
                    'employee_id' => $this->employeeId,
                    'tenant_id' => $this->tenantId,
                ]);

                return;
            }

            // Step 5: Remove employee from each device
            foreach ($enrollments as $enrollment) {
                try {
                    $device = $enrollment->device;

                    if (!$device || !$device->is_active) {
                        continue;
                    }

                    // Prepare MQTT payload for DeletePerson command
                    $payload = json_encode([
                        'messageId' => uniqid('delete_', true),
                        'operator' => 'DeletePerson',
                        'info' => [
                            'customId' => $employee->custom_id,
                        ]
                    ]);

                    // Publish to device-specific topic (Laravel -> Device)
                    $topic = "mqtt/face/{$device->device_id}";
                    $mqttClient->publish($topic, $payload, 1);

                    // Delete the enrollment record
                    $enrollment->delete();

                    Log::channel('mqtt')->info('Employee removed from device', [
                        'employee_id' => $employee->id,
                        'employee_custom_id' => $employee->custom_id,
                        'device_id' => $device->device_id,
                        'topic' => $topic,
                    ]);
                } catch (\Exception $e) {
                    Log::channel('mqtt')->error('Failed to remove employee from device', [
                        'employee_id' => $employee->id,
                        'device_id' => $device->device_id ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::channel('mqtt')->error('Failed to remove employee from devices', [
                'employee_id' => $this->employeeId,
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
