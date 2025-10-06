<?php

use App\Domain\Attendance\Models\AttendanceViolation;
use App\Domain\Attendance\Services\ViolationDetector;
use App\Domain\Shift\Models\Shift;
use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\Employee;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Set up tenant context for testing
    $this->employee = Employee::factory()->create();

    // Create a shift (9 AM - 5 PM)
    $this->shift = Shift::factory()->create([
        'name' => 'Regular Shift',
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'working_days' => 31, // Mon-Fri (binary: 0011111)
    ]);

    // Assign shift to employee with pivot data
    $this->employee->shifts()->attach($this->shift->id, [
        'effective_from' => now()->subDays(30)->toDateString(),
        'effective_to' => null,
    ]);

    $this->detector = app(ViolationDetector::class);
});

test('detects late arrival violation', function () {
    // Employee checks in 30 minutes late (grace period is 15 minutes)
    $checkInTime = Carbon::today()->setTime(9, 30, 0);

    $record = AttendanceRecord::factory()->create([
        'employee_id' => $this->employee->id,
        'recorded_at' => $checkInTime,
        'direction' => 'check-in',
    ]);

    $violation = $this->detector->detectLateArrival($record);

    expect($violation)->not->toBeNull()
        ->and($violation->type)->toBe('late_arrival')
        ->and($violation->severity)->toBe('minor') // 30 minutes = minor
        ->and($violation->minutes_deviation)->toBe(30)
        ->and($violation->employee_id)->toBe($this->employee->id);
});

test('does not detect violation for on-time arrival', function () {
    // Employee checks in on time
    $checkInTime = Carbon::today()->setTime(9, 0, 0);

    $record = AttendanceRecord::factory()->create([
        'employee_id' => $this->employee->id,
        'recorded_at' => $checkInTime,
        'direction' => 'check-in',
    ]);

    $violation = $this->detector->detectLateArrival($record);

    expect($violation)->toBeNull();
});

test('does not detect violation within grace period', function () {
    // Employee checks in 10 minutes late (within 15-minute grace period)
    $checkInTime = Carbon::today()->setTime(9, 10, 0);

    $record = AttendanceRecord::factory()->create([
        'employee_id' => $this->employee->id,
        'recorded_at' => $checkInTime,
        'direction' => 'check-in',
    ]);

    $violation = $this->detector->detectLateArrival($record);

    expect($violation)->toBeNull();
});

test('detects early departure violation', function () {
    // Employee checks out 30 minutes early (grace period is 15 minutes)
    $checkOutTime = Carbon::today()->setTime(16, 30, 0);

    $record = AttendanceRecord::factory()->create([
        'employee_id' => $this->employee->id,
        'recorded_at' => $checkOutTime,
        'direction' => 'check-out',
    ]);

    $violation = $this->detector->detectEarlyDeparture($record);

    expect($violation)->not->toBeNull()
        ->and($violation->type)->toBe('early_departure')
        ->and($violation->severity)->toBe('minor')
        ->and($violation->minutes_deviation)->toBe(30)
        ->and($violation->employee_id)->toBe($this->employee->id);
});

test('does not detect violation for on-time departure', function () {
    // Employee checks out at shift end time
    $checkOutTime = Carbon::today()->setTime(17, 0, 0);

    $record = AttendanceRecord::factory()->create([
        'employee_id' => $this->employee->id,
        'recorded_at' => $checkOutTime,
        'direction' => 'check-out',
    ]);

    $violation = $this->detector->detectEarlyDeparture($record);

    expect($violation)->toBeNull();
});

test('detects extended break violation', function () {
    $date = Carbon::today();

    // Employee starts break at 12:00
    $breakStart = AttendanceRecord::factory()->create([
        'employee_id' => $this->employee->id,
        'recorded_at' => $date->copy()->setTime(12, 0, 0),
        'direction' => 'break-start',
    ]);

    // Employee ends break at 15:00 (3 hours = 180 minutes, max is 120 minutes)
    $breakEnd = AttendanceRecord::factory()->create([
        'employee_id' => $this->employee->id,
        'recorded_at' => $date->copy()->setTime(15, 0, 0),
        'direction' => 'break-end',
    ]);

    $violation = $this->detector->detectExtendedBreak($breakEnd);

    expect($violation)->not->toBeNull()
        ->and($violation->type)->toBe('extended_break')
        ->and($violation->severity)->toBe('major') // 60 minutes over = major (> 60 threshold)
        ->and($violation->minutes_deviation)->toBe(60)
        ->and($violation->employee_id)->toBe($this->employee->id);
});

