<?php

use App\Domain\Attendance\Models\DailyAttendanceSummary;
use App\Domain\Attendance\Services\SummaryCalculator;
use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\Employee;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->calculator = new SummaryCalculator();
});

describe('Bulk Recalculation', function () {
    test('recalculates summaries for single employee and date range', function () {
        $employee = Employee::factory()->create();
        $startDate = Carbon::parse('2025-10-01');
        $endDate = Carbon::parse('2025-10-05');

        // Create attendance records for 5 days
        for ($i = 0; $i < 5; $i++) {
            $date = $startDate->copy()->addDays($i);

            AttendanceRecord::factory()->create([
                'employee_id' => $employee->id,
                'recorded_at' => $date->copy()->setTimeFromTimeString('09:00:00'),
                'direction' => 'check-in',
            ]);

            AttendanceRecord::factory()->create([
                'employee_id' => $employee->id,
                'recorded_at' => $date->copy()->setTimeFromTimeString('17:00:00'),
                'direction' => 'check-out',
            ]);
        }

        // Recalculate summaries
        $count = $this->calculator->recalculateRange($employee, $startDate, $endDate);

        // Should have created 5 summaries
        expect($count)->toBe(5)
            ->and(DailyAttendanceSummary::count())->toBe(5);

        // Verify each summary
        $summaries = DailyAttendanceSummary::where('employee_id', $employee->id)
            ->orderBy('date')
            ->get();

        expect($summaries)->toHaveCount(5);

        foreach ($summaries as $summary) {
            expect($summary->total_work_minutes)->toBe(480)
                ->and($summary->total_work_hours)->toBe(8.0)
                ->and($summary->status)->toBe('present')
                ->and($summary->is_complete)->toBeTrue();
        }
    });

    test('recalculates existing summaries', function () {
        $employee = Employee::factory()->create();
        $date = Carbon::parse('2025-10-01');

        // Create initial summaries with incorrect data
        DailyAttendanceSummary::factory()->create([
            'employee_id' => $employee->id,
            'date' => $date,
            'total_work_minutes' => 100, // Incorrect
            'status' => 'half-day', // Incorrect
        ]);

        // Create actual attendance records
        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'recorded_at' => $date->copy()->setTimeFromTimeString('09:00:00'),
            'direction' => 'check-in',
        ]);

        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'recorded_at' => $date->copy()->setTimeFromTimeString('17:00:00'),
            'direction' => 'check-out',
        ]);

        // Recalculate
        $count = $this->calculator->recalculateRange($employee, $date, $date);

        // Should update the existing summary
        expect($count)->toBe(1)
            ->and(DailyAttendanceSummary::count())->toBe(1);

        $summary = DailyAttendanceSummary::first();
        expect($summary->total_work_minutes)->toBe(480)
            ->and($summary->status)->toBe('present');
    });

    test('creates summaries for days without attendance', function () {
        $employee = Employee::factory()->create();
        $startDate = Carbon::parse('2025-10-01');
        $endDate = Carbon::parse('2025-10-03');

        // No attendance records created

        // Recalculate
        $count = $this->calculator->recalculateRange($employee, $startDate, $endDate);

        // Should create summaries for all 3 days with absent status
        expect($count)->toBe(3)
            ->and(DailyAttendanceSummary::count())->toBe(3);

        $summaries = DailyAttendanceSummary::all();
        foreach ($summaries as $summary) {
            expect($summary->status)->toBe('absent')
                ->and($summary->total_work_minutes)->toBe(0);
        }
    });

    test('recalculates partial attendance days', function () {
        $employee = Employee::factory()->create();
        $startDate = Carbon::parse('2025-10-01');
        $endDate = Carbon::parse('2025-10-03');

        // Day 1: Full day
        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'recorded_at' => $startDate->copy()->setTimeFromTimeString('09:00:00'),
            'direction' => 'check-in',
        ]);
        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'recorded_at' => $startDate->copy()->setTimeFromTimeString('17:00:00'),
            'direction' => 'check-out',
        ]);

        // Day 2: Half day
        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'recorded_at' => $startDate->copy()->addDay()->setTimeFromTimeString('09:00:00'),
            'direction' => 'check-in',
        ]);
        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'recorded_at' => $startDate->copy()->addDay()->setTimeFromTimeString('12:00:00'),
            'direction' => 'check-out',
        ]);

        // Day 3: Absent (no records)

        // Recalculate
        $count = $this->calculator->recalculateRange($employee, $startDate, $endDate);

        expect($count)->toBe(3);

        $summaries = DailyAttendanceSummary::orderBy('date')->get();

        expect($summaries[0]->status)->toBe('present')
            ->and($summaries[0]->total_work_minutes)->toBe(480)
            ->and($summaries[1]->status)->toBe('half-day')
            ->and($summaries[1]->total_work_minutes)->toBe(180)
            ->and($summaries[2]->status)->toBe('absent')
            ->and($summaries[2]->total_work_minutes)->toBe(0);
    });

    test('handles recalculation with break times', function () {
        $employee = Employee::factory()->create();
        $date = Carbon::parse('2025-10-01');

        // Create records with break
        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'recorded_at' => $date->copy()->setTimeFromTimeString('09:00:00'),
            'direction' => 'check-in',
        ]);

        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'recorded_at' => $date->copy()->setTimeFromTimeString('12:00:00'),
            'direction' => 'break-start',
        ]);

        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'recorded_at' => $date->copy()->setTimeFromTimeString('13:00:00'),
            'direction' => 'break-end',
        ]);

        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'recorded_at' => $date->copy()->setTimeFromTimeString('17:00:00'),
            'direction' => 'check-out',
        ]);

        // Recalculate
        $this->calculator->recalculateRange($employee, $date, $date);

        $summary = DailyAttendanceSummary::first();
        expect($summary->total_work_minutes)->toBe(420)
            ->and($summary->total_break_minutes)->toBe(60);
    });

    test('recalculates summaries across month boundaries', function () {
        $employee = Employee::factory()->create();
        $startDate = Carbon::parse('2025-09-29');
        $endDate = Carbon::parse('2025-10-02');

        // Create records for 4 days across month boundary
        for ($i = 0; $i < 4; $i++) {
            $date = $startDate->copy()->addDays($i);

            AttendanceRecord::factory()->create([
                'employee_id' => $employee->id,
                'recorded_at' => $date->copy()->setTimeFromTimeString('09:00:00'),
                'direction' => 'check-in',
            ]);

            AttendanceRecord::factory()->create([
                'employee_id' => $employee->id,
                'recorded_at' => $date->copy()->setTimeFromTimeString('17:00:00'),
                'direction' => 'check-out',
            ]);
        }

        // Recalculate
        $count = $this->calculator->recalculateRange($employee, $startDate, $endDate);

        expect($count)->toBe(4)
            ->and(DailyAttendanceSummary::count())->toBe(4);

        // Verify dates span both months
        $summaries = DailyAttendanceSummary::orderBy('date')->get();
        expect($summaries[0]->date->month)->toBe(9)
            ->and($summaries[3]->date->month)->toBe(10);
    });

    test('returns correct count of recalculated days', function () {
        $employee = Employee::factory()->create();

        // Test various ranges
        $testCases = [
            ['from' => '2025-10-01', 'to' => '2025-10-01', 'expected' => 1],
            ['from' => '2025-10-01', 'to' => '2025-10-07', 'expected' => 7],
            ['from' => '2025-10-01', 'to' => '2025-10-31', 'expected' => 31],
        ];

        foreach ($testCases as $testCase) {
            $startDate = Carbon::parse($testCase['from']);
            $endDate = Carbon::parse($testCase['to']);

            $count = $this->calculator->recalculateRange($employee, $startDate, $endDate);

            expect($count)->toBe($testCase['expected']);
        }
    });

    test('handles single day recalculation', function () {
        $employee = Employee::factory()->create();
        $date = Carbon::parse('2025-10-01');

        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'recorded_at' => $date->copy()->setTimeFromTimeString('09:00:00'),
            'direction' => 'check-in',
        ]);

        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'recorded_at' => $date->copy()->setTimeFromTimeString('17:00:00'),
            'direction' => 'check-out',
        ]);

        // Recalculate single day (from = to)
        $count = $this->calculator->recalculateRange($employee, $date, $date);

        expect($count)->toBe(1)
            ->and(DailyAttendanceSummary::count())->toBe(1);

        $summary = DailyAttendanceSummary::first();
        expect($summary->date->toDateString())->toBe($date->toDateString());
    });

    test('recalculation does not affect other employees', function () {
        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();
        $date = Carbon::parse('2025-10-01');

        // Create records for both employees
        foreach ([$employee1, $employee2] as $emp) {
            AttendanceRecord::factory()->create([
                'employee_id' => $emp->id,
                'recorded_at' => $date->copy()->setTimeFromTimeString('09:00:00'),
                'direction' => 'check-in',
            ]);

            AttendanceRecord::factory()->create([
                'employee_id' => $emp->id,
                'recorded_at' => $date->copy()->setTimeFromTimeString('17:00:00'),
                'direction' => 'check-out',
            ]);
        }

        // Recalculate only for employee1
        $count = $this->calculator->recalculateRange($employee1, $date, $date);

        expect($count)->toBe(1)
            ->and(DailyAttendanceSummary::count())->toBe(1)
            ->and(DailyAttendanceSummary::first()->employee_id)->toBe($employee1->id);
    });
});
