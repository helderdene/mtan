<?php

use App\Domain\Attendance\Models\AttendanceViolation;
use App\Models\NotificationPreference;
use App\Models\Tenant\Employee;
use App\Models\User;
use App\Notifications\DailyViolationDigest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

test('command sends digest to managers with enabled preferences', function () {
    Notification::fake();

    $manager1 = User::factory()->create();
    $manager2 = User::factory()->create();

    // Enable digest for manager1
    NotificationPreference::create([
        'user_id' => $manager1->id,
        'notification_type' => 'violation_digest',
        'enabled' => true,
    ]);

    // Disable digest for manager2
    NotificationPreference::create([
        'user_id' => $manager2->id,
        'notification_type' => 'violation_digest',
        'enabled' => false,
    ]);

    $employee1 = Employee::factory()->create(['manager_id' => $manager1->id]);
    $employee2 = Employee::factory()->create(['manager_id' => $manager2->id]);

    $yesterday = Carbon::yesterday();

    AttendanceViolation::factory()->create([
        'employee_id' => $employee1->id,
        'violation_date' => $yesterday,
    ]);

    AttendanceViolation::factory()->create([
        'employee_id' => $employee2->id,
        'violation_date' => $yesterday,
    ]);

    $this->artisan('notifications:send-daily-violation-digest', [
        '--date' => $yesterday->toDateString(),
    ])->assertSuccessful();

    Notification::assertSentTo($manager1, DailyViolationDigest::class);
    Notification::assertNotSentTo($manager2, DailyViolationDigest::class);
});

test('command filters violations by date', function () {
    Notification::fake();

    $manager = User::factory()->create();

    NotificationPreference::create([
        'user_id' => $manager->id,
        'notification_type' => 'violation_digest',
        'enabled' => true,
    ]);

    $employee = Employee::factory()->create(['manager_id' => $manager->id]);

    $yesterday = Carbon::yesterday();
    $twoDaysAgo = Carbon::now()->subDays(2);

    // Create violation yesterday
    AttendanceViolation::factory()->create([
        'employee_id' => $employee->id,
        'violation_date' => $yesterday,
    ]);

    // Create violation two days ago
    AttendanceViolation::factory()->create([
        'employee_id' => $employee->id,
        'violation_date' => $twoDaysAgo,
    ]);

    $this->artisan('notifications:send-daily-violation-digest', [
        '--date' => $yesterday->toDateString(),
    ])->assertSuccessful();

    Notification::assertSentTo($manager, DailyViolationDigest::class, function ($notification) use ($yesterday) {
        return $notification->violations->count() === 1
            && $notification->date->toDateString() === $yesterday->toDateString();
    });
});

test('command sends separate digests to multiple managers', function () {
    Notification::fake();

    $manager1 = User::factory()->create();
    $manager2 = User::factory()->create();

    NotificationPreference::create([
        'user_id' => $manager1->id,
        'notification_type' => 'violation_digest',
        'enabled' => true,
    ]);

    NotificationPreference::create([
        'user_id' => $manager2->id,
        'notification_type' => 'violation_digest',
        'enabled' => true,
    ]);

    $employee1 = Employee::factory()->create(['manager_id' => $manager1->id]);
    $employee2 = Employee::factory()->create(['manager_id' => $manager2->id]);

    $yesterday = Carbon::yesterday();

    AttendanceViolation::factory()->count(3)->create([
        'employee_id' => $employee1->id,
        'violation_date' => $yesterday,
    ]);

    AttendanceViolation::factory()->count(2)->create([
        'employee_id' => $employee2->id,
        'violation_date' => $yesterday,
    ]);

    $this->artisan('notifications:send-daily-violation-digest', [
        '--date' => $yesterday->toDateString(),
    ])->assertSuccessful();

    Notification::assertSentTo($manager1, DailyViolationDigest::class, function ($notification) {
        return $notification->violations->count() === 3;
    });

    Notification::assertSentTo($manager2, DailyViolationDigest::class, function ($notification) {
        return $notification->violations->count() === 2;
    });
});

test('command handles empty violations gracefully', function () {
    Notification::fake();

    $manager = User::factory()->create();

    NotificationPreference::create([
        'user_id' => $manager->id,
        'notification_type' => 'violation_digest',
        'enabled' => true,
    ]);

    $yesterday = Carbon::yesterday();

    $this->artisan('notifications:send-daily-violation-digest', [
        '--date' => $yesterday->toDateString(),
    ])->assertSuccessful();

    Notification::assertNothingSent();
});

test('command respects severity filtering in preferences', function () {
    Notification::fake();

    $manager = User::factory()->create();

    NotificationPreference::create([
        'user_id' => $manager->id,
        'notification_type' => 'violation_digest',
        'settings' => ['minimum_severity' => 'major'],
        'enabled' => true,
    ]);

    $employee = Employee::factory()->create(['manager_id' => $manager->id]);

    $yesterday = Carbon::yesterday();

    // Create minor violations (should be filtered out)
    AttendanceViolation::factory()->count(3)->create([
        'employee_id' => $employee->id,
        'violation_date' => $yesterday,
        'severity' => 'minor',
    ]);

    // Create major violations (should be included)
    AttendanceViolation::factory()->count(2)->create([
        'employee_id' => $employee->id,
        'violation_date' => $yesterday,
        'severity' => 'major',
    ]);

    $this->artisan('notifications:send-daily-violation-digest', [
        '--date' => $yesterday->toDateString(),
    ])->assertSuccessful();

    Notification::assertSentTo($manager, DailyViolationDigest::class, function ($notification) {
        return $notification->violations->count() === 2
            && $notification->violations->every(fn ($v) => $v->severity === 'major');
    });
});
