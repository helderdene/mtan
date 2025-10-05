# Technical Specification

This is the technical specification for the spec detailed in @.agent-os/specs/2025-10-05-queue-priority-processing/spec.md

> Created: 2025-10-05
> Version: 1.0.0

## Technical Requirements

### Queue Configuration

**Update `.env`**:
```env
# Queue Configuration
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis  # Or predis

# Redis Connection
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0

# Queue Database (fallback for failed jobs table)
QUEUE_DATABASE=mysql
```

**Verify `config/queue.php`** has proper Redis queue configuration:
```php
'redis' => [
    'driver' => 'redis',
    'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
    'queue' => env('REDIS_QUEUE', 'default'),
    'retry_after' => 90,
    'block_for' => null,
    'after_commit' => false,
],
```

### Priority Queue Levels

**Queue Hierarchy** (highest to lowest priority):

1. **attendance-high-priority**
   - Purpose: Real-time attendance events from MQTT (QoS 2)
   - Target Processing Time: < 2 seconds
   - Workers: 2 processes
   - Timeout: 60 seconds
   - Retry: 3 attempts

2. **attendance-default**
   - Purpose: Stranger logs, device status updates, non-critical attendance tasks
   - Target Processing Time: < 10 seconds
   - Workers: 2 processes
   - Timeout: 90 seconds
   - Retry: 3 attempts

3. **reporting**
   - Purpose: Long-running report generation, data exports
   - Target Processing Time: < 5 minutes
   - Workers: 1 process
   - Timeout: 300 seconds (5 minutes)
   - Retry: 1 attempt

4. **notifications**
   - Purpose: Email, SMS, push notifications
   - Target Processing Time: < 30 seconds
   - Workers: 1 process
   - Timeout: 30 seconds
   - Retry: 3 attempts

### Job Queue Assignment

**Update existing job classes** to specify queue:

**ProcessAttendanceEvent** (highest priority):
```php
class ProcessAttendanceEvent implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public array $payload,
        public string $deviceId
    ) {
        $this->onQueue('attendance-high-priority');
    }
}
```

**SyncEmployeeToDevice** (default priority):
```php
class SyncEmployeeToDevice implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Employee $employee,
        public Device $device
    ) {
        $this->onQueue('attendance-default');
    }
}
```

**ViolationNotification** (notifications queue):
```php
class ViolationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public AttendanceViolation $violation
    ) {
        $this->onQueue('notifications');
    }
}
```

**Example Report Job** (reporting queue):
```php
class GenerateMonthlyReport implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Employee $employee,
        public Carbon $month
    ) {
        $this->onQueue('reporting');
    }
}
```

### Supervisor Configuration

**Update Supervisor configs** with actual project paths:

**File**: `supervisor/attendance-high-priority-worker.conf`
```ini
[program:attendance-high-priority-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /Users/helderdene/mtan/artisan queue:work redis --queue=attendance-high-priority --tries=3 --timeout=60 --max-time=3600 --sleep=1
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=2
user=www-data
redirect_stderr=true
stdout_logfile=/Users/helderdene/mtan/storage/logs/queue-high-priority.log
stopwaitsecs=60
```

**File**: `supervisor/attendance-default-worker.conf`
```ini
[program:attendance-default-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /Users/helderdene/mtan/artisan queue:work redis --queue=attendance-default --tries=3 --timeout=90 --max-time=3600 --sleep=1
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=2
user=www-data
redirect_stderr=true
stdout_logfile=/Users/helderdene/mtan/storage/logs/queue-default.log
stopwaitsecs=90
```

**File**: `supervisor/reporting-worker.conf`
```ini
[program:reporting-worker]
process_name=%(program_name)s
command=php /Users/helderdene/mtan/artisan queue:work redis --queue=reporting --tries=1 --timeout=300 --max-time=7200 --sleep=3
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=1
user=www-data
redirect_stderr=true
stdout_logfile=/Users/helderdene/mtan/storage/logs/queue-reporting.log
stopwaitsecs=300
```

**File**: `supervisor/notification-worker.conf`
```ini
[program:notification-worker]
process_name=%(program_name)s
command=php /Users/helderdene/mtan/artisan queue:work redis --queue=notifications --tries=3 --timeout=30 --max-time=3600 --sleep=1
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=1
user=www-data
redirect_stderr=true
stdout_logfile=/Users/helderdene/mtan/storage/logs/queue-notifications.log
stopwaitsecs=30
```

### Queue Monitoring

**Command**: `app/Console/Commands/MonitorQueuesCommand.php`

**Signature**: `queue:monitor {--threshold=100}`

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;

class MonitorQueuesCommand extends Command
{
    protected $signature = 'queue:monitor {--threshold=100}';
    protected $description = 'Monitor queue sizes and alert if thresholds exceeded';

    public function handle()
    {
        $threshold = (int) $this->option('threshold');
        $queues = [
            'attendance-high-priority',
            'attendance-default',
            'reporting',
            'notifications',
        ];

        $this->table(
            ['Queue', 'Pending', 'Processing', 'Failed', 'Status'],
            collect($queues)->map(function ($queue) use ($threshold) {
                $pending = $this->getQueueSize($queue);
                $processing = $this->getProcessingCount($queue);
                $failed = DB::table('failed_jobs')
                    ->where('queue', $queue)
                    ->count();

                $status = $pending > $threshold ? '⚠️  HIGH' : '✓ OK';

                if ($pending > $threshold) {
                    Log::warning("Queue {$queue} size exceeded threshold", [
                        'queue' => $queue,
                        'size' => $pending,
                        'threshold' => $threshold,
                    ]);
                }

                return [
                    $queue,
                    $pending,
                    $processing,
                    $failed,
                    $status,
                ];
            })
        );

        return Command::SUCCESS;
    }

