<?php

namespace App\Jobs;

use App\Domain\Attendance\Services\DirectionDetector;
use App\Domain\Attendance\Services\PatternAnalyzer;
use App\Domain\Attendance\Services\SummaryCalculator;
use App\DTOs\AttendanceEventDTO;
use App\Models\DeviceRegistry;
use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\Device;
use App\Models\Tenant\Employee;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessAttendanceEvent implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 30;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public AttendanceEventDTO $event
    ) {
        // Set the queue for this job (high priority for real-time attendance events)
        $this->onQueue(env('QUEUE_HIGH_PRIORITY', 'attendance-high-priority'));
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        // Exponential backoff: 1 minute, 5 minutes, 15 minutes
        return [60, 300, 900];
    }

    /**
     * Execute the job.
     */
    public function handle(
        TenantDatabaseManager $manager,
        DirectionDetector $detector,
        PatternAnalyzer $patternAnalyzer,
        SummaryCalculator $summaryCalculator,
        \App\Domain\Attendance\Services\ViolationDetector $violationDetector
    ): void
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

            // Step 7: Detect attendance direction using DirectionDetector
            $shift = $employee->current_shift; // Get employee's current shift
            $directionResult = $detector->detect($employee, $recordedAt, $shift);

            // Log low-confidence detections for monitoring
            if ($directionResult->confidence < 50) {
                Log::channel('mqtt')->warning('Low confidence direction detection', [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->full_name,
                    'detected_direction' => $directionResult->direction,
                    'confidence' => $directionResult->confidence,
                    'confidence_level' => $directionResult->getConfidenceLevel(),
                    'reason' => $directionResult->reason,
                    'scores' => $directionResult->scores,
                    'recorded_at' => $recordedAt->format('Y-m-d H:i:s'),
                ]);
            }

            // Step 8: Create attendance record with all MQTT fields and direction detection
            // Convert similarity score from 0-100 range to 0-1 range for database
            $recognitionScore = $this->event->similarity_score / 100;

            $record = AttendanceRecord::on('tenant')->create([
                'employee_id' => $employee->id,
                'device_id' => $device->id,
                'recorded_at' => $recordedAt,
                'direction' => $directionResult->direction,
                'confidence_score' => $directionResult->confidence,
                'detection_reason' => $directionResult->reason,
                'recognition_score' => $recognitionScore,
                'record_id' => $this->event->record_id,
                'person_name' => $this->event->person_name,
                'device_name' => $this->event->device_name ?? $device->device_name,
                'verify_status' => $this->event->verify_status,
                'temperature' => $this->event->temperature,
                'mask_status' => $this->event->mask_status,
                'photo_path' => null, // Photo storage will be implemented in Phase 3
            ]);

            Log::channel('mqtt')->info('Attendance record created with direction detection', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->full_name,
                'custom_id' => $this->event->custom_id,
                'record_id' => $this->event->record_id,
                'device_id' => $device->id,
                'device_name' => $this->event->device_name,
                'recorded_at' => $this->event->timestamp->format('Y-m-d H:i:s'),
                'direction' => $directionResult->direction,
                'confidence' => $directionResult->confidence,
                'confidence_level' => $directionResult->getConfidenceLevel(),
                'detection_reason' => $directionResult->reason,
                'recognition_score' => $this->event->similarity_score,
                'temperature' => $this->event->temperature,
                'mask_status' => $this->event->mask_status,
                'verify_status' => $this->event->verify_status,
            ]);

            // Step 8.5: Update daily attendance summary in real-time
            try {
                $summary = $summaryCalculator->updateSummaryFromEvent($record);

                Log::channel('mqtt')->info('Daily attendance summary updated', [
                    'employee_id' => $employee->id,
                    'date' => $summary->date->toDateString(),
                    'total_work_hours' => $summary->total_work_hours,
                    'total_break_hours' => $summary->total_break_hours,
                    'overtime_hours' => $summary->overtime_hours,
                    'status' => $summary->status,
                    'is_complete' => $summary->is_complete,
                ]);
            } catch (\Exception $e) {
                // Log summary update errors but don't fail the job
                Log::channel('mqtt')->warning('Failed to update daily attendance summary', [
                    'employee_id' => $employee->id,
                    'recorded_at' => $recordedAt->format('Y-m-d H:i:s'),
                    'error' => $e->getMessage(),
                ]);
            }

            // Step 8.6: Detect and log attendance violations
            try {
                $violations = $violationDetector->detectFromRecord($record);

                foreach ($violations as $violation) {
                    event(new \App\Events\ViolationDetected($violation));

                    Log::channel('mqtt')->info('Violation detected', [
                        'employee_id' => $employee->id,
                        'violation_id' => $violation->id,
                        'type' => $violation->type,
                        'severity' => $violation->severity,
                        'minutes_deviation' => $violation->minutes_deviation,
                        'violation_date' => $violation->violation_date->toDateString(),
                    ]);
                }
            } catch (\Exception $e) {
                // Log violation detection errors but don't fail the job
                Log::channel('mqtt')->warning('Failed to detect violations', [
                    'employee_id' => $employee->id,
                    'recorded_at' => $recordedAt->format('Y-m-d H:i:s'),
                    'error' => $e->getMessage(),
                ]);
            }

            // Step 9: Invalidate pattern cache to ensure fresh analysis on next attendance event
            try {
                $patternAnalyzer->invalidatePatternCache($employee);
                Log::channel('mqtt')->debug('Pattern cache invalidated', [
                    'employee_id' => $employee->id,
                ]);
            } catch (\Exception $e) {
                // Log cache invalidation errors but don't fail the job
                Log::channel('mqtt')->warning('Failed to invalidate pattern cache', [
                    'employee_id' => $employee->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Store notification in cache for UI polling
            $cacheKey = "attendance_notification:{$tenant->id}:".now()->timestamp;
            $deviceName = $device->device_name ?: "Device {$device->device_id}";
            $directionLabel = str_replace('-', ' ', $directionResult->direction);
            $message = "{$employee->full_name} {$directionLabel} at {$deviceName} ({$recordedAt->format('H:i')})";

            $notificationData = [
                'type' => 'attendance',
                'message' => $message,
                'employee_id' => $employee->id,
                'employee_name' => $employee->full_name,
                'device_id' => $device->id,
                'device_name' => $deviceName,
                'recorded_at' => $recordedAt->toIso8601String(),
                'direction' => $directionResult->direction,
                'confidence' => $directionResult->confidence,
                'recognition_score' => $this->event->similarity_score,
            ];

            \Cache::put($cacheKey, $notificationData, now()->addMinutes(5));

            Log::channel('mqtt')->info('Attendance notification cached', [
                'cache_key' => $cacheKey,
                'notification' => $notificationData,
            ]);
        } catch (\Exception $e) {
            $attempt = $this->attempts();
            $maxTries = $this->tries;

            Log::channel('mqtt')->error('Failed to process attendance event', [
                'attempt' => $attempt,
                'max_tries' => $maxTries,
                'will_retry' => $attempt < $maxTries,
                'next_retry_in' => $attempt < $maxTries ? $this->backoff()[$attempt - 1] ?? 60 : null,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'event' => $this->event->toArray(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::channel('failed_jobs')->critical('ProcessAttendanceEvent permanently failed after retries', [
            'job_id' => $this->job->getJobId() ?? 'unknown',
            'queue' => $this->job->getQueue() ?? 'unknown',
            'attempts' => $this->attempts(),
            'event_data' => $this->event->toArray(),
            'custom_id' => $this->event->custom_id,
            'device_id' => $this->event->device_id,
            'timestamp' => $this->event->timestamp->toIso8601String(),
            'error_message' => $exception->getMessage(),
            'error_class' => get_class($exception),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Notify administrators of critical attendance data loss
        try {
            $adminEmail = config('mail.admin_email');
            if ($adminEmail) {
                \Notification::route('mail', $adminEmail)
                    ->notify(new \App\Notifications\CriticalJobFailedNotification(
                        jobType: 'ProcessAttendanceEvent',
                        jobId: $this->job->getJobId() ?? 'unknown',
                        attempts: $this->attempts(),
                        exception: $exception,
                        payload: $this->event->toArray()
                    ));
            }
        } catch (\Exception $e) {
            Log::channel('failed_jobs')->error('Failed to send critical job failure notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
