<?php

use App\Domain\Attendance\Services\DirectionDetector;
use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\Employee;
use App\Models\Tenant\Shift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Set up tenant database connection
    Config::set('database.connections.tenant', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);

    DB::purge('tenant');

    // Create employees table
    DB::connection('tenant')->getSchemaBuilder()->create('employees', function ($table) {
        $table->id();
        $table->string('first_name');
        $table->string('last_name');
        $table->string('custom_id')->unique();
        $table->timestamps();
    });

    // Create shifts table
    DB::connection('tenant')->getSchemaBuilder()->create('shifts', function ($table) {
        $table->id();
        $table->string('name');
        $table->time('start_time');
        $table->time('end_time');
        $table->time('break_start')->nullable();
        $table->time('break_end')->nullable();
        $table->integer('working_days');
        $table->timestamps();
    });

    // Create employee_shift pivot table
    DB::connection('tenant')->getSchemaBuilder()->create('employee_shift', function ($table) {
        $table->id();
        $table->foreignId('employee_id')->constrained('employees');
        $table->foreignId('shift_id')->constrained('shifts');
        $table->date('effective_from');
        $table->date('effective_to')->nullable();
        $table->timestamps();
    });

    // Create attendance_records table
    DB::connection('tenant')->getSchemaBuilder()->create('attendance_records', function ($table) {
        $table->id();
        $table->foreignId('employee_id')->constrained('employees');
        $table->foreignId('device_id');
        $table->dateTime('recorded_at');
        $table->string('direction');
        $table->decimal('recognition_score', 5, 4)->nullable();
        $table->integer('confidence_score')->nullable();
        $table->text('detection_reason')->nullable();
        $table->timestamps();
        $table->index(['employee_id', 'recorded_at']);
    });

    $this->detector = new DirectionDetector();
});

test('direction detection completes in under 50ms for typical scenario', function () {
    $employee = Employee::on('tenant')->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'custom_id' => 'EMP001',
    ]);

    $shift = Shift::on('tenant')->create([
        'name' => 'Morning Shift',
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'working_days' => 31,
    ]);

    $timestamp = Carbon::parse('2025-10-06 09:05:00');

    $start = microtime(true);
    $result = $this->detector->detect($employee, $timestamp, $shift);
    $duration = (microtime(true) - $start) * 1000; // Convert to milliseconds

    expect($result)->not->toBeNull()
        ->and($result->direction)->toBe('check-in')
        ->and($duration)->toBeLessThan(50);
});

test('detection with previous record completes in under 50ms', function () {
    $employee = Employee::on('tenant')->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'custom_id' => 'EMP001',
    ]);

    $shift = Shift::on('tenant')->create([
        'name' => 'Morning Shift',
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'working_days' => 31,
    ]);

    // Create previous check-in record
    AttendanceRecord::on('tenant')->create([
        'employee_id' => $employee->id,
        'device_id' => 1,
        'recorded_at' => Carbon::parse('2025-10-06 09:00:00'),
        'direction' => 'check-in',
        'recognition_score' => 0.95,
    ]);

    $timestamp = Carbon::parse('2025-10-06 17:00:00');

    $start = microtime(true);
    $result = $this->detector->detect($employee, $timestamp, $shift);
    $duration = (microtime(true) - $start) * 1000;

    expect($result)->not->toBeNull()
        ->and($result->direction)->toBe('check-out')
        ->and($duration)->toBeLessThan(50);
});

test('overnight shift detection completes in under 50ms', function () {
    $employee = Employee::on('tenant')->create([
        'first_name' => 'Night',
        'last_name' => 'Worker',
        'custom_id' => 'EMP002',
    ]);

    $shift = Shift::on('tenant')->create([
        'name' => 'Night Shift',
        'start_time' => '22:00:00',
        'end_time' => '06:00:00',
        'working_days' => 31,
    ]);

    $timestamp = Carbon::parse('2025-10-06 22:05:00');

    $start = microtime(true);
    $result = $this->detector->detect($employee, $timestamp, $shift);
    $duration = (microtime(true) - $start) * 1000;

    expect($result)->not->toBeNull()
        ->and($result->direction)->toBe('check-in')
        ->and($duration)->toBeLessThan(50);
});

