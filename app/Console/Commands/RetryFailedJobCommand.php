<?php

namespace App\Console\Commands;

use App\Models\FailedJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RetryFailedJobCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:retry-failed
                            {id? : The UUID of the failed job to retry}
                            {--queue= : Retry all failed jobs for a specific queue}
                            {--all : Retry all failed jobs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry failed queue jobs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $id = $this->argument('id');
        $queue = $this->option('queue');
        $all = $this->option('all');

        // Validate that only one option is provided
        $optionsProvided = collect([$id, $queue, $all])->filter()->count();

        if ($optionsProvided > 1) {
            $this->error('Please provide only one option: an ID, --queue, or --all');

            return self::FAILURE;
        }

        if ($optionsProvided === 0) {
            $this->error('Please provide an ID, --queue option, or --all option');

            return self::FAILURE;
        }

        // Retry specific job by ID
        if ($id) {
            return $this->retryJob($id);
        }

        // Retry all jobs for a specific queue
        if ($queue) {
            return $this->retryQueue($queue);
        }

        // Retry all failed jobs
        if ($all) {
            return $this->retryAll();
        }

        return self::SUCCESS;
    }

    /**
     * Retry a specific failed job by ID.
     *
     * @param  string  $id
     * @return int
     */
    protected function retryJob(string $id): int
    {
        $failedJob = FailedJob::find($id);

        if (! $failedJob) {
            $this->error("Failed job with ID [{$id}] not found.");

            return self::FAILURE;
        }

        $this->info("Retrying failed job: {$failedJob->job_class} (ID: {$id})");

        try {
            // Use Laravel's built-in retry functionality
            Artisan::call('queue:retry', ['id' => [$id]]);

            $this->info('✓ Job successfully queued for retry');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("✗ Failed to retry job: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Retry all failed jobs for a specific queue.
     *
     * @param  string  $queueName
     * @return int
     */
    protected function retryQueue(string $queueName): int
    {
        $failedJobs = FailedJob::queue($queueName)->get();

        if ($failedJobs->isEmpty()) {
            $this->warn("No failed jobs found for queue [{$queueName}]");

            return self::SUCCESS;
        }

        $count = $failedJobs->count();

        // Ask for confirmation
        if (! $this->confirm("Found {$count} failed job(s) in queue [{$queueName}]. Retry all?", true)) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $this->info("Retrying {$count} failed job(s) from queue [{$queueName}]...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $retried = 0;
        $failed = 0;

        foreach ($failedJobs as $failedJob) {
            try {
                Artisan::call('queue:retry', ['id' => [$failedJob->id]]);
                $retried++;
            } catch (\Exception $e) {
                $failed++;
                $this->newLine();
                $this->error("✗ Failed to retry job {$failedJob->id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✓ Successfully retried: {$retried}");

        if ($failed > 0) {
            $this->error("✗ Failed to retry: {$failed}");
        }

        return self::SUCCESS;
    }

    /**
     * Retry all failed jobs.
     *
     * @return int
     */
    protected function retryAll(): int
    {
        $count = FailedJob::count();

        if ($count === 0) {
            $this->warn('No failed jobs found.');

            return self::SUCCESS;
        }

        // Ask for confirmation
        if (! $this->confirm("Found {$count} failed job(s). Retry all?", true)) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $this->info("Retrying {$count} failed job(s)...");

        try {
            // Use Laravel's built-in retry all functionality
            Artisan::call('queue:retry', ['id' => ['all']]);

            $this->info("✓ Successfully queued {$count} job(s) for retry");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("✗ Failed to retry jobs: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
