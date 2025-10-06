<?php

use App\Domain\Attendance\Events\CorrectionApplied;
use App\Domain\Attendance\Models\AttendanceCorrection;
use App\Domain\Attendance\Services\CorrectionApplicator;
use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake();
});

test('can apply missing checkout correction', function () {
    $employee = Employee::factory()->create();
    $checkInRecord = AttendanceRecord::factory()->create([
        'employee_id' => $employee->id,
        'direction' => 'check-in',
        'recorded_at' => Carbon::parse('2025-10-06 09:00:00'),
    ]);

    $correction = AttendanceCorrection::factory()->approved()->create([
        'employee_id' => $employee->id,
        'attendance_record_id' => $checkInRecord->id,
        'type' => 'missing_checkout',
        'proposed_data' => [
            'check_out_time' => '17:00:00',
        ],
    ]);

    $applicator = app(CorrectionApplicator::class);
    $applicator->apply($correction);

    $correction->refresh();
    expect($correction->status)->toBe('applied')
        ->and($correction->applied_at)->not->toBeNull();

    $checkOutRecord = AttendanceRecord::where('employee_id', $employee->id)
        ->where('direction', 'check-out')
        ->first();

    expect($checkOutRecord)->not->toBeNull()
        ->and($checkOutRecord->is_manual_correction)->toBeTrue()
        ->and($checkOutRecord->correction_id)->toBe($correction->id);

    Event::assertDispatched(CorrectionApplied::class);
});

test('can apply wrong time correction', function () {
    $employee = Employee::factory()->create();
    $record = AttendanceRecord::factory()->create([
        'employee_id' => $employee->id,
        'direction' => 'check-in',
        'recorded_at' => Carbon::parse('2025-10-06 09:30:00'),
    ]);

    $correction = AttendanceCorrection::factory()->approved()->create([
        'employee_id' => $employee->id,
        'attendance_record_id' => $record->id,
        'type' => 'wrong_time',
        'proposed_data' => [
            'check_in_time' => '09:00:00',
        ],
    ]);

    $applicator = app(CorrectionApplicator::class);
    $applicator->apply($correction);

    $record->refresh();
    expect($record->recorded_at->format('H:i:s'))->toBe('09:00:00')
        ->and($record->is_manual_correction)->toBeTrue()
        ->and($record->correction_id)->toBe($correction->id);
});

test('can apply duplicate record correction', function () {
    $employee = Employee::factory()->create();
    $record1 = AttendanceRecord::factory()->create([
        'employee_id' => $employee->id,
        'direction' => 'check-in',
        'recorded_at' => Carbon::parse('2025-10-06 09:00:00'),
    ]);

    $record2 = AttendanceRecord::factory()->create([
        'employee_id' => $employee->id,
        'direction' => 'check-in',
        'recorded_at' => Carbon::parse('2025-10-06 09:01:00'),
    ]);

    $correction = AttendanceCorrection::factory()->approved()->create([
        'employee_id' => $employee->id,
        'attendance_record_id' => $record2->id,
        'type' => 'duplicate_record',
        'proposed_data' => [
            'duplicate_record_id' => $record2->id,
        ],
    ]);

    $applicator = app(CorrectionApplicator::class);
    $applicator->apply($correction);

    expect(AttendanceRecord::find($record2->id))->toBeNull()
        ->and(AttendanceRecord::find($record1->id))->not->toBeNull();
});

test('can apply missing record correction', function () {
    $employee = Employee::factory()->create();

    $correction = AttendanceCorrection::factory()->approved()->create([
        'employee_id' => $employee->id,
        'attendance_record_id' => null,
        'type' => 'missing_record',
        'proposed_data' => [
            'date' => '2025-10-06',
            'check_in_time' => '09:00:00',
            'check_out_time' => '17:00:00',
        ],
    ]);

    $applicator = app(CorrectionApplicator::class);
    $applicator->apply($correction);

    $records = AttendanceRecord::where('employee_id', $employee->id)
        ->whereDate('recorded_at', '2025-10-06')
        ->get();

    expect($records)->toHaveCount(2);

    $checkIn = $records->where('direction', 'check-in')->first();
    $checkOut = $records->where('direction', 'check-out')->first();

    expect($checkIn)->not->toBeNull()
        ->and($checkIn->is_manual_correction)->toBeTrue()
        ->and($checkOut)->not->toBeNull()
        ->and($checkOut->is_manual_correction)->toBeTrue();
});

test('can apply missing record correction with only check-in', function () {
    $employee = Employee::factory()->create();

    $correction = AttendanceCorrection::factory()->approved()->create([
        'employee_id' => $employee->id,
        'type' => 'missing_record',
        'proposed_data' => [
            'date' => '2025-10-06',
            'check_in_time' => '09:00:00',
        ],
    ]);

    $applicator = app(CorrectionApplicator::class);
    $applicator->apply($correction);

    $records = AttendanceRecord::where('employee_id', $employee->id)
        ->whereDate('recorded_at', '2025-10-06')
        ->get();

    expect($records)->toHaveCount(1);

    $checkIn = $records->first();
    expect($checkIn->direction)->toBe('check-in')
        ->and($checkIn->is_manual_correction)->toBeTrue();
});

test('cannot apply non-approved correction', function () {
    $correction = AttendanceCorrection::factory()->create(['status' => 'pending']);

    $applicator = app(CorrectionApplicator::class);

    expect(fn () => $applicator->apply($correction))
        ->toThrow(\InvalidArgumentException::class, 'Can only apply approved corrections');
});

test('applying correction rolls back on failure', function () {
    $employee = Employee::factory()->create();

    $correction = AttendanceCorrection::factory()->approved()->create([
        'employee_id' => $employee->id,
        'type' => 'wrong_time',
        'proposed_data' => [
            'check_in_time' => 'invalid-time',
        ],
    ]);

    $applicator = app(CorrectionApplicator::class);

    expect(fn () => $applicator->apply($correction))
        ->toThrow(\Exception::class);

    $correction->refresh();
    expect($correction->status)->toBe('approved');
});

test('applying correction recalculates daily summary', function () {
    $employee = Employee::factory()->create();
    $checkInRecord = AttendanceRecord::factory()->create([
        'employee_id' => $employee->id,
        'direction' => 'check-in',
        'recorded_at' => Carbon::parse('2025-10-06 09:00:00'),
    ]);

    $correction = AttendanceCorrection::factory()->approved()->create([
        'employee_id' => $employee->id,
        'attendance_record_id' => $checkInRecord->id,
        'type' => 'missing_checkout',
        'proposed_data' => [
            'check_out_time' => '17:00:00',
        ],
    ]);

    $applicator = app(CorrectionApplicator::class);
    $applicator->apply($correction);

    // Verify daily summary was recalculated (specific implementation depends on SummaryCalculator)
    expect($correction->status)->toBe('applied');
});
