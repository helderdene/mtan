<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redis;

class QueueMetricsController extends Controller
{
    /**
     * Get queue metrics for all configured queues
     */
    public function index(): JsonResponse
    {
        $queues = [
            'attendance-high-priority',
            'attendance-default',
            'notifications',
            'reporting',
        ];

        $metrics = [];

        foreach ($queues as $queue) {
            $metrics[] = [
                'queue' => $queue,
                'size' => $this->getQueueSize($queue),
                'failed_jobs' => $this->getFailedJobsCount($queue),
                'status' => $this->getQueueHealth($queue),
            ];
        }

        return response()->json([
            'metrics' => $metrics,
            'timestamp' => now()->toIso8601String(),
            'total_jobs' => array_sum(array_column($metrics, 'size')),
            'total_failed' => array_sum(array_column($metrics, 'failed_jobs')),
        ]);
    }

    /**
     * Get metrics for a specific queue
     */
    public function show(string $queue): JsonResponse
    {
        $size = $this->getQueueSize($queue);
        $failedJobs = $this->getFailedJobsCount($queue);

        return response()->json([
            'queue' => $queue,
            'size' => $size,
            'failed_jobs' => $failedJobs,
            'status' => $this->getQueueHealth($queue),
            'timestamp' => now()->toIso8601String(),
        ]);
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
            \Log::error("Error getting queue size for {$queue}", [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Get failed jobs count for a queue
     */
    protected function getFailedJobsCount(string $queue): int
    {
        try {
            return \DB::table('failed_jobs')
                ->where('queue', $queue)
                ->count();
        } catch (\Exception $e) {
            \Log::error("Error getting failed jobs count for {$queue}", [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Get queue health status
     */
    protected function getQueueHealth(string $queue): string
    {
        $size = $this->getQueueSize($queue);
        $warning = (int) env('QUEUE_SIZE_WARNING_THRESHOLD', 100);
        $critical = (int) env('QUEUE_SIZE_CRITICAL_THRESHOLD', 500);

        if ($size >= $critical) {
            return 'critical';
        }

        if ($size >= $warning) {
            return 'warning';
        }

        return 'healthy';
    }
}
