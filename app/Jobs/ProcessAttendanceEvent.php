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
                ->where('custom_id', $this->event->person_id)
                ->where('is_active', true)
                ->first();

            if (! $employee) {
                Log::channel('mqtt')->warning('Employee not found or inactive', [
                    'custom_id' => $this->event->person_id,
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

            // Step 6: Check for duplicate records within 1 minute
            $recordedAt = \Carbon\Carbon::parse($this->event->timestamp);
            $oneMinuteAgo = $recordedAt->copy()->subMinute();
            $oneMinuteAfter = $recordedAt->copy()->addMinute();

            $duplicate = AttendanceRecord::on('tenant')
                ->where('employee_id', $employee->id)
                ->where('device_id', $device->id)
                ->whereBetween('recorded_at', [$oneMinuteAgo, $oneMinuteAfter])
                ->exists();

            if ($duplicate) {
                Log::channel('mqtt')->info('Duplicate attendance record detected', [
                    'employee_id' => $employee->id,
                    'device_id' => $device->id,
                    'recorded_at' => $this->event->timestamp,
                ]);

                return;
            }

            // Step 7: Create attendance record (Phase 1: always check-in)
            AttendanceRecord::on('tenant')->create([
                'employee_id' => $employee->id,
                'device_id' => $device->id,
                'recorded_at' => $recordedAt,
                'direction' => 'check-in', // Phase 1 simplification
                'recognition_score' => $this->event->similarity,
            ]);

            Log::channel('mqtt')->info('Attendance record created', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->full_name,
                'device_id' => $device->id,
                'recorded_at' => $this->event->timestamp,
                'recognition_score' => $this->event->similarity,
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
