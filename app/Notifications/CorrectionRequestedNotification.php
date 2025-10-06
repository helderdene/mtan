<?php

namespace App\Notifications;

use App\Domain\Attendance\Models\AttendanceCorrection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CorrectionRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public AttendanceCorrection $correction
    ) {
        $this->queue = 'notifications';
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $this->correction->load(['employee', 'attendanceRecord']);

        return (new MailMessage)
            ->subject('New Attendance Correction Request')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->correction->employee->name.' has submitted a new attendance correction request.')
            ->line('**Type:** '.ucwords(str_replace('_', ' ', $this->correction->type)))
            ->line('**Reason:** '.$this->correction->reason)
            ->action('Review Request', url('/manager/corrections/'.$this->correction->id))
            ->line('Please review this request at your earliest convenience.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'correction_id' => $this->correction->id,
            'employee_id' => $this->correction->employee_id,
            'employee_name' => $this->correction->employee->name,
            'type' => $this->correction->type,
            'status' => $this->correction->status,
            'message' => $this->correction->employee->name.' submitted a '.str_replace('_', ' ', $this->correction->type).' correction request',
        ];
    }
}
