# Technical Specification

This is the technical specification for the spec detailed in @.agent-os/specs/2025-10-05-violation-notifications/spec.md

> Created: 2025-10-05
> Version: 1.0.0

## Technical Requirements

### Notification Classes

**Location**: `app/Notifications/ViolationNotification.php`

```php
<?php

namespace App\Notifications;

use App\Domain\Attendance\Models\AttendanceViolation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ViolationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public AttendanceViolation $violation
    ) {
        // Queue on notifications queue
        $this->onQueue('notifications');
    }

    public function via($notifiable): array
    {
        return ['mail'];
        // Future: ['mail', 'database', 'broadcast']
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Attendance Violation: {$this->violation->employee->name}")
            ->markdown('emails.violations.violation-detected', [
                'violation' => $this->violation,
                'employee' => $this->violation->employee,
                'manager' => $notifiable,
            ]);
    }
}
```

**Location**: `app/Notifications/DailyViolationDigest.php`

```php
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class DailyViolationDigest extends Notification
{
    use Queueable;

    public function __construct(
        public Collection $violations,
        public string $date
    ) {
        $this->onQueue('notifications');
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $stats = [
            'total' => $this->violations->count(),
            'by_type' => $this->violations->groupBy('type')->map->count(),
            'by_severity' => $this->violations->groupBy('severity')->map->count(),
        ];

        return (new MailMessage)
            ->subject("Daily Violation Report - {$this->date}")
            ->markdown('emails.violations.daily-digest', [
                'violations' => $this->violations,
                'stats' => $stats,
                'date' => $this->date,
                'recipient' => $notifiable,
            ]);
    }
}
```

### Email Templates

**Location**: `resources/views/emails/violations/violation-detected.blade.php`

```blade
@component('mail::message')
# Attendance Violation Detected

Hello {{ $manager->name }},

An attendance violation has been detected for your team member.

@component('mail::panel')
**Employee:** {{ $employee->name }} ({{ $employee->employee_code }})
**Violation Type:** {{ ucwords(str_replace('-', ' ', $violation->type)) }}
**Severity:** {{ ucfirst($violation->severity) }}
**Date:** {{ $violation->violation_date->format('F d, Y') }}
@if($violation->minutes_deviation)
**Deviation:** {{ $violation->minutes_deviation }} minutes
@endif
@endcomponent

## Details

@if($violation->type === 'late-arrival')
{{ $employee->name }} checked in at **{{ $violation->metadata['actual_check_in'] }}**, which is {{ $violation->minutes_deviation }} minutes after the shift start time of **{{ $violation->metadata['shift_start'] }}**.
@elseif($violation->type === 'early-departure')
{{ $employee->name }} checked out at **{{ $violation->metadata['actual_check_out'] }}**, which is {{ $violation->minutes_deviation }} minutes before the shift end time of **{{ $violation->metadata['shift_end'] }}**.
@elseif($violation->type === 'extended-break')
{{ $employee->name }} took a break lasting **{{ $violation->metadata['break_duration'] }} minutes**, which exceeds the maximum allowed break time of **{{ $violation->metadata['max_allowed'] }} minutes**.
@elseif($violation->type === 'missing-checkout')
{{ $employee->name }} checked in but did not check out for the day. Last check-in was at **{{ $violation->metadata['last_check_in'] }}**.
@endif

@component('mail::button', ['url' => route('violations.show', $violation)])
View Violation Details
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
```

**Location**: `resources/views/emails/violations/daily-digest.blade.php`

