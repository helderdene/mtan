<?php

namespace App\Jobs;

use App\DTOs\StrangerEventDTO;
use App\Models\DeviceRegistry;
use App\Models\Tenant\Device;
use App\Models\Tenant\StrangerLog;
use App\Services\PhotoStorageService;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessStrangerEvent implements ShouldQueue
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
    public $timeout = 60;

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
        public StrangerEventDTO $event
    ) {
        // Set the queue for this job (default priority for stranger logs)
        $this->onQueue(env('QUEUE_DEFAULT', 'attendance-default'));
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
        PhotoStorageService $photoStorage
    ): void {
        try {
            // Step 1: Resolve tenant from device_id in central database
            $deviceRegistry = DeviceRegistry::on(config('database.default'))
                ->where('device_id', $this->event->device_id)
                ->where('is_active', true)
                ->first();

            if (! $deviceRegistry) {
                Log::channel('mqtt')->error('Stranger event from unknown device', [
                    'device_id' => $this->event->device_id,
                    'record_id' => $this->event->record_id,
                ]);

                return;
            }

            // Step 2: Initialize tenant context
            $tenant = $deviceRegistry->tenant;
            $manager->setTenantContext($tenant->tenant_id);

            // Step 3: Find device in tenant database
            $device = Device::where('device_id', $this->event->device_id)->first();

            if (! $device) {
                Log::channel('mqtt')->error('Device not found in tenant database', [
                    'tenant_id' => $tenant->tenant_id,
                    'device_id' => $this->event->device_id,
                ]);

                return;
            }

            // Step 4: Upload photo to S3
            $photoPath = $photoStorage->uploadStrangerPhoto(
                $this->event->photo_base64,
                $this->event->device_id
            );

            // Step 5: Create stranger log record
            $log = StrangerLog::create([
                'device_id' => $device->id,
                'detected_at' => $this->event->detected_at,
                'photo_path' => $photoPath,
                'match_status' => 'unreviewed',
            ]);

            Log::channel('mqtt')->info('Stranger log created', [
                'tenant_id' => $tenant->tenant_id,
                'device_id' => $this->event->device_id,
                'stranger_log_id' => $log->id,
                'photo_path' => $photoPath,
                'similarity_score' => $this->event->similarity_score,
            ]);

        } catch (\Exception $e) {
            $attempt = $this->attempts();
            $maxTries = $this->tries;

            Log::channel('mqtt')->error('Failed to process stranger event', [
                'attempt' => $attempt,
                'max_tries' => $maxTries,
                'will_retry' => $attempt < $maxTries,
                'next_retry_in' => $this->backoff()[$attempt - 1] ?? 60,
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
     */
    public function failed(\Throwable $exception): void
    {
        Log::channel('mqtt')->critical('Stranger event processing permanently failed', [
            'job_id' => $this->job->getJobId(),
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
            'event' => $this->event->toArray(),
        ]);

        // TODO: Notify admin about failed stranger event processing
    }
}
