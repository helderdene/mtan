<?php

namespace App\Console\Commands;

use App\Domain\Attendance\Services\ViolationDetector;
use App\Events\ViolationDetected;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DetectMissingCheckoutsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'violations:detect-missing-checkouts {--date= : Date to check for missing checkouts (Y-m-d format, defaults to yesterday)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect missing checkout violations for employees who checked in but did not check out';

    /**
     * Execute the console command.
     */
    public function handle(ViolationDetector $detector): int
    {
        // Parse date option or default to yesterday
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::yesterday();

        $this->info("Detecting missing checkouts for date: {$date->toDateString()}");

        try {
            // Detect missing checkouts
            $violations = $detector->detectMissingCheckouts($date);

            if ($violations->isEmpty()) {
                $this->info('No missing checkout violations detected.');

                return self::SUCCESS;
            }

            // Dispatch violation events and display results
            foreach ($violations as $violation) {
                event(new ViolationDetected($violation));

                $this->line(sprintf(
                    '✗ Violation created for Employee ID %d: Missing checkout on %s',
                    $violation->employee_id,
                    $violation->violation_date->toDateString()
                ));
            }

            $this->newLine();
            $this->info("Total missing checkout violations detected: {$violations->count()}");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error detecting missing checkouts: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