```blade
@component('mail::message')
# Daily Violation Report - {{ $date }}

Hello {{ $recipient->name }},

Here is your daily attendance violation summary.

@component('mail::panel')
**Total Violations:** {{ $stats['total'] }}
**By Severity:**
- Minor: {{ $stats['by_severity']['minor'] ?? 0 }}
- Moderate: {{ $stats['by_severity']['moderate'] ?? 0 }}
- Major: {{ $stats['by_severity']['major'] ?? 0 }}
@endcomponent

## Breakdown by Type

@foreach($stats['by_type'] as $type => $count)
- **{{ ucwords(str_replace('-', ' ', $type)) }}:** {{ $count }}
@endforeach

@component('mail::table')
| Employee | Type | Severity | Deviation |
|:---------|:-----|:---------|:----------|
@foreach($violations->take(20) as $violation)
| {{ $violation->employee->name }} | {{ ucwords(str_replace('-', ' ', $violation->type)) }} | {{ ucfirst($violation->severity) }} | {{ $violation->minutes_deviation ?? 'N/A' }} min |
@endforeach
@endcomponent

@if($violations->count() > 20)
*Showing first 20 violations out of {{ $violations->count() }} total.*
@endif

@component('mail::button', ['url' => route('violations.index', ['date' => $date])])
View All Violations
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
```

### NotificationPreference Model

**Location**: `app/Models/NotificationPreference.php`

**Table**: `notification_preferences` (tenant database)

```php
$table->id();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->string('notification_type'); // 'violation', 'digest', etc.
$table->json('settings'); // Severity filters, enabled channels, etc.
$table->boolean('enabled')->default(true);
$table->timestamps();

$table->unique(['user_id', 'notification_type']);
```

**Settings Schema**:
```json
{
  "min_severity": "moderate",
  "violation_types": ["late-arrival", "early-departure", "missing-checkout", "extended-break"],
  "digest_enabled": true,
  "digest_time": "08:00"
}
```

### Event Listener

**Location**: `app/Listeners/SendViolationNotification.php`

```php
<?php

namespace App\Listeners;

use App\Events\ViolationDetected;
use App\Notifications\ViolationNotification;
use App\Models\NotificationPreference;

class SendViolationNotification
{
    public function handle(ViolationDetected $event): void
    {
        $violation = $event->violation;
        $employee = $violation->employee;

        // Find employee's manager
        $manager = $employee->manager; // Assumes Employee has manager() relationship

        if (!$manager) {
            Log::warning("No manager found for employee", [
                'employee_id' => $employee->id,
                'violation_id' => $violation->id,
            ]);
            return;
        }

        // Check notification preferences
        $preference = NotificationPreference::where('user_id', $manager->id)
            ->where('notification_type', 'violation')
            ->first();

        if ($preference && !$preference->enabled) {
            return; // Manager has disabled notifications
        }

        // Check severity filter
        $minSeverity = $preference->settings['min_severity'] ?? 'minor';
        $severityLevels = ['minor' => 1, 'moderate' => 2, 'major' => 3];

        if ($severityLevels[$violation->severity] < $severityLevels[$minSeverity]) {
            return; // Violation severity below threshold
        }

        // Send notification
        $manager->notify(new ViolationNotification($violation));
    }
}
```

**Register in** `app/Providers/EventServiceProvider.php`:
```php
protected $listen = [
    ViolationDetected::class => [
        SendViolationNotification::class,
    ],
];
```

### Daily Digest Command

**Location**: `app/Console/Commands/SendDailyViolationDigestCommand.php`

**Signature**: `notifications:send-daily-violation-digest {--date=}`

**Schedule** (in `app/Console/Kernel.php`):
```php
$schedule->command('notifications:send-daily-violation-digest')
    ->dailyAt('08:00'); // Send at 8 AM
```

