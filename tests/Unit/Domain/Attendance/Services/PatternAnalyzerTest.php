<?php

use App\Domain\Attendance\DTOs\EmployeePattern;
use App\Domain\Attendance\Services\PatternAnalyzer;
use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\Employee;
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
    DB::connection('tenant')->getSchemaBuilder()->create('employees', function ($table) {
        $table->id();
        $table->string('first_name');
        $table->string('last_name');
        $table->string('custom_id')->unique();
        $table->timestamps();
    });

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
        'first_name' => 'John',
        'last_name' => 'Doe',
        'custom_id' => 'EMP001',
    ]);

    $this->analyzer = new PatternAnalyzer();
});

test('calculates pattern with 30 days of consistent data', function () {
    // Create 30 days of consistent check-in/check-out records
    // Check-in: 09:00 ± 5 minutes
    // Check-out: 17:00 ± 5 minutes
    for ($i = 0; $i < 30; $i++) {
        $date = Carbon::now()->subDays($i);

        // Morning check-in (09:00 with slight variation)
        AttendanceRecord::on('tenant')->create([
            'employee_id' => $this->employee->id,
            'device_id' => 1,
            'recorded_at' => $date->copy()->setTime(9, rand(0, 10), 0),
            'direction' => 'check-in',
            'recognition_score' => 0.95,
        ]);

        // Evening check-out (17:00 with slight variation)
        AttendanceRecord::on('tenant')->create([
            'employee_id' => $this->employee->id,
            'device_id' => 1,
            'recorded_at' => $date->copy()->setTime(17, rand(0, 10), 0),
            'direction' => 'check-out',
            'recognition_score' => 0.95,
        ]);
    }

    $pattern = $this->analyzer->analyzePatterns($this->employee);

    expect($pattern)->toBeInstanceOf(EmployeePattern::class)
        ->and($pattern->reliable)->toBeTrue()
        ->and($pattern->recordCount)->toBe(60)
        ->and($pattern->avgCheckInTime)->not->toBeNull()
        ->and($pattern->checkInStdDev)->not->toBeNull()
        ->and($pattern->avgCheckOutTime)->not->toBeNull()
        ->and($pattern->checkOutStdDev)->not->toBeNull();

    // Check-in average should be around 09:00 (32400 seconds)
    expect($pattern->avgCheckInTime)->toBeGreaterThan(32000)
        ->and($pattern->avgCheckInTime)->toBeLessThan(33000);

    // Check-out average should be around 17:00 (61200 seconds)
    expect($pattern->avgCheckOutTime)->toBeGreaterThan(61000)
        ->and($pattern->avgCheckOutTime)->toBeLessThan(62000);
});

test('calculates standard deviation accurately', function () {
    // Create very consistent records (low std dev)
    for ($i = 0; $i < 20; $i++) {
        $date = Carbon::now()->subDays($i);

        AttendanceRecord::on('tenant')->create([
            'employee_id' => $this->employee->id,
            'device_id' => 1,
            'recorded_at' => $date->copy()->setTime(9, 0, 0), // Exactly 09:00
            'direction' => 'check-in',
            'recognition_score' => 0.95,
        ]);
    }

    $pattern = $this->analyzer->analyzePatterns($this->employee);

    // Standard deviation should be very low (near 0)
    expect($pattern->checkInStdDev)->toBeLessThan(60); // Less than 1 minute
});

test('returns unreliable pattern when less than 7 records', function () {
    // Create only 5 records (below threshold)
    for ($i = 0; $i < 5; $i++) {
        AttendanceRecord::on('tenant')->create([
            'employee_id' => $this->employee->id,
            'device_id' => 1,
            'recorded_at' => Carbon::now()->subDays($i)->setTime(9, 0, 0),
            'direction' => 'check-in',
            'recognition_score' => 0.95,
        ]);
    }

    $pattern = $this->analyzer->analyzePatterns($this->employee);

    expect($pattern->reliable)->toBeFalse()
        ->and($pattern->recordCount)->toBe(5)
        ->and($pattern->avgCheckInTime)->toBeNull()
        ->and($pattern->checkInStdDev)->toBeNull();
});

