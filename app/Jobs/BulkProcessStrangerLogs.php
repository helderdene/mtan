<?php

namespace App\Jobs;

use App\Models\Tenant\StrangerLog;
use App\Services\PhotoStorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BulkProcessStrangerLogs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $action,
        public array $strangerLogIds,
        public int $userId,
        public ?int $employeeId = null,
        public ?string $notes = null,
        public ?string $jobId = null
    ) {
        $this->onQueue('attendance-default');
    }

    /**
     * Get the retry backoff times for this job.
     */
    public function backoff(): array
    {
        // Exponential backoff: 1 minute, 5 minutes, 15 minutes
        return [60, 300, 900];
    }

    /**
     * Execute the job.
     */
    public function handle(PhotoStorageService $photoStorage): void
    {
        Log::info('Bulk processing stranger logs started', [
            'job_id' => $this->jobId,
            'action' => $this->action,
            'count' => count($this->strangerLogIds),
            'user_id' => $this->userId,
        ]);

        $processedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        try {
            foreach ($this->strangerLogIds as $logId) {
                try {
                    $log = StrangerLog::find($logId);

                    if (! $log) {
                        $skippedCount++;
                        Log::warning('Stranger log not found', [
                            'job_id' => $this->jobId,
                            'log_id' => $logId,
                        ]);
                        continue;
                    }

                    // Process based on action
                    match ($this->action) {
                        'match' => $this->processMatch($log),
                        'mark-security-issue' => $this->processSecurityIssue($log),
                        'delete' => $this->processDelete($log, $photoStorage),
                    };

                    $processedCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    Log::error('Error processing stranger log in bulk', [
                        'job_id' => $this->jobId,
                        'log_id' => $logId,
                        'action' => $this->action,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Bulk processing stranger logs completed', [
                'job_id' => $this->jobId,
                'action' => $this->action,
                'processed' => $processedCount,
                'skipped' => $skippedCount,
                'errors' => $errorCount,
            ]);
        } catch (\Exception $e) {
            Log::error('Bulk processing stranger logs failed', [
                'job_id' => $this->jobId,
                'action' => $this->action,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Process match action for a stranger log
     */
    protected function processMatch(StrangerLog $log): void
    {
        // Skip if already matched
        if ($log->match_status === 'matched') {
            Log::info('Skipping already matched stranger log', [
                'job_id' => $this->jobId,
                'log_id' => $log->id,
            ]);

            return;
        }

        $log->update([
            'employee_id' => $this->employeeId,
            'matched_by' => $this->userId,
            'match_status' => 'matched',
            'notes' => $this->notes ?? $log->notes,
        ]);

        Log::info('Stranger log matched', [
            'job_id' => $this->jobId,
            'log_id' => $log->id,
            'employee_id' => $this->employeeId,
        ]);
    }

    /**
     * Process mark-security-issue action for a stranger log
     */
    protected function processSecurityIssue(StrangerLog $log): void
    {
        $log->update([
            'match_status' => 'security_issue',
            'notes' => $this->notes,
        ]);

        Log::info('Stranger log marked as security issue', [
            'job_id' => $this->jobId,
            'log_id' => $log->id,
        ]);
    }

    /**
     * Process delete action for a stranger log with photo cleanup
     */
    protected function processDelete(StrangerLog $log, PhotoStorageService $photoStorage): void
    {
        $photoPath = $log->photo_path;

        // Delete stranger log record
        $log->delete();

        // Delete photo from S3
        try {
            $photoStorage->deletePhoto($photoPath);
            Log::info('Stranger log and photo deleted', [
                'job_id' => $this->jobId,
                'log_id' => $log->id,
                'photo_path' => $photoPath,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete photo from S3', [
                'job_id' => $this->jobId,
                'log_id' => $log->id,
                'photo_path' => $photoPath,
                'error' => $e->getMessage(),
            ]);
            // Continue even if photo deletion fails - log is already deleted
        }
    }
}
