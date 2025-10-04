# Tests Specification

This is the tests coverage details for the spec detailed in @.agent-os/specs/2025-10-04-role-based-authorization/spec.md

> Created: 2025-10-04
> Version: 1.0.0

## Test Coverage

### Unit Tests

**File: `tests/Unit/UserModelTest.php`**

Test the User model role checking methods:

```php
<?php

uses(Tests\TestCase::class);

test('isSuperAdmin returns true for super admin role', function () {
    $user = User::factory()->create(['role' => 'super_admin']);
    expect($user->isSuperAdmin())->toBeTrue();
});

test('isSuperAdmin returns false for tenant admin role', function () {
    $user = User::factory()->create(['role' => 'tenant_admin']);
    expect($user->isSuperAdmin())->toBeFalse();
});

test('isTenantAdmin returns true for tenant admin role', function () {
    $user = User::factory()->create(['role' => 'tenant_admin']);
    expect($user->isTenantAdmin())->toBeTrue();
});

test('isTenantAdmin returns false for tenant user role', function () {
    $user = User::factory()->create(['role' => 'tenant_user']);
    expect($user->isTenantAdmin())->toBeFalse();
});

test('isTenantUser returns true for tenant user role', function () {
    $user = User::factory()->create(['role' => 'tenant_user']);
    expect($user->isTenantUser())->toBeTrue();
});

test('hasRole returns true for matching role', function () {
    $user = User::factory()->create(['role' => 'tenant_admin']);
    expect($user->hasRole('tenant_admin'))->toBeTrue();
});

test('hasRole returns false for non-matching role', function () {
    $user = User::factory()->create(['role' => 'tenant_user']);
    expect($user->hasRole('tenant_admin'))->toBeFalse();
});

test('belongsToTenant returns true for matching tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    expect($user->belongsToTenant($tenant->id))->toBeTrue();
});

test('belongsToTenant returns false for different tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenantA->id]);
    expect($user->belongsToTenant($tenantB->id))->toBeFalse();
});
```

### Middleware Tests

**File: `tests/Feature/Middleware/RequiresSuperAdminTest.php`**

```php
<?php

uses(Tests\TestCase::class);

test('super admin can access super admin routes', function () {
    $superAdmin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($superAdmin)
        ->get(route('admin.tenants.index'))
        ->assertStatus(200);
});

test('tenant admin cannot access super admin routes', function () {
    $tenant = Tenant::factory()->create();
    $tenantAdmin = User::factory()->create([
        'role' => 'tenant_admin',
        'tenant_id' => $tenant->id,
    ]);

    $this->actingAs($tenantAdmin)
        ->get(route('admin.tenants.index'))
        ->assertStatus(403)
        ->assertSee('Super admin privileges required');
});

test('tenant user cannot access super admin routes', function () {
    $tenant = Tenant::factory()->create();
    $tenantUser = User::factory()->create([
        'role' => 'tenant_user',
        'tenant_id' => $tenant->id,
    ]);

    $this->actingAs($tenantUser)
        ->get(route('admin.tenants.index'))
        ->assertStatus(403);
});

test('unauthenticated user cannot access super admin routes', function () {
    $this->get(route('admin.tenants.index'))
        ->assertRedirect(route('login'));
});
```

**File: `tests/Feature/Middleware/RequiresTenantAdminTest.php`**

```php
<?php

uses(Tests\TestCase::class);

test('tenant admin can access tenant admin routes', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);

    $tenantAdmin = User::factory()->create([
        'role' => 'tenant_admin',
        'tenant_id' => $tenant->id,
    ]);

    $this->actingAs($tenantAdmin)
        ->get(route('employees.index'))
        ->assertStatus(200);

    tenancy()->end();
});

test('super admin can access tenant admin routes', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);

    $superAdmin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($superAdmin)
        ->get(route('employees.index'))
        ->assertStatus(200);

    tenancy()->end();
});

test('tenant user cannot access tenant admin routes', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);

    $tenantUser = User::factory()->create([
        'role' => 'tenant_user',
        'tenant_id' => $tenant->id,
    ]);

    $this->actingAs($tenantUser)
        ->get(route('employees.index'))
        ->assertStatus(403)
        ->assertSee('Tenant admin privileges required');

    tenancy()->end();
});

test('tenant admin from different tenant cannot access routes', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    tenancy()->initialize($tenantB); // Set context to Tenant B

    $adminA = User::factory()->create([
        'role' => 'tenant_admin',
        'tenant_id' => $tenantA->id, // User belongs to Tenant A
    ]);

    $this->actingAs($adminA)
        ->get(route('employees.index'))
        ->assertStatus(403)
        ->assertSee('You do not belong to this tenant');

    tenancy()->end();
});
```

**File: `tests/Feature/Middleware/EnsureTenantAccessTest.php`**

