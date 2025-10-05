<?php

use App\Domain\Attendance\DTOs\DirectionResult;
use App\Domain\Attendance\Services\DirectionDetector;
use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\Device;
use App\Models\Tenant\Employee;
use App\Models\Tenant\Shift;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    // Disable model events to prevent job dispatching during tests
    Employee::unsetEventDispatcher();
    AttendanceRecord::unsetEventDispatcher();
    Device::unsetEventDispatcher();

    // Create test tenant using TenantDatabaseManager
    $tenant = new \App\DTOs\Tenant(
        id: 'direction-detector-test',
        company_name: 'Test Company',
        subdomain: 'test',
        domain: null,
        database_name: 'tenant_test_direction',
        database_host: env('DB_HOST', '127.0.0.1'),
        subscription_plan: 'professional',
        max_employees: 100,
        max_devices: 10,
        is_active: true,
    );

    $manager = new \App\Services\Tenancy\TenantDatabaseManager;
    $manager->provisionTenant($tenant);

    // Set default connection to tenant for models
    Config::set('database.default', 'tenant');

    // Initialize detector
    $this->detector = new DirectionDetector();
});

afterEach(function () {
    // Restore default connection
    Config::set('database.default', 'sqlite');

    // Drop test database
    $pdo = new \PDO(
        'mysql:host='.env('DB_HOST', '127.0.0.1'),
        env('DB_USERNAME', 'root'),
        env('DB_PASSWORD', '')
    );
    $pdo->exec('DROP DATABASE IF EXISTS tenant_test_direction');
});

