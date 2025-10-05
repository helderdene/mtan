<?php

use App\Domain\Attendance\Services\DirectionDetector;
use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\Employee;
use App\Models\Tenant\Shift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

test('benchmark 1000 detection operations with average under 50ms', function () {
    // Create 100 employees with shifts
    $employees = [];
    $shift = Shift::on('tenant')->create([
        'name' => 'Standard Shift',
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'break_start' => '12:00:00',
        'break_end' => '13:00:00',
        'working_days' => 31,
    ]);

    for ($i = 1; $i <= 100; $i++) {
        $employees[] = Employee::on('tenant')->create([
            'first_name' => "Employee{$i}",
            'last_name' => 'Benchmark',
            'custom_id' => sprintf('EMP%03d', $i),
        ]);
    }

    // Run 1000 detection operations
    $durations = [];
    $operations = 0;

    for ($iteration = 0; $iteration < 10; $iteration++) {
        foreach ($employees as $employee) {
            $timestamp = Carbon::parse('2025-10-06')->addMinutes($iteration * 60);

            $start = microtime(true);
            $result = $this->detector->detect($employee, $timestamp, $shift);
            $duration = (microtime(true) - $start) * 1000;

            $durations[] = $duration;
            $operations++;

            expect($result)->not->toBeNull();
        }
    }

    // Calculate statistics
    $avgDuration = array_sum($durations) / count($durations);
    $minDuration = min($durations);
    $maxDuration = max($durations);
    $p95Duration = $durations[intval(count($durations) * 0.95)];

    // Report results
    echo "\n";
    echo "Benchmark Results:\n";
    echo "==================\n";
    echo "Total operations: {$operations}\n";
    echo "Average time: " . number_format($avgDuration, 2) . "ms\n";
    echo "Min time: " . number_format($minDuration, 2) . "ms\n";
    echo "Max time: " . number_format($maxDuration, 2) . "ms\n";
    echo "P95 time: " . number_format($p95Duration, 2) . "ms\n";
    echo "\n";

    // Assertions
    expect($operations)->toBe(1000)
        ->and($avgDuration)->toBeLessThan(50)
        ->and($p95Duration)->toBeLessThan(100); // Allow p95 up to 100ms
});

test('benchmark with historical data shows caching benefits', function () {
    $employee = Employee::on('tenant')->create([
        'first_name' => 'Cache',
        'last_name' => 'Test',
        'custom_id' => 'CACHE001',
    ]);

    $shift = Shift::on('tenant')->create([
        'name' => 'Day Shift',
        'start_time' => '08:00:00',
        'end_time' => '16:00:00',
        'working_days' => 31,
    ]);

    // Create 30 days of historical records
    for ($i = 1; $i <= 30; $i++) {
        $date = Carbon::now()->subDays($i);

        AttendanceRecord::on('tenant')->create([
            'employee_id' => $employee->id,
            'device_id' => 1,
            'recorded_at' => $date->copy()->setTime(8, 0, 0),
            'direction' => 'check-in',
            'recognition_score' => 0.95,
        ]);

        AttendanceRecord::on('tenant')->create([
            'employee_id' => $employee->id,
            'device_id' => 1,
            'recorded_at' => $date->copy()->setTime(16, 0, 0),
            'direction' => 'check-out',
            'recognition_score' => 0.95,
        ]);
    }

    // Benchmark with historical data
    $durations = [];

    for ($i = 0; $i < 100; $i++) {
        $timestamp = Carbon::parse('2025-10-06')->addMinutes($i * 5);

        $start = microtime(true);
        $this->detector->detect($employee, $timestamp, $shift);
        $durations[] = (microtime(true) - $start) * 1000;
    }

    $avgDuration = array_sum($durations) / count($durations);

    echo "\n";
    echo "Historical Data Benchmark:\n";
    echo "==========================\n";
    echo "Records in database: 60\n";
    echo "Average detection time: " . number_format($avgDuration, 2) . "ms\n";
    echo "\n";

    // With in-memory caching, should still be fast
    expect($avgDuration)->toBeLessThan(50);
});

test('benchmark different shift types maintains consistent performance', function () {
    $shiftTypes = [
        'Morning' => ['start' => '06:00:00', 'end' => '14:00:00'],
        'Day' => ['start' => '09:00:00', 'end' => '17:00:00'],
        'Evening' => ['start' => '14:00:00', 'end' => '22:00:00'],
        'Night' => ['start' => '22:00:00', 'end' => '06:00:00'], // Overnight
    ];

    $results = [];

    foreach ($shiftTypes as $name => $times) {
        $employee = Employee::on('tenant')->create([
            'first_name' => $name,
            'last_name' => 'Worker',
            'custom_id' => strtoupper($name) . '001',
        ]);

        $shift = Shift::on('tenant')->create([
            'name' => "{$name} Shift",
            'start_time' => $times['start'],
            'end_time' => $times['end'],
            'working_days' => 31,
        ]);

        $durations = [];

        for ($i = 0; $i < 100; $i++) {
            $baseTime = Carbon::parse($times['start']);
            $timestamp = $baseTime->addMinutes($i * 5);

            $start = microtime(true);
            $this->detector->detect($employee, $timestamp, $shift);
            $durations[] = (microtime(true) - $start) * 1000;
        }

        $results[$name] = array_sum($durations) / count($durations);
    }

    echo "\n";
    echo "Shift Type Performance:\n";
    echo "=======================\n";
    foreach ($results as $type => $avg) {
        echo sprintf("%-10s: %6.2fms\n", $type, $avg);
    }
    echo "\n";

    // All shift types should be under 50ms
    foreach ($results as $type => $avg) {
        expect($avg)->toBeLessThan(50);
    }
});
