<?php

namespace App\Notifications;

use App\Domain\Attendance\Models\AttendanceCorrection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CorrectionDecisionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the notification may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds before the job should timeout.
     *
     * @var int
     */
    public $timeout = 30;

    public function __construct(
        public AttendanceCorrection $correction
    ) {
        $this->onQueue(env('QUEUE_NOTIFICATIONS', 'notifications'));
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
        $this->correction->load(['reviewedBy']);

        $isApproved = $this->correction->isApproved() || $this->correction->isApplied();
        $status = $isApproved ? 'Approved' : 'Rejected';
        $color = $isApproved ? 'success' : 'error';

        $message = (new MailMessage)
            ->subject("Correction Request {$status}")
            ->greeting('Hello '.$notifiable->name.',')
            ->line("Your attendance correction request has been **{$status}** by ".$this->correction->reviewedBy->name.'.')
            ->line('**Type:** '.ucwords(str_replace('_', ' ', $this->correction->type)));

        if ($this->correction->review_notes) {
            $message->line('**Notes:** '.$this->correction->review_notes);
        }

        $message->action('View Details', url('/corrections/'.$this->correction->id));

        if ($isApproved) {
            $message->line('Your attendance record has been updated accordingly.');
        } else {
            $message->line('If you have any questions, please contact your manager.');
        }

        return $message;
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        $isApproved = $this->correction->isApproved() || $this->correction->isApplied();
        $status = $isApproved ? 'approved' : 'rejected';

        return [
            'correction_id' => $this->correction->id,
            'status' => $status,
            'reviewed_by' => $this->correction->reviewed_by,
            'reviewer_name' => $this->correction->reviewedBy->name,
            'review_notes' => $this->correction->review_notes,
            'message' => "Your {$this->correction->type} correction request has been {$status}",
        ];
    }
}
