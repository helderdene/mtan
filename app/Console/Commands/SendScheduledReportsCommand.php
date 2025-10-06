<?php

namespace App\Console\Commands;

use App\Domain\Reporting\Services\ReportExporter;
use App\Domain\Reporting\Services\ReportGenerator;
use App\Models\NotificationPreference;
use App\Notifications\ScheduledReportNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendScheduledReportsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:send-scheduled
                            {--type= : Report type (daily, weekly, monthly)}
                            {--dry-run : Show what would be sent without sending}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send scheduled reports to users based on their notification preferences';

    /**
     * Execute the console command.
     */
    public function handle(
        ReportGenerator $reportGenerator,
        ReportExporter $reportExporter
    ): int {
        $type = $this->option('type');
        $dryRun = $this->option('dry-run');

        if (! $type) {
            $this->error('Please specify a report type: --type=daily|weekly|monthly');

            return self::FAILURE;
        }

        if (! in_array($type, ['daily', 'weekly', 'monthly'])) {
            $this->error('Invalid report type. Use: daily, weekly, or monthly');

            return self::FAILURE;
        }

        $this->info("Sending {$type} scheduled reports...");

        // Get date range based on frequency
        [$fromDate, $toDate] = $this->getDateRange($type);

        $this->info("Report period: {$fromDate} to {$toDate}");

        // Get users with active preferences for this frequency
        $preferences = NotificationPreference::where('frequency', $type)
            ->where('enabled', true)
            ->with('user')
            ->get();

        if ($preferences->isEmpty()) {
            $this->warn('No users found with active preferences for this frequency.');

            return self::SUCCESS;
        }

        $this->info("Found {$preferences->count()} user(s) to notify");

        $sent = 0;
        $errors = 0;

        foreach ($preferences as $preference) {
            try {
                $this->line("Processing {$preference->user->name} ({$preference->user->email})...");

                // Generate report based on preference
                $reportType = $preference->report_type ?? 'attendance';
                $filters = $preference->filters ?? [];

                if ($reportType === 'attendance') {
                    $reportData = $reportGenerator->generateAttendanceReport(
                        $fromDate,
                        $toDate,
                        $filters
                    );
                } else {
                    $reportData = $reportGenerator->generateViolationReport(
                        $fromDate,
                        $toDate,
                        $filters
                    );
                }

                // Export to PDF
                $pdfPath = $reportExporter->exportToPdf(
                    $reportData->toArray(),
                    "{$reportType}-report"
                );

                if ($dryRun) {
                    $this->info("  [DRY RUN] Would send {$reportType} report to {$preference->user->email}");
                    @unlink($pdfPath); // Clean up
                } else {
                    // Send notification with attachment
                    $preference->user->notify(
                        new ScheduledReportNotification($reportType, $type, $pdfPath, $reportData->toArray())
                    );
                    $this->info("  ✓ Sent {$reportType} report");
                }

                $sent++;
            } catch (\Exception $e) {
                $this->error("  ✗ Failed: {$e->getMessage()}");
                $errors++;
            }
        }

        $this->newLine();
        $this->info("Summary:");
        $this->info("  - Reports sent: {$sent}");

        if ($errors > 0) {
            $this->warn("  - Errors: {$errors}");
        }

        return self::SUCCESS;
    }

    /**
     * Get date range based on frequency
     */
    protected function getDateRange(string $frequency): array
    {
        $today = Carbon::today();

        return match ($frequency) {
            'daily' => [
                $today->copy()->subDay()->format('Y-m-d'),
                $today->copy()->subDay()->format('Y-m-d'),
            ],
            'weekly' => [
                $today->copy()->subWeek()->startOfWeek()->format('Y-m-d'),
                $today->copy()->subWeek()->endOfWeek()->format('Y-m-d'),
            ],
            'monthly' => [
                $today->copy()->subMonth()->startOfMonth()->format('Y-m-d'),
                $today->copy()->subMonth()->endOfMonth()->format('Y-m-d'),
            ],
        };
    }
}
