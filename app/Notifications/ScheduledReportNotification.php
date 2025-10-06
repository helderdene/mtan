<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ScheduledReportNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $timeout = 30;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $reportType,
        public string $frequency,
        public string $filePath,
        public array $summary
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
        $reportTypeTitle = ucfirst($this->reportType);
        $frequencyTitle = ucfirst($this->frequency);

        $message = (new MailMessage)
            ->subject("{$frequencyTitle} {$reportTypeTitle} Report")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your {$this->frequency} {$this->reportType} report is ready.")
            ->line($this->getSummaryText())
            ->attach($this->filePath, [
                'as' => basename($this->filePath),
                'mime' => 'application/pdf',
            ])
            ->line('Please find the detailed report attached to this email.')
            ->line('Thank you for using our attendance management system!');

        return $message;
    }

    /**
     * Get summary text based on report type
     */
    protected function getSummaryText(): string
    {
        $period = "Period: {$this->summary['period']['from']} to {$this->summary['period']['to']}";

        if ($this->reportType === 'attendance') {
            $total = $this->summary['summary']['total_employees'] ?? 0;
            $hours = $this->summary['summary']['total_work_hours'] ?? 0;
            $rate = $this->summary['summary']['attendance_rate'] ?? 0;

            return "{$period} | Employees: {$total} | Total Hours: ".number_format($hours, 2).' | Attendance Rate: '.number_format($rate, 1).'%';
        } else {
            $total = $this->summary['summary']['total_violations'] ?? 0;
            $employees = $this->summary['summary']['employees_with_violations'] ?? 0;

            return "{$period} | Total Violations: {$total} | Employees Affected: {$employees}";
        }
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'report_type' => $this->reportType,
            'frequency' => $this->frequency,
            'period' => $this->summary['period'] ?? null,
        ];
    }
}
