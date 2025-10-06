<?php

namespace App\Domain\Attendance\Listeners;

use App\Domain\Attendance\Events\CorrectionApproved;
use App\Domain\Attendance\Events\CorrectionRejected;
use App\Notifications\CorrectionDecisionNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyEmployeeOfCorrectionDecision implements ShouldQueue
{
    public $queue = 'notifications';

    /**
     * Handle the event.
     */
    public function handle(CorrectionApproved|CorrectionRejected $event): void
    {
        $correction = $event->correction;
        $correction->load('employee');

        $employee = $correction->employee;

        $employee->notify(new CorrectionDecisionNotification($correction));

        $status = $correction->isApproved() || $correction->isApplied() ? 'approved' : 'rejected';

        Log::info('Employee notified of correction decision', [
            'correction_id' => $correction->id,
            'employee_id' => $employee->id,
            'status' => $status,
        ]);
    }
}
