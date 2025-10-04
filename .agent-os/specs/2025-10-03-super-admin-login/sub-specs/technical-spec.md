# Technical Specification

This is the technical specification for the spec detailed in @.agent-os/specs/2025-10-03-super-admin-login/spec.md

## Technical Requirements

### Authentication Architecture

- **Subdomain Detection Middleware**: Create middleware that inspects the request domain to determine if it's the admin subdomain (admin.*) or a tenant subdomain
- **Super Admin Guard**: Configure a new Laravel authentication guard named 'super-admin' that uses:
  - Provider: Eloquent model pointing to `App\Models\SuperAdmin`
  - Connection: Central database (`central` connection)
  - Session driver with isolated session key (`super_admin_session`)
- **Route Separation**: Split routes into two groups:
  - `routes/super-admin.php` - Super admin routes with 'super-admin' guard
  - `routes/web.php` - Tenant routes with default 'web' guard

### Super Admin Model & Database

- **SuperAdmin Model** (`app/Models/SuperAdmin.php`):
  - Extend `Illuminate\Foundation\Auth\User`
  - Use `central` database connection
  - Table: `super_admins` (already exists per CLAUDE.md)
  - Implement `Authenticatable` contract
  - Include relationships: impersonationLogs()

### Frontend Implementation

- **Super Admin Pages** (`resources/js/pages/super-admin/`):
  - `Login.vue` - Super admin login form (distinct from tenant login)
  - `Dashboard.vue` - System overview with tenant list, metrics
  - `Tenants/Index.vue` - Tenant management interface with impersonation button
  - `Tenants/Show.vue` - Individual tenant details
  - `SystemSettings.vue` - Global system configuration
- **Super Admin Layout** (`resources/js/layouts/SuperAdminLayout.vue`):
  - Separate navigation structure from tenant layout
  - Include "Exit Impersonation" banner when in impersonation mode
  - Super admin branding and navigation menu

### Tenant Impersonation System

- **Impersonation Flow**:
  1. Super admin clicks "Impersonate" on tenant user
  2. Store original super admin session ID in `impersonation_context` session key
  3. Initialize tenant context via tenancy system
  4. Authenticate as target tenant user using `loginUsingId()`
  5. Add impersonation flag to session: `is_impersonating = true`
  6. Redirect to tenant dashboard with impersonation banner
- **Exit Impersonation**:
  1. Read `impersonation_context` to retrieve super admin session
  2. End tenant session and tenancy context
  3. Restore super admin session
  4. Redirect back to super admin dashboard
- **Audit Logging**:
  - Log every impersonation start/end to `central.impersonation_logs` table
  - Include: super_admin_id, tenant_id, user_id, started_at, ended_at, ip_address

### Middleware Stack

- **SuperAdminMiddleware** (`app/Http/Middleware/SuperAdminMiddleware.php`):
  - Check if request is from admin subdomain
  - Require 'super-admin' guard authentication
  - Redirect to admin login if unauthenticated
- **TenantAuthMiddleware** (`app/Http/Middleware/TenantAuthMiddleware.php`):
  - Ensure request is NOT from admin subdomain
  - Use default 'web' guard for tenant authentication
  - Enforce tenant context is set
- **ImpersonationMiddleware** (`app/Http/Middleware/ImpersonationMiddleware.php`):
  - Detect if session has `is_impersonating` flag
  - Share impersonation status with Inertia for UI banner
  - Provide helper to exit impersonation

### Inertia Shared Data

Update `HandleInertiaRequests` middleware to share:
- `auth.isSuperAdmin` - Boolean indicating super admin context
- `auth.isImpersonating` - Boolean indicating impersonation mode
- `auth.impersonatedUser` - Details of impersonated user (if applicable)
- `auth.originalSuperAdmin` - Super admin details when impersonating

### Route Configuration

**routes/super-admin.php:**
```php
Route::domain('admin.{domain}')->group(function () {
    Route::middleware('guest:super-admin')->group(function () {
        Route::get('/login', [SuperAdminAuthController::class, 'showLogin']);
        Route::post('/login', [SuperAdminAuthController::class, 'login']);
    });

    Route::middleware('auth:super-admin')->group(function () {
        Route::get('/dashboard', [SuperAdminDashboardController::class, 'index']);
        Route::get('/tenants', [SuperAdminTenantController::class, 'index']);
        Route::post('/tenants/{tenant}/impersonate/{user}', [SuperAdminImpersonationController::class, 'start']);
        Route::post('/logout', [SuperAdminAuthController::class, 'logout']);
    });
});
```

**Impersonation routes (available in both super admin and tenant context):**
```php
Route::post('/exit-impersonation', [SuperAdminImpersonationController::class, 'exit'])
    ->middleware('impersonation');
```

### Security Considerations

- Super admin sessions use separate session key to prevent conflicts
- Impersonation requires active super admin session validation
- All impersonation actions are audit logged with timestamps and IP addresses
- Tenant users cannot access admin subdomain (enforced at middleware level)
- Super admin credentials never cross into tenant database

### Configuration Updates

**config/auth.php:**
```php
'guards' => [
    'web' => [...], // Existing tenant guard
    'super-admin' => [
        'driver' => 'session',
        'provider' => 'super-admins',
    ],
],

'providers' => [
    'users' => [...], // Existing tenant users
    'super-admins' => [
        'driver' => 'eloquent',
        'model' => App\Models\SuperAdmin::class,
    ],
],
```

**config/session.php:**
```php
// Use domain-specific session cookies
'domain' => env('SESSION_DOMAIN', null),
'secure' => env('SESSION_SECURE_COOKIE', true),
```

## External Dependencies

No new external dependencies required. This implementation uses existing Laravel authentication infrastructure and Inertia.js frontend framework.