test('detection without shift assignment completes in under 50ms', function () {
    $employee = Employee::on('tenant')->create([
        'first_name' => 'Flexible',
        'last_name' => 'Worker',
        'custom_id' => 'EMP003',
    ]);

    $timestamp = Carbon::parse('2025-10-06 09:00:00');

    $start = microtime(true);
    $result = $this->detector->detect($employee, $timestamp, null);
    $duration = (microtime(true) - $start) * 1000;

    expect($result)->not->toBeNull()
        ->and($result->direction)->toBe('check-in')
        ->and($duration)->toBeLessThan(50);
});

test('batch detection of 100 operations completes with acceptable average time', function () {
    $employee = Employee::on('tenant')->create([
        'first_name' => 'Batch',
        'last_name' => 'Test',
        'custom_id' => 'EMP004',
    ]);

    $shift = Shift::on('tenant')->create([
        'name' => 'Day Shift',
        'start_time' => '08:00:00',
        'end_time' => '16:00:00',
        'working_days' => 31,
    ]);

    $durations = [];

    for ($i = 0; $i < 100; $i++) {
        $timestamp = Carbon::parse('2025-10-06')->addMinutes($i * 5);

        $start = microtime(true);
        $this->detector->detect($employee, $timestamp, $shift);
        $durations[] = (microtime(true) - $start) * 1000;
    }

    $avgDuration = array_sum($durations) / count($durations);
    $maxDuration = max($durations);

    expect($avgDuration)->toBeLessThan(50)
        ->and($maxDuration)->toBeLessThan(100); // Allow some outliers up to 100ms
});

test('detection with multiple previous records maintains performance', function () {
    $employee = Employee::on('tenant')->create([
        'first_name' => 'Frequent',
        'last_name' => 'User',
        'custom_id' => 'EMP005',
    ]);

    $shift = Shift::on('tenant')->create([
        'name' => 'Standard Shift',
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'working_days' => 31,
    ]);

    // Create 30 days of attendance records
    for ($i = 1; $i <= 30; $i++) {
        $date = Carbon::now()->subDays($i);

        AttendanceRecord::on('tenant')->create([
            'employee_id' => $employee->id,
            'device_id' => 1,
            'recorded_at' => $date->copy()->setTime(9, 0, 0),
            'direction' => 'check-in',
            'recognition_score' => 0.95,
        ]);

        AttendanceRecord::on('tenant')->create([
            'employee_id' => $employee->id,
            'device_id' => 1,
            'recorded_at' => $date->copy()->setTime(17, 0, 0),
            'direction' => 'check-out',
            'recognition_score' => 0.95,
        ]);
    }

    $timestamp = Carbon::parse('2025-10-06 09:00:00');

    $start = microtime(true);
    $result = $this->detector->detect($employee, $timestamp, $shift);
    $duration = (microtime(true) - $start) * 1000;

    expect($result)->not->toBeNull()
        ->and($duration)->toBeLessThan(50); // Should still be fast due to LIMIT 1 in query
});

test('concurrent detection operations maintain individual performance', function () {
    $employees = [];
    $shift = Shift::on('tenant')->create([
        'name' => 'Shared Shift',
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'working_days' => 31,
    ]);

    // Create 10 employees
    for ($i = 1; $i <= 10; $i++) {
        $employees[] = Employee::on('tenant')->create([
            'first_name' => "Employee{$i}",
            'last_name' => 'Test',
            'custom_id' => "EMP00{$i}",
        ]);
    }

    $timestamp = Carbon::parse('2025-10-06 09:05:00');
    $durations = [];

    // Simulate concurrent detection for all employees
    foreach ($employees as $employee) {
        $start = microtime(true);
        $this->detector->detect($employee, $timestamp, $shift);
        $durations[] = (microtime(true) - $start) * 1000;
    }

    $avgDuration = array_sum($durations) / count($durations);

    expect($avgDuration)->toBeLessThan(50)
        ->and(count($durations))->toBe(10);
});
