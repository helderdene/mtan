# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **Multi-Tenant Attendance Monitoring System** - an enterprise-grade biometric attendance platform built on Laravel + Vue.js + Inertia.js stack. The system provides:

- **Backend**: Laravel 12 (PHP 8.3+) with multi-tenant database architecture
- **Frontend**: Vue 3 + TypeScript + Inertia.js for seamless SPA-like navigation
- **UI**: Tailwind CSS 4 with Reka UI components (headless UI library)
- **Real-Time Processing**: MQTT 5.0 for device communication with Redis-backed queues
- **Multi-Tenancy**: Database-per-tenant isolation with automatic provisioning
- **Biometric Integration**: Device-based facial recognition via MQTT protocol
- **Testing**: Pest PHP for backend testing
- **Build**: Vite with Laravel plugin and Wayfinder for type-safe routing

### Product Mission

See `.agent-os/product/mission-lite.md` for full product vision. The system enables organizations to:
- Track employee attendance via biometric facial recognition devices
- Manage complex shift schedules (fixed, rotating, overnight shifts)
- Automatically detect violations (late arrival, early departure, missing checkout)
- Process attendance events in real-time (<2 seconds) via MQTT
- Provide complete data isolation per tenant for security and compliance

## Development Commands

### Backend (PHP/Laravel)
```bash
# Start development servers (Laravel, queue, logs, Vite) - recommended
composer dev

# Start Laravel development server only
php artisan serve

# Run tests
composer test
# Or run Pest directly
php artisan test

# Run specific test
php artisan test --filter test_name

# Code formatting
./vendor/bin/pint

# Database migrations
php artisan migrate
php artisan migrate:fresh --seed

# Clear caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Frontend (Vue/TypeScript)
```bash
# Start Vite dev server
npm run dev

# Build for production
npm run build

# Build with SSR support
npm run build:ssr

# Linting
npm run lint

