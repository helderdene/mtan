<?php

use App\Domain\Shift\Services\RotationScheduler;
use App\Models\EmployeeShiftRotation;
use App\Models\ShiftRotationPattern;
use App\Models\Tenant\Employee;
use App\Models\Tenant\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('rotation pattern can be created with sequence', function () {
    $shift1 = Shift::factory()->create();
    $shift2 = Shift::factory()->create();
    $shift3 = Shift::factory()->create();

    $pattern = ShiftRotationPattern::create([
        'name' => 'Morning-Afternoon-Night Rotation',
        'cycle_type' => 'weekly',
        'rotation_sequence' => [$shift1->id, $shift2->id, $shift3->id],
        'description' => 'Test rotation pattern',
    ]);

    expect($pattern->exists())->toBeTrue()
        ->and($pattern->rotation_length)->toBe(3)
        ->and($pattern->rotation_sequence)->toBe([$shift1->id, $shift2->id, $shift3->id]);
});

test('employee can be assigned to rotation pattern', function () {
    $pattern = ShiftRotationPattern::factory()->create();
    $employee = Employee::factory()->create();

    $rotation = EmployeeShiftRotation::create([
        'employee_id' => $employee->id,
        'rotation_pattern_id' => $pattern->id,
        'start_date' => now(),
        'current_position' => 0,
    ]);

    expect($rotation->exists())->toBeTrue()
        ->and($rotation->employee_id)->toBe($employee->id)
        ->and($rotation->rotationPattern->id)->toBe($pattern->id);
});

test('rotation can advance to next position', function () {
    $pattern = ShiftRotationPattern::factory()->create([
        'rotation_sequence' => [1, 2, 3],
    ]);

    $employee = Employee::factory()->create();

    $rotation = EmployeeShiftRotation::factory()->create([
        'employee_id' => $employee->id,
        'rotation_pattern_id' => $pattern->id,
        'current_position' => 0,
    ]);

    $rotation->advanceRotation();

    expect($rotation->current_position)->toBe(1);

    $rotation->advanceRotation();

    expect($rotation->current_position)->toBe(2);

    $rotation->advanceRotation();

    // Should wrap around
    expect($rotation->current_position)->toBe(0);
});

test('flexible shift can be created with check-in window', function () {
    $shift = Shift::factory()->flexible('08:00:00', '10:00:00', 8.0)->create();

    expect($shift->isFlexible())->toBeTrue()
        ->and($shift->flexible_checkin_start)->toBe('08:00:00')
        ->and($shift->flexible_checkin_end)->toBe('10:00:00')
        ->and((float) $shift->core_hours_required)->toBe(8.0);
});

test('shift model correctly identifies shift types', function () {
    $fixedShift = Shift::factory()->create(['shift_type' => 'fixed']);
    $flexibleShift = Shift::factory()->flexible()->create();
    $rotatingShift = Shift::factory()->rotating()->create();

    expect($fixedShift->isFixed())->toBeTrue()
        ->and($fixedShift->isFlexible())->toBeFalse()
        ->and($fixedShift->isRotating())->toBeFalse();

    expect($flexibleShift->isFlexible())->toBeTrue()
        ->and($flexibleShift->isFixed())->toBeFalse();

    expect($rotatingShift->isRotating())->toBeTrue()
        ->and($rotatingShift->isFixed())->toBeFalse();
});