describe('DirectionDetector Service', function () {
    test('can instantiate detector with default weights', function () {
        $detector = new DirectionDetector();

        expect($detector)->toBeInstanceOf(DirectionDetector::class);
    });

    test('can instantiate detector with custom weights', function () {
        $customWeights = [
            'last_record' => 25,
            'shift_timing' => 40,
            'work_duration' => 20,
            'fallback' => 15,
        ];

        $detector = new DirectionDetector($customWeights);

        expect($detector)->toBeInstanceOf(DirectionDetector::class);
    });

    test('detect returns direction result', function () {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create([
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        $timestamp = Carbon::parse('2025-10-05 09:05:00');

        $result = $this->detector->detect($employee, $timestamp, $shift);

        expect($result)->toBeInstanceOf(DirectionResult::class);
        expect($result->direction)->not->toBeEmpty();
        expect($result->confidence)->toBeInt();
        expect($result->scores)->toBeArray();
        expect($result->reason)->not->toBeEmpty();
    });

    test('detect favors check in for first record of day', function () {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create([
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        // No previous attendance records
        $timestamp = Carbon::parse('2025-10-05 09:00:00');

        $result = $this->detector->detect($employee, $timestamp, $shift);

        expect($result->direction)->toBe('check-in');
        expect($result->confidence)->toBeGreaterThan(50);
    });

    test('detect favors check out after check in', function () {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create([
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        // Create previous check-in
        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'recorded_at' => Carbon::parse('2025-10-05 09:00:00'),
            'direction' => 'check-in',
        ]);

        $timestamp = Carbon::parse('2025-10-05 17:00:00');

        $result = $this->detector->detect($employee, $timestamp, $shift);

        expect($result->direction)->toBe('check-out');
    });

    test('detect handles overnight shift', function () {
        $employee = Employee::factory()->create();

        // Overnight shift: 22:00 to 06:00 next day
        $shift = Shift::factory()->create([
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
        ]);

        // Check-in at 22:05
        $timestamp = Carbon::parse('2025-10-05 22:05:00');

        $result = $this->detector->detect($employee, $timestamp, $shift);

        expect($result->direction)->toBe('check-in');
        expect($result->confidence)->toBeGreaterThan(50);
    });

    test('detect handles overnight shift checkout next day', function () {
        $employee = Employee::factory()->create();

        // Overnight shift: 22:00 to 06:00 next day
        $shift = Shift::factory()->create([
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
        ]);

        // Check-in at 22:00
        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'recorded_at' => Carbon::parse('2025-10-05 22:00:00'),
            'direction' => 'check-in',
        ]);

        // Check-out at 06:00 next day
        $timestamp = Carbon::parse('2025-10-06 06:00:00');

        $result = $this->detector->detect($employee, $timestamp, $shift);

        expect($result->direction)->toBe('check-out');
    });

    test('detect handles break start after check in', function () {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create([
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);

        // Check-in at 09:00
        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'recorded_at' => Carbon::parse('2025-10-05 09:00:00'),
            'direction' => 'check-in',
        ]);

        // Event near break time
        $timestamp = Carbon::parse('2025-10-05 12:00:00');

        $result = $this->detector->detect($employee, $timestamp, $shift);

        expect($result->direction)->toBe('break-start');
    });

    test('detect handles break end after break start', function () {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create([
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);

        // Check-in at 09:00
        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'recorded_at' => Carbon::parse('2025-10-05 09:00:00'),
            'direction' => 'check-in',
        ]);

        // Break start at 12:00
        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'recorded_at' => Carbon::parse('2025-10-05 12:00:00'),
            'direction' => 'break-start',
        ]);

        // Event after break
        $timestamp = Carbon::parse('2025-10-05 13:00:00');

        $result = $this->detector->detect($employee, $timestamp, $shift);

        expect($result->direction)->toBe('break-end');
    });

    test('detect without shift uses fallback logic', function () {
        $employee = Employee::factory()->create();

        // Morning time without shift
        $timestamp = Carbon::parse('2025-10-05 09:00:00');

        $result = $this->detector->detect($employee, $timestamp, null);

        expect($result)->toBeInstanceOf(DirectionResult::class);
        expect($result->direction)->not->toBeEmpty();
    });

    test('get last attendance record returns most recent', function () {
        $employee = Employee::factory()->create();

        // Create multiple records
        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'recorded_at' => Carbon::parse('2025-10-05 09:00:00'),
            'direction' => 'check-in',
        ]);

        $latestRecord = AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'recorded_at' => Carbon::parse('2025-10-05 12:00:00'),
            'direction' => 'break-start',
        ]);

        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('getLastAttendanceRecord');
        $method->setAccessible(true);

        $result = $method->invoke($this->detector, $employee, Carbon::parse('2025-10-05 17:00:00'));

        expect($result)->not->toBeNull();
        expect($result->id)->toBe($latestRecord->id);
        expect($result->direction)->toBe('break-start');
    });

    test('get last attendance record returns null for no records', function () {
        $employee = Employee::factory()->create();

        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('getLastAttendanceRecord');
        $method->setAccessible(true);

        $result = $method->invoke($this->detector, $employee, Carbon::now());

        expect($result)->toBeNull();
    });

    test('get last attendance record only returns records before timestamp', function () {
        $employee = Employee::factory()->create();

        // Create record before timestamp
        $beforeRecord = AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'recorded_at' => Carbon::parse('2025-10-05 09:00:00'),
            'direction' => 'check-in',
        ]);

        // Create record after timestamp (should be ignored)
        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'recorded_at' => Carbon::parse('2025-10-05 18:00:00'),
            'direction' => 'check-out',
        ]);

        $reflection = new \ReflectionClass($this->detector);
        $method = $reflection->getMethod('getLastAttendanceRecord');
        $method->setAccessible(true);

        $timestamp = Carbon::parse('2025-10-05 12:00:00');
        $result = $method->invoke($this->detector, $employee, $timestamp);

        expect($result)->not->toBeNull();
        expect($result->id)->toBe($beforeRecord->id);
    });
});

