<?php

namespace App\Jobs;

use App\DTOs\AttendanceEventDTO;
use App\Models\DeviceRegistry;
use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\Device;
use App\Models\Tenant\Employee;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessAttendanceEvent implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public AttendanceEventDTO $event
    ) {
        // Set the queue for this job
        $this->onQueue('attendance');
    }

    /**
     * Execute the job.
     */
    public function handle(TenantDatabaseManager $manager): void
    {
        try {
            // Step 1: Resolve tenant from device_id in central database
            // Use on() to explicitly query the central database (default connection)
            $deviceRegistry = DeviceRegistry::on(config('database.default'))
                ->where('device_id', $this->event->device_id)
                ->where('is_active', true)
                ->first();

            if (! $deviceRegistry) {
                Log::channel('mqtt')->warning('Device not registered', [
                    'device_id' => $this->event->device_id,
                ]);

                return;
            }

            $tenant = $deviceRegistry->tenant;

            if (! $tenant || ! $tenant->is_active) {
                Log::channel('mqtt')->warning('Tenant not found or inactive', [
                    'device_id' => $this->event->device_id,
                    'tenant_id' => $deviceRegistry->tenant_id,
                ]);

                return;
            }

            // Step 2: Set up tenant database connection
            $tenantDto = new \App\DTOs\Tenant(
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

            // Step 3: Handle stranger events (log but don't create attendance record)
            if ($this->event->event_type === 'stranger' || ! $this->event->person_id) {
                Log::channel('mqtt')->info('Stranger detected', [
                    'device_id' => $this->event->device_id,
                    'tenant_id' => $tenant->id,
                    'image_url' => $this->event->image_url,
                ]);

                return;
            }

            // Step 4: Look up employee by custom_id
            $employee = Employee::on('tenant')
                ->where('custom_id', $this->event->custom_id)
                ->where('is_active', true)
                ->first();

            if (! $employee) {
                Log::channel('mqtt')->warning('Employee not found or inactive', [
                    'custom_id' => $this->event->custom_id,
                    'device_id' => $this->event->device_id,
                    'tenant_id' => $tenant->id,
                ]);

                return;
            }

            // Step 5: Look up device in tenant database
            $device = Device::on('tenant')
                ->where('device_id', $this->event->device_id)
                ->first();

            if (! $device) {
                Log::channel('mqtt')->warning('Device not found in tenant database', [
                    'device_id' => $this->event->device_id,
                    'tenant_id' => $tenant->id,
                ]);

                return;
            }

            // Step 6: Check for duplicate records using record_id
            $recordedAt = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $this->event->timestamp->format('Y-m-d H:i:s'));

            // First check if we've already processed this exact record_id
            $duplicate = AttendanceRecord::on('tenant')
                ->where('record_id', $this->event->record_id)
                ->exists();

            if ($duplicate) {
                Log::channel('mqtt')->info('Duplicate attendance record detected (same record_id)', [
                    'employee_id' => $employee->id,
                    'device_id' => $device->id,
                    'record_id' => $this->event->record_id,
                    'recorded_at' => $this->event->timestamp->format('Y-m-d H:i:s'),
                ]);

                return;
            }

            // Also check for duplicate by time window as fallback
            $oneMinuteAgo = $recordedAt->copy()->subMinute();
            $oneMinuteAfter = $recordedAt->copy()->addMinute();

            $timeDuplicate = AttendanceRecord::on('tenant')
                ->where('employee_id', $employee->id)
                ->where('device_id', $device->id)
                ->whereBetween('recorded_at', [$oneMinuteAgo, $oneMinuteAfter])
                ->exists();

            if ($timeDuplicate) {
                Log::channel('mqtt')->info('Duplicate attendance record detected (within 1-minute window)', [
                    'employee_id' => $employee->id,
                    'device_id' => $device->id,
                    'recorded_at' => $this->event->timestamp->format('Y-m-d H:i:s'),
                ]);

                return;
            }

            // Step 7: Create attendance record with all MQTT fields
            // Convert similarity score from 0-100 range to 0-1 range for database
            $recognitionScore = $this->event->similarity_score / 100;

            AttendanceRecord::on('tenant')->create([
                'employee_id' => $employee->id,
                'device_id' => $device->id,
                'recorded_at' => $recordedAt,
                'direction' => 'check-in', // Phase 1 simplification
                'recognition_score' => $recognitionScore,
                'record_id' => $this->event->record_id,
                'person_name' => $this->event->person_name,
                'device_name' => $this->event->device_name ?? $device->device_name,
                'verify_status' => $this->event->verify_status,
                'temperature' => $this->event->temperature,
                'mask_status' => $this->event->mask_status,
                'photo_path' => null, // Photo storage will be implemented in Phase 3
            ]);

            Log::channel('mqtt')->info('Attendance record created with full MQTT metadata', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->full_name,
                'custom_id' => $this->event->custom_id,
                'record_id' => $this->event->record_id,
                'device_id' => $device->id,
                'device_name' => $this->event->device_name,
                'recorded_at' => $this->event->timestamp->format('Y-m-d H:i:s'),
                'recognition_score' => $this->event->similarity_score,
                'temperature' => $this->event->temperature,
                'mask_status' => $this->event->mask_status,
                'verify_status' => $this->event->verify_status,
            ]);

            // Store notification in cache for UI polling
            $cacheKey = "attendance_notification:{$tenant->id}:" . now()->timestamp;
            $deviceName = $device->device_name ?: "Device {$device->device_id}";
            $message = "{$employee->full_name} checked in at {$deviceName} ({$recordedAt->format('H:i')})";

            $notificationData = [
                'type' => 'attendance',
                'message' => $message,
                'employee_id' => $employee->id,
                'employee_name' => $employee->full_name,
                'device_id' => $device->id,
                'device_name' => $deviceName,
                'recorded_at' => $recordedAt->toIso8601String(),
                'direction' => 'check-in',
                'recognition_score' => $this->event->similarity,
            ];

            \Cache::put($cacheKey, $notificationData, now()->addMinutes(5));

            Log::channel('mqtt')->info('Attendance notification cached', [
                'cache_key' => $cacheKey,
                'notification' => $notificationData,
            ]);
        } catch (\Exception $e) {
            Log::channel('mqtt')->error('Failed to process attendance event', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'event' => $this->event->toArray(),
            ]);

            throw $e;
        }
    }
}
