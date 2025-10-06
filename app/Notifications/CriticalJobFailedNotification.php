<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CriticalJobFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $timeout = 30;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $jobType,
        public string $jobId,
        public int $attempts,
        public \Throwable $exception,
        public array $payload
    ) {
        $this->onQueue(env('QUEUE_NOTIFICATIONS', 'notifications'));
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $errorMessage = $this->exception->getMessage();
        $errorClass = get_class($this->exception);

        return (new MailMessage)
            ->error()
            ->subject("🚨 Critical Job Failure: {$this->jobType}")
            ->greeting('Critical Job Failure Alert')
            ->line("A critical job has permanently failed after {$this->attempts} retry attempts.")
            ->line('')
            ->line("**Job Details:**")
            ->line("- **Type:** {$this->jobType}")
            ->line("- **Job ID:** {$this->jobId}")
            ->line("- **Attempts:** {$this->attempts}")
            ->line("- **Error:** {$errorClass}")
            ->line('')
            ->line("**Error Message:**")
            ->line("```\n{$errorMessage}\n```")
            ->line('')
            ->line("**Payload:**")
            ->line("```json\n".json_encode($this->payload, JSON_PRETTY_PRINT)."\n```")
            ->line('')
            ->line('Please investigate this failure immediately. Attendance data or device sync operations may have been lost.')
            ->action('View Failed Jobs', url('/admin/failed-jobs'))
            ->line('You can retry failed jobs via the admin panel or using the command:')
            ->line('`php artisan queue:retry-failed '.$this->jobId.'`');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'job_type' => $this->jobType,
            'job_id' => $this->jobId,
            'attempts' => $this->attempts,
            'error_class' => get_class($this->exception),
            'error_message' => $this->exception->getMessage(),
        ];
    }
}
