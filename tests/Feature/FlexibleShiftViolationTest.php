<?php

use App\Domain\Attendance\Models\AttendanceViolation;
use App\Domain\Attendance\Services\ViolationDetector;
use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\Employee;
use App\Models\Tenant\Shift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Set up tenant context for testing
    $this->employee = Employee::factory()->create();

    // Create a flexible shift (8 AM - 10 AM check-in window, 8 hours core required)
    $this->flexibleShift = Shift::factory()->flexible(
        windowStart: '08:00:00',
        windowEnd: '10:00:00',
        coreHours: 8.0
    )->create([
        'name' => 'Flexible Shift',
        'start_time' => '08:00:00', // Reference time (earliest window)
        'end_time' => '17:00:00',   // Reference time (latest expected end)
    ]);

    // Assign shift to employee
    $this->employee->shifts()->attach($this->flexibleShift->id, [
        'effective_from' => now()->subDays(30)->toDateString(),
        'effective_to' => null,
    ]);

    $this->detector = app(ViolationDetector::class);
});

test('detects late arrival for flexible shift outside window (before start)', function () {
    // Employee checks in at 7:45 AM (before flexible window starts at 8:00 AM)
    $checkInTime = Carbon::today()->setTime(7, 45, 0);

    $record = AttendanceRecord::factory()->create([
        'employee_id' => $this->employee->id,
        'recorded_at' => $checkInTime,
        'direction' => 'check-in',
    ]);

    $violation = $this->detector->detectLateArrival($record);

    expect($violation)->not->toBeNull()
        ->and($violation->type)->toBe('late_arrival')
        ->and($violation->employee_id)->toBe($this->employee->id)
        ->and($violation->metadata)->toHaveKey('flexible_window')
        ->and($violation->metadata['flexible_window']['start'])->toBe('08:00:00')
        ->and($violation->metadata['flexible_window']['end'])->toBe('10:00:00');
});

test('detects late arrival for flexible shift outside window (after end)', function () {
    // Employee checks in at 10:20 AM (after flexible window ends at 10:00 AM, beyond grace)
    // Grace period is 15 minutes, so 10:15 is the hard cutoff
    $checkInTime = Carbon::today()->setTime(10, 20, 0);

    $record = AttendanceRecord::factory()->create([
        'employee_id' => $this->employee->id,
        'recorded_at' => $checkInTime,
        'direction' => 'check-in',
    ]);

    $violation = $this->detector->detectLateArrival($record);

    expect($violation)->not->toBeNull()
        ->and($violation->type)->toBe('late_arrival')
        ->and($violation->employee_id)->toBe($this->employee->id)
        ->and($violation->metadata)->toHaveKey('flexible_window')
        ->and($violation->metadata['flexible_window']['end'])->toBe('10:00:00');
});

test('does not detect violation for check-in within flexible window', function () {
    // Employee checks in at 9:00 AM (within 8:00-10:00 window)
    $checkInTime = Carbon::today()->setTime(9, 0, 0);

    $record = AttendanceRecord::factory()->create([
        'employee_id' => $this->employee->id,
        'recorded_at' => $checkInTime,
        'direction' => 'check-in',
    ]);

    $violation = $this->detector->detectLateArrival($record);

    expect($violation)->toBeNull();
});

test('does not detect violation for check-in at window boundaries', function () {
    // Check in at window start (8:00 AM)
    $recordStart = AttendanceRecord::factory()->create([
        'employee_id' => $this->employee->id,
        'recorded_at' => Carbon::today()->setTime(8, 0, 0),
        'direction' => 'check-in',
    ]);

    expect($this->detector->detectLateArrival($recordStart))->toBeNull();

    // Check in at window end (10:00 AM)
    $recordEnd = AttendanceRecord::factory()->create([
        'employee_id' => $this->employee->id,
        'recorded_at' => Carbon::today()->setTime(10, 0, 0),
        'direction' => 'check-in',
    ]);

    expect($this->detector->detectLateArrival($recordEnd))->toBeNull();
});

test('applies grace period to flexible window end boundary', function () {
    // Employee checks in at 10:10 AM (10 minutes after window end)
    // Grace period is 15 minutes, so this should be within grace
    $checkInTime = Carbon::today()->setTime(10, 10, 0);

    $record = AttendanceRecord::factory()->create([
        'employee_id' => $this->employee->id,
        'recorded_at' => $checkInTime,
        'direction' => 'check-in',
    ]);

    $violation = $this->detector->detectLateArrival($record);

    // Should still be within grace period
    expect($violation)->toBeNull();
});

test('detects early departure for flexible shift based on core hours', function () {
    // Employee checks in at 9:00 AM
    $checkInTime = Carbon::today()->setTime(9, 0, 0);

    AttendanceRecord::factory()->create([
        'employee_id' => $this->employee->id,
        'recorded_at' => $checkInTime,
        'direction' => 'check-in',
    ]);

    // Expected end time: 9:00 AM + 8 hours = 5:00 PM
    // Employee checks out at 4:00 PM (1 hour early, beyond 15-min grace)
    $checkOutTime = Carbon::today()->setTime(16, 0, 0);

    $checkOutRecord = AttendanceRecord::factory()->create([
        'employee_id' => $this->employee->id,
        'recorded_at' => $checkOutTime,
        'direction' => 'check-out',
    ]);

    $violation = $this->detector->detectEarlyDeparture($checkOutRecord);

    expect($violation)->not->toBeNull()
        ->and($violation->type)->toBe('early_departure')
        ->and($violation->employee_id)->toBe($this->employee->id)
        ->and($violation->minutes_deviation)->toBe(60)
        ->and($violation->metadata)->toHaveKey('expected_end_time')
        ->and($violation->metadata)->toHaveKey('core_hours_required')
        ->and((float) $violation->metadata['core_hours_required'])->toBe(8.0);
});

