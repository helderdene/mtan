<?php

namespace App\Domain\Attendance\Listeners;

use App\Domain\Attendance\Events\CorrectionRequested;
use App\Notifications\CorrectionRequestedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyManagerOfCorrectionRequest implements ShouldQueue
{
    public $queue = 'notifications';

    /**
     * Handle the event.
     */
    public function handle(CorrectionRequested $event): void
    {
        $correction = $event->correction;
        $correction->load('employee.manager');

        $manager = $correction->employee->manager;

        if (!$manager) {
            Log::warning('No manager found for employee', [
                'employee_id' => $correction->employee_id,
                'correction_id' => $correction->id,
            ]);

            return;
        }

        $manager->notify(new CorrectionRequestedNotification($correction));

        Log::info('Manager notified of correction request', [
            'correction_id' => $correction->id,
            'manager_id' => $manager->id,
            'employee_id' => $correction->employee_id,
        ]);
    }
}
