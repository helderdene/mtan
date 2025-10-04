# API Specification

This is the API specification for the spec detailed in @.agent-os/specs/2025-10-03-super-admin-login/spec.md

## Note on API Scope

This spec focuses on web-based super admin authentication and impersonation. API endpoints are for Inertia.js/internal use only, not external API consumers.

## Endpoints

### POST /login
**Domain:** admin.{domain}

**Purpose:** Authenticate super administrator against central database
**Middleware:** guest:super-admin, web
**Controller:** App\Http\Controllers\SuperAdmin\AuthController@login

**Request Body:**
```json
{
  "email": "admin@example.com",
  "password": "securepassword",
  "remember": true
}
```

**Response (Success - 200):**
```json
{
  "message": "Authenticated successfully",
  "redirect": "/dashboard"
}
```

**Errors:**
- 422 Validation Error: Invalid credentials
- 403 Forbidden: Account is inactive (is_active = false)

---

### POST /logout
**Domain:** admin.{domain}

**Purpose:** End super admin session
**Middleware:** auth:super-admin, web
**Controller:** App\Http\Controllers\SuperAdmin\AuthController@logout

**Response (Success - 200):**
```json
{
  "message": "Logged out successfully",
  "redirect": "/login"
}
```

---

### GET /dashboard
**Domain:** admin.{domain}

**Purpose:** Display super admin dashboard with system overview
**Middleware:** auth:super-admin, web
**Controller:** App\Http\Controllers\SuperAdmin\DashboardController@index

**Response:** Inertia render of SuperAdmin/Dashboard.vue with props:
```php
[
    'stats' => [
        'total_tenants' => 42,
        'active_tenants' => 38,
        'total_users' => 1847,
        'active_impersonations' => 2
    ],
    'recent_tenants' => [...], // Latest 5 tenants
]
```

---

### GET /tenants
**Domain:** admin.{domain}

**Purpose:** List all tenants with search and filtering
**Middleware:** auth:super-admin, web
**Controller:** App\Http\Controllers\SuperAdmin\TenantController@index

**Query Parameters:**
- `search` (string): Filter by tenant name or domain
- `status` (string): active|inactive|all
- `page` (int): Pagination

**Response:** Inertia render of SuperAdmin/Tenants/Index.vue with props:
```php
[
    'tenants' => [
        ['id' => 'abc123', 'name' => 'Acme Corp', 'domain' => 'acme.example.com', ...],
        ...
    ],
    'filters' => ['search' => '...', 'status' => '...'],
]
```

---

### POST /tenants/{tenant}/impersonate/{user}
**Domain:** admin.{domain}

**Purpose:** Start impersonating a tenant user
**Middleware:** auth:super-admin, web
**Controller:** App\Http\Controllers\SuperAdmin\ImpersonationController@start

**Route Parameters:**
- `tenant` (string): Tenant ID
- `user` (int): Tenant user ID

**Business Logic:**
1. Validate super admin is authenticated
2. Verify tenant exists in central.tenants
3. Initialize tenant context
4. Verify user exists in tenant database
5. Store original super admin session in `impersonation_context`
6. Create impersonation log record (started_at = now, ended_at = null)
7. Authenticate as tenant user using `Auth::guard('web')->loginUsingId($userId)`
8. Set session flag: `is_impersonating = true`
9. Redirect to tenant's dashboard with impersonation banner

**Response (Success - 302):**
```json
{
  "redirect": "https://acme.example.com/dashboard"
}
```

**Errors:**
- 404 Not Found: Tenant or user does not exist
- 403 Forbidden: Impersonation not allowed (e.g., user is inactive)
- 500 Server Error: Failed to initialize tenant context

---

### POST /exit-impersonation
**Domain:** Any (works on both admin and tenant subdomains)

**Purpose:** Exit tenant impersonation and return to super admin context
**Middleware:** impersonation, web
**Controller:** App\Http\Controllers\SuperAdmin\ImpersonationController@exit

**Business Logic:**
1. Retrieve `impersonation_context` from session
2. Retrieve active impersonation log record
3. Update log record: `ended_at = now`, `exit_reason = 'manual'`
4. End current tenant session (logout tenant user)
5. End tenant context
6. Restore super admin session from `impersonation_context`
7. Clear impersonation flags from session
8. Redirect to super admin dashboard

**Response (Success - 302):**
```json
{
  "redirect": "https://admin.example.com/dashboard",
  "message": "Impersonation ended"
}
```

**Errors:**
- 400 Bad Request: No active impersonation session
- 500 Server Error: Failed to restore super admin session

---

## Controllers Summary

### AuthController
- `showLogin()`: Render login page (Inertia)
- `login(Request $request)`: Validate credentials, authenticate super admin
- `logout()`: End super admin session

### DashboardController
- `index()`: Render super admin dashboard with system metrics

### TenantController
- `index()`: List tenants with search/filter
- `show($tenantId)`: Show individual tenant details

### ImpersonationController
- `start($tenantId, $userId)`: Begin impersonating tenant user
- `exit()`: End impersonation session

## Error Handling

All controllers should return appropriate HTTP status codes:
- 200: Success
- 302: Redirect (after successful action)
- 400: Bad Request (invalid impersonation state)
- 403: Forbidden (inactive account, unauthorized action)
- 404: Not Found (tenant/user doesn't exist)
- 422: Validation Error (invalid form data)
- 500: Server Error (system failure)

## Audit Logging

Every impersonation action (start/exit) must be logged to `central.impersonation_logs` with:
- Timestamp
- Super admin ID
- Tenant ID and user details
- IP address and user agent
- Exit reason (manual, timeout, forced)
