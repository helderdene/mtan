<?php

use App\Models\Tenant\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run central database migrations
    Artisan::call('migrate', ['--path' => 'database/migrations/central']);

    // Create test tenant
    $tenant = \App\Models\Tenant::create([
        'id' => 'shift_update_test',
        'company_name' => 'Test Company',
        'subdomain' => 'test',
        'domain' => null,
        'database_name' => 'tenant_test_shift_update',
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
});

afterEach(function () {
    // Drop test database
    $pdo = new \PDO(
        'mysql:host='.env('DB_HOST', '127.0.0.1'),
        env('DB_USERNAME', 'root'),
        env('DB_PASSWORD', '')
    );
    $pdo->exec('DROP DATABASE IF EXISTS tenant_test_shift_update');
});

describe('Shift Update with Valid Breaks', function () {
    test('updates shift with valid break times', function () {
        $user = User::factory()->create(['role' => 'tenant_admin']);
        $shift = Shift::factory()->create([
            'name' => 'Morning Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start' => null,
            'break_end' => null,
        ]);

        $response = $this->actingAs($user)->put(route('shifts.update', $shift), [
            'name' => 'Morning Shift Updated',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $response->assertRedirect(route('shifts.index'));
        $response->assertSessionHasNoErrors();

        $shift->refresh();
        expect($shift->name)->toBe('Morning Shift Updated');
        expect($shift->break_start)->toBe('12:00:00');
        expect($shift->break_end)->toBe('13:00:00');
    });

    test('updates shift from no breaks to with breaks', function () {
        $user = User::factory()->create(['role' => 'tenant_admin']);
        $shift = Shift::factory()->create([
            'break_start' => null,
            'break_end' => null,
        ]);

        $response = $this->actingAs($user)->put(route('shifts.update', $shift), [
            'name' => $shift->name,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $response->assertRedirect(route('shifts.index'));
        $response->assertSessionHasNoErrors();

        $shift->refresh();
        expect($shift->break_start)->toBe('12:00:00');
        expect($shift->break_end)->toBe('13:00:00');
    });

    test('updates shift from with breaks to no breaks', function () {
        $user = User::factory()->create(['role' => 'tenant_admin']);
        $shift = Shift::factory()->withBreak()->create();

        $response = $this->actingAs($user)->put(route('shifts.update', $shift), [
            'name' => $shift->name,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $response->assertRedirect(route('shifts.index'));
        $response->assertSessionHasNoErrors();

        $shift->refresh();
        expect($shift->break_start)->toBeNull();
        expect($shift->break_end)->toBeNull();
    });

    test('updates break times while keeping shift hours the same', function () {
        $user = User::factory()->create(['role' => 'tenant_admin']);
        $shift = Shift::factory()->withBreak('12:00:00', '13:00:00')->create();

        $response = $this->actingAs($user)->put(route('shifts.update', $shift), [
            'name' => $shift->name,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start' => '14:00:00',
            'break_end' => '15:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $response->assertRedirect(route('shifts.index'));
        $response->assertSessionHasNoErrors();

        $shift->refresh();
        expect($shift->break_start)->toBe('14:00:00');
        expect($shift->break_end)->toBe('15:00:00');
    });
});

describe('Shift Update with Invalid Breaks', function () {
    test('rejects update when break_start is missing but break_end is provided', function () {
        $user = User::factory()->create(['role' => 'tenant_admin']);
        $shift = Shift::factory()->create();

        $response = $this->actingAs($user)->put(route('shifts.update', $shift), [
            'name' => $shift->name,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_end' => '13:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $response->assertSessionHasErrors(['break_end']);
    });

    test('rejects update when break_end is missing but break_start is provided', function () {
        $user = User::factory()->create(['role' => 'tenant_admin']);
        $shift = Shift::factory()->create();

        $response = $this->actingAs($user)->put(route('shifts.update', $shift), [
            'name' => $shift->name,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start' => '12:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $response->assertSessionHasErrors(['break_start']);
    });

    test('rejects update when break is outside shift hours', function () {
        $user = User::factory()->create(['role' => 'tenant_admin']);
        $shift = Shift::factory()->create();

        $response = $this->actingAs($user)->put(route('shifts.update', $shift), [
            'name' => $shift->name,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start' => '08:00:00',
            'break_end' => '09:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $response->assertSessionHasErrors(['break_start']);
    });

    test('rejects update when break_start is after break_end', function () {
        $user = User::factory()->create(['role' => 'tenant_admin']);
        $shift = Shift::factory()->create();

        $response = $this->actingAs($user)->put(route('shifts.update', $shift), [
            'name' => $shift->name,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start' => '13:00:00',
            'break_end' => '12:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $response->assertSessionHasErrors(['break_start']);
    });

    test('rejects update when break duration is 0 minutes', function () {
        $user = User::factory()->create(['role' => 'tenant_admin']);
        $shift = Shift::factory()->create();

        $response = $this->actingAs($user)->put(route('shifts.update', $shift), [
            'name' => $shift->name,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start' => '12:00:00',
            'break_end' => '12:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $response->assertSessionHasErrors(['break_end']);
    });

    test('rejects update when break duration exceeds 2 hours', function () {
        $user = User::factory()->create(['role' => 'tenant_admin']);
        $shift = Shift::factory()->create();

        $response = $this->actingAs($user)->put(route('shifts.update', $shift), [
            'name' => $shift->name,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start' => '12:00:00',
            'break_end' => '15:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $response->assertSessionHasErrors(['break_end']);
    });
});

describe('Overnight Shift Update', function () {
    test('updates to overnight shift with valid break', function () {
        $user = User::factory()->create(['role' => 'tenant_admin']);
        $shift = Shift::factory()->create([
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        $response = $this->actingAs($user)->put(route('shifts.update', $shift), [
            'name' => $shift->name,
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'break_start' => '02:00:00',
            'break_end' => '02:30:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $response->assertRedirect(route('shifts.index'));
        $response->assertSessionHasNoErrors();

        $shift->refresh();
        expect($shift->start_time)->toBe('22:00:00');
        expect($shift->end_time)->toBe('06:00:00');
        expect($shift->break_start)->toBe('02:00:00');
        expect($shift->break_end)->toBe('02:30:00');
    });

    test('rejects update when overnight shift break is outside valid hours', function () {
        $user = User::factory()->create(['role' => 'tenant_admin']);
        $shift = Shift::factory()->overnight()->create();

        $response = $this->actingAs($user)->put(route('shifts.update', $shift), [
            'name' => $shift->name,
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'break_start' => '10:00:00',
            'break_end' => '10:30:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $response->assertSessionHasErrors(['break_start']);
    });
});
