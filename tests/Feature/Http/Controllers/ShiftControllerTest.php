<?php

use App\Models\Tenant\Department;
use App\Models\Tenant\Employee;
use App\Models\Tenant\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create a test tenant database
    $pdo = new \PDO(
        'mysql:host='.env('DB_HOST', '127.0.0.1'),
        env('DB_USERNAME', 'root'),
        env('DB_PASSWORD', '')
    );
    $pdo->exec('CREATE DATABASE IF NOT EXISTS tenant_shift_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

    // Configure tenant connection
    config()->set('database.connections.tenant', [
        'driver' => 'mysql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'database' => 'tenant_shift_test',
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
    ]);

    DB::purge('tenant');
    DB::reconnect('tenant');

    // Run tenant migrations
    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => 'database/migrations/tenant',
        '--force' => true,
    ]);

    // Create test user with tenant_admin role
    $this->user = User::factory()->create(['role' => 'tenant_admin']);

    // Create test department
    $this->department = Department::on('tenant')->create([
        'name' => 'IT Department',
        'description' => 'Information Technology',
    ]);
});

afterEach(function () {
    // Drop test database
    $pdo = new \PDO(
        'mysql:host='.env('DB_HOST', '127.0.0.1'),
        env('DB_USERNAME', 'root'),
        env('DB_PASSWORD', '')
    );
    $pdo->exec('DROP DATABASE IF EXISTS tenant_shift_test');
});

describe('ShiftController', function () {
    test('index displays shifts list', function () {
        // Create shifts
        Shift::on('tenant')->create([
            'name' => 'Morning Shift',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        Shift::on('tenant')->create([
            'name' => 'Evening Shift',
            'start_time' => '16:00:00',
            'end_time' => '00:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->user)->get(route('shifts.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('shifts/Index')
            ->has('shifts.data', 2)
        );
    });

    test('create displays shift form', function () {
        $response = $this->actingAs($this->user)->get(route('shifts.create'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('shifts/Form'));
    });

    test('store creates new shift', function () {
        $response = $this->actingAs($this->user)->post(route('shifts.store'), [
            'name' => 'Night Shift',
            'start_time' => '00:00:00',
            'end_time' => '08:00:00',
            'working_days' => [1, 2, 3, 4, 5], // Monday to Friday
            'is_default' => false,
        ]);

        $response->assertRedirect(route('shifts.index'));
        $response->assertSessionHas('success', 'Shift created successfully.');

        expect(Shift::on('tenant')->where('name', 'Night Shift')->exists())->toBeTrue();

        $shift = Shift::on('tenant')->where('name', 'Night Shift')->first();
        expect($shift->start_time)->toBe('00:00:00');
        expect($shift->end_time)->toBe('08:00:00');
        expect($shift->working_days)->toBe([1, 2, 3, 4, 5]);
    });

    test('store validates required fields', function () {
        $response = $this->actingAs($this->user)->post(route('shifts.store'), [
            'name' => '',
            'start_time' => '',
            'end_time' => '',
        ]);

        $response->assertSessionHasErrors(['name', 'start_time', 'end_time']);
    });

    test('store validates working days format', function () {
        $response = $this->actingAs($this->user)->post(route('shifts.store'), [
            'name' => 'Test Shift',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'working_days' => ['invalid_day'],
        ]);

        $response->assertSessionHasErrors(['working_days.0']);
    });

    test('store validates time format', function () {
        $response = $this->actingAs($this->user)->post(route('shifts.store'), [
            'name' => 'Test Shift',
            'start_time' => 'invalid',
            'end_time' => 'invalid',
            'working_days' => ['monday'],
        ]);

        $response->assertSessionHasErrors(['start_time', 'end_time']);
    });

    test('edit displays shift edit form', function () {
        $shift = Shift::on('tenant')->create([
            'name' => 'Morning Shift',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->user)->get(route('shifts.edit', $shift->id));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('shifts/Form')
            ->has('shift')
        );
    });

    test('update modifies existing shift', function () {
        $shift = Shift::on('tenant')->create([
            'name' => 'Morning Shift',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'working_days' => [1, 2, 3, 4, 5],
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->user)->put(route('shifts.update', $shift->id), [
            'name' => 'Updated Morning Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'working_days' => [1, 2, 3],
            'is_default' => true,
        ]);

        $response->assertRedirect(route('shifts.index'));
        $response->assertSessionHas('success', 'Shift updated successfully.');

        $shift->refresh();
        expect($shift->name)->toBe('Updated Morning Shift');
        expect($shift->start_time)->toBe('09:00:00');
        expect($shift->end_time)->toBe('17:00:00');
        expect($shift->working_days)->toBe([1, 2, 3]);
        expect($shift->is_default)->toBeTrue();
    });

    test('destroy deletes shift without employees', function () {
        $shift = Shift::on('tenant')->create([
            'name' => 'Morning Shift',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->user)->delete(route('shifts.destroy', $shift->id));

        $response->assertRedirect(route('shifts.index'));
        $response->assertSessionHas('success', 'Shift deleted successfully.');

        expect(Shift::on('tenant')->where('id', $shift->id)->exists())->toBeFalse();
    });

    test('destroy prevents deletion of shift with employees', function () {
        $shift = Shift::on('tenant')->create([
            'name' => 'Morning Shift',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $employee = Employee::withoutEvents(function () {
            return Employee::on('tenant')->create([
                'custom_id' => 'EMP001',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
                'department_id' => $this->department->id,
                'is_active' => true,
            ]);
        });

        // Assign shift to employee
        $employee->shifts()->attach($shift->id, [
            'effective_from' => now()->subMonth(),
            'effective_to' => null,
        ]);

        $response = $this->actingAs($this->user)->delete(route('shifts.destroy', $shift->id));

        $response->assertRedirect(route('shifts.index'));
        $response->assertSessionHas('error');

        expect(Shift::on('tenant')->where('id', $shift->id)->exists())->toBeTrue();
    });

    test('requires authentication', function () {
        $response = $this->get(route('shifts.index'));
        $response->assertRedirect(route('login'));
    });

    test('index displays employee count for each shift', function () {
        $shift = Shift::on('tenant')->create([
            'name' => 'Morning Shift',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        // Create employees and assign to shift
        for ($i = 1; $i <= 3; $i++) {
            $employee = Employee::withoutEvents(function () use ($i) {
                return Employee::on('tenant')->create([
                    'custom_id' => "EMP00{$i}",
                    'first_name' => "Employee{$i}",
                    'last_name' => 'Test',
                    'email' => "employee{$i}@example.com",
                    'department_id' => $this->department->id,
                    'is_active' => true,
                ]);
            });

            $employee->shifts()->attach($shift->id, [
                'effective_from' => now()->subMonth(),
                'effective_to' => null,
            ]);
        }

        $response = $this->actingAs($this->user)->get(route('shifts.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('shifts/Index')
            ->where('shifts.data.0.employees_count', 3)
        );
    });
});
