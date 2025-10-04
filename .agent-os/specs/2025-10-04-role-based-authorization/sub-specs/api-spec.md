# API Specification

This is the API specification for the spec detailed in @.agent-os/specs/2025-10-04-role-based-authorization/spec.md

> Created: 2025-10-04
> Version: 1.0.0

## Overview

This spec does not introduce new API endpoints. Instead, it documents which existing routes will be protected with role-based authorization middleware and the expected HTTP responses for unauthorized access attempts.

## No New API Endpoints

This specification focuses solely on middleware implementation. No new routes are created. All authorization is applied to existing routes defined in:
- `routes/web.php` - Web interface routes (Inertia.js)
- `routes/auth.php` - Authentication routes
- `routes/settings.php` - User settings routes
- `routes/api.php` - Future RESTful API routes (Phase 3)

## Route Protection Matrix

### Public Routes (No Authorization Required)

| Route | Method | Middleware | Description |
|-------|--------|-----------|-------------|
| `/` | GET | none | Welcome page |
| `/login` | GET, POST | guest | Login form and submission |
| `/register` | GET, POST | guest | Registration form and submission |
| `/forgot-password` | GET, POST | guest | Password reset request |
| `/reset-password` | GET, POST | guest | Password reset form |

### Authenticated User Routes (tenant_user, tenant_admin, super_admin)

| Route | Method | Middleware | Role Required | Description |
|-------|--------|-----------|---------------|-------------|
| `/dashboard` | GET | auth, tenant_access | tenant_user+ | User dashboard |
| `/settings/profile` | GET, PUT | auth, tenant_access | tenant_user+ | User profile settings |
| `/settings/password` | GET, PUT | auth, tenant_access | tenant_user+ | Change password |
| `/settings/appearance` | GET, PUT | auth, tenant_access | tenant_user+ | Theme preferences |
| `/settings/two-factor` | GET, POST, DELETE | auth, tenant_access | tenant_user+ | 2FA management |
| `/attendance/my-records` | GET | auth, tenant_access | tenant_user+ | View own attendance |
| `/leave-requests` | GET, POST | auth, tenant_access | tenant_user+ | Leave request management |

**Authorization Logic:**
- All authenticated users (regardless of role) can access these routes
- Tenant isolation enforced via `tenant_access` middleware
- Super admins can access these routes for any tenant

### Tenant Admin Routes (tenant_admin, super_admin)

| Route | Method | Middleware | Role Required | Description |
|-------|--------|-----------|---------------|-------------|
| `/employees` | GET | auth, tenant_access, tenant_admin | tenant_admin+ | List employees |
| `/employees/create` | GET, POST | auth, tenant_access, tenant_admin | tenant_admin+ | Create employee |
| `/employees/{id}` | GET | auth, tenant_access, tenant_admin | tenant_admin+ | View employee |
| `/employees/{id}/edit` | GET, PUT | auth, tenant_access, tenant_admin | tenant_admin+ | Edit employee |
| `/employees/{id}` | DELETE | auth, tenant_access, tenant_admin | tenant_admin+ | Delete employee |
| `/devices` | GET | auth, tenant_access, tenant_admin | tenant_admin+ | List devices |
| `/devices/create` | GET, POST | auth, tenant_access, tenant_admin | tenant_admin+ | Create device |
| `/devices/{id}` | GET, PUT, DELETE | auth, tenant_access, tenant_admin | tenant_admin+ | Manage device |
| `/shifts` | GET, POST, PUT, DELETE | auth, tenant_access, tenant_admin | tenant_admin+ | Shift management |
| `/departments` | GET, POST, PUT, DELETE | auth, tenant_access, tenant_admin | tenant_admin+ | Department management |
| `/reports/attendance` | GET | auth, tenant_access, tenant_admin | tenant_admin+ | Attendance reports |
| `/reports/violations` | GET | auth, tenant_access, tenant_admin | tenant_admin+ | Violation reports |
| `/reports/daily-summary` | GET | auth, tenant_access, tenant_admin | tenant_admin+ | Daily summaries |

**Authorization Logic:**
- Only tenant_admin and super_admin can access these routes
- Tenant admins restricted to their own tenant via `tenant_access` middleware
- Super admins can access these routes for any tenant (bypass tenant_access check)

### Super Admin Routes (super_admin only)

