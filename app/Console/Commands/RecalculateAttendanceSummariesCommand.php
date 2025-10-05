<?php

namespace App\Console\Commands;

use App\Domain\Attendance\Services\SummaryCalculator;
use App\Models\Tenant\Employee;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RecalculateAttendanceSummariesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:recalculate-summaries
                            {--employee= : Employee ID to recalculate (optional, defaults to all employees)}
                            {--from= : Start date (YYYY-MM-DD)}
                            {--to= : End date (YYYY-MM-DD)}
                            {--force : Force recalculation even if summaries exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate daily attendance summaries for a date range';

    /**
     * Execute the console command.
     */
    public function handle(SummaryCalculator $calculator): int
    {
        // Validate and parse dates
        $fromDate = $this->option('from');
        $toDate = $this->option('to');

        if (! $fromDate || ! $toDate) {
            $this->error('Both --from and --to dates are required.');
            $this->info('Example: php artisan attendance:recalculate-summaries --from=2025-10-01 --to=2025-10-31');

            return self::FAILURE;
        }

        try {
            $startDate = Carbon::parse($fromDate);
            $endDate = Carbon::parse($toDate);
        } catch (\Exception $e) {
            $this->error('Invalid date format. Please use YYYY-MM-DD format.');

            return self::FAILURE;
        }

        if ($startDate->gt($endDate)) {
            $this->error('Start date must be before or equal to end date.');

            return self::FAILURE;
        }

        // Get employees to process
        $employeeId = $this->option('employee');
        if ($employeeId) {
            $employees = Employee::where('id', $employeeId)->get();

            if ($employees->isEmpty()) {
                $this->error("Employee with ID {$employeeId} not found.");

                return self::FAILURE;
            }
        } else {
            $employees = Employee::where('is_active', true)->get();
        }

        $this->info('Recalculating attendance summaries...');
        $this->newLine();
        $this->info("Date range: {$startDate->toDateString()} to {$endDate->toDateString()}");
        $this->info("Employees: ".($employeeId ? 1 : $employees->count()));
        $this->newLine();

        // Calculate total days
        $totalDays = $startDate->diffInDays($endDate) + 1;
        $totalOperations = $employees->count() * $totalDays;

        // Progress bar
        $bar = $this->output->createProgressBar($totalOperations);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');
        $bar->setMessage('Starting...');

        $totalRecalculated = 0;
        $employeeStats = [];

        // Process each employee
        foreach ($employees as $employee) {
            $bar->setMessage("Processing: {$employee->name}");

            $count = 0;
            $currentDate = $startDate->copy();

            // Process each date in the range
            while ($currentDate->lte($endDate)) {
                $calculator->calculateForDate($employee, $currentDate->copy());
                $count++;
                $bar->advance();
                $currentDate->addDay();
            }

            $totalRecalculated += $count;
            $employeeStats[] = [
                'id' => $employee->id,
                'name' => $employee->name,
                'days' => $count,
            ];
        }

        $bar->setMessage('Complete!');
        $bar->finish();
        $this->newLine(2);

        // Display summary
        $this->info('✓ Recalculation complete!');
        $this->newLine();

        // Summary table
        $this->table(
            ['Employee ID', 'Employee Name', 'Days Recalculated'],
            array_map(fn ($stat) => [
                $stat['id'],
                $stat['name'],
                $stat['days'],
            ], $employeeStats)
        );

        $this->newLine();
        $this->info("Total summaries recalculated: {$totalRecalculated}");
        $this->info("Date range: {$startDate->toDateString()} to {$endDate->toDateString()}");

        return self::SUCCESS;
    }
}