test('does not detect violation for normal break duration', function () {
    $date = Carbon::today();

    // Employee starts break at 12:00
    $breakStart = AttendanceRecord::factory()->create([
        'employee_id' => $this->employee->id,
        'recorded_at' => $date->copy()->setTime(12, 0, 0),
        'direction' => 'break-start',
    ]);

    // Employee ends break at 13:00 (1 hour)
    $breakEnd = AttendanceRecord::factory()->create([
        'employee_id' => $this->employee->id,
        'recorded_at' => $date->copy()->setTime(13, 0, 0),
        'direction' => 'break-end',
    ]);

    $violation = $this->detector->detectExtendedBreak($breakEnd);

    expect($violation)->toBeNull();
});

test('detects missing checkout violation', function () {
    $date = Carbon::yesterday();

    // Employee checked in but never checked out
    AttendanceRecord::factory()->create([
        'employee_id' => $this->employee->id,
        'recorded_at' => $date->copy()->setTime(9, 0, 0),
        'direction' => 'check-in',
    ]);

    $violations = $this->detector->detectMissingCheckouts($date);

    expect($violations)->toHaveCount(1)
        ->and($violations->first()->type)->toBe('missing_checkout')
        ->and($violations->first()->severity)->toBe('moderate')
        ->and($violations->first()->employee_id)->toBe($this->employee->id);
});

test('does not detect missing checkout when checkout exists', function () {
    $date = Carbon::yesterday();

    // Employee checked in
    AttendanceRecord::factory()->create([
        'employee_id' => $this->employee->id,
        'recorded_at' => $date->copy()->setTime(9, 0, 0),
        'direction' => 'check-in',
    ]);

    // Employee checked out
    AttendanceRecord::factory()->create([
        'employee_id' => $this->employee->id,
        'recorded_at' => $date->copy()->setTime(17, 0, 0),
        'direction' => 'check-out',
    ]);

    $violations = $this->detector->detectMissingCheckouts($date);

    expect($violations)->toBeEmpty();
});

test('calculates severity correctly for violations', function () {
    expect($this->detector->calculateSeverity('late_arrival', 20))->toBe('minor')
        ->and($this->detector->calculateSeverity('late_arrival', 45))->toBe('moderate')
        ->and($this->detector->calculateSeverity('late_arrival', 90))->toBe('major');
});

test('detects violations from attendance record', function () {
    // Create a late check-in
    $checkInTime = Carbon::today()->setTime(9, 30, 0);

    $record = AttendanceRecord::factory()->create([
        'employee_id' => $this->employee->id,
        'recorded_at' => $checkInTime,
        'direction' => 'check-in',
    ]);

    $violations = $this->detector->detectFromRecord($record);

    expect($violations)->toHaveCount(1)
        ->and($violations->first()->type)->toBe('late_arrival');
});

test('stores violation metadata correctly', function () {
    $checkInTime = Carbon::today()->setTime(9, 30, 0);

    $record = AttendanceRecord::factory()->create([
        'employee_id' => $this->employee->id,
        'recorded_at' => $checkInTime,
        'direction' => 'check-in',
    ]);

    $violation = $this->detector->detectLateArrival($record);

    expect($violation->metadata)->toHaveKey('shift_start_time')
        ->and($violation->metadata)->toHaveKey('actual_check_in_time')
        ->and($violation->metadata)->toHaveKey('grace_period_minutes')
        ->and($violation->metadata['shift_start_time'])->toBe('09:00:00')
        ->and($violation->metadata['actual_check_in_time'])->toBe('09:30:00')
        ->and($violation->metadata['grace_period_minutes'])->toBe(15);
});
