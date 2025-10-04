# API Specification

This is the API specification for the spec detailed in @.agent-os/specs/2025-10-02-phase1-multi-tenant-foundation/spec.md

## API Overview

**IMPORTANT**: Phase 1 focuses exclusively on **Inertia.js-based web routes** for tenant admin interfaces. There is **NO RESTful API** in Phase 1.

### Phase 1 Scope
- ✅ **Inertia.js Web Routes**: Full CRUD interfaces for employees, shifts, devices, attendance viewing
- ✅ **MQTT Command Publishing**: Internal MQTT commands for device sync (not HTTP-based)
- ✅ **Session-based Auth**: Laravel Fortify with Inertia.js views
- ✅ **Super Admin Routes**: Tenant provisioning and device registry management
- ❌ **RESTful API**: Deferred to Phase 4
- ❌ **API Token Authentication**: Deferred to Phase 4 (Sanctum)
- ❌ **Webhooks**: Deferred to Phase 4
- ❌ **External Integrations**: Deferred to Phase 4

### Why No REST API in Phase 1?
Phase 1 establishes core infrastructure and admin interfaces using Inertia.js for rapid development. The RESTful API (documented in `@docs/detailed_spec_document.md` lines 2262-2361) will be implemented in Phase 4 to support external integrations, mobile apps, and third-party systems.

## Web Routes (Inertia.js)

All web routes use Inertia.js to render Vue components. Routes are tenant-scoped via `TenantMiddleware`.

### Route File: `routes/web.php`

```php
// Tenant-scoped routes
Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // Employee Management
    Route::resource('employees', EmployeeController::class);
    Route::post('employees/{employee}/sync-devices', [EmployeeController::class, 'syncDevices'])
        ->name('employees.sync-devices');

    // Shift Management
    Route::resource('shifts', ShiftController::class);

    // Device Management
    Route::get('devices', [DeviceController::class, 'index'])
        ->name('devices.index');
    Route::get('devices/register', [DeviceController::class, 'create'])
        ->name('devices.create');
    Route::post('devices', [DeviceController::class, 'store'])
        ->name('devices.store');

    // Attendance Records (View Only in Phase 1)
    Route::get('attendance', [AttendanceController::class, 'index'])
        ->name('attendance.index');
    Route::get('attendance/{employee}', [AttendanceController::class, 'show'])
        ->name('attendance.show');
});
```

### Route File: `routes/super-admin.php` (New)

```php
// Super admin routes (central database)
Route::middleware(['auth:super_admin', 'super-admin'])->prefix('super-admin')->group(function () {
    Route::get('/dashboard', [SuperAdmin\DashboardController::class, 'index'])
        ->name('super-admin.dashboard');

    // Tenant Management
    Route::get('/tenants', [SuperAdmin\TenantController::class, 'index'])
        ->name('super-admin.tenants.index');
    Route::get('/tenants/create', [SuperAdmin\TenantController::class, 'create'])
        ->name('super-admin.tenants.create');
    Route::post('/tenants', [SuperAdmin\TenantController::class, 'store'])
        ->name('super-admin.tenants.store');
    Route::post('/tenants/{tenant}/provision', [SuperAdmin\TenantController::class, 'provision'])
        ->name('super-admin.tenants.provision');

    // Device Registry
    Route::resource('device-registry', SuperAdmin\DeviceRegistryController::class);

    // MQTT Broker Configuration
    Route::resource('mqtt-brokers', SuperAdmin\MqttBrokerController::class);
});
```

## Controller Endpoints

### EmployeeController

**Purpose**: Manage employees within tenant context

#### GET /employees
**Route Name**: `employees.index`
**Purpose**: List all employees with pagination and filters
**Parameters**:
- `page` (integer, optional): Page number (default: 1)
- `per_page` (integer, optional): Items per page (default: 15, max: 100)
- `search` (string, optional): Search by name or email
- `department_id` (integer, optional): Filter by department
- `status` (string, optional): Filter by status (active, inactive, all)