test('does not detect early departure when core hours are met', function () {
    // Employee checks in at 9:00 AM
    $checkInTime = Carbon::today()->setTime(9, 0, 0);

    AttendanceRecord::factory()->create([
        'employee_id' => $this->employee->id,
        'recorded_at' => $checkInTime,
        'direction' => 'check-in',
    ]);

    // Expected end time: 9:00 AM + 8 hours = 5:00 PM
    // Employee checks out at 5:00 PM (exactly on time)
    $checkOutTime = Carbon::today()->setTime(17, 0, 0);

    $checkOutRecord = AttendanceRecord::factory()->create([
        'employee_id' => $this->employee->id,
        'recorded_at' => $checkOutTime,
        'direction' => 'check-out',
    ]);

    $violation = $this->detector->detectEarlyDeparture($checkOutRecord);

    expect($violation)->toBeNull();
});

test('calculates expected end time correctly for late check-in', function () {
    // Employee checks in at 10:00 AM (latest allowed time)
    $checkInTime = Carbon::today()->setTime(10, 0, 0);

    AttendanceRecord::factory()->create([
        'employee_id' => $this->employee->id,
        'recorded_at' => $checkInTime,
        'direction' => 'check-in',
    ]);

    // Expected end time: 10:00 AM + 8 hours = 6:00 PM
    // Employee checks out at 5:30 PM (30 minutes early, beyond grace)
    $checkOutTime = Carbon::today()->setTime(17, 30, 0);

    $checkOutRecord = AttendanceRecord::factory()->create([
        'employee_id' => $this->employee->id,
        'recorded_at' => $checkOutTime,
        'direction' => 'check-out',
    ]);

    $violation = $this->detector->detectEarlyDeparture($checkOutRecord);

    expect($violation)->not->toBeNull()
        ->and($violation->type)->toBe('early_departure')
        ->and($violation->minutes_deviation)->toBe(30)
        ->and($violation->metadata['expected_end_time'])->toBe('18:00:00');
});

test('respects grace period for early departure on flexible shift', function () {
    // Employee checks in at 9:00 AM
    $checkInTime = Carbon::today()->setTime(9, 0, 0);

    AttendanceRecord::factory()->create([
        'employee_id' => $this->employee->id,
        'recorded_at' => $checkInTime,
        'direction' => 'check-in',
    ]);

    // Expected end time: 9:00 AM + 8 hours = 5:00 PM
    // Employee checks out at 4:50 PM (10 minutes early, within 15-min grace)
    $checkOutTime = Carbon::today()->setTime(16, 50, 0);

    $checkOutRecord = AttendanceRecord::factory()->create([
        'employee_id' => $this->employee->id,
        'recorded_at' => $checkOutTime,
        'direction' => 'check-out',
    ]);

    $violation = $this->detector->detectEarlyDeparture($checkOutRecord);

    expect($violation)->toBeNull();
});

test('handles flexible shift with break time correctly', function () {
    // Create flexible shift with 1-hour break
    $shiftWithBreak = Shift::factory()->flexible(
        windowStart: '08:00:00',
        windowEnd: '10:00:00',
        coreHours: 8.0
    )->withBreak('12:00:00', '13:00:00')->create([
        'name' => 'Flexible Shift with Break',
        'start_time' => '08:00:00',
        'end_time' => '18:00:00',
    ]);

    $employee = Employee::factory()->create();
    $employee->shifts()->attach($shiftWithBreak->id, [
        'effective_from' => now()->subDays(30)->toDateString(),
        'effective_to' => null,
    ]);

    // Employee checks in at 9:00 AM
    AttendanceRecord::factory()->create([
        'employee_id' => $employee->id,
        'recorded_at' => Carbon::today()->setTime(9, 0, 0),
        'direction' => 'check-in',
    ]);

    // Expected: 9:00 AM + 8 hours + 1 hour break = 6:00 PM
    // Employee checks out at 6:00 PM
    $checkOutRecord = AttendanceRecord::factory()->create([
        'employee_id' => $employee->id,
        'recorded_at' => Carbon::today()->setTime(18, 0, 0),
        'direction' => 'check-out',
    ]);

    $violation = $this->detector->detectEarlyDeparture($checkOutRecord);

    expect($violation)->toBeNull();
});

test('stores flexible shift metadata in violation record', function () {
    // Employee checks in after window
    $checkInTime = Carbon::today()->setTime(10, 30, 0);

    $record = AttendanceRecord::factory()->create([
        'employee_id' => $this->employee->id,
        'recorded_at' => $checkInTime,
        'direction' => 'check-in',
    ]);

    $violation = $this->detector->detectLateArrival($record);

    expect($violation->metadata)->toHaveKey('flexible_window')
        ->and($violation->metadata['flexible_window'])->toBeArray()
        ->and($violation->metadata['flexible_window'])->toHaveKeys(['start', 'end'])
        ->and($violation->metadata)->toHaveKey('shift_type')
        ->and($violation->metadata['shift_type'])->toBe('flexible');
});

test('extended break detection works same for flexible shifts', function () {
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
        ->and($violation->minutes_deviation)->toBe(60);
});
