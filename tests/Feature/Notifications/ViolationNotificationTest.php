<?php

use App\Domain\Attendance\Models\AttendanceViolation;
use App\Events\ViolationDetected;
use App\Models\NotificationPreference;
use App\Models\Tenant\Employee;
use App\Models\User;
use App\Notifications\ViolationNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

test('manager receives notification when violation detected', function () {
    Notification::fake();

    $manager = User::factory()->create();
    $employee = Employee::factory()->create(['manager_id' => $manager->id]);

    $violation = AttendanceViolation::factory()->create([
        'employee_id' => $employee->id,
        'severity' => 'moderate',
    ]);

    // Dispatch the event
    event(new ViolationDetected($violation));

    Notification::assertSentTo(
        $manager,
        ViolationNotification::class,
        fn ($notification) => $notification->violation->id === $violation->id
    );
});

test('notification respects severity filtering', function () {
    Notification::fake();

    $manager = User::factory()->create();

    // Set preference to only notify for major and critical violations
    NotificationPreference::create([
        'user_id' => $manager->id,
        'notification_type' => 'violation_immediate',
        'settings' => ['minimum_severity' => 'major'],
        'enabled' => true,
    ]);

    $employee = Employee::factory()->create(['manager_id' => $manager->id]);

    // Create minor violation (should not notify)
    $minorViolation = AttendanceViolation::factory()->create([
        'employee_id' => $employee->id,
        'severity' => 'minor',
    ]);

    event(new ViolationDetected($minorViolation));

    Notification::assertNotSentTo($manager, ViolationNotification::class);

    // Create major violation (should notify)
    $majorViolation = AttendanceViolation::factory()->create([
        'employee_id' => $employee->id,
        'severity' => 'major',
    ]);

    event(new ViolationDetected($majorViolation));

    Notification::assertSentTo(
        $manager,
        ViolationNotification::class,
        fn ($notification) => $notification->violation->id === $majorViolation->id
    );
});

test('no notification when preferences disabled', function () {
    Notification::fake();

    $manager = User::factory()->create();

    NotificationPreference::create([
        'user_id' => $manager->id,
        'notification_type' => 'violation_immediate',
        'enabled' => false,
    ]);

    $employee = Employee::factory()->create(['manager_id' => $manager->id]);

    $violation = AttendanceViolation::factory()->create([
        'employee_id' => $employee->id,
        'severity' => 'critical',
    ]);

    event(new ViolationDetected($violation));

    Notification::assertNotSentTo($manager, ViolationNotification::class);
});

test('no notification when employee has no manager', function () {
    Notification::fake();

    $employee = Employee::factory()->create(['manager_id' => null]);

    $violation = AttendanceViolation::factory()->create([
        'employee_id' => $employee->id,
    ]);

    event(new ViolationDetected($violation));

    Notification::assertNothingSent();
});

test('default preference created when none exists', function () {
    $manager = User::factory()->create();
    $employee = Employee::factory()->create(['manager_id' => $manager->id]);

    expect(NotificationPreference::where('user_id', $manager->id)->count())->toBe(0);

    $violation = AttendanceViolation::factory()->create([
        'employee_id' => $employee->id,
    ]);

    event(new ViolationDetected($violation));

    // Preference should be auto-created
    $preference = NotificationPreference::where('user_id', $manager->id)
        ->where('notification_type', 'violation_immediate')
        ->first();

    expect($preference)->not->toBeNull()
        ->and($preference->enabled)->toBeTrue();
});
