<?php

use App\Domain\Attendance\Models\DailyAttendanceSummary;
use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\Employee;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('RecalculateAttendanceSummariesCommand', function () {
    test('requires both from and to dates', function () {
        $this->artisan('attendance:recalculate-summaries')
            ->expectsOutput('Both --from and --to dates are required.')
            ->assertFailed();

        $this->artisan('attendance:recalculate-summaries --from=2025-10-01')
            ->expectsOutput('Both --from and --to dates are required.')
            ->assertFailed();

        $this->artisan('attendance:recalculate-summaries --to=2025-10-31')
            ->expectsOutput('Both --from and --to dates are required.')
            ->assertFailed();
    });

    test('validates date format', function () {
        $this->artisan('attendance:recalculate-summaries --from=invalid --to=2025-10-31')
            ->expectsOutput('Invalid date format. Please use YYYY-MM-DD format.')
            ->assertFailed();

        $this->artisan('attendance:recalculate-summaries --from=2025-10-01 --to=invalid')
            ->expectsOutput('Invalid date format. Please use YYYY-MM-DD format.')
            ->assertFailed();
    });

    test('validates date range order', function () {
        $this->artisan('attendance:recalculate-summaries --from=2025-10-31 --to=2025-10-01')
            ->expectsOutput('Start date must be before or equal to end date.')
            ->assertFailed();
    });

    test('fails when employee not found', function () {
        $this->artisan('attendance:recalculate-summaries --employee=999999 --from=2025-10-01 --to=2025-10-31')
            ->expectsOutput('Employee with ID 999999 not found.')
            ->assertFailed();
    });

    test('recalculates summaries for single employee', function () {
        $employee = Employee::factory()->create(['name' => 'John Doe']);

        // Create attendance records for 5 days
        for ($i = 0; $i < 5; $i++) {
            $date = Carbon::parse('2025-10-01')->addDays($i);

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

        $this->artisan("attendance:recalculate-summaries --employee={$employee->id} --from=2025-10-01 --to=2025-10-05")
            ->expectsOutputToContain('Recalculating attendance summaries...')
            ->expectsOutputToContain('Date range: 2025-10-01 to 2025-10-05')
            ->expectsOutputToContain('Employees: 1')
            ->expectsOutputToContain('✓ Recalculation complete!')
            ->expectsOutputToContain('Total summaries recalculated: 5')
            ->assertSuccessful();

        // Verify summaries were created
        expect(DailyAttendanceSummary::count())->toBe(5);

        $summaries = DailyAttendanceSummary::where('employee_id', $employee->id)->get();
        expect($summaries)->toHaveCount(5);

        foreach ($summaries as $summary) {
            expect($summary->total_work_minutes)->toBe(480)
                ->and($summary->status)->toBe('present');
        }
    });

    test('recalculates summaries for all active employees', function () {
        $employee1 = Employee::factory()->create(['name' => 'Alice Smith', 'is_active' => true]);
        $employee2 = Employee::factory()->create(['name' => 'Bob Jones', 'is_active' => true]);
        $employee3 = Employee::factory()->create(['name' => 'Charlie Brown', 'is_active' => false]);

        // Create attendance for active employees only
        foreach ([$employee1, $employee2] as $employee) {
            for ($i = 0; $i < 3; $i++) {
                $date = Carbon::parse('2025-10-01')->addDays($i);

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
        }

        $this->artisan('attendance:recalculate-summaries --from=2025-10-01 --to=2025-10-03')
            ->expectsOutputToContain('Recalculating attendance summaries...')
            ->expectsOutputToContain('Date range: 2025-10-01 to 2025-10-03')
            ->expectsOutputToContain('Employees: 2')
            ->expectsOutputToContain('✓ Recalculation complete!')
            ->expectsOutputToContain('Total summaries recalculated: 6')
            ->assertSuccessful();

        // Verify summaries for both active employees
        expect(DailyAttendanceSummary::where('employee_id', $employee1->id)->count())->toBe(3)
            ->and(DailyAttendanceSummary::where('employee_id', $employee2->id)->count())->toBe(3)
            ->and(DailyAttendanceSummary::where('employee_id', $employee3->id)->count())->toBe(0);
    });

    test('handles date range with single day', function () {
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

        $this->artisan("attendance:recalculate-summaries --employee={$employee->id} --from=2025-10-01 --to=2025-10-01")
            ->expectsOutput('Total summaries recalculated: 1')
            ->assertSuccessful();

        expect(DailyAttendanceSummary::count())->toBe(1);
    });

    test('handles month boundary crossing', function () {
        $employee = Employee::factory()->create();

        // Create records across September-October boundary
        for ($i = 0; $i < 4; $i++) {
            $date = Carbon::parse('2025-09-29')->addDays($i);

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

        $this->artisan("attendance:recalculate-summaries --employee={$employee->id} --from=2025-09-29 --to=2025-10-02")
            ->expectsOutputToContain('Date range: 2025-09-29 to 2025-10-02')
            ->expectsOutputToContain('Total summaries recalculated: 4')
            ->assertSuccessful();

        $summaries = DailyAttendanceSummary::orderBy('date')->get();
        expect($summaries[0]->date->month)->toBe(9)
            ->and($summaries[3]->date->month)->toBe(10);
    });

    test('creates summaries even for days without attendance', function () {
        $employee = Employee::factory()->create();

        // No attendance records created

        $this->artisan("attendance:recalculate-summaries --employee={$employee->id} --from=2025-10-01 --to=2025-10-03")
            ->expectsOutput('Total summaries recalculated: 3')
            ->assertSuccessful();

        // Should create 3 summaries with absent status
        expect(DailyAttendanceSummary::count())->toBe(3);

        $summaries = DailyAttendanceSummary::all();
        foreach ($summaries as $summary) {
            expect($summary->status)->toBe('absent')
                ->and($summary->total_work_minutes)->toBe(0);
        }
    });

    test('updates existing summaries on recalculation', function () {
        $employee = Employee::factory()->create();
        $date = Carbon::parse('2025-10-01');

        // Create incorrect initial summary
        DailyAttendanceSummary::factory()->create([
            'employee_id' => $employee->id,
            'date' => $date,
            'total_work_minutes' => 100,
            'status' => 'half-day',
        ]);

        // Create correct attendance records
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

        $this->artisan("attendance:recalculate-summaries --employee={$employee->id} --from=2025-10-01 --to=2025-10-01")
            ->assertSuccessful();

        // Should still have only 1 summary (updated, not duplicated)
        expect(DailyAttendanceSummary::count())->toBe(1);

        $summary = DailyAttendanceSummary::first();
        expect($summary->total_work_minutes)->toBe(480)
            ->and($summary->status)->toBe('present');
    });

    test('displays progress and statistics', function () {
        $employee = Employee::factory()->create(['name' => 'Progress Test Employee']);

        // Create attendance for 3 days
        for ($i = 0; $i < 3; $i++) {
            $date = Carbon::parse('2025-10-01')->addDays($i);

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

        $this->artisan("attendance:recalculate-summaries --employee={$employee->id} --from=2025-10-01 --to=2025-10-03")
            ->expectsOutputToContain('Progress Test Employee')
            ->expectsOutputToContain('Total summaries recalculated: 3')
            ->expectsOutputToContain('Date range: 2025-10-01 to 2025-10-03')
            ->expectsOutputToContain('✓ Recalculation complete!')
            ->assertSuccessful();
    });
});
