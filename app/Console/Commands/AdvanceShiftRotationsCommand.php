<?php

namespace App\Console\Commands;

use App\Domain\Shift\Services\RotationScheduler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AdvanceShiftRotationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rotations:advance {--date= : The date to advance rotations for (Y-m-d format)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Advance shift rotations for employees based on their rotation patterns';

    /**
     * Execute the console command.
     */
    public function handle(RotationScheduler $scheduler): int
    {
        $this->info('Starting shift rotation advancement...');

        $date = $this->option('date')
            ? \Carbon\Carbon::parse($this->option('date'))
            : now();

        $this->info("Processing rotations for date: {$date->format('Y-m-d')}");

        try {
            $count = $scheduler->advanceEligibleRotations($date);

            $this->info("Successfully advanced {$count} rotation(s)");

            Log::info('Shift rotations advanced', [
                'date' => $date->format('Y-m-d'),
                'count' => $count,
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to advance rotations: {$e->getMessage()}");

            Log::error('Failed to advance shift rotations', [
                'date' => $date->format('Y-m-d'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
