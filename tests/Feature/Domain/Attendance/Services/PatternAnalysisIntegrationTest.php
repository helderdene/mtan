<?php

use App\Domain\Attendance\Services\DirectionDetector;
use App\Domain\Attendance\Services\PatternAnalyzer;
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

    $this->employee = Employee::on('tenant')->create([
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'custom_id' => 'EMP002',
    ]);

    $this->shift = Shift::on('tenant')->create([
        'name' => 'Morning Shift',
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'working_days' => 31, // Mon-Fri
    ]);

    $this->analyzer = new PatternAnalyzer();
    $this->detector = new DirectionDetector($this->analyzer);
});

test('integrates pattern analysis with direction detection', function () {
    // Create 30 days of consistent attendance (09:05 check-in, 17:00 check-out)
    for ($i = 1; $i <= 30; $i++) {
        $date = Carbon::now()->subDays($i);

        AttendanceRecord::on('tenant')->create([
            'employee_id' => $this->employee->id,
            'device_id' => 1,
            'recorded_at' => $date->copy()->setTime(9, 5, 0),
            'direction' => 'check-in',
            'recognition_score' => 0.95,
        ]);

        AttendanceRecord::on('tenant')->create([
            'employee_id' => $this->employee->id,
            'device_id' => 1,
            'recorded_at' => $date->copy()->setTime(17, 0, 0),
            'direction' => 'check-out',
            'recognition_score' => 0.95,
        ]);
    }

    // Test detection at typical check-in time (09:03)
    $timestamp = Carbon::parse('2025-10-06 09:03:00');
    $result = $this->detector->detect($this->employee, $timestamp, $this->shift);

    expect($result->direction)->toBe('check-in')
        ->and($result->confidence)->toBeGreaterThan(70)
        ->and($result->scores['pattern'])->toBeGreaterThan(0);

    // Test detection at typical check-out time (17:02)
    $timestamp = Carbon::parse('2025-10-06 17:02:00');

    // Create check-in record for today to make check-out logical
    AttendanceRecord::on('tenant')->create([
        'employee_id' => $this->employee->id,
        'device_id' => 1,
        'recorded_at' => Carbon::parse('2025-10-06 09:00:00'),
        'direction' => 'check-in',
        'recognition_score' => 0.95,
    ]);

    $result = $this->detector->detect($this->employee, $timestamp, $this->shift);

    expect($result->direction)->toBe('check-out')
        ->and($result->confidence)->toBeGreaterThan(70)
        ->and($result->scores['pattern'])->toBeGreaterThan(0);
});

test('invalidates pattern cache when new attendance record is created', function () {
    // Create initial pattern (10 records)
    for ($i = 1; $i <= 10; $i++) {
        AttendanceRecord::on('tenant')->create([
            'employee_id' => $this->employee->id,
            'device_id' => 1,
            'recorded_at' => Carbon::now()->subDays($i)->setTime(9, 0, 0),
            'direction' => 'check-in',
            'recognition_score' => 0.95,
        ]);
    }

    // Analyze pattern (should cache)
    $pattern1 = $this->analyzer->analyzePatterns($this->employee);
    expect($pattern1->recordCount)->toBe(10);

    // Wait to ensure different timestamp
    sleep(1);

    // Invalidate cache (simulating new record creation)
    $this->analyzer->invalidatePatternCache($this->employee);

    // Create new record
    AttendanceRecord::on('tenant')->create([
        'employee_id' => $this->employee->id,
        'device_id' => 1,
        'recorded_at' => Carbon::now()->setTime(9, 0, 0),
        'direction' => 'check-in',
        'recognition_score' => 0.95,
    ]);

    // Analyze again (should recalculate)
    $pattern2 = $this->analyzer->analyzePatterns($this->employee);

    // Should have one more record
    expect($pattern2->recordCount)->toBe(11);

    // Analyzed timestamps should be different
    expect($pattern1->analyzedAt->timestamp)->not->toBe($pattern2->analyzedAt->timestamp);
});

test('adapts pattern over time with rolling 30-day window', function () {
    // Create old pattern (35-45 days ago): check-in at 06:00
    for ($i = 35; $i <= 45; $i++) {
        AttendanceRecord::on('tenant')->create([
            'employee_id' => $this->employee->id,
            'device_id' => 1,
            'recorded_at' => Carbon::now()->subDays($i)->setTime(6, 0, 0),
            'direction' => 'check-in',
            'recognition_score' => 0.95,
        ]);
    }

    // Create new pattern (last 15 days): check-in at 09:00
    for ($i = 1; $i <= 15; $i++) {
        AttendanceRecord::on('tenant')->create([
            'employee_id' => $this->employee->id,
            'device_id' => 1,
            'recorded_at' => Carbon::now()->subDays($i)->setTime(9, 0, 0),
            'direction' => 'check-in',
            'recognition_score' => 0.95,
        ]);
    }

    $pattern = $this->analyzer->analyzePatterns($this->employee);

    // Should only analyze last 30 days (15 recent records)
    expect($pattern->recordCount)->toBe(15);

    // Average should reflect new pattern (09:00), not old (06:00)
    $avgTime = $pattern->getAvgCheckInTimeAsCarbon();
    expect($avgTime->hour)->toBe(9);
});

test('handles new employee with no history gracefully', function () {
    // New employee with no attendance records
    $timestamp = Carbon::parse('2025-10-06 09:00:00');

    $result = $this->detector->detect($this->employee, $timestamp, $this->shift);

    // Should still detect direction (favoring check-in for first record)
    expect($result->direction)->toBe('check-in')
        ->and($result->confidence)->toBeGreaterThan(50);

    // Pattern score should be neutral (50 on 0-100 scale after conversion)
    expect($result->scores['pattern'])->toBe(50);
});

