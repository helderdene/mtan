<?php

use App\Domain\Attendance\Models\AttendanceViolation;
use App\Domain\Attendance\Models\DailyAttendanceSummary;
use App\Domain\Reporting\Services\ReportGenerator;
use App\Models\Tenant\Department;
use App\Models\Tenant\Employee;
use Carbon\Carbon;

beforeEach(function () {
    $this->reportGenerator = new ReportGenerator();
});

test('generateAttendanceReport returns correct structure', function () {
    $employee = Employee::factory()->create();
    $summary = DailyAttendanceSummary::factory()->create([
        'employee_id' => $employee->id,
        'date' => '2025-10-01',
        'total_work_minutes' => 480,
        'status' => 'present',
    ]);

    $report = $this->reportGenerator->generateAttendanceReport('2025-10-01', '2025-10-31');

    expect($report->period)->toHaveKeys(['from', 'to'])
        ->and($report->summary)->toHaveKeys([
            'total_employees',
            'total_records',
            'total_work_hours',
            'attendance_rate',
        ])
        ->and($report->records)->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->and($report->records->count())->toBeGreaterThan(0);
});

test('generateAttendanceReport filters by employee_id', function () {
    $employee1 = Employee::factory()->create();
    $employee2 = Employee::factory()->create();

    DailyAttendanceSummary::factory()->create(['employee_id' => $employee1->id, 'date' => '2025-10-01']);
    DailyAttendanceSummary::factory()->create(['employee_id' => $employee1->id, 'date' => '2025-10-02']);
    DailyAttendanceSummary::factory()->create(['employee_id' => $employee1->id, 'date' => '2025-10-03']);
    DailyAttendanceSummary::factory()->create(['employee_id' => $employee2->id, 'date' => '2025-10-04']);
    DailyAttendanceSummary::factory()->create(['employee_id' => $employee2->id, 'date' => '2025-10-05']);

    $report = $this->reportGenerator->generateAttendanceReport(
        '2025-10-01',
        '2025-10-31',
        ['employee_id' => $employee1->id]
    );

    expect($report->records->count())->toBe(3)
        ->and($report->records->every(fn ($r) => $r['employee_id'] === $employee1->id))->toBeTrue();
});

test('generateAttendanceReport filters by department_id', function () {
    $department = Department::factory()->create();
    $employee1 = Employee::factory()->create(['department_id' => $department->id]);
    $employee2 = Employee::factory()->create(['department_id' => null]);

    DailyAttendanceSummary::factory()->create(['employee_id' => $employee1->id, 'date' => '2025-10-01']);
    DailyAttendanceSummary::factory()->create(['employee_id' => $employee2->id, 'date' => '2025-10-01']);

    $report = $this->reportGenerator->generateAttendanceReport(
        '2025-10-01',
        '2025-10-31',
        ['department_id' => $department->id]
    );

    expect($report->records->count())->toBe(1)
        ->and($report->records->first()['employee_id'])->toBe($employee1->id);
});

test('generateAttendanceReport filters by status', function () {
    $employee = Employee::factory()->create();

    DailyAttendanceSummary::factory()->create(['employee_id' => $employee->id, 'status' => 'present', 'date' => '2025-10-01']);
    DailyAttendanceSummary::factory()->create(['employee_id' => $employee->id, 'status' => 'absent', 'date' => '2025-10-02']);

    $report = $this->reportGenerator->generateAttendanceReport(
        '2025-10-01',
        '2025-10-31',
        ['status' => 'present']
    );

    expect($report->records->count())->toBe(1)
        ->and($report->records->first()['status'])->toBe('present');
});

test('generateAttendanceReport calculates summary correctly', function () {
    $employee1 = Employee::factory()->create();
    $employee2 = Employee::factory()->create();

    // Employee 1: 2 days present (8 hours each)
    DailyAttendanceSummary::factory()->create([
        'employee_id' => $employee1->id,
        'date' => '2025-10-01',
        'total_work_minutes' => 480,
        'overtime_minutes' => 30,
        'status' => 'present',
    ]);
    DailyAttendanceSummary::factory()->create([
        'employee_id' => $employee1->id,
        'date' => '2025-10-02',
        'total_work_minutes' => 480,
        'overtime_minutes' => 0,
        'status' => 'present',
    ]);

    // Employee 2: 1 day absent
    DailyAttendanceSummary::factory()->create([
        'employee_id' => $employee2->id,
        'date' => '2025-10-01',
        'total_work_minutes' => 0,
        'overtime_minutes' => 0,
        'status' => 'absent',
    ]);

    $report = $this->reportGenerator->generateAttendanceReport('2025-10-01', '2025-10-31');

    expect($report->summary['total_employees'])->toBe(2)
        ->and($report->summary['total_records'])->toBe(3)
        ->and($report->summary['total_work_hours'])->toBe(16.0)
        ->and($report->summary['total_overtime_hours'])->toBe(0.5)
        ->and($report->summary['attendance_rate'])->toBeGreaterThan(0);
});

