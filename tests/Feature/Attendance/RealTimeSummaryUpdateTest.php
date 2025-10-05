<?php

use App\Domain\Attendance\Models\DailyAttendanceSummary;
use App\Domain\Attendance\Services\SummaryCalculator;
use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\Employee;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->employee = Employee::factory()->create();
    $this->calculator = new SummaryCalculator();
    $this->date = Carbon::parse('2025-10-06');
});

describe('Real-time Summary Updates', function () {
    test('creates summary on first attendance event of the day', function () {
        // No summary exists yet
        expect(DailyAttendanceSummary::count())->toBe(0);

        // Create first attendance record (check-in)
        $record = AttendanceRecord::factory()->create([
            'employee_id' => $this->employee->id,
            'recorded_at' => $this->date->copy()->setTimeFromTimeString('09:00:00'),
            'direction' => 'check-in',
        ]);

        // Update summary from event
        $summary = $this->calculator->updateSummaryFromEvent($record);

        // Summary should be created
        expect(DailyAttendanceSummary::count())->toBe(1)
            ->and($summary->employee_id)->toBe($this->employee->id)
            ->and($summary->date->toDateString())->toBe($this->date->toDateString())
            ->and($summary->first_check_in)->toBe('09:00:00')
            ->and($summary->total_work_minutes)->toBe(0) // Still checked in
            ->and($summary->is_complete)->toBeFalse();
    });

    test('updates existing summary on subsequent attendance events', function () {
        // Create check-in record
        $checkIn = AttendanceRecord::factory()->create([
            'employee_id' => $this->employee->id,
            'recorded_at' => $this->date->copy()->setTimeFromTimeString('09:00:00'),
            'direction' => 'check-in',
        ]);

        // Create initial summary
        $initialSummary = $this->calculator->updateSummaryFromEvent($checkIn);
        $initialId = $initialSummary->id;

        expect($initialSummary->total_work_minutes)->toBe(0)
            ->and($initialSummary->is_complete)->toBeFalse();

        // Create check-out record
        $checkOut = AttendanceRecord::factory()->create([
            'employee_id' => $this->employee->id,
            'recorded_at' => $this->date->copy()->setTimeFromTimeString('17:00:00'),
            'direction' => 'check-out',
        ]);

        // Update summary
        $updatedSummary = $this->calculator->updateSummaryFromEvent($checkOut);

        // Should update the same record, not create a new one
        expect(DailyAttendanceSummary::count())->toBe(1)
            ->and($updatedSummary->id)->toBe($initialId)
            ->and($updatedSummary->total_work_minutes)->toBe(480) // 8 hours
            ->and($updatedSummary->total_work_hours)->toBe(8.0)
            ->and($updatedSummary->last_check_out)->toBe('17:00:00')
            ->and($updatedSummary->is_complete)->toBeTrue();
    });

    test('updates summary with break time calculation', function () {
        // Check-in
        $checkIn = AttendanceRecord::factory()->create([
            'employee_id' => $this->employee->id,
            'recorded_at' => $this->date->copy()->setTimeFromTimeString('09:00:00'),
            'direction' => 'check-in',
        ]);
        $this->calculator->updateSummaryFromEvent($checkIn);

        // Break start
        $breakStart = AttendanceRecord::factory()->create([
            'employee_id' => $this->employee->id,
            'recorded_at' => $this->date->copy()->setTimeFromTimeString('12:00:00'),
            'direction' => 'break-start',
        ]);
        $this->calculator->updateSummaryFromEvent($breakStart);

        // Break end
        $breakEnd = AttendanceRecord::factory()->create([
            'employee_id' => $this->employee->id,
            'recorded_at' => $this->date->copy()->setTimeFromTimeString('13:00:00'),
            'direction' => 'break-end',
        ]);
        $this->calculator->updateSummaryFromEvent($breakEnd);

        // Check-out
        $checkOut = AttendanceRecord::factory()->create([
            'employee_id' => $this->employee->id,
            'recorded_at' => $this->date->copy()->setTimeFromTimeString('17:00:00'),
            'direction' => 'check-out',
        ]);
        $summary = $this->calculator->updateSummaryFromEvent($checkOut);

        // Verify calculations
        expect($summary->total_work_minutes)->toBe(420) // 7 hours (9-12 + 13-17)
            ->and($summary->total_work_hours)->toBe(7.0)
            ->and($summary->total_break_minutes)->toBe(60) // 1 hour
            ->and($summary->total_break_hours)->toBe(1.0)
            ->and($summary->is_complete)->toBeTrue();
    });

    test('handles multiple updates throughout the day', function () {
        $records = [
            ['time' => '09:00:00', 'direction' => 'check-in'],
            ['time' => '10:30:00', 'direction' => 'break-start'],
            ['time' => '10:45:00', 'direction' => 'break-end'],
            ['time' => '12:00:00', 'direction' => 'break-start'],
            ['time' => '13:00:00', 'direction' => 'break-end'],
            ['time' => '17:00:00', 'direction' => 'check-out'],
        ];

        $summary = null;

        foreach ($records as $recordData) {
            $record = AttendanceRecord::factory()->create([
                'employee_id' => $this->employee->id,
                'recorded_at' => $this->date->copy()->setTimeFromTimeString($recordData['time']),
                'direction' => $recordData['direction'],
            ]);

            $summary = $this->calculator->updateSummaryFromEvent($record);
        }

        // Should only have one summary record
        expect(DailyAttendanceSummary::count())->toBe(1);

        // Verify final calculations
        // Work: 9:00-10:30 (1.5h) + 10:45-12:00 (1.25h) + 13:00-17:00 (4h) = 6.75h = 405 min
        expect($summary->total_work_minutes)->toBe(405)
            ->and($summary->total_work_hours)->toBe(6.75)
            ->and($summary->total_break_minutes)->toBe(75) // 15 + 60 minutes
            ->and($summary->total_break_hours)->toBe(1.25)
            ->and($summary->first_check_in)->toBe('09:00:00')
            ->and($summary->last_check_out)->toBe('17:00:00')
            ->and($summary->is_complete)->toBeTrue();
    });

    test('marks summary as incomplete when employee is still checked in', function () {
        $checkIn = AttendanceRecord::factory()->create([
            'employee_id' => $this->employee->id,
            'recorded_at' => $this->date->copy()->setTimeFromTimeString('09:00:00'),
            'direction' => 'check-in',
        ]);

        $summary = $this->calculator->updateSummaryFromEvent($checkIn);

        expect($summary->is_complete)->toBeFalse()
            ->and($summary->total_work_minutes)->toBe(0)
            ->and($summary->first_check_in)->toBe('09:00:00')
            ->and($summary->last_check_out)->toBeNull();
    });

    test('marks summary as complete after check-out', function () {
        // Check-in
        $checkIn = AttendanceRecord::factory()->create([
            'employee_id' => $this->employee->id,
            'recorded_at' => $this->date->copy()->setTimeFromTimeString('09:00:00'),
            'direction' => 'check-in',
        ]);
        $incompleteSummary = $this->calculator->updateSummaryFromEvent($checkIn);

        expect($incompleteSummary->is_complete)->toBeFalse();

        // Check-out
        $checkOut = AttendanceRecord::factory()->create([
            'employee_id' => $this->employee->id,
            'recorded_at' => $this->date->copy()->setTimeFromTimeString('17:00:00'),
            'direction' => 'check-out',
        ]);
        $completeSummary = $this->calculator->updateSummaryFromEvent($checkOut);

        expect($completeSummary->is_complete)->toBeTrue()
            ->and($completeSummary->total_work_minutes)->toBe(480);
    });

    test('updates status based on work hours', function () {
        // Work only 3 hours (half-day)
        $checkIn = AttendanceRecord::factory()->create([
            'employee_id' => $this->employee->id,
            'recorded_at' => $this->date->copy()->setTimeFromTimeString('09:00:00'),
            'direction' => 'check-in',
        ]);
        $this->calculator->updateSummaryFromEvent($checkIn);

        $checkOut = AttendanceRecord::factory()->create([
            'employee_id' => $this->employee->id,
            'recorded_at' => $this->date->copy()->setTimeFromTimeString('12:00:00'),
            'direction' => 'check-out',
        ]);
        $summary = $this->calculator->updateSummaryFromEvent($checkOut);

        expect($summary->status)->toBe('half-day')
            ->and($summary->total_work_minutes)->toBe(180);
    });

    test('handles overnight shift updates', function () {
        // Check-in at 23:00
        $checkIn = AttendanceRecord::factory()->create([
            'employee_id' => $this->employee->id,
            'recorded_at' => $this->date->copy()->setTimeFromTimeString('23:00:00'),
            'direction' => 'check-in',
        ]);
        $this->calculator->updateSummaryFromEvent($checkIn);

        // Check-out at 06:00 next day
        $checkOut = AttendanceRecord::factory()->create([
            'employee_id' => $this->employee->id,
            'recorded_at' => $this->date->copy()->addDay()->setTimeFromTimeString('06:00:00'),
            'direction' => 'check-out',
        ]);
        $summary = $this->calculator->updateSummaryFromEvent($checkOut);

        // Summary should be for the start date (Oct 6) and include the next day's check-out
        expect($summary->date->toDateString())->toBe($this->date->toDateString())
            ->and($summary->total_work_minutes)->toBe(420) // 7 hours
            ->and($summary->total_work_hours)->toBe(7.0)
            ->and($summary->first_check_in)->toBe('23:00:00')
            ->and($summary->last_check_out)->toBe('06:00:00')
            ->and($summary->is_complete)->toBeTrue();
    });

    test('different employees have separate summaries', function () {
        $employee1 = $this->employee;
        $employee2 = Employee::factory()->create();

        // Employee 1 check-in
        $record1 = AttendanceRecord::factory()->create([
            'employee_id' => $employee1->id,
            'recorded_at' => $this->date->copy()->setTimeFromTimeString('09:00:00'),
            'direction' => 'check-in',
        ]);
        $summary1 = $this->calculator->updateSummaryFromEvent($record1);

        // Employee 2 check-in
        $record2 = AttendanceRecord::factory()->create([
            'employee_id' => $employee2->id,
            'recorded_at' => $this->date->copy()->setTimeFromTimeString('10:00:00'),
            'direction' => 'check-in',
        ]);
        $summary2 = $this->calculator->updateSummaryFromEvent($record2);

        // Should have 2 separate summaries
        expect(DailyAttendanceSummary::count())->toBe(2)
            ->and($summary1->employee_id)->toBe($employee1->id)
            ->and($summary2->employee_id)->toBe($employee2->id)
            ->and($summary1->id)->not->toBe($summary2->id);
    });
});
