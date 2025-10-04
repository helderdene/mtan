<?php

use App\Models\Tenant\Department;
use App\Models\Tenant\Device;
use App\Models\Tenant\Employee;
use App\Models\Tenant\Shift;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Create test tenant using TenantDatabaseManager
    $tenant = new \App\DTOs\Tenant(
        id: 'employee-model-test',
        company_name: 'Test Company',
        subdomain: 'test',
        domain: null,
        database_name: 'tenant_test_employee',
        database_host: env('DB_HOST', '127.0.0.1'),
        subscription_plan: 'professional',
        max_employees: 100,
        max_devices: 10,
        is_active: true,
    );

    $manager = new \App\Services\Tenancy\TenantDatabaseManager();
    $manager->provisionTenant($tenant);

    // Set default connection to tenant for models
    Config::set('database.default', 'tenant');
});

afterEach(function () {
    // Restore default connection
    Config::set('database.default', 'sqlite');

    // Drop test database
    $pdo = new \PDO(
        'mysql:host=' . env('DB_HOST', '127.0.0.1'),
        env('DB_USERNAME', 'root'),
        env('DB_PASSWORD', '')
    );
    $pdo->exec('DROP DATABASE IF EXISTS tenant_test_employee');
});

describe('Employee Model', function () {
    test('can create employee', function () {
        $department = Department::create([
            'name' => 'Engineering',
            'description' => 'Engineering department',
        ]);

        $employee = Employee::create([
            'custom_id' => 'EMP001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+1234567890',
            'department_id' => $department->id,
            'is_active' => true,
            'hired_at' => now(),
        ]);

        expect($employee)->toBeInstanceOf(Employee::class);
        expect($employee->id)->not->toBeNull();
        expect($employee->custom_id)->toBe('EMP001');
        expect($employee->first_name)->toBe('John');
        expect($employee->last_name)->toBe('Doe');
        expect($employee->full_name)->toBe('John Doe');
    });

    test('has department relationship', function () {
        $department = Department::create([
            'name' => 'Sales',
            'description' => 'Sales department',
        ]);

        $employee = Employee::create([
            'custom_id' => 'EMP002',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane.smith@example.com',
            'department_id' => $department->id,
        ]);

        expect($employee->department)->toBeInstanceOf(Department::class);
        expect($employee->department->name)->toBe('Sales');
    });

    test('has shifts relationship', function () {
        $department = Department::create(['name' => 'IT']);

        $employee = Employee::create([
            'custom_id' => 'EMP003',
            'first_name' => 'Bob',
            'last_name' => 'Johnson',
            'email' => 'bob@example.com',
            'department_id' => $department->id,
        ]);

        $dayShift = Shift::create([
            'name' => 'Day Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'working_days' => json_encode(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
        ]);

        $employee->shifts()->attach($dayShift->id, [
            'effective_from' => now()->toDateString(),
        ]);

        expect($employee->shifts)->toHaveCount(1);
        expect($employee->shifts->first())->toBeInstanceOf(Shift::class);
        expect($employee->shifts->first()->name)->toBe('Day Shift');
    });

    test('has current shift relationship', function () {
        $department = Department::create(['name' => 'HR']);

        $employee = Employee::create([
            'custom_id' => 'EMP004',
            'first_name' => 'Alice',
            'last_name' => 'Williams',
            'email' => 'alice@example.com',
            'department_id' => $department->id,
        ]);

        // Create past shift (should not be current)
        $pastShift = Shift::create([
            'name' => 'Past Shift',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'working_days' => json_encode(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
        ]);

        $employee->shifts()->attach($pastShift->id, [
            'effective_from' => now()->subMonths(2)->toDateString(),
            'effective_to' => now()->subMonth()->toDateString(),
        ]);

        // Create current shift
        $currentShift = Shift::create([
            'name' => 'Current Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'working_days' => json_encode(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
        ]);

        $employee->shifts()->attach($currentShift->id, [
            'effective_from' => now()->toDateString(),
        ]);

        expect($employee->currentShift)->not->toBeNull();
        expect($employee->currentShift->name)->toBe('Current Shift');
    });

    test('active scope filters active employees', function () {
        $department = Department::create(['name' => 'Operations']);

        Employee::create([
            'custom_id' => 'EMP005',
            'first_name' => 'Active',
            'last_name' => 'Employee',
            'email' => 'active@example.com',
            'department_id' => $department->id,
            'is_active' => true,
        ]);

        Employee::create([
            'custom_id' => 'EMP006',
            'first_name' => 'Inactive',
            'last_name' => 'Employee',
            'email' => 'inactive@example.com',
            'department_id' => $department->id,
            'is_active' => false,
        ]);

        $activeEmployees = Employee::active()->get();

        expect($activeEmployees)->toHaveCount(1);
        expect($activeEmployees->first()->first_name)->toBe('Active');
    });

    test('by department scope filters by department', function () {
        $engineering = Department::create(['name' => 'Engineering']);
        $sales = Department::create(['name' => 'Sales']);

        Employee::create([
            'custom_id' => 'EMP007',
            'first_name' => 'Engineer',
            'last_name' => 'One',
            'email' => 'eng1@example.com',
            'department_id' => $engineering->id,
        ]);

        Employee::create([
            'custom_id' => 'EMP008',
            'first_name' => 'Salesperson',
            'last_name' => 'One',
            'email' => 'sales1@example.com',
            'department_id' => $sales->id,
        ]);

        $engineeringEmployees = Employee::byDepartment($engineering->id)->get();

        expect($engineeringEmployees)->toHaveCount(1);
        expect($engineeringEmployees->first()->first_name)->toBe('Engineer');
    });

    test('generates custom_id automatically if not provided', function () {
        $department = Department::create(['name' => 'IT']);

        $employee = Employee::create([
            'first_name' => 'Auto',
            'last_name' => 'Generated',
            'email' => 'auto@example.com',
            'department_id' => $department->id,
        ]);

        expect($employee->custom_id)->not->toBeNull();
        expect($employee->custom_id)->toStartWith('EMP');
    });

    test('custom_id is unique', function () {
        $department = Department::create(['name' => 'Finance']);

        Employee::create([
            'custom_id' => 'EMP999',
            'first_name' => 'First',
            'last_name' => 'Employee',
            'email' => 'first@example.com',
            'department_id' => $department->id,
        ]);

        // Attempting to create another employee with same custom_id should fail
        expect(fn () => Employee::create([
            'custom_id' => 'EMP999',
            'first_name' => 'Second',
            'last_name' => 'Employee',
            'email' => 'second@example.com',
            'department_id' => $department->id,
        ]))->toThrow(\Illuminate\Database\QueryException::class);
    });

    test('has device enrollments relationship', function () {
        $department = Department::create(['name' => 'Security']);

        $employee = Employee::create([
            'custom_id' => 'EMP010',
            'first_name' => 'Security',
            'last_name' => 'Guard',
            'email' => 'security@example.com',
            'department_id' => $department->id,
        ]);

        $device = DB::connection('tenant')->table('devices')->insertGetId([
            'device_id' => 'DEVICE001',
            'name' => 'Main Entrance',
            'location' => 'Building A',
            'ip_address' => '192.168.1.100',
            'capacity' => 3000,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('tenant')->table('device_enrollments')->insert([
            'employee_id' => $employee->id,
            'device_id' => $device,
            'enrollment_status' => 'synced',
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect($employee->deviceEnrollments)->toHaveCount(1);
        expect($employee->deviceEnrollments->first()->enrollment_status)->toBe('synced');
    });

    test('has attendance records relationship', function () {
        $department = Department::create(['name' => 'Admin']);

        $employee = Employee::create([
            'custom_id' => 'EMP011',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'department_id' => $department->id,
        ]);

        $device = DB::connection('tenant')->table('devices')->insertGetId([
            'device_id' => 'DEVICE002',
            'name' => 'Office Door',
            'location' => 'Building B',
            'ip_address' => '192.168.1.101',
            'capacity' => 3000,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('tenant')->table('attendance_records')->insert([
            'employee_id' => $employee->id,
            'device_id' => $device,
            'recorded_at' => now(),
            'direction' => 'check-in',
            'recognition_score' => 0.9850,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect($employee->attendanceRecords)->toHaveCount(1);
        expect($employee->attendanceRecords->first()->direction)->toBe('check-in');
    });

    test('deleting employee cascades to enrollments and attendance records', function () {
        $department = Department::create(['name' => 'Test']);

        $employee = Employee::create([
            'custom_id' => 'EMP012',
            'first_name' => 'Delete',
            'last_name' => 'Test',
            'email' => 'delete@example.com',
            'department_id' => $department->id,
        ]);

        $device = DB::connection('tenant')->table('devices')->insertGetId([
            'device_id' => 'DEVICE003',
            'name' => 'Test Device',
            'location' => 'Test Location',
            'ip_address' => '192.168.1.102',
            'capacity' => 3000,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('tenant')->table('device_enrollments')->insert([
            'employee_id' => $employee->id,
            'device_id' => $device,
            'enrollment_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('tenant')->table('attendance_records')->insert([
            'employee_id' => $employee->id,
            'device_id' => $device,
            'recorded_at' => now(),
            'direction' => 'check-in',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $employeeId = $employee->id;
        $employee->delete();

        // Verify cascade deletes
        expect(DB::connection('tenant')->table('device_enrollments')->where('employee_id', $employeeId)->count())->toBe(0);
        expect(DB::connection('tenant')->table('attendance_records')->where('employee_id', $employeeId)->count())->toBe(0);
    });
});
