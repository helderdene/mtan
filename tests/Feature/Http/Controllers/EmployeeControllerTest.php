<?php

use App\Models\Tenant\Department;
use App\Models\Tenant\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run central database migrations
    Artisan::call('migrate', ['--path' => 'database/migrations/central']);

    // Create test tenant
    $tenant = \App\Models\Tenant::create([
        'id' => 'employee_controller_test',
        'company_name' => 'Test Company',
        'subdomain' => 'test',
        'domain' => null,
        'database_name' => 'tenant_test_employee_ctrl',
        'database_host' => env('DB_HOST', '127.0.0.1'),
        'subscription_plan' => 'professional',
        'max_employees' => 100,
        'max_devices' => 10,
        'is_active' => true,
    ]);

    // Provision tenant database
    $tenantDto = new \App\DTOs\Tenant(
        id: $tenant->id,
        company_name: $tenant->company_name,
        subdomain: $tenant->subdomain,
        domain: $tenant->domain,
        database_name: $tenant->database_name,
        database_host: $tenant->database_host,
        subscription_plan: $tenant->subscription_plan,
        max_employees: $tenant->max_employees,
        max_devices: $tenant->max_devices,
        is_active: $tenant->is_active,
    );

    $manager = new \App\Services\Tenancy\TenantDatabaseManager;
    $manager->provisionTenant($tenantDto);
    $manager->setupTenantConnection($tenantDto);

    // Create authenticated user
    $this->user = User::factory()->create();

    // Create test department
    $this->department = Department::on('tenant')->create(['name' => 'Engineering']);
});

afterEach(function () {
    // Drop test database
    $pdo = new \PDO(
        'mysql:host='.env('DB_HOST', '127.0.0.1'),
        env('DB_USERNAME', 'root'),
        env('DB_PASSWORD', '')
    );
    $pdo->exec('DROP DATABASE IF EXISTS tenant_test_employee_ctrl');
});

describe('EmployeeController', function () {
    test('index displays employees list', function () {
        $employee1 = Employee::on('tenant')->create([
            'custom_id' => 'EMP001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);

        $employee2 = Employee::on('tenant')->create([
            'custom_id' => 'EMP002',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get(route('employees.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('employees/Index')
            ->has('employees.data', 2)
            ->where('employees.data.0.custom_id', 'EMP002') // Most recent first
            ->where('employees.data.1.custom_id', 'EMP001')
        );
    });

    test('index can search employees by name', function () {
        Employee::on('tenant')->create([
            'custom_id' => 'EMP001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);

        Employee::on('tenant')->create([
            'custom_id' => 'EMP002',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get(route('employees.index', ['search' => 'Jane']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('employees/Index')
            ->has('employees.data', 1)
            ->where('employees.data.0.first_name', 'Jane')
        );
    });

    test('create displays employee form', function () {
        $response = $this->actingAs($this->user)->get(route('employees.create'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('employees/Form')
            ->has('departments')
        );
    });

    test('store creates new employee', function () {
        $response = $this->actingAs($this->user)->post(route('employees.store'), [
            'custom_id' => 'EMP003',
            'first_name' => 'Alice',
            'last_name' => 'Johnson',
            'email' => 'alice@example.com',
            'phone' => '+1234567890',
            'department_id' => $this->department->id,
            'is_active' => true,
            'hired_at' => '2025-01-15',
        ]);

        $response->assertRedirect(route('employees.index'));
        $response->assertSessionHas('success', 'Employee created and sync initiated to devices.');

        expect(Employee::on('tenant')->where('custom_id', 'EMP003')->exists())->toBeTrue();

        $employee = Employee::on('tenant')->where('custom_id', 'EMP003')->first();
        expect($employee->first_name)->toBe('Alice');
        expect($employee->last_name)->toBe('Johnson');
        expect($employee->email)->toBe('alice@example.com');
    });

    test('store validates required fields', function () {
        $response = $this->actingAs($this->user)->post(route('employees.store'), [
            'custom_id' => '',
            'first_name' => '',
            'last_name' => '',
            'email' => '',
        ]);

        $response->assertSessionHasErrors(['custom_id', 'first_name', 'last_name', 'email']);
    });

    test('store validates unique custom_id', function () {
        Employee::on('tenant')->create([
            'custom_id' => 'EMP001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'department_id' => $this->department->id,
        ]);

        $response = $this->actingAs($this->user)->post(route('employees.store'), [
            'custom_id' => 'EMP001', // Duplicate
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
        ]);

        $response->assertSessionHasErrors(['custom_id']);
    });

    test('store validates custom_id format', function () {
        $response = $this->actingAs($this->user)->post(route('employees.store'), [
            'custom_id' => 'emp001', // Lowercase not allowed
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);

        $response->assertSessionHasErrors(['custom_id']);
    });

    test('edit displays employee edit form', function () {
        $employee = Employee::on('tenant')->create([
            'custom_id' => 'EMP001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'department_id' => $this->department->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('employees.edit', $employee->id));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('employees/Form')
            ->has('employee')
            ->has('departments')
            ->where('employee.custom_id', 'EMP001')
        );
    });

    test('update modifies existing employee', function () {
        $employee = Employee::on('tenant')->create([
            'custom_id' => 'EMP001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'department_id' => $this->department->id,
        ]);

        $response = $this->actingAs($this->user)->put(route('employees.update', $employee->id), [
            'first_name' => 'Johnny',
            'last_name' => 'Doe',
            'email' => 'johnny@example.com',
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('employees.index'));
        $response->assertSessionHas('success', 'Employee updated and sync initiated to devices.');

        $employee->refresh();
        expect($employee->first_name)->toBe('Johnny');
        expect($employee->email)->toBe('johnny@example.com');
    });

    test('destroy deletes employee without attendance records', function () {
        $employee = Employee::on('tenant')->create([
            'custom_id' => 'EMP001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'department_id' => $this->department->id,
        ]);

        $response = $this->actingAs($this->user)->delete(route('employees.destroy', $employee->id));

        $response->assertRedirect(route('employees.index'));
        $response->assertSessionHas('success', 'Employee deleted successfully.');

        expect(Employee::on('tenant')->where('id', $employee->id)->exists())->toBeFalse();
    });

    test('destroy prevents deletion of employee with attendance records', function () {
        $employee = Employee::on('tenant')->create([
            'custom_id' => 'EMP001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'department_id' => $this->department->id,
        ]);

        $device = \App\Models\Tenant\Device::on('tenant')->create([
            'device_id' => 'DEVICE001',
            'name' => 'Test Device',
            'location' => 'Test Location',
            'ip_address' => '192.168.1.100',
            'capacity' => 3000,
            'is_active' => true,
        ]);

        // Create attendance record
        \App\Models\Tenant\AttendanceRecord::on('tenant')->create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'recorded_at' => now(),
            'direction' => 'check-in',
        ]);

        $response = $this->actingAs($this->user)->delete(route('employees.destroy', $employee->id));

        $response->assertRedirect(route('employees.index'));
        $response->assertSessionHas('error');

        expect(Employee::on('tenant')->where('id', $employee->id)->exists())->toBeTrue();
    });

    test('requires authentication', function () {
        $response = $this->get(route('employees.index'));
        $response->assertRedirect(route('login'));
    });
});
