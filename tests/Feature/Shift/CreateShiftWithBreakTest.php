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
        'id' => 'shift_break_test',
        'company_name' => 'Test Company',
        'subdomain' => 'test',
        'domain' => null,
        'database_name' => 'tenant_test_shift_break',
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
    $pdo->exec('DROP DATABASE IF EXISTS tenant_test_shift_break');
});

describe('Shift Creation with Valid Breaks', function () {
    test('creates shift with valid break times', function () {
        $user = User::factory()->create(['role' => 'tenant_admin']);

        $response = $this->actingAs($user)->post(route('shifts.store'), [
            'name' => 'Morning Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $response->assertRedirect(route('shifts.index'));
        $response->assertSessionHasNoErrors();

        expect(Shift::on('tenant')->count())->toBe(1);

        $shift = Shift::on('tenant')->first();
        expect($shift->name)->toBe('Morning Shift');
        expect($shift->break_start)->toBe('12:00:00');
        expect($shift->break_end)->toBe('13:00:00');
    });

    test('creates shift without break times (both null)', function () {
        $user = User::factory()->create(['role' => 'tenant_admin']);

        $response = $this->actingAs($user)->post(route('shifts.store'), [
            'name' => 'Morning Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $response->assertRedirect(route('shifts.index'));
        $response->assertSessionHasNoErrors();

        $shift = Shift::on('tenant')->first();
        expect($shift->break_start)->toBeNull();
        expect($shift->break_end)->toBeNull();
    });

    test('creates overnight shift with valid break times', function () {
        $user = User::factory()->create(['role' => 'tenant_admin']);

        $response = $this->actingAs($user)->post(route('shifts.store'), [
            'name' => 'Night Shift',
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'break_start' => '02:00:00',
            'break_end' => '02:30:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $response->assertRedirect(route('shifts.index'));
        $response->assertSessionHasNoErrors();

        $shift = Shift::on('tenant')->first();
        expect($shift->break_start)->toBe('02:00:00');
        expect($shift->break_end)->toBe('02:30:00');
    });

    test('creates shift with minimum break duration (1 minute)', function () {
        $user = User::factory()->create(['role' => 'tenant_admin']);

        $response = $this->actingAs($user)->post(route('shifts.store'), [
            'name' => 'Morning Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start' => '12:00:00',
            'break_end' => '12:01:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $response->assertRedirect(route('shifts.index'));
        $response->assertSessionHasNoErrors();
    });

    test('creates shift with maximum break duration (2 hours)', function () {
        $user = User::factory()->create(['role' => 'tenant_admin']);

        $response = $this->actingAs($user)->post(route('shifts.store'), [
            'name' => 'Morning Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start' => '12:00:00',
            'break_end' => '14:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $response->assertRedirect(route('shifts.index'));
        $response->assertSessionHasNoErrors();
    });
});

describe('Shift Creation with Invalid Breaks', function () {
    test('rejects shift when break_start is missing but break_end is provided', function () {
        $user = User::factory()->create(['role' => 'tenant_admin']);

        $response = $this->actingAs($user)->post(route('shifts.store'), [
            'name' => 'Morning Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_end' => '13:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $response->assertSessionHasErrors(['break_end']);
        expect(Shift::on('tenant')->count())->toBe(0);
    });

    test('rejects shift when break_end is missing but break_start is provided', function () {
        $user = User::factory()->create(['role' => 'tenant_admin']);

        $response = $this->actingAs($user)->post(route('shifts.store'), [
            'name' => 'Morning Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start' => '12:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $response->assertSessionHasErrors(['break_start']);
        expect(Shift::on('tenant')->count())->toBe(0);
    });

    test('rejects shift when break is outside shift hours (before start)', function () {
        $user = User::factory()->create(['role' => 'tenant_admin']);

        $response = $this->actingAs($user)->post(route('shifts.store'), [
            'name' => 'Morning Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start' => '08:00:00',
            'break_end' => '09:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $response->assertSessionHasErrors(['break_start']);
        expect(Shift::on('tenant')->count())->toBe(0);
    });

    test('rejects shift when break is outside shift hours (after end)', function () {
        $user = User::factory()->create(['role' => 'tenant_admin']);

        $response = $this->actingAs($user)->post(route('shifts.store'), [
            'name' => 'Morning Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start' => '17:00:00',
            'break_end' => '18:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $response->assertSessionHasErrors(['break_end']);
        expect(Shift::on('tenant')->count())->toBe(0);
    });

    test('rejects shift when break_start is after break_end', function () {
        $user = User::factory()->create(['role' => 'tenant_admin']);

        $response = $this->actingAs($user)->post(route('shifts.store'), [
            'name' => 'Morning Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start' => '13:00:00',
            'break_end' => '12:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $response->assertSessionHasErrors(['break_start']);
        expect(Shift::on('tenant')->count())->toBe(0);
    });

    test('rejects shift when break duration is 0 minutes', function () {
        $user = User::factory()->create(['role' => 'tenant_admin']);

        $response = $this->actingAs($user)->post(route('shifts.store'), [
            'name' => 'Morning Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start' => '12:00:00',
            'break_end' => '12:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $response->assertSessionHasErrors(['break_end']);
        expect(Shift::on('tenant')->count())->toBe(0);
    });

    test('rejects shift when break duration exceeds 2 hours', function () {
        $user = User::factory()->create(['role' => 'tenant_admin']);

        $response = $this->actingAs($user)->post(route('shifts.store'), [
            'name' => 'Morning Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start' => '12:00:00',
            'break_end' => '15:00:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $response->assertSessionHasErrors(['break_end']);
        expect(Shift::on('tenant')->count())->toBe(0);
    });
});

describe('Overnight Shift Break Validation', function () {
    test('accepts break within overnight shift (before midnight)', function () {
        $user = User::factory()->create(['role' => 'tenant_admin']);

        $response = $this->actingAs($user)->post(route('shifts.store'), [
            'name' => 'Night Shift',
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'break_start' => '23:00:00',
            'break_end' => '23:30:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $response->assertRedirect(route('shifts.index'));
        $response->assertSessionHasNoErrors();
    });

    test('accepts break within overnight shift (after midnight)', function () {
        $user = User::factory()->create(['role' => 'tenant_admin']);

        $response = $this->actingAs($user)->post(route('shifts.store'), [
            'name' => 'Night Shift',
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'break_start' => '02:00:00',
            'break_end' => '02:30:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $response->assertRedirect(route('shifts.index'));
        $response->assertSessionHasNoErrors();
    });

    test('rejects break outside overnight shift hours', function () {
        $user = User::factory()->create(['role' => 'tenant_admin']);

        $response = $this->actingAs($user)->post(route('shifts.store'), [
            'name' => 'Night Shift',
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'break_start' => '10:00:00',
            'break_end' => '10:30:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
        ]);

        $response->assertSessionHasErrors(['break_start']);
        expect(Shift::on('tenant')->count())->toBe(0);
    });
});