# Code formatting
npm run format
npm run format:check
```

## Architecture

### Multi-Tenant Architecture

This system implements **database-per-tenant** isolation for security and compliance:

1. **Central Database**: Stores tenant registry, device mappings, super admins, and MQTT broker configs
2. **Tenant Databases**: Each tenant gets an isolated database (e.g., `tenant_abc123_def456`) with:
   - Employees, departments, shifts, devices
   - Attendance records, daily summaries, violations
   - Leave requests, attendance corrections, audit logs

**Tenant Resolution Flow**:
- Web requests: Resolved by domain/subdomain or API token
- MQTT messages: Resolved by device_id lookup in central database
- All tenant data access goes through tenancy middleware

**Key Classes** (to be implemented):
- `app/Infrastructure/Multitenancy/TenantResolver.php` - Resolves tenant context from request
- `app/Infrastructure/Multitenancy/TenantDatabaseManager.php` - Provisions tenant databases
- `app/Infrastructure/Multitenancy/TenantMiddleware.php` - Ensures tenant context is set

### MQTT Integration Architecture

The system processes biometric attendance events from devices via MQTT:

**MQTT Message Flow**:
1. Device performs facial recognition and publishes to `mqtt/face/{device_id}/Rec`
2. `MQTTClient` receives message with employee `custom_id` and recognition score
3. Message dispatched to Redis queue (`attendance-high-priority`)
4. `ProcessAttendanceEvent` job processes the event:
   - Finds employee by `custom_id` (NOT by biometric data - that stays on device)
   - Determines direction (check-in/out/break) using smart algorithm
   - Creates attendance record and updates daily summary
   - Detects violations and sends notifications
5. Real-time dashboard updates via broadcast events

**IMPORTANT**: Biometric processing happens on devices. The system only:
- Receives employee ID (`custom_id`) from device after recognition
- Validates the ID exists in employee table
- Processes attendance logic (direction, violations, summaries)
- Never stores or processes biometric templates centrally

**Key Classes** (to be implemented):
- `app/Infrastructure/MQTT/MQTTClient.php` - MQTT connection and subscription management
- `app/Infrastructure/MQTT/MessageHandler.php` - Routes MQTT messages to appropriate handlers
- `app/Domain/Attendance/Services/AttendanceProcessor.php` - Core attendance logic
- `app/Domain/Attendance/Services/DirectionDetector.php` - Smart direction detection algorithm

### Smart Direction Detection

The system automatically determines check-in/check-out direction using:
- **Last Record Analysis**: Previous direction determines likely next direction
- **Shift Timing**: Proximity to shift start/end/break times
- **Historical Patterns**: Employee's typical check-in/out times from last 30 days
- **Work Duration**: Minimum time between check-in and check-out
- **Confidence Scoring**: Multi-factor weighted scoring (0-100) for each possible direction

This eliminates the need for separate entry/exit devices or manual direction selection.

### Full-Stack Data Flow (Inertia.js)

This application uses **Inertia.js** to bridge Laravel and Vue without building an API:
1. **Server-side**: Laravel controllers return `Inertia::render('PageName', $data)` instead of views
2. **Client-side**: Vue components receive server data as props automatically
3. **Navigation**: `<Link>` components make XHR requests that replace page components without full reloads
4. **Shared Data**: `HandleInertiaRequests` middleware shares global data (auth user, app name, etc.) to all pages

### Route Organization

Routes are split across multiple files in `routes/`:
- `web.php` - Main routes (home, dashboard, attendance pages)
- `auth.php` - Authentication routes (login, register, password reset, email verification)
- `settings.php` - User settings routes (profile, password, appearance, 2FA)
- `api.php` - RESTful API routes for external integrations (to be added)

All routes use Inertia to render Vue components from `resources/js/pages/`.

### Frontend Structure
```
resources/js/
├── app.ts              # Inertia app setup and initialization
├── ssr.ts              # SSR entry point (if using build:ssr)
├── pages/              # Inertia page components (mapped to routes)
│   ├── auth/           # Authentication pages (Login, Register, etc.)
│   ├── settings/       # Settings pages (Profile, Password, Appearance, TwoFactor)
│   ├── Welcome.vue
│   └── Dashboard.vue
├── layouts/            # Layout wrappers
│   ├── app/            # App layouts (AppSidebarLayout, AppHeaderLayout)
│   ├── auth/           # Auth layouts (AuthCardLayout, AuthSplitLayout, AuthSimpleLayout)
│   ├── settings/       # Settings layout
│   ├── AppLayout.vue   # Main app layout (wrapper for AppSidebarLayout)
│   └── AuthLayout.vue  # Main auth layout
├── components/         # Shared components (AppHeader, AppSidebar, Breadcrumbs, etc.)
│   └── ui/             # Reka UI components (Button, Input, Dialog, etc.)
├── composables/        # Vue composables (useAppearance, useTwoFactorAuth, useInitials)
├── lib/                # Utilities (utils.ts for cn() helper)
└── types/              # TypeScript type definitions
    ├── index.d.ts      # Main types (User, NavItem, AppPageProps, etc.)
    └── globals.d.ts    # Global type augmentations