**Response**: Inertia render of `employees/Index.vue` with props:
```typescript
{
  employees: PaginatedCollection<Employee>,
  departments: Collection<Department>,
  filters: {
    search: string | null,
    department_id: number | null,
    status: string
  }
}
```

---

#### GET /employees/create
**Route Name**: `employees.create`
**Purpose**: Show employee creation form
**Response**: Inertia render of `employees/Create.vue` with props:
```typescript
{
  departments: Collection<Department>,
  shifts: Collection<Shift>,
  employeeTypes: string[]
}
```

---

#### POST /employees
**Route Name**: `employees.store`
**Purpose**: Create new employee
**Request Body**:
```typescript
{
  employee_code: string,        // Required, unique
  name: string,                 // Required
  email: string | null,         // Nullable, unique
  phone: string | null,
  department_id: number | null,
  designation: string | null,
  employee_type: 'full-time' | 'part-time' | 'contractor' | 'intern',
  card_number: string | null,
  joining_date: string,         // YYYY-MM-DD
  shift_id: number | null       // Assign shift immediately
}
```

**Business Logic**:
1. Validate request
2. Generate `custom_id` (next available EMP###)
3. Create employee record
4. If shift_id provided, create employee_shift record
5. Dispatch `SyncEmployeeToDevices` job to queue
6. Return redirect to employees.index with success message

**Response**: Redirect to `employees.index` with flash message
**Errors**:
- 422 Validation Error: Field-specific errors
- 403 Forbidden: Tenant has reached max_employees limit

---

#### GET /employees/{employee}/edit
**Route Name**: `employees.edit`
**Purpose**: Show employee edit form
**Response**: Inertia render of `employees/Edit.vue` with props:
```typescript
{
  employee: Employee & {
    current_shift: Shift | null,
    device_enrollments: DeviceEnrollment[]
  },
  departments: Collection<Department>,
  shifts: Collection<Shift>,
  employeeTypes: string[]
}
```

---

#### PUT /employees/{employee}
**Route Name**: `employees.update`
**Purpose**: Update employee
**Request Body**: Same as POST /employees
**Business Logic**:
1. Validate request
2. Update employee record
3. If name changed, dispatch `SyncEmployeeToDevices` job
4. If shift_id changed, update employee_shifts
5. Return redirect with success message

**Response**: Redirect to `employees.index` with flash message
**Errors**: 422 Validation Error, 403 Forbidden

---

#### DELETE /employees/{employee}
**Route Name**: `employees.destroy`
**Purpose**: Soft delete employee
**Business Logic**:
1. Soft delete employee
2. Set is_active = false
3. Dispatch `RemoveEmployeeFromDevices` job
4. Return redirect with success message

**Response**: Redirect to `employees.index` with flash message

---

#### POST /employees/{employee}/sync-devices
**Route Name**: `employees.sync-devices`
**Purpose**: Manually trigger device sync for employee
**Business Logic**:
1. Dispatch `SyncEmployeeToDevices` job (high priority)
2. Return JSON response

**Response**:
```json
{
  "message": "Employee sync queued for all devices",
  "employee_id": 123,
  "devices_count": 5
}
```

---

### ShiftController

**Purpose**: Manage shifts within tenant context

#### GET /shifts
**Route Name**: `shifts.index`
**Purpose**: List all shifts
**Response**: Inertia render of `shifts/Index.vue` with props:
```typescript
{
  shifts: Collection<Shift & {
    employee_count: number
  }>
}
```

---

#### POST /shifts
**Route Name**: `shifts.store`
**Purpose**: Create new shift
**Request Body**:
```typescript
{
  name: string,                    // Required
  code: string,                    // Required, unique
  start_time: string,              // HH:MM:SS
  end_time: string,                // HH:MM:SS
  break_start: string | null,      // HH:MM:SS
  break_end: string | null,        // HH:MM:SS
  grace_period_minutes: number,    // Default 15
  working_days: number[],          // [1,2,3,4,5] for Mon-Fri
  description: string | null
}
```

**Validation Rules**:
- start_time and end_time required
- If break_start provided, break_end required (and vice versa)
- working_days must be array of 1-7 (Monday = 1, Sunday = 7)
- grace_period_minutes must be 0-60

**Response**: Redirect to `shifts.index` with flash message

---

### DeviceController

**Purpose**: Manage devices within tenant context

#### GET /devices
**Route Name**: `devices.index`
**Purpose**: List all devices for tenant
**Response**: Inertia render of `devices/Index.vue` with props:
```typescript
{
  devices: Collection<Device & {
    enrollment_count: number,
    is_online: boolean,
    last_seen_minutes_ago: number | null
  }>
}
```

---

#### POST /devices
**Route Name**: `devices.store`
**Purpose**: Register new device for tenant
**Request Body**:
```typescript
{
  device_id: string,        // Required, must exist in central device_registry
  name: string,             // Required
  location: string | null,
  timezone: string          // Default 'UTC'
}
```

**Business Logic**:
1. Validate device_id exists in central device_registry
2. Validate device_id belongs to current tenant
3. Create device record in tenant database
4. Return redirect with success message

**Response**: Redirect to `devices.index` with flash message
**Errors**:
- 422 Validation Error
- 404 Not Found: device_id not in central registry
- 403 Forbidden: device_id belongs to different tenant

---

### AttendanceController

**Purpose**: View attendance records (Phase 1: Read-only)

#### GET /attendance
**Route Name**: `attendance.index`
**Purpose**: List recent attendance records
**Parameters**:
- `date` (string, optional): Filter by date (YYYY-MM-DD)
- `employee_id` (integer, optional): Filter by employee
- `device_id` (integer, optional): Filter by device

**Response**: Inertia render of `attendance/Index.vue` with props:
```typescript
{
  records: PaginatedCollection<AttendanceRecord & {
    employee: Employee,
    device: Device
  }>,
  filters: {
    date: string | null,
    employee_id: number | null,
    device_id: number | null
  },
  employees: Collection<Employee>,
  devices: Collection<Device>
}
```

---

#### GET /attendance/{employee}
**Route Name**: `attendance.show`
**Purpose**: View attendance history for specific employee
**Parameters**:
- `from` (string, optional): Start date (YYYY-MM-DD, default: 30 days ago)
- `to` (string, optional): End date (YYYY-MM-DD, default: today)

**Response**: Inertia render of `attendance/Show.vue` with props:
```typescript
{
  employee: Employee & {
    department: Department,
    current_shift: Shift
  },
  records: PaginatedCollection<AttendanceRecord & {
    device: Device
  }>,
  dateRange: {
    from: string,
    to: string
  },
  summary: {
    total_records: number,
    total_days: number
  }
}
```

---

## Super Admin Routes

### SuperAdmin\TenantController

**Purpose**: Manage tenants (central database operations)

#### POST /super-admin/tenants
**Route Name**: `super-admin.tenants.store`
**Purpose**: Create new tenant
**Request Body**:
```typescript
{
  company_name: string,
  domain: string,              // Unique
  subdomain: string,           // Unique
  subscription_plan: 'trial' | 'basic' | 'professional' | 'enterprise',
  max_employees: number,       // Default based on plan
  max_devices: number,         // Default based on plan
  admin_name: string,
  admin_email: string,
  admin_password: string
}
```

**Business Logic**:
1. Validate request
2. Create tenant record in central database
3. Generate database_name: `tenant_{uuid_without_dashes}`
4. Set trial_ends_at: 30 days from now
5. Return redirect to tenants.index

**Response**: Redirect to `super-admin.tenants.index` with flash message

---

#### POST /super-admin/tenants/{tenant}/provision
**Route Name**: `super-admin.tenants.provision`
**Purpose**: Provision tenant database
**Business Logic**:
1. Call `TenantDatabaseManager::createTenantDatabase($tenant)`
2. Run tenant migrations
3. Seed default data
4. Create default admin user
5. Send welcome email to admin
6. Return redirect with success message

**Response**: Redirect to `super-admin.tenants.index` with flash message
**Errors**:
- 500 Internal Error: Database creation failed
- 422 Validation Error: Tenant already provisioned

---

## MQTT Command Publishing (Internal)

### Device Sync Commands

**These are not HTTP endpoints**, but MQTT commands published by the system:

#### AddPerson Command

**Topic**: `mqtt/command/{device_id}/AddPerson`
**Payload**:
```json
{
  "message_id": "unique_message_id",
  "operator": "SyncEmployeeToDevices",
  "command": "AddPerson",
  "data": {
    "custom_id": "EMP001",
    "person_name": "John Doe",
    "card_number": "12345",
    "metadata": {
      "employee_id": 123,
      "synced_at": "2025-10-02T10:30:00Z"
    }
  }
}
```

**Expected Acknowledgement**:
**Topic**: `mqtt/face/{device_id}/Ack`
**Payload**:
```json
{
  "message_id": "unique_message_id",
  "operator": "AddPerson",
  "status_code": "0",
  "result": "success",
  "timestamp": "2025-10-02T10:30:05Z"
}
```

---

#### DeletePerson Command

**Topic**: `mqtt/command/{device_id}/DeletePerson`
**Payload**:
```json
{
  "message_id": "unique_message_id",
  "operator": "RemoveEmployeeFromDevices",
  "command": "DeletePerson",
  "data": {
    "custom_id": "EMP001"
  }
}
```

---

## Error Handling

### Standard Error Response (Validation)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": [
      "Error message 1",
      "Error message 2"
    ]
  }
}
```

### Authorization Error

```json
{
  "message": "This action is unauthorized.",
  "code": "FORBIDDEN"
}
```

### Tenant Not Found

```json
{
  "message": "Tenant not found or inactive.",
  "code": "TENANT_NOT_FOUND"
}
```

### Rate Limit Error

```json
{
  "message": "Too many requests. Please try again later.",
  "code": "RATE_LIMIT_EXCEEDED",
  "retry_after": 60
}
```

---

## Rate Limiting

**Phase 1 Rate Limits**:
- Web routes: 60 requests per minute per user
- Device registration: 5 requests per minute per tenant
- Employee sync: 10 requests per minute per tenant

**Implementation**: Laravel rate limiter middleware

---

## Authentication

**Phase 1**: Session-based authentication via Laravel Fortify + Inertia.js

- Web routes: `auth` middleware (session-based)
- Super admin routes: `auth:super_admin` guard
- API routes: Not implemented in Phase 1 (deferred to Phase 4)

**Future Phases**:
- Phase 4: Sanctum token-based authentication for RESTful API
- Phase 4: Webhook signature validation

---

## Response Format

### Inertia.js Responses

All web routes return Inertia responses (not JSON):
```php
return Inertia::render('PageComponent', [
    'propName' => $data
]);
```

### AJAX Responses (for internal API calls)

```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": { ... }
}
```

---

## Testing Endpoints

For development and testing, the following CLI commands can simulate API operations:

```bash
# Test MQTT message publishing
php artisan mqtt:publish-test --device=device001 --custom-id=EMP001

# Test tenant provisioning
php artisan tenant:create --name="Test Company" --domain="test.local"
php artisan tenant:provision {tenant_id}

# Test employee sync
php artisan device:sync-employee {employee_id}
```

---

## Future API Extensions (Phase 4)

The following endpoints will be added in Phase 4 for external integrations:

- `POST /api/v1/attendance/records` - Submit attendance record
- `GET /api/v1/attendance/records` - Query attendance records
- `POST /api/v1/employees` - Create employee via API
- `PUT /api/v1/employees/{id}` - Update employee via API
- `POST /api/v1/webhooks/configure` - Configure webhooks
- `GET /api/v1/reports/daily` - Get daily attendance report

**Authentication**: Sanctum token-based authentication
**Rate Limits**: 100 requests per minute per API token
