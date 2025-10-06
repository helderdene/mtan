<?php

namespace App\Listeners;

use App\Events\ViolationDetected;
use App\Models\NotificationPreference;
use App\Notifications\ViolationNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendViolationNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(ViolationDetected $event): void
    {
        $violation = $event->violation;
        $employee = $violation->employee;

        // Get the employee's manager
        $manager = $employee->manager;

        if (! $manager) {
            Log::info('No manager assigned for employee', [
                'employee_id' => $employee->id,
                'violation_id' => $violation->id,
            ]);

            return;
        }

        // Check notification preferences
        $preference = NotificationPreference::where('user_id', $manager->id)
            ->where('notification_type', 'violation_immediate')
            ->first();

        // If no preference exists, create default (enabled for all severities)
        if (! $preference) {
            $preference = NotificationPreference::create([
                'user_id' => $manager->id,
                'notification_type' => 'violation_immediate',
                'settings' => [],
                'enabled' => true,
            ]);
        }

        // Check if notification should be sent based on preferences
        if (! $preference->shouldNotifyForSeverity($violation->severity)) {
            Log::info('Notification skipped due to severity filter', [
                'manager_id' => $manager->id,
                'violation_severity' => $violation->severity,
                'minimum_severity' => $preference->getMinimumSeverity(),
            ]);

            return;
        }

        // Send notification
        $manager->notify(new ViolationNotification($violation));

        Log::info('Violation notification sent', [
            'manager_id' => $manager->id,
            'violation_id' => $violation->id,
            'severity' => $violation->severity,
        ]);
    }
}