```

### Backend Structure (Attendance System)

The backend follows **Domain-Driven Design** for the attendance system:

```
app/
├── Console/
│   └── Commands/
│       ├── MQTTConsumerCommand.php      # Long-running MQTT consumer process
│       ├── ProcessAttendanceQueue.php   # Queue worker for attendance processing
│       └── GenerateReportsCommand.php   # Scheduled report generation
├── Domain/                              # Core business logic (to be implemented)
│   ├── Attendance/
│   │   ├── Actions/                     # Single-purpose action classes
│   │   ├── Models/                      # AttendanceRecord, DailyAttendanceSummary, AttendanceViolation
│   │   ├── Services/                    # AttendanceProcessor, DirectionDetector, ViolationChecker
│   │   └── DTOs/                        # Data transfer objects
│   ├── Employee/
│   │   ├── Models/                      # Employee, EmployeeShift, Department
│   │   └── Services/                    # FaceEnrollmentService
│   ├── Shift/
│   │   ├── Models/                      # Shift, ShiftOverride, ShiftRotation
│   │   └── Services/                    # ShiftAssignmentService
│   └── Device/
│       ├── Models/                      # Device
│       └── Services/                    # DeviceSyncService, DeviceCommandService
├── Infrastructure/                      # Technical infrastructure (to be implemented)
│   ├── MQTT/
│   │   ├── MQTTClient.php              # MQTT connection management with auto-reconnect
│   │   ├── MessageHandler.php           # Routes MQTT messages to domain handlers
│   │   └── TopicSubscriber.php          # Topic subscription logic
│   ├── Multitenancy/
│   │   ├── TenantResolver.php           # Resolves tenant from request/MQTT message
│   │   ├── TenantDatabaseManager.php    # Creates and manages tenant databases
│   │   └── TenantMiddleware.php         # Sets tenant context for requests
│   └── Cache/
│       └── CacheKeyGenerator.php        # Generates tenant-isolated cache keys
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/                      # RESTful API endpoints (to be added)
│   │   ├── Dashboard/                   # Dashboard controllers (to be added)
│   │   ├── Auth/                        # Authentication controllers
│   │   └── Settings/                    # Settings controllers
│   ├── Middleware/
│   │   ├── HandleInertiaRequests.php    # Shares data to all Inertia pages
│   │   ├── HandleAppearance.php         # Manages theme preferences
│   │   ├── EnsureTenantExists.php       # Validates tenant context (to be added)
│   │   └── RateLimitMiddleware.php      # API rate limiting (to be added)
│   └── Requests/                        # Form request validation
├── Models/
│   └── User.php                         # System users (will be tenant-scoped)
└── Providers/
    ├── AppServiceProvider.php
    ├── FortifyServiceProvider.php       # Configures Laravel Fortify views and 2FA
    └── TenancyServiceProvider.php       # Registers tenancy services (to be added)
