<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class DailyViolationDigest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Collection $violations,
        public Carbon $date
    ) {
        $this->onQueue('notifications');
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
        $stats = $this->calculateStatistics();

        $message = (new MailMessage)
            ->subject("Daily Violation Digest - {$this->date->format('F j, Y')}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Here is your daily attendance violation summary for {$this->date->format('l, F j, Y')}:");

        // Add summary statistics
        $message->line("**Total Violations:** {$stats['total']}")
            ->line('');

        // Breakdown by type
        if (! empty($stats['by_type'])) {
            $message->line('**Breakdown by Type:**');
            foreach ($stats['by_type'] as $type => $count) {
                $typeLabel = ucwords(str_replace('_', ' ', $type));
                $message->line("- {$typeLabel}: {$count}");
            }
            $message->line('');
        }

        // Breakdown by severity
        if (! empty($stats['by_severity'])) {
            $message->line('**Breakdown by Severity:**');
            foreach ($stats['by_severity'] as $severity => $count) {
                $emoji = match ($severity) {
                    'critical' => '🔴',
                    'major' => '🟠',
                    'moderate' => '🟡',
                    'minor' => '🟢',
                    default => '',
                };
                $message->line("{$emoji} ".ucfirst($severity).": {$count}");
            }
            $message->line('');
        }

        // Add top violations (up to 20)
        $topViolations = $this->violations->take(20);
        if ($topViolations->isNotEmpty()) {
            $message->line('**Recent Violations:**');
            foreach ($topViolations as $violation) {
                $employee = $violation->employee;
                $type = ucwords(str_replace('_', ' ', $violation->type));
                $message->line("• {$employee->full_name} - {$type} ({$violation->severity})");
            }

            if ($this->violations->count() > 20) {
                $remaining = $this->violations->count() - 20;
                $message->line('')
                    ->line("_...and {$remaining} more violations_");
            }
        }

        $message->action('View All Violations', url('/violations?date='.$this->date->toDateString()));

        return $message;
    }

    /**
     * Calculate statistics from violations
     */
    protected function calculateStatistics(): array
    {
        return [
            'total' => $this->violations->count(),
            'by_type' => $this->violations->countBy('type')->toArray(),
            'by_severity' => $this->violations->countBy('severity')->toArray(),
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $stats = $this->calculateStatistics();

        return [
            'date' => $this->date->toDateString(),
            'total_violations' => $stats['total'],
            'by_type' => $stats['by_type'],
            'by_severity' => $stats['by_severity'],
        ];
    }
}