| Route | Method | Middleware | Role Required | Description |
|-------|--------|-----------|---------------|-------------|
| `/admin/tenants` | GET | auth, super_admin | super_admin | List all tenants |
| `/admin/tenants/create` | GET, POST | auth, super_admin | super_admin | Create new tenant |
| `/admin/tenants/{id}` | GET | auth, super_admin | super_admin | View tenant details |
| `/admin/tenants/{id}/edit` | GET, PUT | auth, super_admin | super_admin | Edit tenant |
| `/admin/tenants/{id}` | DELETE | auth, super_admin | super_admin | Delete tenant |
| `/admin/settings` | GET, PUT | auth, super_admin | super_admin | System settings |
| `/admin/mqtt-brokers` | GET, POST, PUT, DELETE | auth, super_admin | super_admin | MQTT broker config |
| `/admin/metrics` | GET | auth, super_admin | super_admin | System metrics |
| `/admin/switch-tenant/{id}` | POST | auth, super_admin | super_admin | Switch tenant context |

**Authorization Logic:**
- Only super_admin role can access these routes
- No tenant isolation (platform-wide access)
- Super admins can switch tenant context to access tenant-specific data

## HTTP Response Codes

### Success Responses

| Code | Scenario | Response Body |
|------|----------|--------------|
| 200 | Authorized GET request | Inertia page component or JSON data |
| 201 | Authorized POST request (create) | Redirect or JSON with created resource |
| 302 | Authorized redirect | Redirect to target route |

### Error Responses

| Code | Scenario | Response Body | Example |
|------|----------|--------------|---------|
| 401 | Unauthenticated request | Redirect to `/login` | User not logged in attempts to access `/dashboard` |
| 403 | Insufficient role privileges | Inertia error page or JSON error | tenant_user attempts to access `/employees` |
| 403 | Tenant isolation violation | Inertia error page or JSON error | tenant_admin from Tenant A attempts to access Tenant B data |
| 404 | Route not found | Inertia error page | User accesses non-existent route |

### 403 Forbidden Response Format (Inertia.js)

**Web Requests:**
```php
abort(403, 'Access denied. Tenant admin privileges required.');
```

**Rendered as:**
- Inertia error page: `resources/js/pages/errors/403.vue`
- Contains error message and link to dashboard
- User remains authenticated but cannot access the resource

**API Requests (future Phase 3):**
```json
{
  "message": "Access denied. Tenant admin privileges required.",
  "error": "forbidden",
  "status": 403
}
```

### 401 Unauthorized Response Format

**Web Requests:**
```php
// Handled by Laravel's Authenticate middleware
redirect()->route('login');
```

**API Requests (future Phase 3):**
```json
{
  "message": "Unauthenticated.",
  "error": "unauthorized",
  "status": 401
}
```

## Middleware Application Examples

### Example 1: Tenant Admin Accessing Employee List

**Request:**
```
GET /employees
Headers:
  Cookie: laravel_session=abc123
  X-Inertia: true
```

**Middleware Chain:**
1. `auth` - Verifies user is authenticated (pass ✓)
2. `tenant_access` - Verifies user belongs to current tenant (pass ✓)
3. `tenant_admin` - Verifies user has tenant_admin or super_admin role (pass ✓)

**Response:**
```
200 OK
X-Inertia: true
Content-Type: application/json

{
  "component": "Employees/Index",
  "props": {
    "employees": [...]
  }
}
```

### Example 2: Tenant User Attempting to Access Employee List

**Request:**
```
GET /employees
Headers:
  Cookie: laravel_session=xyz789
  X-Inertia: true
```

**Middleware Chain:**
1. `auth` - Verifies user is authenticated (pass ✓)
2. `tenant_access` - Verifies user belongs to current tenant (pass ✓)
3. `tenant_admin` - Verifies user has tenant_admin or super_admin role (fail ✗)

**Response:**
```
403 Forbidden
X-Inertia: true
Content-Type: application/json

{
  "component": "errors/403",
  "props": {
    "message": "Access denied. Tenant admin privileges required."
  }
}
```

### Example 3: Tenant Admin from Tenant A Attempting to Access Tenant B Data

**Scenario:** Tenant Admin manually changes URL from `tenantA.attendance.local/employees/5` to `tenantB.attendance.local/employees/5`

**Request:**
```
GET /employees/5
Host: tenantB.attendance.local
Headers:
  Cookie: laravel_session=abc123
```

