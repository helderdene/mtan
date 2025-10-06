<?php

namespace App\Notifications;

use App\Domain\Attendance\Models\AttendanceViolation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ViolationNotification extends Notification implements ShouldQueue
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
        public AttendanceViolation $violation
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
        $violation = $this->violation;
        $employee = $violation->employee;

        $subject = $this->getSubject();
        $greeting = "Hello {$notifiable->name},";
        $intro = $this->getIntroduction();

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting($greeting)
            ->line($intro)
            ->line("**Employee:** {$employee->full_name}")
            ->line("**Date:** {$violation->violation_date->format('l, F j, Y')}")
            ->line("**Type:** ".ucwords(str_replace('_', ' ', $violation->type)))
            ->line("**Severity:** ".ucfirst($violation->severity));

        // Add type-specific details
        $message = $this->addTypeSpecificDetails($message);

        // Add action button
        $message->action('View Violation Details', url('/violations/'.$violation->id));

        return $message;
    }

    /**
     * Get the subject line for the email
     */
    protected function getSubject(): string
    {
        $severityPrefix = match ($this->violation->severity) {
            'critical' => '🔴 CRITICAL',
            'major' => '🟠 MAJOR',
            'moderate' => '🟡',
            'minor' => '🟢',
            default => '',
        };

        return "{$severityPrefix} Attendance Violation Detected - {$this->violation->employee->full_name}";
    }

    /**
     * Get introduction text based on violation type
     */
    protected function getIntroduction(): string
    {
        return match ($this->violation->type) {
            'late_arrival' => 'An employee under your management arrived late to their shift.',
            'early_departure' => 'An employee under your management left before their shift ended.',
            'extended_break' => 'An employee under your management exceeded their allowed break time.',
            'missing_checkout' => 'An employee under your management did not check out at the end of their shift.',
            default => 'An attendance violation has been detected for an employee under your management.',
        };
    }

    /**
     * Add type-specific details to the message
     */
    protected function addTypeSpecificDetails(MailMessage $message): MailMessage
    {
        $details = $this->violation->metadata;

        if ($this->violation->type === 'late_arrival' && isset($details['minutes_late'])) {
            $message->line("**Minutes Late:** {$details['minutes_late']} minutes");
            if (isset($details['expected_time'], $details['actual_time'])) {
                $message->line("**Expected:** {$details['expected_time']}");
                $message->line("**Actual:** {$details['actual_time']}");
            }
        }

        if ($this->violation->type === 'early_departure' && isset($details['minutes_early'])) {
            $message->line("**Left Early By:** {$details['minutes_early']} minutes");
            if (isset($details['expected_time'], $details['actual_time'])) {
                $message->line("**Expected:** {$details['expected_time']}");
                $message->line("**Actual:** {$details['actual_time']}");
            }
        }

        if ($this->violation->type === 'extended_break' && isset($details['break_duration_minutes'])) {
            $message->line("**Break Duration:** {$details['break_duration_minutes']} minutes");
            if (isset($details['allowed_break_minutes'])) {
                $message->line("**Allowed:** {$details['allowed_break_minutes']} minutes");
            }
        }

        if ($this->violation->type === 'missing_checkout') {
            $lastSeen = $details['last_check_in'] ?? 'N/A';
            $message->line("**Last Seen:** {$lastSeen}");
        }

        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'violation_id' => $this->violation->id,
            'employee_id' => $this->violation->employee_id,
            'type' => $this->violation->type,
            'severity' => $this->violation->severity,
            'date' => $this->violation->violation_date->toDateString(),
        ];
    }
}
