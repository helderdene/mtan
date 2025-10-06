<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class MonitorQueuesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:monitor
                            {--alert : Send alerts if thresholds are exceeded}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor queue sizes and alert if thresholds are exceeded';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $queues = [
            'attendance-high-priority',
            'attendance-default',
            'notifications',
            'reporting',
        ];

        $warningThreshold = (int) env('QUEUE_SIZE_WARNING_THRESHOLD', 100);
        $criticalThreshold = (int) env('QUEUE_SIZE_CRITICAL_THRESHOLD', 500);

        $this->info('Queue Monitoring Report - '.now()->format('Y-m-d H:i:s'));
        $this->newLine();

        $alerts = [];

        foreach ($queues as $queue) {
            $size = $this->getQueueSize($queue);
            $status = $this->getQueueStatus($size, $warningThreshold, $criticalThreshold);

            $this->line(sprintf(
                '%-30s | Size: %-6d | Status: %s',
                $queue,
                $size,
                $status
            ));

            if ($status !== '<info>✓ OK</info>' && $this->option('alert')) {
                $alerts[] = [
                    'queue' => $queue,
                    'size' => $size,
                    'status' => strip_tags($status),
                ];
            }
        }

        if (! empty($alerts)) {
            $this->newLine();
            $this->warn('Alerts:');
            foreach ($alerts as $alert) {
                $this->warn("  - {$alert['queue']}: {$alert['size']} jobs ({$alert['status']})");
            }

            // Here you could send notifications, log to monitoring service, etc.
            \Log::channel('queue')->warning('Queue size threshold exceeded', [
                'alerts' => $alerts,
                'timestamp' => now(),
            ]);
        }

        return self::SUCCESS;
    }

    /**
     * Get the size of a queue
     */
    protected function getQueueSize(string $queue): int
    {
        try {
            $connection = Redis::connection(env('REDIS_QUEUE_CONNECTION', 'queue'));
            $key = config('queue.connections.redis.queue', 'queues:').$queue;

            return (int) $connection->llen($key);
        } catch (\Exception $e) {
            $this->error("Error getting size for queue {$queue}: ".$e->getMessage());

            return 0;
        }
    }

    /**
     * Get queue status based on size thresholds
     */
    protected function getQueueStatus(int $size, int $warning, int $critical): string
    {
        if ($size >= $critical) {
            return '<fg=red>✗ CRITICAL</>';
        }

        if ($size >= $warning) {
            return '<fg=yellow>⚠ WARNING</>';
        }

        return '<info>✓ OK</info>';
    }
}
