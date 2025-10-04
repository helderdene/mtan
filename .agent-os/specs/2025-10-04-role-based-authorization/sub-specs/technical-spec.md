# Technical Specification

This is the technical specification for the spec detailed in @.agent-os/specs/2025-10-04-role-based-authorization/spec.md

> Created: 2025-10-04
> Version: 1.0.0

## Technical Requirements

### Role Definitions

The system will support three predefined roles stored in the `users.role` column:

1. **super_admin**
   - Platform-wide access to all tenants and system configuration
   - Can provision new tenants, manage MQTT broker configs, view system metrics
   - Stored in central database `super_admins` table (already exists per architecture)
   - Can switch tenant context to access any tenant's data
   - Bypasses tenant isolation checks

2. **tenant_admin**
   - Full administrative access within assigned tenant only
   - Can manage employees, devices, shifts, departments, attendance records
   - Cannot access other tenants' data or system-wide settings
   - Must belong to a specific tenant (foreign key: `tenant_id`)
   - Cannot create/delete tenants or modify MQTT broker settings

3. **tenant_user**
   - Default role for new users within a tenant
   - Self-service access: view own attendance, submit leave requests, view own profile
   - Cannot access admin pages, manage other employees, or configure devices
   - Read-only access to own attendance data and assigned shifts
   - Must belong to a specific tenant (foreign key: `tenant_id`)

### Database Schema Changes

**Migration: `add_role_to_users_table`**

```php
Schema::table('users', function (Blueprint $table) {
    $table->enum('role', ['super_admin', 'tenant_admin', 'tenant_user'])
          ->default('tenant_user')
          ->after('email');
    $table->index('role'); // Performance optimization for role-based queries
});
```

**Migration: `add_tenant_id_to_users_table`** (if not already exists)

```php
Schema::table('users', function (Blueprint $table) {
    $table->foreignId('tenant_id')->nullable()->constrained('tenants')->onDelete('cascade');
    $table->index('tenant_id');
});
```

**Notes:**
- `tenant_id` is nullable to support super_admins who don't belong to a specific tenant
- `tenant_id` should be set for all tenant_admin and tenant_user roles
- Cascade delete ensures user cleanup when tenant is deleted

### Middleware Implementation

**1. RequiresSuperAdmin Middleware**

File: `app/Http/Middleware/RequiresSuperAdmin.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequiresSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            abort(403, 'Access denied. Super admin privileges required.');
        }

        return $next($request);
    }
}
```

**2. RequiresTenantAdmin Middleware**

File: `app/Http/Middleware/RequiresTenantAdmin.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequiresTenantAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'Authentication required.');
        }

        // Super admins can access tenant admin routes
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Tenant admins must belong to current tenant context
        if (!$user->isTenantAdmin()) {
            abort(403, 'Access denied. Tenant admin privileges required.');
        }

        // Ensure user belongs to current tenant (tenant isolation check)
        $currentTenantId = tenancy()->getTenant()?->id;
        if ($currentTenantId && $user->tenant_id !== $currentTenantId) {
            abort(403, 'Access denied. You do not belong to this tenant.');
        }

        return $next($request);
    }
}
```

**3. EnsureTenantAccess Middleware** (enhanced tenant isolation)

File: `app/Http/Middleware/EnsureTenantAccess.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'Authentication required.');
        }

        // Super admins bypass tenant checks
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // All other users must belong to current tenant
        $currentTenantId = tenancy()->getTenant()?->id;

        if (!$currentTenantId) {
            abort(403, 'Tenant context not set.');
        }

        if ($user->tenant_id !== $currentTenantId) {
            abort(403, 'Access denied. You do not belong to this tenant.');
        }

        return $next($request);
    }
}
```

### User Model Enhancements