describe('DirectionDetector Edge Cases', function () {
    test('handles employee with no shift using fallback logic', function () {
        $employee = Employee::factory()->create();

        // Morning event without shift should favor check-in
        $morningTimestamp = Carbon::parse('2025-10-05 09:00:00');
        $result = $this->detector->detect($employee, $morningTimestamp, null);

        expect($result->direction)->toBe('check-in');
        expect($result->confidence)->toBeGreaterThan(0);
    });

    test('detects check-out after multiple check-ins without check-out', function () {
        $employee = Employee::factory()->create();

        // Create two check-ins (unusual but possible due to errors)
        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'recorded_at' => Carbon::parse('2025-10-05 09:00:00'),
            'direction' => 'check-in',
        ]);

        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'recorded_at' => Carbon::parse('2025-10-05 09:05:00'),
            'direction' => 'check-in',
        ]);

        // Next event should be check-out (based on last record)
        $timestamp = Carbon::parse('2025-10-05 17:00:00');
        $result = $this->detector->detect($employee, $timestamp, null);

        expect($result->direction)->toBe('check-out');
    });

    test('handles afternoon timestamp with no previous records', function () {
        $employee = Employee::factory()->create();

        // Afternoon event without history - last_record weight (30%) trumps fallback (20%)
        // So first record will always be check-in regardless of time
        $afternoonTimestamp = Carbon::parse('2025-10-05 14:00:00');
        $result = $this->detector->detect($employee, $afternoonTimestamp, null);

        expect($result->direction)->toBe('check-in'); // First record always check-in
        expect($result->confidence)->toBeGreaterThan(0);
    });

    test('handles event far outside shift window', function () {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create([
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        // Event at 3 AM - far from shift
        $timestamp = Carbon::parse('2025-10-05 03:00:00');
        $result = $this->detector->detect($employee, $timestamp, $shift);

        expect($result)->toBeInstanceOf(DirectionResult::class);
        expect($result->direction)->not->toBeEmpty();
        // Confidence might be lower for unusual times
    });

    test('handles same-minute successive events', function () {
        $employee = Employee::factory()->create();

        // First event
        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'recorded_at' => Carbon::parse('2025-10-05 09:00:00'),
            'direction' => 'check-in',
        ]);

        // Event 30 seconds later - still returns a direction
        $timestamp = Carbon::parse('2025-10-05 09:00:30');
        $result = $this->detector->detect($employee, $timestamp, null);

        expect($result)->toBeInstanceOf(DirectionResult::class);
        expect($result->direction)->not->toBeEmpty();
    });

    test('handles break without shift break times defined', function () {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create([
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start' => null,
            'break_end' => null,
        ]);

        // Check-in first
        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'recorded_at' => Carbon::parse('2025-10-05 09:00:00'),
            'direction' => 'check-in',
        ]);

        // Midday event - should not favor break since no break times
        $timestamp = Carbon::parse('2025-10-05 12:00:00');
        $result = $this->detector->detect($employee, $timestamp, $shift);

        expect($result->direction)->toBeIn(['check-out', 'break-start']);
    });

    test('maintains reasonable confidence scores for all edge cases', function () {
        $employee = Employee::factory()->create();

        $scenarios = [
            ['time' => '2025-10-05 00:00:00', 'shift' => null], // Midnight
            ['time' => '2025-10-05 06:00:00', 'shift' => null], // Early morning
            ['time' => '2025-10-05 23:59:00', 'shift' => null], // Late night
        ];

        foreach ($scenarios as $scenario) {
            $timestamp = Carbon::parse($scenario['time']);
            $result = $this->detector->detect($employee, $timestamp, $scenario['shift']);

            expect($result->confidence)->toBeInt();
            expect($result->confidence)->toBeGreaterThanOrEqual(0);
            expect($result->confidence)->toBeLessThanOrEqual(100);
        }
    });

    test('generates meaningful detection reasons for edge cases', function () {
        $employee = Employee::factory()->create();

        // No previous records, unusual time
        $timestamp = Carbon::parse('2025-10-05 02:30:00');
        $result = $this->detector->detect($employee, $timestamp, null);

        expect($result->reason)->not->toBeEmpty();
        expect($result->reason)->toContain('confidence');
    });
});
