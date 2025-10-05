# Technical Specification

This is the technical specification for the spec detailed in @.agent-os/specs/2025-10-05-failed-job-handling/spec.md

> Created: 2025-10-05
> Version: 1.0.0

## Technical Requirements

### Retry Configuration

**Update Job Classes** with retry logic:

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\Middleware\RateLimited;

class ProcessAttendanceEvent implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times to retry the job.
     */
    public int $tries = 3;

    /**
     * Number of seconds to wait before retrying.
     * Uses exponential backoff: 60s, 300s, 900s
     */
    public int $backoff = 60;

    /**
     * Maximum execution time (seconds).
     */
    public int $timeout = 60;

    /**
     * Maximum number of exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    public function __construct(
        public array $payload,
        public string $deviceId
    ) {
        $this->onQueue('attendance-high-priority');
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        // Exponential backoff: 1 min, 5 min, 15 min
        return [60, 300, 900];
    }

    /**
     * Handle job execution.
     */
    public function handle()
    {
        try {
            // Main job logic
            // ...
        } catch (\Exception $e) {
            // Log error with context
            Log::error("ProcessAttendanceEvent failed", [
                'device_id' => $this->deviceId,
                'payload' => $this->payload,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Check if should retry
            if ($this->attempts() < $this->tries) {
                // Release back to queue for retry
                $this->release($this->backoff()[$this->attempts() - 1] ?? 60);
            } else {
                // All retries exhausted, fail the job
                $this->fail($e);
            }
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception)
    {
        Log::critical("ProcessAttendanceEvent failed permanently", [
            'device_id' => $this->deviceId,
            'payload' => $this->payload,
            'error' => $exception->getMessage(),
        ]);

        // Send alert to administrators
        Notification::route('mail', config('app.admin_email'))
            ->notify(new CriticalJobFailedNotification($this, $exception));
    }
}
```

### Failed Job Model

**Location**: `app/Models/FailedJob.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedJob extends Model
{
    protected $table = 'failed_jobs';

    protected $fillable = [
        'uuid',
        'connection',
        'queue',
        'payload',
        'exception',
        'failed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'failed_at' => 'datetime',
    ];

    /**
     * Scope for specific queue.
     */
    public function scopeQueue($query, string $queue)
    {
        return $query->where('queue', $queue);
    }

    /**
     * Scope for date range.
     */
    public function scopeFailedBetween($query, $start, $end)
    {
        return $query->whereBetween('failed_at', [$start, $end]);
    }

    /**
     * Get job class name from payload.
     */
    public function getJobClassAttribute(): string
    {
        $payload = is_string($this->payload)
            ? json_decode($this->payload, true)
            : $this->payload;

        return $payload['displayName'] ?? 'Unknown';
    }

    /**
     * Get decoded payload.
     */
    public function getDecodedPayloadAttribute(): array
    {
        return is_string($this->payload)
            ? json_decode($this->payload, true)
            : $this->payload;
    }
}
```

### Retry Commands

**Command**: `app/Console/Commands/RetryFailedJobCommand.php`

**Signature**: `queue:retry-failed {id?} {--queue=} {--all}`

```php
<?php

namespace App\Console\Commands;

use App\Models\FailedJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RetryFailedJobCommand extends Command
{
    protected $signature = 'queue:retry-failed
        {id? : The ID of the failed job to retry}
        {--queue= : Retry all failed jobs from this queue}
        {--all : Retry all failed jobs}';

    protected $description = 'Retry failed jobs with filtering options';

    public function handle()
    {
        if ($this->option('all')) {
            return $this->retryAll();
        }

        if ($queue = $this->option('queue')) {
            return $this->retryQueue($queue);
        }

        if ($id = $this->argument('id')) {
            return $this->retryJob($id);
        }

        $this->error('Please specify --all, --queue, or provide a job ID');
        return Command::FAILURE;
    }

    protected function retryJob(int $id)
    {
        $failedJob = FailedJob::find($id);

        if (!$failedJob) {
            $this->error("Failed job {$id} not found");
            return Command::FAILURE;
        }

        $this->info("Retrying job {$id}: {$failedJob->job_class}");

        Artisan::call('queue:retry', ['id' => $failedJob->uuid]);

        $this->info("Job {$id} has been pushed back to the {$failedJob->queue} queue");
        return Command::SUCCESS;
    }

    protected function retryQueue(string $queue)
    {
        $jobs = FailedJob::where('queue', $queue)->get();

        if ($jobs->isEmpty()) {
            $this->info("No failed jobs found in queue: {$queue}");
            return Command::SUCCESS;
        }

        $this->info("Found {$jobs->count()} failed jobs in queue: {$queue}");

        if (!$this->confirm('Do you want to retry all these jobs?')) {
            return Command::SUCCESS;
        }

        foreach ($jobs as $job) {
            Artisan::call('queue:retry', ['id' => $job->uuid]);
            $this->info("Retried job {$job->id}");
        }

        $this->info("All jobs from queue {$queue} have been retried");
        return Command::SUCCESS;
    }

    protected function retryAll()
    {
        $count = FailedJob::count();

        if ($count === 0) {
            $this->info('No failed jobs to retry');
            return Command::SUCCESS;
        }

        $this->warn("Found {$count} failed jobs");

        if (!$this->confirm('Do you want to retry ALL failed jobs?')) {
            return Command::SUCCESS;
        }

        Artisan::call('queue:retry', ['id' => 'all']);

        $this->info("All {$count} failed jobs have been retried");
        return Command::SUCCESS;
    }
}
```

### API Endpoints

**Routes**: `routes/api.php`

```php
// Failed Job Management
GET    /api/failed-jobs                      // List failed jobs
GET    /api/failed-jobs/{id}                 // View failed job details
POST   /api/failed-jobs/{id}/retry           // Retry specific job
POST   /api/failed-jobs/retry-all            // Retry all failed jobs
DELETE /api/failed-jobs/{id}                 // Delete failed job
POST   /api/failed-jobs/prune                // Delete old failed jobs

// Query parameters:
// ?queue=attendance-high-priority
// ?from=2025-10-01&to=2025-10-31
// ?job_class=ProcessAttendanceEvent
```

**Controller**: `app/Http/Controllers/Api/FailedJobController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Models\FailedJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class FailedJobController extends Controller
{
    public function index(Request $request)
    {
        $query = FailedJob::query();

        if ($queue = $request->query('queue')) {
            $query->queue($queue);
        }

        if ($from = $request->query('from')) {
            $query->where('failed_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->where('failed_at', '<=', $to);
        }

        $jobs = $query->orderBy('failed_at', 'desc')
            ->paginate(20);

        return response()->json($jobs);
    }

    public function show(int $id)
    {
        $job = FailedJob::findOrFail($id);

        return response()->json([
            'id' => $job->id,
            'uuid' => $job->uuid,
            'queue' => $job->queue,
            'job_class' => $job->job_class,
            'payload' => $job->decoded_payload,
            'exception' => $job->exception,
            'failed_at' => $job->failed_at,
        ]);
    }

    public function retry(int $id)
    {
        $job = FailedJob::findOrFail($id);

        Artisan::call('queue:retry', ['id' => $job->uuid]);

        return response()->json([
            'message' => 'Job has been retried',
            'job_id' => $job->id,
            'queue' => $job->queue,
        ]);
    }

    public function retryAll(Request $request)
    {
        $queue = $request->input('queue');

        if ($queue) {
            $jobs = FailedJob::where('queue', $queue)->get();

            foreach ($jobs as $job) {
                Artisan::call('queue:retry', ['id' => $job->uuid]);
            }

            return response()->json([
                'message' => "Retried {$jobs->count()} jobs from queue {$queue}",
                'count' => $jobs->count(),
            ]);
        }

        Artisan::call('queue:retry', ['id' => 'all']);

        $count = FailedJob::count();

        return response()->json([
            'message' => "Retried all failed jobs",
            'count' => $count,
        ]);
    }

    public function destroy(int $id)
    {
        $job = FailedJob::findOrFail($id);

        Artisan::call('queue:forget', ['id' => $job->uuid]);

        return response()->json([
            'message' => 'Failed job has been deleted',
        ]);
    }

    public function prune(Request $request)
    {
        $hours = $request->input('hours', 168); // Default 7 days

        Artisan::call('queue:prune-failed', ['--hours' => $hours]);

        return response()->json([
            'message' => "Deleted failed jobs older than {$hours} hours",
        ]);
    }
}
```

### Alert Notification

**Notification**: `app/Notifications/CriticalJobFailedNotification.php`

```php
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CriticalJobFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public $job,
        public \Throwable $exception
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('Critical Job Failed')
            ->line('A critical job has failed after all retry attempts.')
            ->line("Job: " . get_class($this->job))
            ->line("Queue: " . $this->job->queue)
            ->line("Error: " . $this->exception->getMessage())
            ->action('View Failed Jobs', url('/admin/failed-jobs'))
            ->line('Please investigate and retry if necessary.');
    }
}
```

**Configure in `.env`**:
```env
ADMIN_EMAIL=admin@yourdomain.com
```

### Scheduled Pruning

**Schedule** (in `app/Console/Kernel.php`):
```php
// Prune failed jobs older than 7 days
$schedule->command('queue:prune-failed --hours=168')
    ->weekly();
```

### Enhanced Logging

**Create Log Channel** for failed jobs in `config/logging.php`:

```php
'channels' => [
    // ... existing channels

    'failed_jobs' => [
        'driver' => 'daily',
        'path' => storage_path('logs/failed-jobs.log'),
        'level' => 'error',
        'days' => 14,
    ],
],
```

**Use in job failure**:
```php
Log::channel('failed_jobs')->error("Job failed", [
    'job' => get_class($this),
    'attempts' => $this->attempts(),
    'error' => $exception->getMessage(),
]);
```

### Testing Requirements

**Unit Tests** (`tests/Unit/JobRetryTest.php`):
- Test exponential backoff calculation
- Test retry limit enforcement
- Test failed job model scopes

**Feature Tests** (`tests/Feature/FailedJobHandlingTest.php`):
- Test job fails after max retries
- Test manual retry via command
- Test API endpoints for failed job management
- Test notification on critical job failure

**Example Test**:
```php
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Artisan;

test('job retries with exponential backoff', function () {
    Queue::fake();

    $job = new ProcessAttendanceEvent(['custom_id' => 'EMP001'], 'device-001');

    // Simulate failure
    $job->failed(new \Exception('Test failure'));

    // Should have retried 3 times
    expect($job->attempts())->toBeLessThanOrEqual(3);
});

test('failed job can be retried via command', function () {
    $failedJob = FailedJob::factory()->create();

    Artisan::call('queue:retry-failed', ['id' => $failedJob->id]);

    // Job should be removed from failed_jobs table
    expect(FailedJob::find($failedJob->id))->toBeNull();
});
```

## Approach

1. **Phase 1: Retry Configuration**
   - Update existing job classes with retry properties
   - Implement exponential backoff logic
   - Add failed() method to handle permanent failures

2. **Phase 2: Failed Job Model**
   - Create FailedJob model with scopes
   - Add accessors for payload and job class
   - Write unit tests for model methods

3. **Phase 3: Retry Commands**
   - Create RetryFailedJobCommand
   - Implement retry logic for individual, queue-specific, and bulk retries
   - Write feature tests for command

4. **Phase 4: API Endpoints**
   - Create FailedJobController
   - Implement REST endpoints for failed job management
   - Add authorization middleware
   - Write API tests

5. **Phase 5: Alerting**
   - Create CriticalJobFailedNotification
   - Configure email notification
   - Add logging channel for failed jobs
   - Test notification delivery

6. **Phase 6: Scheduled Maintenance**
   - Add scheduled job pruning to Kernel
   - Configure retention period
   - Test scheduled task execution

## External Dependencies

None - uses Laravel's built-in queue system