    protected function getQueueSize(string $queue): int
    {
        return Redis::llen("queues:{$queue}");
    }

    protected function getProcessingCount(string $queue): int
    {
        // Count reserved jobs for this queue
        return Redis::zcard("queues:{$queue}:reserved");
    }
}
```

**Schedule Monitoring** (in `app/Console/Kernel.php`):
```php
$schedule->command('queue:monitor --threshold=100')
    ->everyFiveMinutes();
```

### API Endpoint for Queue Metrics

**Route**: `routes/api.php`
```php
Route::get('/queue/metrics', [QueueMetricsController::class, 'index'])
    ->middleware('auth:sanctum');
```

**Controller**: `app/Http/Controllers/Api/QueueMetricsController.php`
```php
public function index()
{
    $queues = ['attendance-high-priority', 'attendance-default', 'reporting', 'notifications'];

    $metrics = collect($queues)->mapWithKeys(function ($queue) {
        return [$queue => [
            'pending' => Redis::llen("queues:{$queue}"),
            'processing' => Redis::zcard("queues:{$queue}:reserved"),
            'failed' => DB::table('failed_jobs')->where('queue', $queue)->count(),
        ]];
    });

    return response()->json([
        'queues' => $metrics,
        'workers' => $this->getWorkerCount(),
        'timestamp' => now()->toIso8601String(),
    ]);
}

protected function getWorkerCount(): int
{
    // Count running queue:work processes
    $output = shell_exec('ps aux | grep "queue:work" | grep -v grep | wc -l');
    return (int) trim($output);
}
```

### Health Check Integration

**Add to health check endpoint**:
```php
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'database' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
        'redis' => Redis::ping() ? 'connected' : 'disconnected',
        'queues' => [
            'workers' => shell_exec('ps aux | grep "queue:work" | grep -v grep | wc -l'),
            'failed_jobs' => DB::table('failed_jobs')->count(),
        ],
    ]);
});
```

### Deployment Guide

**Production Deployment Steps**:

1. **Install Supervisor** (Ubuntu/Debian):
```bash
sudo apt update
sudo apt install supervisor
```

2. **Update Supervisor configs**:
```bash
# Copy configs to supervisor directory
sudo cp supervisor/*.conf /etc/supervisor/conf.d/

# Update all configs with correct project path
sudo sed -i 's|/path/to/mtan|/var/www/mtan|g' /etc/supervisor/conf.d/*.conf

# Update user if needed
sudo sed -i 's|user=www-data|user=ubuntu|g' /etc/supervisor/conf.d/*.conf
```

3. **Reload Supervisor**:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```

4. **Verify workers are running**:
```bash
sudo supervisorctl status
```

Expected output:
```
attendance-default-worker:00     RUNNING   pid 1234, uptime 0:01:00
attendance-default-worker:01     RUNNING   pid 1235, uptime 0:01:00
attendance-high-priority-worker:00 RUNNING pid 1236, uptime 0:01:00
attendance-high-priority-worker:01 RUNNING pid 1237, uptime 0:01:00
notification-worker              RUNNING   pid 1238, uptime 0:01:00
reporting-worker                 RUNNING   pid 1239, uptime 0:01:00
```

5. **Monitor logs**:
```bash
tail -f storage/logs/queue-high-priority.log
tail -f storage/logs/queue-default.log
```

### Performance Tuning

**Redis Configuration** (`redis.conf`):
```conf
# Increase max memory
maxmemory 256mb
maxmemory-policy allkeys-lru

# Enable persistence
save 900 1
save 300 10
save 60 10000

# AOF for durability
appendonly yes
appendfsync everysec
```

**Laravel Queue Configuration**:
```php
// config/queue.php
'redis' => [
    'driver' => 'redis',
    'connection' => 'default',
    'queue' => 'default',
    'retry_after' => 90,
    'block_for' => null, // Set to 5 for blocking mode (more efficient)
    'after_commit' => true, // Only dispatch after DB transaction commits
],
```

### Testing Requirements

**Unit Tests** (`tests/Unit/QueuePriorityTest.php`):
- Test job queue assignment
- Test queue size calculation
- Test monitoring thresholds

**Feature Tests** (`tests/Feature/QueueProcessingTest.php`):
- Test high-priority jobs process before default jobs
- Test failed job handling
- Test queue metrics endpoint
- Test monitoring command output

**Example Test**:
```php
use Illuminate\Support\Facades\Queue;

test('attendance events use high priority queue', function () {
    Queue::fake();

    $payload = ['custom_id' => 'EMP001'];

    ProcessAttendanceEvent::dispatch($payload, 'device-001');

    Queue::assertPushedOn('attendance-high-priority', ProcessAttendanceEvent::class);
});
```

## External Dependencies

**Required**:
- Redis server (>= 5.0)
- Supervisor (>= 3.0)
- PHP Redis extension (`phpredis`) or Predis package

**Installation**:
```bash
# Install Redis
sudo apt install redis-server

# Install PHP Redis extension
sudo apt install php-redis

# Or use Predis (composer)
composer require predis/predis
```