```php
<?php

uses(Tests\TestCase::class);

test('user can access routes in their own tenant', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);

    $user = User::factory()->create([
        'role' => 'tenant_user',
        'tenant_id' => $tenant->id,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertStatus(200);

    tenancy()->end();
});

test('user cannot access routes in different tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    tenancy()->initialize($tenantB);

    $userA = User::factory()->create([
        'role' => 'tenant_user',
        'tenant_id' => $tenantA->id,
    ]);

    $this->actingAs($userA)
        ->get(route('dashboard'))
        ->assertStatus(403)
        ->assertSee('You do not belong to this tenant');

    tenancy()->end();
});

test('super admin bypasses tenant access checks', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);

    $superAdmin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($superAdmin)
        ->get(route('dashboard'))
        ->assertStatus(200);

    tenancy()->end();
});

test('middleware aborts when tenant context is not set', function () {
    $user = User::factory()->create([
        'role' => 'tenant_user',
        'tenant_id' => 1,
    ]);

    // No tenant context initialized
    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertStatus(403)
        ->assertSee('Tenant context not set');
});
```

### Gate Tests

**File: `tests/Feature/Authorization/GateTest.php`**

```php
<?php

uses(Tests\TestCase::class);
use Illuminate\Support\Facades\Gate;

test('isSuperAdmin gate allows super admin', function () {
    $superAdmin = User::factory()->create(['role' => 'super_admin']);
    expect(Gate::forUser($superAdmin)->allows('isSuperAdmin'))->toBeTrue();
});

test('isSuperAdmin gate denies tenant admin', function () {
    $tenantAdmin = User::factory()->create(['role' => 'tenant_admin']);
    expect(Gate::forUser($tenantAdmin)->allows('isSuperAdmin'))->toBeFalse();
});

test('isTenantAdmin gate allows tenant admin', function () {
    $tenantAdmin = User::factory()->create(['role' => 'tenant_admin']);
    expect(Gate::forUser($tenantAdmin)->allows('isTenantAdmin'))->toBeTrue();
});

test('isTenantAdmin gate allows super admin', function () {
    $superAdmin = User::factory()->create(['role' => 'super_admin']);
    expect(Gate::forUser($superAdmin)->allows('isTenantAdmin'))->toBeTrue();
});

test('isTenantAdmin gate denies tenant user', function () {
    $tenantUser = User::factory()->create(['role' => 'tenant_user']);
    expect(Gate::forUser($tenantUser)->allows('isTenantAdmin'))->toBeFalse();
});

test('canAccessTenant gate allows user from same tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    expect(Gate::forUser($user)->allows('canAccessTenant', $tenant->id))->toBeTrue();
});

test('canAccessTenant gate denies user from different tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenantA->id]);

    expect(Gate::forUser($user)->allows('canAccessTenant', $tenantB->id))->toBeFalse();
});

test('canAccessTenant gate allows super admin for any tenant', function () {
    $tenant = Tenant::factory()->create();
    $superAdmin = User::factory()->create(['role' => 'super_admin']);

    expect(Gate::forUser($superAdmin)->allows('canAccessTenant', $tenant->id))->toBeTrue();
});

test('manageEmployees gate allows tenant admin', function () {
    $tenantAdmin = User::factory()->create(['role' => 'tenant_admin']);
    expect(Gate::forUser($tenantAdmin)->allows('manageEmployees'))->toBeTrue();
});

test('manageEmployees gate denies tenant user', function () {
    $tenantUser = User::factory()->create(['role' => 'tenant_user']);
    expect(Gate::forUser($tenantUser)->allows('manageEmployees'))->toBeFalse();
});

test('manageDevices gate allows super admin', function () {
    $superAdmin = User::factory()->create(['role' => 'super_admin']);
    expect(Gate::forUser($superAdmin)->allows('manageDevices'))->toBeTrue();
});

test('viewReports gate allows tenant admin', function () {
    $tenantAdmin = User::factory()->create(['role' => 'tenant_admin']);
    expect(Gate::forUser($tenantAdmin)->allows('viewReports'))->toBeTrue();
});
```

### Integration Tests

**File: `tests/Feature/Authorization/RouteProtectionTest.php`**

