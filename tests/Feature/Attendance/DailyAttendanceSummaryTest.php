<?php

use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Attendance\Models\DailyAttendanceSummary;
use App\Models\Tenant\Employee;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->employee = Employee::factory()->create();
    $this->date = Carbon::parse('2025-10-06');
});

test('can create daily attendance summary', function () {
    $summary = DailyAttendanceSummary::factory()->create([
        'employee_id' => $this->employee->id,
        'date' => $this->date,
    ]);

    expect($summary->exists())->toBeTrue()
        ->and($summary->employee_id)->toBe($this->employee->id)
        ->and($summary->date->format('Y-m-d'))->toBe('2025-10-06');
});

test('belongs to employee', function () {
    $summary = DailyAttendanceSummary::factory()->create([
        'employee_id' => $this->employee->id,
    ]);

    expect($summary->employee)->toBeInstanceOf(Employee::class)
        ->and($summary->employee->id)->toBe($this->employee->id);
});

test('enforces unique constraint on employee and date', function () {
    DailyAttendanceSummary::factory()->create([
        'employee_id' => $this->employee->id,
        'date' => $this->date,
    ]);

    // Attempting to create duplicate should fail
    expect(fn () => DailyAttendanceSummary::factory()->create([
        'employee_id' => $this->employee->id,
        'date' => $this->date,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('converts work minutes to hours', function () {
    $summary = DailyAttendanceSummary::factory()->create([
        'total_work_minutes' => 480, // 8 hours
    ]);

    expect($summary->total_work_hours)->toBe(8.0);
});

test('converts break minutes to hours', function () {
    $summary = DailyAttendanceSummary::factory()->create([
        'total_break_minutes' => 60, // 1 hour
    ]);

    expect($summary->total_break_hours)->toBe(1.0);
});

test('converts overtime minutes to hours', function () {
    $summary = DailyAttendanceSummary::factory()->create([
        'overtime_minutes' => 90, // 1.5 hours
    ]);

    expect($summary->overtime_hours)->toBe(1.5);
});

test('rounds hours to 2 decimal places', function () {
    $summary = DailyAttendanceSummary::factory()->create([
        'total_work_minutes' => 125, // 2.083333... hours
    ]);

    expect($summary->total_work_hours)->toBe(2.08);
});

test('factory creates summary with present status by default', function () {
    $summary = DailyAttendanceSummary::factory()->create();

    expect($summary->status)->toBe('present')
        ->and($summary->total_work_minutes)->toBeGreaterThan(0);
});

test('factory creates absent status summary', function () {
    $summary = DailyAttendanceSummary::factory()->absent()->create();

    expect($summary->status)->toBe('absent')
        ->and($summary->total_work_minutes)->toBe(0)
        ->and($summary->first_check_in)->toBeNull()
        ->and($summary->last_check_out)->toBeNull();
});

test('factory creates half-day status summary', function () {
    $summary = DailyAttendanceSummary::factory()->halfDay()->create();

    expect($summary->status)->toBe('half-day')
        ->and($summary->total_work_minutes)->toBeLessThan(240); // < 4 hours
});

test('factory creates on-leave status summary', function () {
    $summary = DailyAttendanceSummary::factory()->onLeave()->create();

    expect($summary->status)->toBe('on-leave')
        ->and($summary->total_work_minutes)->toBe(0);
});

test('factory creates holiday status summary', function () {
    $summary = DailyAttendanceSummary::factory()->holiday()->create();

    expect($summary->status)->toBe('holiday')
        ->and($summary->total_work_minutes)->toBe(0);
});

test('can have multiple summaries for different dates', function () {
    $summary1 = DailyAttendanceSummary::factory()->create([
        'employee_id' => $this->employee->id,
        'date' => '2025-10-06',
    ]);

    $summary2 = DailyAttendanceSummary::factory()->create([
        'employee_id' => $this->employee->id,
        'date' => '2025-10-07',
    ]);

    expect($summary1->exists())->toBeTrue()
        ->and($summary2->exists())->toBeTrue()
        ->and($summary1->id)->not->toBe($summary2->id);
});

test('is_complete defaults to false', function () {
    $summary = DailyAttendanceSummary::factory()->create();

    expect($summary->is_complete)->toBeFalse();
});

test('can mark summary as complete', function () {
    $summary = DailyAttendanceSummary::factory()->create([
        'is_complete' => true,
    ]);

    expect($summary->is_complete)->toBeTrue();
});

test('stores first check-in and last check-out times', function () {
    $summary = DailyAttendanceSummary::factory()->create([
        'first_check_in' => '09:00:00',
        'last_check_out' => '18:00:00',
    ]);

    expect($summary->first_check_in)->toBe('09:00:00')
        ->and($summary->last_check_out)->toBe('18:00:00');
});

test('validates status enum values', function () {
    $validStatuses = ['present', 'absent', 'half-day', 'on-leave', 'holiday'];

    foreach ($validStatuses as $status) {
        $summary = DailyAttendanceSummary::factory()->create(['status' => $status]);
        expect($summary->status)->toBe($status);
    }
});

test('employee has many daily summaries', function () {
    DailyAttendanceSummary::factory()->count(3)->create([
        'employee_id' => $this->employee->id,
    ]);

    $this->employee->refresh();

    expect($this->employee->dailySummaries)->toHaveCount(3);
});