```

**Key Design Principles**:
- **Domain Layer**: Pure business logic, no framework dependencies
- **Infrastructure Layer**: Framework-specific implementations (Laravel, MQTT, Redis)
- **Separation of Concerns**: Actions for single operations, Services for complex workflows
- **DTOs**: Type-safe data transfer between layers

### Database Schema Organization

**Central Database** (`attendance_central`):
- `tenants` - Tenant registry with subscription info
- `device_registry` - Device-to-tenant mappings
- `super_admins` - System administrators
- `mqtt_broker_configs` - MQTT broker configurations
- `tenant_usage_metrics` - Usage tracking per tenant

**Tenant Databases** (e.g., `tenant_abc123_def456`):
- `employees` - Employee records with `custom_id` for device sync
- `device_enrollments` - Tracks sync and enrollment status per device
- `departments` - Organizational structure
- `shifts` - Shift definitions (fixed, rotating, flexible)
- `employee_shifts` - Shift assignments with date ranges
- `shift_overrides` - Special dates (holidays, off days)
- `devices` - Tenant-specific device configurations
- `attendance_records` - Individual attendance events
- `daily_attendance_summaries` - Aggregated daily attendance
- `attendance_violations` - Tracked violations with severity
- `stranger_logs` - Unrecognized face attempts
- `leave_requests` - Leave management
- `attendance_corrections` - Correction requests
- `audit_logs` - Compliance tracking

**Migration Strategy**:
- Central migrations: `database/migrations/central/`
- Tenant migrations: `database/migrations/tenant/`
- Run tenant migrations on provisioning: `php artisan tenants:migrate {tenant_id}`

### Queue Architecture

**Queue Priority Levels**:
- `attendance-high-priority` - Real-time attendance events (QoS 2 from MQTT)
- `attendance-default` - Stranger logs, device status updates
- `reporting` - Long-running report generation
- `notifications` - Email/SMS notifications

**Queue Workers Configuration**:
```bash
# Start multiple queue workers with Supervisor
php artisan queue:work redis --queue=attendance-high-priority --tries=3 --timeout=60
php artisan queue:work redis --queue=attendance-default --tries=3 --timeout=90
php artisan queue:work redis --queue=reporting --tries=1 --timeout=300
```

**Critical Jobs**:
- `ProcessAttendanceEvent` - Main attendance processing logic
- `UpdateDailySummary` - Recalculates daily attendance summaries
- `SendViolationNotifications` - Alerts managers of violations
- `TriggerAttendanceWebhooks` - External system notifications
- `ProcessStrangerEvent` - Logs unrecognized faces
- `UpdateDeviceStatus` - Device health monitoring

### Key Features

1. **Multi-Tenant Authentication**: Laravel Fortify with tenant-scoped users (login, registration, password reset, email verification, 2FA)
2. **Employee Management**: CRUD with automatic `custom_id` generation and device sync tracking
3. **Smart Attendance Processing**: Automatic direction detection with 97% accuracy using multi-factor scoring
4. **Shift Management**: Fixed, rotating, and flexible shifts with grace periods and overnight support
5. **Real-Time Dashboards**: Sub-2-second updates via MQTT → Redis → Laravel Echo/WebSockets
6. **Violation Detection**: Automatic detection of late arrival, early departure, missing checkout, extended breaks
7. **Device Sync**: Employee data synced to devices via MQTT commands (AddPerson, EditPerson, DeletePerson)
8. **Theme System**: Dark/light mode managed via `useAppearance` composable
9. **Type-Safe Routing**: Laravel Wayfinder generates TypeScript route helpers
10. **API Integration**: RESTful API with webhook support for external systems (to be implemented)

### Component Patterns
- **Layout System**: Pages use layouts via `<AppLayout>` or `<AuthLayout>` wrapper components
- **Breadcrumbs**: Pass `breadcrumbs` prop to `AppLayout` for navigation
- **UI Components**: Use Reka UI primitives from `components/ui/` (pre-styled headless components)
- **Icons**: Lucide Vue Next for all icons

### Testing

**Testing Framework**: Pest PHP (behavior-driven testing)

**Test Organization**:
- `tests/Feature/` - Integration tests for API endpoints, MQTT processing, tenant isolation
- `tests/Unit/` - Unit tests for services, direction detection algorithm, violation logic
- `tests/Pest.php` - Global test helpers and setup

**Key Test Patterns**:
```php
// Test with tenant context
test('attendance record creates with tenant isolation', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);

    $employee = Employee::factory()->create();
    $record = AttendanceRecord::factory()->create(['employee_id' => $employee->id]);

    expect($record->exists())->toBeTrue();

    tenancy()->end();
});

// Test direction detection
test('detects check-in when near shift start', function () {
    $employee = Employee::factory()->create();
    $shift = Shift::factory()->create(['start_time' => '09:00:00']);
    $timestamp = Carbon::parse('09:05:00');

    $detector = app(DirectionDetector::class);
    $direction = $detector->detect($employee, $timestamp, null, $shift);

    expect($direction)->toBe('check-in');
});

// Test MQTT message processing
test('processes MQTT attendance message', function () {
    Queue::fake();

    $payload = [
        'info' => [
            'custom_id' => 'EMP001',
            'RecordID' => 'REC123',
            'time' => '2025-10-02 09:05:00',
            'similarity1' => 98.5,
        ]
    ];

    $handler = app(MessageHandler::class);
    $handler->handleRecognitionEvent('mqtt/face/device001/Rec', $payload);

    Queue::assertPushed(ProcessAttendanceEvent::class);
});
```

**Database Testing**:
- Use `:memory:` SQLite for fast unit tests
- Use MySQL for integration tests requiring tenant databases
- Factory patterns for all models: `Employee::factory()->create()`
- Test authentication: `$this->actingAs($user)`

**Commands**:
```bash
# Run all tests
composer test