test('scores pattern at 1 sigma proximity', function () {
    // Create consistent pattern: 09:00 ± 5 minutes (std dev ≈ 5 min = 300 sec)
    for ($i = 0; $i < 15; $i++) {
        AttendanceRecord::on('tenant')->create([
            'employee_id' => $this->employee->id,
            'device_id' => 1,
            'recorded_at' => Carbon::now()->subDays($i)->setTime(9, rand(0, 10), 0),
            'direction' => 'check-in',
            'recognition_score' => 0.95,
        ]);
    }

    // Test timestamp at 09:03 (within 1σ of 09:00)
    $timestamp = Carbon::parse('2025-10-06 09:03:00');
    $score = $this->analyzer->scorePattern($this->employee, $timestamp, 'check-in');

    // Should get 20 points (within 1σ)
    expect($score)->toBe(20);
});

test('scores pattern at 2 sigma proximity', function () {
    // Create very consistent pattern: 09:00 ± 2 minutes
    for ($i = 0; $i < 15; $i++) {
        AttendanceRecord::on('tenant')->create([
            'employee_id' => $this->employee->id,
            'device_id' => 1,
            'recorded_at' => Carbon::now()->subDays($i)->setTime(9, rand(0, 4), 0),
            'direction' => 'check-in',
            'recognition_score' => 0.95,
        ]);
    }

    // Test timestamp at 09:08 (within 2σ but outside 1σ)
    $timestamp = Carbon::parse('2025-10-06 09:08:00');
    $score = $this->analyzer->scorePattern($this->employee, $timestamp, 'check-in');

    // Should get 15 points (within 2σ)
    expect($score)->toBeGreaterThanOrEqual(10)
        ->and($score)->toBeLessThanOrEqual(20);
});

test('scores pattern at 3 sigma proximity', function () {
    // Create very consistent pattern
    for ($i = 0; $i < 15; $i++) {
        AttendanceRecord::on('tenant')->create([
            'employee_id' => $this->employee->id,
            'device_id' => 1,
            'recorded_at' => Carbon::now()->subDays($i)->setTime(9, 0, 0),
            'direction' => 'check-in',
            'recognition_score' => 0.95,
        ]);
    }

    // Test timestamp far from pattern (within 3σ)
    $timestamp = Carbon::parse('2025-10-06 09:15:00');
    $score = $this->analyzer->scorePattern($this->employee, $timestamp, 'check-in');

    // Should get 5-10 points (within 3σ or outside)
    expect($score)->toBeGreaterThanOrEqual(5)
        ->and($score)->toBeLessThanOrEqual(15);
});

test('returns neutral score for unreliable pattern', function () {
    // Create only 3 records (below threshold)
    for ($i = 0; $i < 3; $i++) {
        AttendanceRecord::on('tenant')->create([
            'employee_id' => $this->employee->id,
            'device_id' => 1,
            'recorded_at' => Carbon::now()->subDays($i)->setTime(9, 0, 0),
            'direction' => 'check-in',
            'recognition_score' => 0.95,
        ]);
    }

    $timestamp = Carbon::parse('2025-10-06 09:00:00');
    $score = $this->analyzer->scorePattern($this->employee, $timestamp, 'check-in');

    // Should return neutral score (10)
    expect($score)->toBe(10);
});

test('caches pattern analysis results', function () {
    // Create sufficient records
    for ($i = 0; $i < 10; $i++) {
        AttendanceRecord::on('tenant')->create([
            'employee_id' => $this->employee->id,
            'device_id' => 1,
            'recorded_at' => Carbon::now()->subDays($i)->setTime(9, 0, 0),
            'direction' => 'check-in',
            'recognition_score' => 0.95,
        ]);
    }

    // First call should calculate and cache
    $pattern1 = $this->analyzer->analyzePatterns($this->employee);

    // Second call should hit cache
    $pattern2 = $this->analyzer->analyzePatterns($this->employee);

    // Both should have same analyzed_at timestamp (from cache)
    expect($pattern1->analyzedAt->timestamp)->toBe($pattern2->analyzedAt->timestamp);
});