**Implementation**:
```php
public function handle()
{
    $date = $this->option('date')
        ? Carbon::parse($this->option('date'))
        : Carbon::yesterday(); // Previous day's violations

    // Get all managers who want daily digests
    $managers = User::whereHas('notificationPreferences', function ($query) {
        $query->where('notification_type', 'violation')
              ->where('enabled', true)
              ->whereJsonContains('settings->digest_enabled', true);
    })->get();

    foreach ($managers as $manager) {
        // Get violations for manager's team
        $violations = AttendanceViolation::whereHas('employee', function ($query) use ($manager) {
            $query->where('manager_id', $manager->id);
        })
        ->whereDate('violation_date', $date)
        ->with('employee')
        ->get();

        if ($violations->isEmpty()) {
            continue; // No violations for this manager
        }

        // Send digest
        $manager->notify(new DailyViolationDigest(
            $violations,
            $date->format('Y-m-d')
        ));
    }

    $this->info("Sent daily digest to {$managers->count()} managers");
}
```

### Database Schema Updates

**Add manager relationship to employees** (if not exists):

**Migration**: `database/migrations/tenant/YYYY_MM_DD_HHMMSS_add_manager_to_employees.php`

```php
Schema::table('employees', function (Blueprint $table) {
    $table->foreignId('manager_id')->nullable()->after('department_id')
        ->constrained('users')->nullOnDelete()
        ->comment('Employee\'s direct manager');
});
```

**Employee Model Update**:
```php
public function manager(): BelongsTo
{
    return $this->belongsTo(User::class, 'manager_id');
}
```

### Production Email Configuration

**IMPORTANT**: Update `.env` for production email service

**Option 1: AWS SES**
```env
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=us-east-1
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

**Option 2: Postmark**
```env
MAIL_MAILER=postmark
POSTMARK_TOKEN=your_postmark_server_token
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

**Option 3: SMTP (SendGrid, Mailgun, etc.)**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your_sendgrid_api_key
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

### Queue Configuration

Ensure notifications queue is processed:

```bash
# Start queue worker for notifications
php artisan queue:work redis --queue=notifications --tries=3 --timeout=30
```

Or via Supervisor:
```ini
[program:attendance-notification-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/mtan/artisan queue:work redis --queue=notifications --tries=3 --timeout=30
autostart=true
autorestart=true
numprocs=1
```

### Testing Requirements

**Unit Tests** (`tests/Unit/ViolationNotificationTest.php`):
- Test notification content generation
- Test severity filtering logic
- Test manager resolution
- Test digest statistics calculation

**Feature Tests** (`tests/Feature/ViolationNotificationTest.php`):
- Test notification dispatch on violation creation
- Test queue job processing
- Test email sending (using Mail::fake())
- Test notification preferences filtering
- Test daily digest command execution

**Example Test**:
```php
use Illuminate\Support\Facades\Mail;
use App\Notifications\ViolationNotification;

test('manager receives notification on violation detection', function () {
    Mail::fake();

    $manager = User::factory()->create();
    $employee = Employee::factory()->create(['manager_id' => $manager->id]);

    $violation = AttendanceViolation::factory()->create([
        'employee_id' => $employee->id,
        'type' => 'late-arrival',
        'severity' => 'major',
    ]);

    event(new ViolationDetected($violation));

    Mail::assertQueued(ViolationNotification::class, function ($mail) use ($manager) {
        return $mail->hasTo($manager->email);
    });
});
```

## Approach

1. **Phase 1: Core Notification Infrastructure**
   - Create notification classes and email templates
   - Implement NotificationPreference model and migration
   - Add manager_id to employees table

2. **Phase 2: Event Integration**
   - Create ViolationDetected event
   - Implement SendViolationNotification listener
   - Register event-listener mapping

3. **Phase 3: Daily Digest**
   - Create SendDailyViolationDigestCommand
   - Schedule command in Kernel
   - Test digest generation and filtering

4. **Phase 4: Production Setup**
   - Configure production email service
   - Set up queue worker for notifications queue
   - Monitor notification delivery

## External Dependencies

**Required**:
- Laravel Notification system (built-in)
- Production email service (SES/Postmark/SMTP)

**Optional** (future enhancements):
- `laravel/slack-notification-channel` for Slack notifications
- `laravel/vonage-notification-channel` for SMS