test('generateAttendanceReport handles empty data', function () {
    $report = $this->reportGenerator->generateAttendanceReport('2025-10-01', '2025-10-31');

    expect($report->records->count())->toBe(0)
        ->and($report->summary['total_employees'])->toBe(0)
        ->and($report->summary['average_work_hours_per_employee'])->toBe(0)
        ->and($report->summary['attendance_rate'])->toBe(0);
});

test('generateViolationReport returns correct structure', function () {
    $employee = Employee::factory()->create();
    $violation = AttendanceViolation::factory()->create([
        'employee_id' => $employee->id,
        'violation_date' => '2025-10-01',
        'type' => 'late_arrival',
        'severity' => 'moderate',
    ]);

    $report = $this->reportGenerator->generateViolationReport('2025-10-01', '2025-10-31');

    expect($report->period)->toHaveKeys(['from', 'to'])
        ->and($report->summary)->toHaveKeys([
            'total_violations',
            'employees_with_violations',
            'type_breakdown',
            'severity_breakdown',
            'repeat_offenders',
        ])
        ->and($report->records)->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->and($report->records->count())->toBeGreaterThan(0);
});

test('generateViolationReport filters by employee_id', function () {
    $employee1 = Employee::factory()->create();
    $employee2 = Employee::factory()->create();

    AttendanceViolation::factory()->create(['employee_id' => $employee1->id, 'violation_date' => '2025-10-01']);
    AttendanceViolation::factory()->create(['employee_id' => $employee1->id, 'violation_date' => '2025-10-02']);
    AttendanceViolation::factory()->create(['employee_id' => $employee1->id, 'violation_date' => '2025-10-03']);
    AttendanceViolation::factory()->create(['employee_id' => $employee2->id, 'violation_date' => '2025-10-04']);
    AttendanceViolation::factory()->create(['employee_id' => $employee2->id, 'violation_date' => '2025-10-05']);

    $report = $this->reportGenerator->generateViolationReport(
        '2025-10-01',
        '2025-10-31',
        ['employee_id' => $employee1->id]
    );

    expect($report->records->count())->toBe(3)
        ->and($report->records->every(fn ($r) => $r['employee_id'] === $employee1->id))->toBeTrue();
});

test('generateViolationReport filters by type', function () {
    $employee = Employee::factory()->create();

    AttendanceViolation::factory()->create(['employee_id' => $employee->id, 'type' => 'late_arrival', 'violation_date' => '2025-10-01']);
    AttendanceViolation::factory()->create(['employee_id' => $employee->id, 'type' => 'early_departure', 'violation_date' => '2025-10-02']);

    $report = $this->reportGenerator->generateViolationReport(
        '2025-10-01',
        '2025-10-31',
        ['type' => 'late_arrival']
    );

    expect($report->records->count())->toBe(1)
        ->and($report->records->first()['type'])->toBe('late_arrival');
});

test('generateViolationReport filters by severity', function () {
    $employee = Employee::factory()->create();

    AttendanceViolation::factory()->create(['employee_id' => $employee->id, 'severity' => 'major', 'violation_date' => '2025-10-01']);
    AttendanceViolation::factory()->create(['employee_id' => $employee->id, 'severity' => 'minor', 'violation_date' => '2025-10-02']);

    $report = $this->reportGenerator->generateViolationReport(
        '2025-10-01',
        '2025-10-31',
        ['severity' => 'major']
    );

    expect($report->records->count())->toBe(1)
        ->and($report->records->first()['severity'])->toBe('major');
});

test('generateViolationReport identifies repeat offenders', function () {
    $employee1 = Employee::factory()->create();
    $employee2 = Employee::factory()->create();

    // Employee 1: 6 violations (repeat offender)
    for ($i = 1; $i <= 6; $i++) {
        AttendanceViolation::factory()->create([
            'employee_id' => $employee1->id,
            'type' => 'late_arrival',
            'violation_date' => "2025-10-0{$i}",
        ]);
    }

    // Employee 2: 2 violations (not a repeat offender)
    AttendanceViolation::factory()->create([
        'employee_id' => $employee2->id,
        'type' => 'early_departure',
        'violation_date' => '2025-10-07',
    ]);
    AttendanceViolation::factory()->create([
        'employee_id' => $employee2->id,
        'type' => 'early_departure',
        'violation_date' => '2025-10-08',
    ]);

    $report = $this->reportGenerator->generateViolationReport('2025-10-01', '2025-10-31');

    expect($report->summary['repeat_offenders'])->toHaveCount(1)
        ->and($report->summary['repeat_offenders'][0]['employee_id'])->toBe($employee1->id)
        ->and($report->summary['repeat_offenders'][0]['violation_count'])->toBe(6)
        ->and($report->summary['repeat_offenders'][0]['most_common_type'])->toBe('late_arrival');
});

test('generateViolationReport handles empty data', function () {
    $report = $this->reportGenerator->generateViolationReport('2025-10-01', '2025-10-31');

    expect($report->records->count())->toBe(0)
        ->and($report->summary['total_violations'])->toBe(0)
        ->and($report->summary['employees_with_violations'])->toBe(0)
        ->and($report->summary['repeat_offenders'])->toBeEmpty();
});