**Middleware Chain:**
1. `auth` - Verifies user is authenticated (pass ✓)
2. Tenant resolution - Sets current tenant to Tenant B based on domain
3. `tenant_access` - Verifies user belongs to Tenant B (fail ✗, user belongs to Tenant A)

**Response:**
```
403 Forbidden
X-Inertia: true

{
  "component": "errors/403",
  "props": {
    "message": "Access denied. You do not belong to this tenant."
  }
}
```

### Example 4: Super Admin Accessing Any Tenant's Data

**Request:**
```
GET /employees
Host: tenantA.attendance.local
Headers:
  Cookie: laravel_session=super123
```

**Middleware Chain:**
1. `auth` - Verifies user is authenticated (pass ✓)
2. `tenant_access` - Checks if super_admin (bypass ✓, super admins exempt from tenant checks)
3. `tenant_admin` - Checks if super_admin or tenant_admin (pass ✓)

**Response:**
```
200 OK
X-Inertia: true

{
  "component": "Employees/Index",
  "props": {
    "employees": [...]
  }
}
```

## Gate Usage in Views (Blade) and Frontend (Vue)

### Blade Templates (if used)

```blade
@can('manageEmployees')
    <a href="{{ route('employees.create') }}">Add Employee</a>
@endcan

@can('isSuperAdmin')
    <a href="{{ route('admin.tenants.index') }}">Manage Tenants</a>
@endcan
```

### Inertia.js Shared Data (Frontend Authorization)

**File: `app/Http/Middleware/HandleInertiaRequests.php`**

```php
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'auth' => [
            'user' => $request->user() ? [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'role' => $request->user()->role,
                'is_super_admin' => $request->user()->isSuperAdmin(),
                'is_tenant_admin' => $request->user()->isTenantAdmin(),
            ] : null,
        ],
        'can' => [
            'manageEmployees' => $request->user() ? Gate::allows('manageEmployees') : false,
            'manageDevices' => $request->user() ? Gate::allows('manageDevices') : false,
            'viewReports' => $request->user() ? Gate::allows('viewReports') : false,
        ],
    ];
}
```

### Vue Component Usage

```vue
<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const auth = page.props.auth
const can = page.props.can
</script>

<template>
  <div>
    <a v-if="can.manageEmployees" :href="route('employees.create')">
      Add Employee
    </a>

    <a v-if="auth.user.is_super_admin" :href="route('admin.tenants.index')">
      Manage Tenants
    </a>
  </div>
</template>
```

**Note:** Frontend authorization is for UX only (hiding buttons). Backend middleware is the security enforcement layer.

## Testing Authorization

### Pest Test Example: Unauthorized Access

```php
test('tenant user cannot access employee management', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'role' => 'tenant_user',
        'tenant_id' => $tenant->id,
    ]);

    $this->actingAs($user)
        ->get(route('employees.index'))
        ->assertStatus(403);
});
```

### Pest Test Example: Tenant Isolation

```php
test('tenant admin cannot access another tenants employees', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $adminA = User::factory()->create([
        'role' => 'tenant_admin',
        'tenant_id' => $tenantA->id,
    ]);

    tenancy()->initialize($tenantB); // Switch to Tenant B context

    $this->actingAs($adminA)
        ->get(route('employees.index'))
        ->assertStatus(403)
        ->assertSee('You do not belong to this tenant');
});
```

### Pest Test Example: Super Admin Bypass

```php
test('super admin can access any tenants data', function () {
    $tenant = Tenant::factory()->create();
    $superAdmin = User::factory()->create(['role' => 'super_admin']);

    tenancy()->initialize($tenant);

    $this->actingAs($superAdmin)
        ->get(route('employees.index'))
        ->assertStatus(200);
});
```

## Future API Routes (Phase 3)

When RESTful API routes are added in Phase 3, the same middleware will be applied:

```php
// routes/api.php
Route::middleware(['auth:sanctum', 'tenant_access'])->group(function () {

    // Tenant admin API routes
    Route::middleware('tenant_admin')->group(function () {
        Route::apiResource('employees', Api\EmployeeController::class);
        Route::apiResource('devices', Api\DeviceController::class);
    });

    // Super admin API routes
    Route::middleware('super_admin')->prefix('admin')->group(function () {
        Route::apiResource('tenants', Api\TenantController::class);
    });
});
```

**API Authentication:**
- Laravel Sanctum for API token authentication (Phase 3)
- Tokens will include role information
- Same Gates and middleware apply to API routes
