<?php

use App\Domain\Attendance\Events\CorrectionApproved;
use App\Domain\Attendance\Events\CorrectionRejected;
use App\Domain\Attendance\Events\CorrectionRequested;
use App\Domain\Attendance\Models\AttendanceCorrection;
use App\Models\Tenant\Employee;
use App\Models\User;
use App\Notifications\CorrectionDecisionNotification;
use App\Notifications\CorrectionRequestedNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

test('manager is notified when employee creates correction request', function () {
    $manager = User::factory()->create();
    $employee = Employee::factory()->create(['manager_id' => $manager->id]);

    $correction = AttendanceCorrection::factory()->create([
        'employee_id' => $employee->id,
    ]);

    event(new CorrectionRequested($correction));

    Notification::assertSentTo(
        $manager,
        CorrectionRequestedNotification::class,
        function ($notification) use ($correction) {
            return $notification->correction->id === $correction->id;
        }
    );
});

test('no notification sent when employee has no manager', function () {
    $employee = Employee::factory()->create(['manager_id' => null]);

    $correction = AttendanceCorrection::factory()->create([
        'employee_id' => $employee->id,
    ]);

    event(new CorrectionRequested($correction));

    Notification::assertNothingSent();
});

test('employee is notified when correction is approved', function () {
    $manager = User::factory()->create();
    $employee = Employee::factory()->create(['manager_id' => $manager->id]);

    $correction = AttendanceCorrection::factory()->approved()->create([
        'employee_id' => $employee->id,
        'reviewed_by' => $manager->id,
    ]);

    event(new CorrectionApproved($correction));

    Notification::assertSentTo(
        $employee,
        CorrectionDecisionNotification::class,
        function ($notification) use ($correction) {
            return $notification->correction->id === $correction->id;
        }
    );
});

test('employee is notified when correction is rejected', function () {
    $manager = User::factory()->create();
    $employee = Employee::factory()->create(['manager_id' => $manager->id]);

    $correction = AttendanceCorrection::factory()->rejected()->create([
        'employee_id' => $employee->id,
        'reviewed_by' => $manager->id,
    ]);

    event(new CorrectionRejected($correction));

    Notification::assertSentTo(
        $employee,
        CorrectionDecisionNotification::class,
        function ($notification) use ($correction) {
            return $notification->correction->id === $correction->id;
        }
    );
});

test('correction request notification contains correct data', function () {
    $manager = User::factory()->create();
    $employee = Employee::factory()->create([
        'manager_id' => $manager->id,
        'name' => 'John Doe',
    ]);

    $correction = AttendanceCorrection::factory()->create([
        'employee_id' => $employee->id,
        'type' => 'wrong_time',
        'reason' => 'Device malfunction recorded wrong time',
    ]);

    event(new CorrectionRequested($correction));

    Notification::assertSentTo(
        $manager,
        CorrectionRequestedNotification::class,
        function ($notification) use ($correction) {
            $mailData = $notification->toMail($notification);
            $arrayData = $notification->toArray($notification);

            return $arrayData['correction_id'] === $correction->id
                && $arrayData['employee_name'] === 'John Doe'
                && $arrayData['type'] === 'wrong_time';
        }
    );
});

test('correction decision notification contains review notes', function () {
    $manager = User::factory()->create(['name' => 'Manager Smith']);
    $employee = Employee::factory()->create(['manager_id' => $manager->id]);

    $correction = AttendanceCorrection::factory()->approved()->create([
        'employee_id' => $employee->id,
        'reviewed_by' => $manager->id,
        'review_notes' => 'Verified with security logs',
    ]);

    event(new CorrectionApproved($correction));

    Notification::assertSentTo(
        $employee,
        CorrectionDecisionNotification::class,
        function ($notification) use ($correction) {
            $arrayData = $notification->toArray($notification);

            return $arrayData['correction_id'] === $correction->id
                && $arrayData['review_notes'] === 'Verified with security logs'
                && $arrayData['status'] === 'approved';
        }
    );
});

test('notifications are queued for async processing', function () {
    $manager = User::factory()->create();
    $employee = Employee::factory()->create(['manager_id' => $manager->id]);

    $correction = AttendanceCorrection::factory()->create([
        'employee_id' => $employee->id,
    ]);

    event(new CorrectionRequested($correction));

    Notification::assertSentTo(
        $manager,
        CorrectionRequestedNotification::class,
        function ($notification) {
            return $notification->queue === 'notifications';
        }
    );
});
