<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FailedJob;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

class FailedJobController extends Controller
{
    /**
     * Display a listing of failed jobs.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = FailedJob::query();

        // Filter by queue
        if ($request->filled('queue')) {
            $query->queue($request->input('queue'));
        }

        // Filter by date range
        if ($request->filled('from') && $request->filled('to')) {
            $from = Carbon::parse($request->input('from'))->startOfDay();
            $to = Carbon::parse($request->input('to'))->endOfDay();
            $query->failedBetween($from, $to);
        }

        // Filter by job class (search in payload)
        if ($request->filled('job_class')) {
            $jobClass = $request->input('job_class');
            $query->where('payload', 'LIKE', "%{$jobClass}%");
        }

        // Order by latest first
        $query->orderBy('failed_at', 'desc');

        // Paginate results
        $perPage = $request->input('per_page', 15);
        $failedJobs = $query->paginate($perPage);

        // Transform data to include computed attributes
        $failedJobs->getCollection()->transform(function ($job) {
            return [
                'id' => $job->id,
                'connection' => $job->connection,
                'queue' => $job->queue,
                'job_class' => $job->job_class,
                'job_data' => $job->job_data,
                'exception' => $job->exception,
                'failed_at' => $job->failed_at->toIso8601String(),
                'failed_time_ago' => $job->failed_time_ago,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $failedJobs->items(),
            'meta' => [
                'current_page' => $failedJobs->currentPage(),
                'from' => $failedJobs->firstItem(),
                'last_page' => $failedJobs->lastPage(),
                'per_page' => $failedJobs->perPage(),
                'to' => $failedJobs->lastItem(),
                'total' => $failedJobs->total(),
            ],
        ]);
    }

    /**
     * Display the specified failed job.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        $failedJob = FailedJob::find($id);

        if (! $failedJob) {
            return response()->json([
                'success' => false,
                'message' => 'Failed job not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $failedJob->id,
                'connection' => $failedJob->connection,
                'queue' => $failedJob->queue,
                'job_class' => $failedJob->job_class,
                'job_data' => $failedJob->job_data,
                'payload' => $failedJob->decoded_payload,
                'exception' => $failedJob->exception,
                'failed_at' => $failedJob->failed_at->toIso8601String(),
                'failed_time_ago' => $failedJob->failed_time_ago,
            ],
        ]);
    }

    /**
     * Retry a specific failed job.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function retry(string $id): JsonResponse
    {
        $failedJob = FailedJob::find($id);

        if (! $failedJob) {
            return response()->json([
                'success' => false,
                'message' => 'Failed job not found',
            ], 404);
        }

        try {
            Artisan::call('queue:retry', ['id' => [$id]]);

            return response()->json([
                'success' => true,
                'message' => 'Job successfully queued for retry',
                'data' => [
                    'id' => $id,
                    'job_class' => $failedJob->job_class,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry job',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retry all failed jobs or all for a specific queue.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function retryAll(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'queue' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $queue = $request->input('queue');

        try {
            if ($queue) {
                // Retry all jobs for specific queue
                $failedJobs = FailedJob::queue($queue)->get();

                if ($failedJobs->isEmpty()) {
                    return response()->json([
                        'success' => true,
                        'message' => "No failed jobs found for queue [{$queue}]",
                        'data' => ['count' => 0],
                    ]);
                }

                foreach ($failedJobs as $failedJob) {
                    Artisan::call('queue:retry', ['id' => [$failedJob->id]]);
                }

                return response()->json([
                    'success' => true,
                    'message' => "Successfully retried all jobs for queue [{$queue}]",
                    'data' => ['count' => $failedJobs->count()],
                ]);
            } else {
                // Retry all failed jobs
                $count = FailedJob::count();

                if ($count === 0) {
                    return response()->json([
                        'success' => true,
                        'message' => 'No failed jobs found',
                        'data' => ['count' => 0],
                    ]);
                }

                Artisan::call('queue:retry', ['id' => ['all']]);

                return response()->json([
                    'success' => true,
                    'message' => 'Successfully retried all failed jobs',
                    'data' => ['count' => $count],
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry jobs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a specific failed job.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        $failedJob = FailedJob::find($id);

        if (! $failedJob) {
            return response()->json([
                'success' => false,
                'message' => 'Failed job not found',
            ], 404);
        }

        try {
            Artisan::call('queue:forget', ['id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Failed job successfully deleted',
                'data' => ['id' => $id],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete job',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Prune old failed jobs.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function prune(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'hours' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $hours = $request->input('hours', 168); // Default: 7 days (168 hours)

        try {
            Artisan::call('queue:prune-failed', ['--hours' => $hours]);

            $output = Artisan::output();

            // Extract count from output if available
            preg_match('/(\d+) entries/', $output, $matches);
            $count = $matches[1] ?? 'unknown';

            return response()->json([
                'success' => true,
                'message' => "Successfully pruned failed jobs older than {$hours} hours",
                'data' => [
                    'hours' => $hours,
                    'deleted_count' => $count,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to prune jobs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
