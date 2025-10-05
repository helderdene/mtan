<?php

use App\Domain\Shift\DTOs\EffectiveShift;
use App\Domain\Shift\Models\Employee;
use App\Domain\Shift\Models\Shift;
use App\Domain\Shift\Models\ShiftOverride;
use App\Domain\Shift\Services\OverrideService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clear cache before each test
    \Illuminate\Support\Facades\Cache::flush();

    $this->service = new OverrideService();
    $this->shift = Shift::factory()->create([
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
    ]);
    $this->employee = Employee::factory()->create();
    $this->date = Carbon::parse('2025-10-10');
});

test('getActiveOverride returns null when no override exists', function () {
    $result = $this->service->getActiveOverride($this->date, $this->shift, $this->employee);

    expect($result)->toBeNull();
});

test('getActiveOverride prioritizes employee-specific shift override', function () {
    // Create company-wide override
    ShiftOverride::factory()->create([
        'shift_id' => $this->shift->id,
        'employee_id' => null,
        'override_date' => $this->date,
        'type' => 'holiday',
        'reason' => 'Company Holiday',
    ]);

    // Create employee-specific override (higher priority)
    $employeeOverride = ShiftOverride::factory()->create([
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
        'override_date' => $this->date,
        'type' => 'half-day',
        'custom_start_time' => '09:00:00',
        'custom_end_time' => '13:00:00',
    ]);

    $result = $this->service->getActiveOverride($this->date, $this->shift, $this->employee);

    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($employeeOverride->id)
        ->and($result->type)->toBe('half-day');
});

test('getActiveOverride prioritizes employee off-day over company-wide shift override', function () {
    // Create company-wide shift override
    ShiftOverride::factory()->create([
        'shift_id' => $this->shift->id,
        'employee_id' => null,
        'override_date' => $this->date,
        'type' => 'holiday',
    ]);

    // Create employee-specific off-day (no shift)
    $offDay = ShiftOverride::factory()->create([
        'shift_id' => null,
        'employee_id' => $this->employee->id,
        'override_date' => $this->date,
        'type' => 'off-day',
    ]);

    $result = $this->service->getActiveOverride($this->date, $this->shift, $this->employee);

    expect($result->id)->toBe($offDay->id)
        ->and($result->type)->toBe('off-day');
});

test('getActiveOverride returns company-wide override when no employee-specific override exists', function () {
    $companyOverride = ShiftOverride::factory()->create([
        'shift_id' => $this->shift->id,
        'employee_id' => null,
        'override_date' => $this->date,
        'type' => 'holiday',
    ]);

    $result = $this->service->getActiveOverride($this->date, $this->shift, $this->employee);

    expect($result->id)->toBe($companyOverride->id);
});

test('isWorkRequired returns false for holiday override', function () {
    ShiftOverride::factory()->create([
        'shift_id' => $this->shift->id,
        'employee_id' => null,
        'override_date' => $this->date,
        'type' => 'holiday',
    ]);

    $result = $this->service->isWorkRequired($this->date, $this->employee);

    expect($result)->toBeFalse();
});

test('isWorkRequired returns false for off-day override', function () {
    ShiftOverride::factory()->create([
        'shift_id' => null,
        'employee_id' => $this->employee->id,
        'override_date' => $this->date,
        'type' => 'off-day',
    ]);

    $result = $this->service->isWorkRequired($this->date, $this->employee);

    expect($result)->toBeFalse();
});

test('isWorkRequired returns true for half-day override', function () {
    ShiftOverride::factory()->create([
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
        'override_date' => $this->date,
        'type' => 'half-day',
        'custom_start_time' => '09:00:00',
        'custom_end_time' => '13:00:00',
    ]);

    $result = $this->service->isWorkRequired($this->date, $this->employee);

    expect($result)->toBeTrue();
});

test('isWorkRequired returns true when no override exists', function () {
    $result = $this->service->isWorkRequired($this->date, $this->employee);

    expect($result)->toBeTrue();
});

test('getEffectiveShiftTimes returns null for holiday', function () {
    ShiftOverride::factory()->create([
        'shift_id' => $this->shift->id,
        'employee_id' => null,
        'override_date' => $this->date,
        'type' => 'holiday',
    ]);

    $result = $this->service->getEffectiveShiftTimes($this->date, $this->shift, $this->employee);

    expect($result)->toBeNull();
});

test('getEffectiveShiftTimes returns null for off-day', function () {
    ShiftOverride::factory()->create([
        'shift_id' => null,
        'employee_id' => $this->employee->id,
        'override_date' => $this->date,
        'type' => 'off-day',
    ]);

    $result = $this->service->getEffectiveShiftTimes($this->date, $this->shift, $this->employee);

    expect($result)->toBeNull();
});

test('getEffectiveShiftTimes returns modified times for half-day', function () {
    ShiftOverride::factory()->create([
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
        'override_date' => $this->date,
        'type' => 'half-day',
        'custom_start_time' => '09:00:00',
        'custom_end_time' => '13:00:00',
    ]);

    $result = $this->service->getEffectiveShiftTimes($this->date, $this->shift, $this->employee);

    expect($result)->toBeInstanceOf(EffectiveShift::class)
        ->and($result->isModified)->toBeTrue()
        ->and($result->startTime->format('H:i'))->toBe('09:00')
        ->and($result->endTime->format('H:i'))->toBe('13:00')
        ->and($result->getDurationMinutes())->toBe(240);
});

test('getEffectiveShiftTimes returns regular times when no override', function () {
    $result = $this->service->getEffectiveShiftTimes($this->date, $this->shift, $this->employee);

    expect($result)->toBeInstanceOf(EffectiveShift::class)
        ->and($result->isModified)->toBeFalse()
        ->and($result->startTime->format('H:i'))->toBe('09:00')
        ->and($result->endTime->format('H:i'))->toBe('17:00')
        ->and($result->getDurationMinutes())->toBe(480);
});

test('getEffectiveShiftTimes returns modified times for custom-shift', function () {
    ShiftOverride::factory()->create([
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
        'override_date' => $this->date,
        'type' => 'custom-shift',
        'custom_start_time' => '10:00:00',
        'custom_end_time' => '18:00:00',
    ]);

    $result = $this->service->getEffectiveShiftTimes($this->date, $this->shift, $this->employee);

    expect($result)->toBeInstanceOf(EffectiveShift::class)
        ->and($result->isModified)->toBeTrue()
        ->and($result->startTime->format('H:i'))->toBe('10:00')
        ->and($result->endTime->format('H:i'))->toBe('18:00');
});

test('EffectiveShift handles overnight custom shifts correctly', function () {
    ShiftOverride::factory()->create([
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
        'override_date' => $this->date,
        'type' => 'custom-shift',
        'custom_start_time' => '22:00:00',
        'custom_end_time' => '06:00:00',
    ]);

    $result = $this->service->getEffectiveShiftTimes($this->date, $this->shift, $this->employee);

    expect($result)->toBeInstanceOf(EffectiveShift::class)
        ->and($result->startTime->format('Y-m-d H:i'))->toBe('2025-10-10 22:00')
        ->and($result->endTime->format('Y-m-d H:i'))->toBe('2025-10-11 06:00');
});
