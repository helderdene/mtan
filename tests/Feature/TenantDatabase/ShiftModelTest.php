<?php

use App\Models\Tenant\Department;
use App\Models\Tenant\Employee;
use App\Models\Tenant\Shift;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    // Create test tenant using TenantDatabaseManager
    $tenant = new \App\DTOs\Tenant(
        id: 'shift-model-test',
        company_name: 'Test Company',
        subdomain: 'test',
        domain: null,
        database_name: 'tenant_test_shift',
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
    $pdo->exec('DROP DATABASE IF EXISTS tenant_test_shift');
});

describe('Shift Model', function () {
    test('can create shift', function () {
        $shift = Shift::create([
            'name' => 'Morning Shift',
            'start_time' => '06:00:00',
            'end_time' => '14:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        expect($shift)->toBeInstanceOf(Shift::class);
        expect($shift->id)->not->toBeNull();
        expect($shift->name)->toBe('Morning Shift');
        expect($shift->start_time)->toBe('06:00:00');
        expect($shift->end_time)->toBe('14:00:00');
    });

    test('working_days is cast to array', function () {
        $shift = Shift::create([
            'name' => 'Day Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        ]);

        expect($shift->working_days)->toBeArray();
        expect($shift->working_days)->toHaveCount(5);
        expect($shift->working_days)->toContain('monday');
        expect($shift->working_days)->toContain('friday');
    });

    test('is_default is cast to boolean', function () {
        $defaultShift = Shift::create([
            'name' => 'Default Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => true,
        ]);

        $regularShift = Shift::create([
            'name' => 'Regular Shift',
            'start_time' => '10:00:00',
            'end_time' => '18:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        expect($defaultShift->is_default)->toBeTrue();
        expect($regularShift->is_default)->toBeFalse();
    });

    test('has employees relationship', function () {
        $department = Department::create(['name' => 'Engineering']);

        $employee = Employee::create([
            'custom_id' => 'EMP001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'department_id' => $department->id,
        ]);

        $shift = Shift::create([
            'name' => 'Day Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        ]);

        $shift->employees()->attach($employee->id, [
            'effective_from' => now()->toDateString(),
        ]);

        expect($shift->employees)->toHaveCount(1);
        expect($shift->employees->first())->toBeInstanceOf(Employee::class);
        expect($shift->employees->first()->custom_id)->toBe('EMP001');
    });

    test('default scope filters default shift', function () {
        Shift::create([
            'name' => 'Regular Shift 1',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        Shift::create([
            'name' => 'Default Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => true,
        ]);

        Shift::create([
            'name' => 'Regular Shift 2',
            'start_time' => '10:00:00',
            'end_time' => '18:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $defaultShifts = Shift::default()->get();

        expect($defaultShifts)->toHaveCount(1);
        expect($defaultShifts->first()->name)->toBe('Default Shift');
    });

    test('can handle overnight shifts', function () {
        $nightShift = Shift::create([
            'name' => 'Night Shift',
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        ]);

        expect($nightShift->start_time)->toBe('22:00:00');
        expect($nightShift->end_time)->toBe('06:00:00');
    });

    test('can have weekend working days', function () {
        $weekendShift = Shift::create([
            'name' => 'Weekend Shift',
            'start_time' => '08:00:00',
            'end_time' => '20:00:00',
            'working_days' => ['saturday', 'sunday'],
        ]);

        expect($weekendShift->working_days)->toHaveCount(2);
        expect($weekendShift->working_days)->toContain('saturday');
        expect($weekendShift->working_days)->toContain('sunday');
    });

    test('can have all days as working days', function () {
        $flexibleShift = Shift::create([
            'name' => 'Flexible 24/7',
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        ]);

        expect($flexibleShift->working_days)->toHaveCount(7);
    });

    test('multiple employees can be assigned to same shift', function () {
        $department = Department::create(['name' => 'Sales']);

        $employee1 = Employee::create([
            'custom_id' => 'EMP101',
            'first_name' => 'Alice',
            'last_name' => 'Johnson',
            'email' => 'alice@example.com',
            'department_id' => $department->id,
        ]);

        $employee2 = Employee::create([
            'custom_id' => 'EMP102',
            'first_name' => 'Bob',
            'last_name' => 'Smith',
            'email' => 'bob@example.com',
            'department_id' => $department->id,
        ]);

        $shift = Shift::create([
            'name' => 'Day Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        ]);

        $shift->employees()->attach([
            $employee1->id => ['effective_from' => now()->toDateString()],
            $employee2->id => ['effective_from' => now()->toDateString()],
        ]);

        expect($shift->employees)->toHaveCount(2);
    });

    test('shift assignment has effective_from and effective_to dates', function () {
        $department = Department::create(['name' => 'Operations']);

        $employee = Employee::create([
            'custom_id' => 'EMP201',
            'first_name' => 'Charlie',
            'last_name' => 'Brown',
            'email' => 'charlie@example.com',
            'department_id' => $department->id,
        ]);

        $shift = Shift::create([
            'name' => 'Morning Shift',
            'start_time' => '06:00:00',
            'end_time' => '14:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        ]);

        $effectiveFrom = now()->startOfMonth();
        $effectiveTo = now()->endOfMonth();

        $shift->employees()->attach($employee->id, [
            'effective_from' => $effectiveFrom->toDateString(),
            'effective_to' => $effectiveTo->toDateString(),
        ]);

        $assignedShift = $employee->shifts->first();

        expect($assignedShift->pivot->effective_from)->toBe($effectiveFrom->toDateString());
        expect($assignedShift->pivot->effective_to)->toBe($effectiveTo->toDateString());
    });
});