File: `app/Models/User.php`

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'tenant_id',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => 'string',
    ];

    // Relationship to tenant
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    // Role checking methods
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isTenantAdmin(): bool
    {
        return $this->role === 'tenant_admin';
    }

    public function isTenantUser(): bool
    {
        return $this->role === 'tenant_user';
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function belongsToTenant(int $tenantId): bool
    {
        return $this->tenant_id === $tenantId;
    }
}
```

### Authorization Gates

File: `app/Providers/AuthServiceProvider.php`

```php
<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        // Model policies will be registered here in future specs
    ];

    public function boot(): void
    {
        // Super admin gate
        Gate::define('isSuperAdmin', function (User $user) {
            return $user->isSuperAdmin();
        });

        // Tenant admin gate (includes super admins)
        Gate::define('isTenantAdmin', function (User $user) {
            return $user->isSuperAdmin() || $user->isTenantAdmin();
        });

        // Check if user can access specific tenant
        Gate::define('canAccessTenant', function (User $user, int $tenantId) {
            return $user->isSuperAdmin() || $user->belongsToTenant($tenantId);
        });

        // Check if user can manage employees (tenant admin or super admin)
        Gate::define('manageEmployees', function (User $user) {
            return $user->isSuperAdmin() || $user->isTenantAdmin();
        });

        // Check if user can manage devices (tenant admin or super admin)
        Gate::define('manageDevices', function (User $user) {
            return $user->isSuperAdmin() || $user->isTenantAdmin();
        });

        // Check if user can view reports (tenant admin or super admin)
        Gate::define('viewReports', function (User $user) {
            return $user->isSuperAdmin() || $user->isTenantAdmin();
        });
    }
}
```

### Route Protection Strategy

**File: `app/Http/Kernel.php` - Register Middleware Aliases**

```php
protected $middlewareAliases = [
    'auth' => \App\Http\Middleware\Authenticate::class,
    'super_admin' => \App\Http\Middleware\RequiresSuperAdmin::class,
    'tenant_admin' => \App\Http\Middleware\RequiresTenantAdmin::class,
    'tenant_access' => \App\Http\Middleware\EnsureTenantAccess::class,
    // ... other middleware
];
```

**File: `routes/web.php` - Route Group Protection**

```php
<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public routes (no authentication)
Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Authentication routes (login, register, etc.)
require __DIR__.'/auth.php';

// Authenticated user routes (tenant_user and above)
Route::middleware(['auth', 'tenant_access'])->group(function () {

    // Dashboard (all authenticated users)
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    // User settings (all authenticated users can manage own profile)
    require __DIR__.'/settings.php';

    // Tenant admin routes
    Route::middleware('tenant_admin')->group(function () {

        // Employee management
        Route::prefix('employees')->name('employees.')->group(function () {
            Route::get('/', [EmployeeController::class, 'index'])->name('index');
            Route::get('/create', [EmployeeController::class, 'create'])->name('create');
            Route::post('/', [EmployeeController::class, 'store'])->name('store');
            Route::get('/{employee}', [EmployeeController::class, 'show'])->name('show');
            Route::get('/{employee}/edit', [EmployeeController::class, 'edit'])->name('edit');
            Route::put('/{employee}', [EmployeeController::class, 'update'])->name('update');
            Route::delete('/{employee}', [EmployeeController::class, 'destroy'])->name('destroy');
        });

        // Device management
        Route::prefix('devices')->name('devices.')->group(function () {
            Route::get('/', [DeviceController::class, 'index'])->name('index');
            Route::get('/create', [DeviceController::class, 'create'])->name('create');
            Route::post('/', [DeviceController::class, 'store'])->name('store');
            Route::get('/{device}', [DeviceController::class, 'show'])->name('show');
            Route::put('/{device}', [DeviceController::class, 'update'])->name('update');
            Route::delete('/{device}', [DeviceController::class, 'destroy'])->name('destroy');
        });

        // Shift management
        Route::resource('shifts', ShiftController::class);

        // Department management
        Route::resource('departments', DepartmentController::class);

        // Attendance reports
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/attendance', [ReportController::class, 'attendance'])->name('attendance');
            Route::get('/violations', [ReportController::class, 'violations'])->name('violations');
            Route::get('/daily-summary', [ReportController::class, 'dailySummary'])->name('daily-summary');
        });
    });

    // Super admin routes
    Route::middleware('super_admin')->prefix('admin')->name('admin.')->group(function () {

        // Tenant management
        Route::resource('tenants', TenantController::class);

        // System settings
        Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
        Route::put('/settings', [AdminController::class, 'updateSettings'])->name('settings.update');

        // MQTT broker configuration
        Route::resource('mqtt-brokers', MqttBrokerController::class);

        // System metrics
        Route::get('/metrics', [AdminController::class, 'metrics'])->name('metrics');
    });
});
```

### Gate Usage in Controllers

Example: `app/Http/Controllers/EmployeeController.php`

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EmployeeController extends Controller
{
    public function index()
    {
        // Additional authorization check in controller (optional, middleware already applied)
        Gate::authorize('manageEmployees');

        $employees = Employee::all();
        return Inertia::render('Employees/Index', ['employees' => $employees]);
    }

    public function destroy(Employee $employee)
    {
        Gate::authorize('manageEmployees');

        // Ensure employee belongs to current tenant
        $currentTenantId = tenancy()->getTenant()?->id;
        if ($employee->tenant_id !== $currentTenantId) {
            abort(403, 'Cannot delete employee from different tenant.');
        }

        $employee->delete();
        return redirect()->route('employees.index');
    }
}
```