test('invalidates pattern cache', function () {
    // Create records and analyze
    for ($i = 0; $i < 10; $i++) {
        AttendanceRecord::on('tenant')->create([
            'employee_id' => $this->employee->id,
            'device_id' => 1,
            'recorded_at' => Carbon::now()->subDays($i)->setTime(9, 0, 0),
            'direction' => 'check-in',
            'recognition_score' => 0.95,
        ]);
    }

    $pattern1 = $this->analyzer->analyzePatterns($this->employee);

    // Wait a moment to ensure different timestamp
    sleep(1);

    // Invalidate cache
    $this->analyzer->invalidatePatternCache($this->employee);

    // Next analysis should recalculate
    $pattern2 = $this->analyzer->analyzePatterns($this->employee);

    // Analyzed_at should be different
    expect($pattern1->analyzedAt->timestamp)->not->toBe($pattern2->analyzedAt->timestamp);
});

test('handles new employee with no records', function () {
    // Employee with no attendance records
    $pattern = $this->analyzer->analyzePatterns($this->employee);

    expect($pattern->reliable)->toBeFalse()
        ->and($pattern->recordCount)->toBe(0);

    $timestamp = Carbon::parse('2025-10-06 09:00:00');
    $score = $this->analyzer->scorePattern($this->employee, $timestamp, 'check-in');

    // Should return neutral score
    expect($score)->toBe(10);
});

test('handles irregular schedules', function () {
    // Create records with high variance (06:00 to 12:00)
    $times = [6, 7, 8, 9, 10, 11, 12];

    for ($i = 0; $i < 14; $i++) {
        $hour = $times[$i % 7];
        AttendanceRecord::on('tenant')->create([
            'employee_id' => $this->employee->id,
            'device_id' => 1,
            'recorded_at' => Carbon::now()->subDays($i)->setTime($hour, 0, 0),
            'direction' => 'check-in',
            'recognition_score' => 0.95,
        ]);
    }

    $pattern = $this->analyzer->analyzePatterns($this->employee);

    expect($pattern->reliable)->toBeTrue()
        ->and($pattern->checkInStdDev)->toBeGreaterThan(3600); // > 1 hour std dev
});

test('only analyzes last 30 days of records', function () {
    // Create old records (40 days ago)
    for ($i = 35; $i < 45; $i++) {
        AttendanceRecord::on('tenant')->create([
            'employee_id' => $this->employee->id,
            'device_id' => 1,
            'recorded_at' => Carbon::now()->subDays($i)->setTime(6, 0, 0),
            'direction' => 'check-in',
            'recognition_score' => 0.95,
        ]);
    }

    // Create recent records (last 15 days) with different time
    for ($i = 0; $i < 15; $i++) {
        AttendanceRecord::on('tenant')->create([
            'employee_id' => $this->employee->id,
            'device_id' => 1,
            'recorded_at' => Carbon::now()->subDays($i)->setTime(9, 0, 0),
            'direction' => 'check-in',
            'recognition_score' => 0.95,
        ]);
    }

    $pattern = $this->analyzer->analyzePatterns($this->employee);

    // Should only count recent 15 records
    expect($pattern->recordCount)->toBe(15);

    // Average should be around 09:00, not 06:00
    expect($pattern->avgCheckInTime)->toBeGreaterThan(30000); // > 08:20
});

test('converts time to seconds from midnight correctly', function () {
    $reflection = new ReflectionClass($this->analyzer);
    $method = $reflection->getMethod('timeToSecondsFromMidnight');
    $method->setAccessible(true);

    $timestamp = Carbon::parse('2025-10-06 09:30:45');
    $seconds = $method->invoke($this->analyzer, $timestamp);

    // 9 hours * 3600 + 30 min * 60 + 45 sec = 34245
    expect($seconds)->toBe(34245);
});

test('calculates standard deviation correctly', function () {
    $reflection = new ReflectionClass($this->analyzer);
    $method = $reflection->getMethod('calculateStandardDeviation');
    $method->setAccessible(true);

    // Simple test case: [2, 4, 4, 4, 5, 5, 7, 9]
    // Mean = 5, Variance = 4, StdDev = 2
    $values = [2, 4, 4, 4, 5, 5, 7, 9];
    $mean = 5.0;

    $stdDev = $method->invoke($this->analyzer, $values, $mean);

    expect($stdDev)->toBe(2.0);
});