```php
<?php

uses(Tests\TestCase::class);

test('employee index route requires tenant admin role', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);

    $tenantUser = User::factory()->create([
        'role' => 'tenant_user',
        'tenant_id' => $tenant->id,
    ]);

    $this->actingAs($tenantUser)
        ->get(route('employees.index'))
        ->assertStatus(403);

    tenancy()->end();
});

test('device create route requires tenant admin role', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);

    $tenantUser = User::factory()->create([
        'role' => 'tenant_user',
        'tenant_id' => $tenant->id,
    ]);

    $this->actingAs($tenantUser)
        ->get(route('devices.create'))
        ->assertStatus(403);

    tenancy()->end();
});

test('tenant management route requires super admin role', function () {
    $tenantAdmin = User::factory()->create(['role' => 'tenant_admin']);

    $this->actingAs($tenantAdmin)
        ->get(route('admin.tenants.index'))
        ->assertStatus(403);
});

test('dashboard route accessible to all authenticated users', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);

    $tenantUser = User::factory()->create([
        'role' => 'tenant_user',
        'tenant_id' => $tenant->id,
    ]);

    $this->actingAs($tenantUser)
        ->get(route('dashboard'))
        ->assertStatus(200);

    tenancy()->end();
});

test('settings routes accessible to all authenticated users', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);

    $tenantUser = User::factory()->create([
        'role' => 'tenant_user',
        'tenant_id' => $tenant->id,
    ]);

    $this->actingAs($tenantUser)
        ->get(route('settings.profile'))
        ->assertStatus(200);

    tenancy()->end();
});
```

### Tenant Isolation Tests

**File: `tests/Feature/Authorization/TenantIsolationTest.php`**

```php
<?php

uses(Tests\TestCase::class);

test('tenant admin cannot view employees from different tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $adminA = User::factory()->create([
        'role' => 'tenant_admin',
        'tenant_id' => $tenantA->id,
    ]);

    tenancy()->initialize($tenantB);

    $this->actingAs($adminA)
        ->get(route('employees.index'))
        ->assertStatus(403)
        ->assertSee('You do not belong to this tenant');

    tenancy()->end();
});

test('tenant admin cannot create employee in different tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $adminA = User::factory()->create([
        'role' => 'tenant_admin',
        'tenant_id' => $tenantA->id,
    ]);

    tenancy()->initialize($tenantB);

    $this->actingAs($adminA)
        ->post(route('employees.store'), [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ])
        ->assertStatus(403);

    tenancy()->end();
});

test('super admin can access any tenant data', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $superAdmin = User::factory()->create(['role' => 'super_admin']);

    // Access Tenant A
    tenancy()->initialize($tenantA);
    $this->actingAs($superAdmin)
        ->get(route('employees.index'))
        ->assertStatus(200);
    tenancy()->end();

    // Access Tenant B
    tenancy()->initialize($tenantB);
    $this->actingAs($superAdmin)
        ->get(route('employees.index'))
        ->assertStatus(200);
    tenancy()->end();
});

test('user without tenant_id cannot access tenant routes', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);

    // User with no tenant_id (orphaned user)
    $orphanedUser = User::factory()->create([
        'role' => 'tenant_user',
        'tenant_id' => null,
    ]);

    $this->actingAs($orphanedUser)
        ->get(route('dashboard'))
        ->assertStatus(403);

    tenancy()->end();
});
```

## Mocking Requirements

### Factory Enhancements

**File: `database/factories/UserFactory.php`**

Add role states to User factory:

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => 'tenant_user', // Default role
            'tenant_id' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'super_admin',
            'tenant_id' => null, // Super admins don't belong to a tenant
        ]);
    }

    public function tenantAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'tenant_admin',
        ]);
    }

    public function tenantUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'tenant_user',
        ]);
    }
}
```

**Usage in tests:**
```php
$superAdmin = User::factory()->superAdmin()->create();
$tenantAdmin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
$tenantUser = User::factory()->tenantUser()->create(['tenant_id' => $tenant->id]);
```

### Tenant Factory

**File: `database/factories/TenantFactory.php`** (if not exists)

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TenantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'domain' => fake()->unique()->domainName(),
            'subdomain' => fake()->unique()->slug(),
            'database_name' => 'tenant_' . Str::random(16),
            'is_active' => true,
        ];
    }
}
```

## Test Execution

### Run All Authorization Tests

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test tests/Feature/Middleware/
php artisan test tests/Feature/Authorization/

# Run with coverage
php artisan test --coverage

# Run specific test file
php artisan test tests/Feature/Middleware/RequiresSuperAdminTest.php

# Run specific test by name
php artisan test --filter test_super_admin_can_access_super_admin_routes
```

### Test Database Configuration

**File: `phpunit.xml`**

Ensure test database is configured:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

For tenant isolation tests, consider using a separate test database:

```xml
<env name="DB_CONNECTION" value="mysql"/>
<env name="DB_DATABASE" value="attendance_test"/>
```

## Code Coverage Targets

- **Middleware**: 100% coverage (all paths tested)
- **User Model**: 100% coverage (all role methods tested)
- **Gates**: 100% coverage (all authorization logic tested)
- **Route Protection**: 90%+ coverage (all protected routes tested)
- **Tenant Isolation**: 100% coverage (critical security feature)

## Continuous Integration

Add to CI/CD pipeline:

```yaml
# .github/workflows/tests.yml
- name: Run Authorization Tests
  run: php artisan test tests/Feature/Authorization/ --coverage
```

Ensure all authorization tests pass before deployment to production.