## Approach

### Multi-Tenant Isolation Strategy

1. **Tenant Context Resolution**
   - Web requests: Resolved by domain/subdomain (handled by existing TenantMiddleware)
   - All authorization checks happen AFTER tenant context is set
   - Middleware order: `auth` → `tenant_access` → `tenant_admin` or `super_admin`

2. **Role-Based Access Control Flow**
   ```
   Request → Authenticate → Resolve Tenant → Check Role → Check Tenant Ownership → Allow/Deny
   ```

3. **Super Admin Exception Handling**
   - Super admins bypass tenant ownership checks
   - Can switch tenant context via special route: `/admin/switch-tenant/{tenant_id}`
   - Original tenant stored in session for audit purposes

4. **Authorization Exception Responses**
   - HTTP 403 for role violations (unauthorized role)
   - HTTP 403 for tenant violations (wrong tenant)
   - HTTP 401 for authentication failures (not logged in)
   - Inertia error pages: `resources/js/pages/errors/403.vue`

### Performance Considerations

1. **Index Optimization**
   - Index on `users.role` for fast role-based queries
   - Index on `users.tenant_id` for tenant isolation checks
   - Composite index on `(tenant_id, role)` for combined queries

2. **Caching Strategy**
   - Cache user roles in session after login (avoid DB lookup on every request)
   - Invalidate role cache on user update
   - Cache Gates results for request lifecycle (Laravel default behavior)

3. **Middleware Order**
   - Place `auth` middleware first to avoid unnecessary tenant resolution for unauthenticated users
   - Place `tenant_access` before role checks to fail fast on tenant violations

## External Dependencies

### Laravel Framework Components
- **Laravel Gates**: Core authorization mechanism (built-in, no package required)
- **Laravel Middleware**: HTTP request filtering (built-in)
- **Illuminate\Support\Facades\Gate**: Facade for Gate registration

### Existing Project Dependencies
- **Tenancy Package**: Assumes tenant context is already resolved (via existing TenantMiddleware)
- **Inertia.js**: Error pages must be created for 403 responses
- **User Model**: Must be enhanced with role methods and tenant relationship

### No New External Packages Required
- This implementation uses Laravel's native authorization features only
- No additional Composer packages needed (e.g., Spatie Permission package is NOT used)