# Run specific test file
php artisan test tests/Feature/AttendanceProcessingTest.php

# Run tests with coverage
php artisan test --coverage

# Run specific test by name
php artisan test --filter test_direction_detection
```

## Development Workflow

### Setting Up Multi-Tenancy

1. **Configure central database connection** in `config/database.php`:
```php
'central' => [
    'driver' => 'mysql',
    'host' => env('CENTRAL_DB_HOST', '127.0.0.1'),
    'database' => env('CENTRAL_DB_DATABASE', 'attendance_central'),
    // ... other settings
],
```

2. **Run central database migrations**:
```bash
php artisan migrate --database=central --path=database/migrations/central
```

3. **Create a test tenant**:
```bash
php artisan tenant:create \
    --name="Test Company" \
    --domain="test.attendance.local" \
    --subdomain="test"
```

4. **Provision tenant database**:
```bash
php artisan tenant:provision {tenant_id}
```

### Running MQTT Consumer

```bash
# Start MQTT consumer in background
php artisan mqtt:consume &

# Monitor MQTT consumer logs
php artisan pail --filter=mqtt

# Test MQTT connection
php artisan mqtt:test-connection
```

### Working with Attendance Data

```bash
# Process pending attendance records
php artisan attendance:process

# Generate daily summaries for date
php artisan attendance:summarize --date=2025-10-02

# Recalculate violations
php artisan attendance:recalculate-violations --from=2025-10-01 --to=2025-10-31

# Sync employee to all devices
php artisan device:sync-employee {employee_id}

# Test direction detection for employee
php artisan attendance:test-direction {employee_id} --timestamp="2025-10-02 09:05:00"
```

### Cache Management

```bash
# Clear all caches (including tenant-specific)
php artisan cache:clear

# Clear tenant-specific cache
php artisan cache:clear-tenant {tenant_id}

# Warm up cache for tenant
php artisan cache:warm {tenant_id}
```

## Important Implementation Notes

### Employee ID Management

- **`custom_id`**: System-generated unique ID (e.g., `EMP001`, `EMP002`) synced to devices for recognition
- **`employee_code`**: Human-readable code (e.g., company badge number)
- **Device Recognition Flow**: Device recognizes face → sends `custom_id` in MQTT message → system looks up employee by `custom_id`
- **Never use `employee_code` for device sync** - always use `custom_id` as primary identifier

### MQTT Message Format

Expected MQTT payload from devices:
```json
{
  "info": {
    "custom_id": "EMP001",
    "RecordID": "REC123456",
    "time": "2025-10-02 09:05:23",
    "similarity1": 98.5,
    "personId": "12345",
    "personName": "John Doe",
    "facesluiceName": "Main Entrance",
    "VerifyStatus": "1",
    "temperature": 36.5,
    "mask_status": 1,
    "pic": "base64_encoded_image"
  }
}
```

**Important**: `custom_id` is the primary identifier. `personId` is device-internal and should NOT be used for employee lookup.

### Tenant Context Best Practices

- Always resolve tenant before accessing tenant data
- Use `tenancy()->initialize($tenant)` in console commands
- Don't forget `tenancy()->end()` after tenant operations
- Cache tenant data with tenant-specific keys: `tenant:{$tenantId}:key`
- Test tenant isolation thoroughly - ensure no cross-tenant data leakage

## Documentation References

- **Product Mission**: `.agent-os/product/mission.md` - Full product vision and features
- **Tech Stack**: `.agent-os/product/tech-stack.md` - Complete technical architecture
- **Roadmap**: `.agent-os/product/roadmap.md` - 5-phase development plan
- **Detailed Spec**: `docs/detailed_spec_document.md` - In-depth technical specifications (2000+ lines)
