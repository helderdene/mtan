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
        'id' => 'department_controller_test',
        'company_name' => 'Test Company',
        'subdomain' => 'test',
        'domain' => null,
        'database_name' => 'tenant_test_dept_ctrl',
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

    $manager = new \App\Services\Tenancy\TenantDatabaseManager();
    $manager->provisionTenant($tenantDto);
    $manager->setupTenantConnection($tenantDto);

    // Create authenticated user
    $this->user = User::factory()->create();
});

afterEach(function () {
    // Drop test database
    $pdo = new \PDO(
        'mysql:host=' . env('DB_HOST', '127.0.0.1'),
        env('DB_USERNAME', 'root'),
        env('DB_PASSWORD', '')
    );
    $pdo->exec('DROP DATABASE IF EXISTS tenant_test_dept_ctrl');
});

describe('DepartmentController', function () {
    test('index displays departments list with employee count', function () {
        $dept1 = Department::on('tenant')->create(['name' => 'Engineering']);
        $dept2 = Department::on('tenant')->create(['name' => 'Sales']);

        // Add employees to first department
        Employee::on('tenant')->create([
            'custom_id' => 'EMP001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'department_id' => $dept1->id,
        ]);

        Employee::on('tenant')->create([
            'custom_id' => 'EMP002',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'department_id' => $dept1->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('departments.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('departments/Index')
            ->has('departments', 2)
            ->where('departments.0.name', 'Engineering')
            ->where('departments.0.employees_count', 2)
            ->where('departments.1.name', 'Sales')
            ->where('departments.1.employees_count', 0)
        );
    });

    test('create displays department form', function () {
        $response = $this->actingAs($this->user)->get(route('departments.create'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('departments/Form')
        );
    });

    test('store creates new department', function () {
        $response = $this->actingAs($this->user)->post(route('departments.store'), [
            'name' => 'Marketing',
        ]);

        $response->assertRedirect(route('departments.index'));
        $response->assertSessionHas('success', 'Department created successfully.');

        expect(Department::on('tenant')->where('name', 'Marketing')->exists())->toBeTrue();
    });

    test('store validates required name', function () {
        $response = $this->actingAs($this->user)->post(route('departments.store'), [
            'name' => '',
        ]);

        $response->assertSessionHasErrors(['name']);
    });

    test('store validates unique name', function () {
        Department::on('tenant')->create(['name' => 'Engineering']);

        $response = $this->actingAs($this->user)->post(route('departments.store'), [
            'name' => 'Engineering', // Duplicate
        ]);

        $response->assertSessionHasErrors(['name']);
    });

    test('edit displays department edit form', function () {
        $department = Department::on('tenant')->create(['name' => 'Engineering']);

        $response = $this->actingAs($this->user)->get(route('departments.edit', $department->id));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('departments/Form')
            ->has('department')
            ->where('department.name', 'Engineering')
        );
    });

    test('update modifies existing department', function () {
        $department = Department::on('tenant')->create(['name' => 'Engineering']);

        $response = $this->actingAs($this->user)->put(route('departments.update', $department->id), [
            'name' => 'Software Engineering',
        ]);

        $response->assertRedirect(route('departments.index'));
        $response->assertSessionHas('success', 'Department updated successfully.');

        $department->refresh();
        expect($department->name)->toBe('Software Engineering');
    });

    test('destroy deletes department without employees', function () {
        $department = Department::on('tenant')->create(['name' => 'Engineering']);

        $response = $this->actingAs($this->user)->delete(route('departments.destroy', $department->id));

        $response->assertRedirect(route('departments.index'));
        $response->assertSessionHas('success', 'Department deleted successfully.');

        expect(Department::on('tenant')->where('id', $department->id)->exists())->toBeFalse();
    });

    test('destroy prevents deletion of department with employees', function () {
        $department = Department::on('tenant')->create(['name' => 'Engineering']);

        Employee::on('tenant')->create([
            'custom_id' => 'EMP001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'department_id' => $department->id,
        ]);

        $response = $this->actingAs($this->user)->delete(route('departments.destroy', $department->id));

        $response->assertRedirect(route('departments.index'));
        $response->assertSessionHas('error');

        expect(Department::on('tenant')->where('id', $department->id)->exists())->toBeTrue();
    });

    test('requires authentication', function () {
        $response = $this->get(route('departments.index'));
        $response->assertRedirect(route('login'));
    });
});
