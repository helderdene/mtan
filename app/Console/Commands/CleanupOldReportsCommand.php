<?php

namespace App\Console\Commands;

use App\Domain\Reporting\Services\ReportExporter;
use Illuminate\Console\Command;

class CleanupOldReportsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:cleanup
                            {--days=7 : Number of days to keep reports}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old report files from storage';

    /**
     * Execute the console command.
     */
    public function handle(ReportExporter $reportExporter): int
    {
        $days = (int) $this->option('days');

        $this->info("Cleaning up reports older than {$days} days...");

        $deleted = $reportExporter->cleanupOldReports($days);

        if ($deleted > 0) {
            $this->info("✓ Deleted {$deleted} old report file(s)");
        } else {
            $this->info('No old report files to delete');
        }

        return self::SUCCESS;
    }
}