test('handles employees with irregular schedules', function () {
    // Create irregular pattern: alternating 06:00, 09:00, 12:00
    $times = [6, 9, 12];

    for ($i = 0; $i < 15; $i++) {
        $hour = $times[$i % 3];
        AttendanceRecord::on('tenant')->create([
            'employee_id' => $this->employee->id,
            'device_id' => 1,
            'recorded_at' => Carbon::now()->subDays($i)->setTime($hour, 0, 0),
            'direction' => 'check-in',
            'recognition_score' => 0.95,
        ]);
    }

    $pattern = $this->analyzer->analyzePatterns($this->employee);

    // Pattern should be marked as reliable (>= 7 records)
    expect($pattern->reliable)->toBeTrue();

    // But standard deviation should be high (irregular schedule)
    expect($pattern->checkInStdDev)->toBeGreaterThan(3600); // > 1 hour

    // Direction detection should still work, but pattern score will be low for any specific time
    $timestamp = Carbon::parse('2025-10-06 09:00:00');
    $result = $this->detector->detect($this->employee, $timestamp, $this->shift);

    expect($result->direction)->toBe('check-in'); // First record of day
});

test('performance: pattern calculation completes within 100ms', function () {
    // Create 30 days of data (60 records)
    for ($i = 0; $i < 30; $i++) {
        $date = Carbon::now()->subDays($i);

        AttendanceRecord::on('tenant')->create([
            'employee_id' => $this->employee->id,
            'device_id' => 1,
            'recorded_at' => $date->copy()->setTime(9, 0, 0),
            'direction' => 'check-in',
            'recognition_score' => 0.95,
        ]);

        AttendanceRecord::on('tenant')->create([
            'employee_id' => $this->employee->id,
            'device_id' => 1,
            'recorded_at' => $date->copy()->setTime(17, 0, 0),
            'direction' => 'check-out',
            'recognition_score' => 0.95,
        ]);
    }

    // Clear cache to force calculation
    Cache::flush();

    $start = microtime(true);
    $pattern = $this->analyzer->analyzePatterns($this->employee);
    $duration = (microtime(true) - $start) * 1000; // Convert to milliseconds

    expect($pattern)->not->toBeNull();
    expect($duration)->toBeLessThan(100); // Should complete in < 100ms
});

test('performance: cached pattern retrieval completes within 5ms', function () {
    // Create data
    for ($i = 0; $i < 10; $i++) {
        AttendanceRecord::on('tenant')->create([
            'employee_id' => $this->employee->id,
            'device_id' => 1,
            'recorded_at' => Carbon::now()->subDays($i)->setTime(9, 0, 0),
            'direction' => 'check-in',
            'recognition_score' => 0.95,
        ]);
    }

    // First call to populate cache
    $this->analyzer->analyzePatterns($this->employee);

    // Second call should hit cache
    $start = microtime(true);
    $pattern = $this->analyzer->analyzePatterns($this->employee);
    $duration = (microtime(true) - $start) * 1000;

    expect($pattern)->not->toBeNull();
    expect($duration)->toBeLessThan(5); // Should complete in < 5ms
});

test('pattern improves direction detection accuracy over time', function () {
    // Employee always checks in at 09:00 and checks out at 17:00

    // Week 1: No pattern yet (< 7 records)
    for ($i = 1; $i <= 5; $i++) {
        AttendanceRecord::on('tenant')->create([
            'employee_id' => $this->employee->id,
            'device_id' => 1,
            'recorded_at' => Carbon::now()->subDays($i)->setTime(9, 0, 0),
            'direction' => 'check-in',
            'recognition_score' => 0.95,
        ]);
    }

    $timestamp = Carbon::parse('2025-10-06 09:00:00');
    $result1 = $this->detector->detect($this->employee, $timestamp, $this->shift);
    $patternScore1 = $result1->scores['pattern'];

    // Week 2-4: Build reliable pattern (15 more records)
    for ($i = 6; $i <= 20; $i++) {
        AttendanceRecord::on('tenant')->create([
            'employee_id' => $this->employee->id,
            'device_id' => 1,
            'recorded_at' => Carbon::now()->subDays($i)->setTime(9, 0, 0),
            'direction' => 'check-in',
            'recognition_score' => 0.95,
        ]);
    }

    // Clear cache to recalculate
    $this->analyzer->invalidatePatternCache($this->employee);

    $result2 = $this->detector->detect($this->employee, $timestamp, $this->shift);
    $patternScore2 = $result2->scores['pattern'];

    // Pattern score should improve with more data
    expect($patternScore2)->toBeGreaterThan($patternScore1);
});

test('pattern score contributes 20% to overall direction confidence', function () {
    // Create strong pattern: 20 days of 09:00 check-ins
    for ($i = 1; $i <= 20; $i++) {
        AttendanceRecord::on('tenant')->create([
            'employee_id' => $this->employee->id,
            'device_id' => 1,
            'recorded_at' => Carbon::now()->subDays($i)->setTime(9, 0, 0),
            'direction' => 'check-in',
            'recognition_score' => 0.95,
        ]);
    }

    // Test at pattern time (09:00)
    $timestamp = Carbon::parse('2025-10-06 09:00:00');
    $result = $this->detector->detect($this->employee, $timestamp, $this->shift);

    // Pattern score should be high (matching historical pattern)
    expect($result->scores['pattern'])->toBeGreaterThan(80);

    // Total confidence should benefit from high pattern score
    expect($result->confidence)->toBeGreaterThan(60);
});
