# Database Schema

This is the database schema implementation for the spec detailed in @.agent-os/specs/2025-10-02-phase1-multi-tenant-foundation/spec.md

## Schema Overview

The system uses a **two-database architecture**:
1. **Central Database** (`attendance_central`): Manages tenants, device registry, super admins
2. **Tenant Databases** (`tenant_{uuid}`): Isolated databases per tenant with employee, attendance, and shift data

## Central Database Schema

### Migration: `create_tenants_table.php`

```php
Schema::create('tenants', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('company_name');
    $table->string('domain')->unique();
    $table->string('subdomain', 100)->unique();
    $table->string('database_name', 100);
    $table->string('database_host');
    $table->enum('subscription_plan', ['trial', 'basic', 'professional', 'enterprise'])
          ->default('trial');
    $table->unsignedInteger('max_employees')->default(50);
    $table->unsignedInteger('max_devices')->default(5);
    $table->json('features')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamp('trial_ends_at')->nullable();
    $table->timestamp('subscription_starts_at')->nullable();
    $table->timestamp('subscription_ends_at')->nullable();
    $table->timestamps();

    $table->index('domain');
    $table->index('is_active');
    $table->index(['subscription_plan', 'subscription_ends_at']);
});
```

**Rationale**:
- UUID primary key provides better distribution and security than auto-increment
- Domain and subdomain allow flexible tenant routing
- Subscription tracking enables automated trial expiration and billing
- JSON features field allows per-tenant feature flags
- Indexes on domain and is_active optimize tenant resolution queries

---

### Migration: `create_device_registry_table.php`

```php
Schema::create('device_registry', function (Blueprint $table) {
    $table->id();
    $table->uuid('tenant_id');
    $table->string('device_id', 100)->unique();
    $table->string('device_name');
    $table->string('device_type', 50);
    $table->string('location')->nullable();
    $table->string('ip_address', 45)->nullable();
    $table->string('mac_address', 17)->nullable();
    $table->string('firmware_version', 50)->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamp('last_seen_at')->nullable();
    $table->timestamp('registered_at')->useCurrent();
    $table->json('metadata')->nullable();
    $table->timestamps();

    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
    $table->index(['tenant_id', 'device_id']);
    $table->index(['device_id', 'is_active']);
});
```

**Rationale**:
- Central registry enables MQTT message routing to correct tenant
- device_id must be globally unique across all tenants
- Composite index on (tenant_id, device_id) optimizes tenant-specific device lookups
- last_seen_at tracks device health for monitoring
- Cascade delete ensures cleanup when tenant is deleted
- ip_address supports both IPv4 (15 chars) and IPv6 (45 chars)

---

### Migration: `create_super_admins_table.php`

```php
Schema::create('super_admins', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('password');
    $table->timestamp('email_verified_at')->nullable();
    $table->text('two_factor_secret')->nullable();
    $table->boolean('two_factor_enabled')->default(false);
    $table->timestamp('last_login_at')->nullable();
    $table->string('last_login_ip', 45)->nullable();
    $table->boolean('is_active')->default(true);
    $table->rememberToken();
    $table->timestamps();
});
```

**Rationale**:
- Separate from tenant users for security isolation
- Two-factor authentication for enhanced security
- Login tracking for audit purposes
- is_active allows disabling without deletion

---

### Migration: `create_mqtt_broker_configs_table.php`

```php
Schema::create('mqtt_broker_configs', function (Blueprint $table) {
    $table->id();
    $table->string('name', 100);
    $table->string('host');
    $table->unsignedInteger('port')->default(1883);
    $table->enum('protocol', ['tcp', 'tls', 'ws', 'wss'])->default('tcp');
    $table->string('username')->nullable();
    $table->string('password')->nullable();
    $table->string('client_id')->nullable();
    $table->boolean('clean_session')->default(true);
    $table->unsignedInteger('keep_alive')->default(60);
    $table->unsignedTinyInteger('qos')->default(1);
    $table->boolean('is_primary')->default(false);
    $table->boolean('is_active')->default(true);
    $table->unsignedInteger('priority')->default(0);
    $table->unsignedInteger('max_connections')->default(1000);
    $table->string('certificate_path', 500)->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();

    $table->index(['is_active', 'priority']);
});
```

**Rationale**:
- Supports multiple MQTT brokers for high availability
- Priority and is_primary allow broker failover configuration
- TLS certificate path for secure connections
- QoS configuration per broker for message reliability tuning

---

### Migration: `create_tenant_usage_metrics_table.php`

```php
Schema::create('tenant_usage_metrics', function (Blueprint $table) {
    $table->id();
    $table->uuid('tenant_id');
    $table->date('metric_date');
    $table->unsignedInteger('total_employees')->default(0);
    $table->unsignedInteger('active_employees')->default(0);
    $table->unsignedInteger('total_devices')->default(0);
    $table->unsignedInteger('active_devices')->default(0);
    $table->unsignedInteger('attendance_records_count')->default(0);
    $table->decimal('storage_used_mb', 12, 2)->default(0);
    $table->unsignedInteger('api_calls_count')->default(0);
    $table->unsignedInteger('webhook_calls_count')->default(0);
    $table->timestamp('created_at')->useCurrent();

    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
    $table->unique(['tenant_id', 'metric_date']);
    $table->index(['tenant_id', 'metric_date']);
});
```

**Rationale**:
- Daily metrics for billing and usage analytics
- Unique constraint prevents duplicate metrics for same date
- Tracks resource usage for quota enforcement
- Can be aggregated for monthly/yearly reports

---

## Tenant Database Schema

Each tenant gets an isolated database with the following schema:

### Migration: `create_departments_table.php`

```php
Schema::create('departments', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('code', 50)->unique();
    $table->unsignedBigInteger('parent_id')->nullable();
    $table->unsignedBigInteger('manager_id')->nullable();
    $table->text('description')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->foreign('parent_id')->references('id')->on('departments')->onDelete('set null');
    $table->index('parent_id');
    $table->index('is_active');
});
```

**Rationale**:
- Hierarchical structure with parent_id for organizational charts
- manager_id foreign key added after employees table created
- Code field for integration with external HR systems
- Set null on delete preserves data integrity when parent/manager removed

---

### Migration: `create_employees_table.php`

```php
Schema::create('employees', function (Blueprint $table) {
    $table->id();
    $table->string('employee_code', 50)->unique();
    $table->string('custom_id', 50)->unique()->comment('System-generated ID synced to devices');
    $table->string('name');
    $table->string('email')->unique()->nullable();
    $table->string('phone', 20)->nullable();
    $table->unsignedBigInteger('department_id')->nullable();
    $table->string('designation', 100)->nullable();
    $table->enum('employee_type', ['full-time', 'part-time', 'contractor', 'intern'])
          ->default('full-time');
    $table->string('card_number', 50)->unique()->nullable();
    $table->date('joining_date');
    $table->date('leaving_date')->nullable();
    $table->unsignedBigInteger('reporting_manager_id')->nullable();
    $table->boolean('is_active')->default(true);
    $table->json('metadata')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
    $table->foreign('reporting_manager_id')->references('id')->on('employees')->onDelete('set null');
    $table->index('employee_code');
    $table->index('custom_id');
    $table->index('is_active');
    $table->index('department_id');
    $table->fullText(['name', 'email']);
});
```

**Rationale**:
- **custom_id**: Unique identifier synced to devices (e.g., EMP001, EMP002)
- **employee_code**: Human-readable code (e.g., badge number)
- Distinction between custom_id (device lookup) and employee_code (display/reporting)
- Soft deletes preserve historical attendance data
- Full-text index on name/email for search functionality
- Indexes on custom_id and employee_code optimize device lookups and reports

---

### Migration: `create_shifts_table.php`

```php
Schema::create('shifts', function (Blueprint $table) {
    $table->id();
    $table->string('name', 100);
    $table->string('code', 50)->unique();
    $table->time('start_time');
    $table->time('end_time');
    $table->time('break_start')->nullable();
    $table->time('break_end')->nullable();
    $table->unsignedInteger('grace_period_minutes')->default(15);
    $table->unsignedInteger('early_departure_threshold_minutes')->default(15);
    $table->unsignedInteger('overtime_threshold_minutes')->default(30);
    $table->unsignedInteger('half_day_threshold_minutes')->default(240);
    $table->json('working_days')->comment('[1,2,3,4,5] for Mon-Fri');
    $table->enum('shift_type', ['fixed', 'flexible', 'rotating'])->default('fixed');
    $table->boolean('is_overnight')->default(false)->comment('Shift crosses midnight');
    $table->string('color_code', 7)->default('#3498db');
    $table->boolean('is_active')->default(true);
    $table->text('description')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();

    $table->index('is_active');
    $table->index('shift_type');
});
```

**Rationale**:
- Grace period for late arrivals (business rule flexibility)
- Thresholds enable violation detection (Phase 2)
- working_days JSON array allows custom week configurations
- is_overnight flag for shifts crossing midnight (e.g., 22:00-07:00)
- color_code for UI visualization in calendars/dashboards
- Phase 1 uses only 'fixed' shift_type

---

### Migration: `create_employee_shifts_table.php`

```php
Schema::create('employee_shifts', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('employee_id');
    $table->unsignedBigInteger('shift_id');
    $table->date('effective_from');
    $table->date('effective_to')->nullable();
    $table->unsignedBigInteger('assigned_by')->nullable();
    $table->string('assignment_reason', 500)->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
    $table->foreign('shift_id')->references('id')->on('shifts')->onDelete('cascade');
    $table->foreign('assigned_by')->references('id')->on('users')->onDelete('set null');
    $table->index(['employee_id', 'effective_from', 'effective_to']);
    $table->index('shift_id');
    $table->index('is_active');
});
```

**Rationale**:
- Date range (effective_from, effective_to) supports shift changes over time
- Composite index optimizes queries for "which shift on date X?"
- assigned_by tracks who made the assignment for audit trail
- Phase 1 enforces one active shift per employee (business rule in application)
- Cascade delete ensures cleanup when employee or shift deleted

---

### Migration: `create_devices_table.php`

```php
Schema::create('devices', function (Blueprint $table) {
    $table->id();
    $table->string('device_id', 100)->unique()->comment('Matches central registry');
    $table->string('name');
    $table->string('location')->nullable();
    $table->string('device_type', 50);
    $table->string('ip_address', 45)->nullable();
    $table->string('mac_address', 17)->nullable();
    $table->string('firmware_version', 50)->nullable();
    $table->unsignedInteger('capacity')->nullable()->comment('Max face templates');
    $table->unsignedInteger('current_count')->default(0);
    $table->boolean('is_entry_device')->default(true);
    $table->boolean('is_exit_device')->default(true);
    $table->string('timezone', 50)->default('UTC');
    $table->json('settings')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamp('last_sync_at')->nullable();
    $table->timestamp('last_heartbeat_at')->nullable();
    $table->timestamps();

    $table->index('device_id');
    $table->index('is_active');
});
```

**Rationale**:
- device_id matches central registry for cross-database lookup
- Capacity tracking prevents device overload
- is_entry_device/is_exit_device flags for future direction detection logic
- Timezone per device handles multi-region deployments
- last_sync_at and last_heartbeat_at for device health monitoring

---

### Migration: `create_device_enrollments_table.php`

```php
Schema::create('device_enrollments', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('employee_id');
    $table->unsignedBigInteger('device_id');
    $table->timestamp('sync_requested_at')->nullable()->comment('When system sent sync command');
    $table->timestamp('enrolled_at')->nullable()->comment('When device confirmed enrollment');
    $table->boolean('is_enrolled')->default(false);
    $table->decimal('enrollment_quality', 5, 2)->nullable()->comment('Quality score from device');
    $table->enum('sync_status', ['pending', 'synced', 'enrolled', 'failed'])->default('pending');
    $table->unsignedInteger('sync_attempts')->default(0);
    $table->text('last_sync_error')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();

    $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
    $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');
    $table->unique(['employee_id', 'device_id']);
    $table->index('sync_status');
    $table->index('is_enrolled');
});
```

**Rationale**:
- Tracks employee sync status per device for troubleshooting
- sync_status progression: pending → synced → enrolled
- enrollment_quality from device helps identify poor face captures
- sync_attempts and last_sync_error enable retry logic and debugging
- Unique constraint prevents duplicate enrollments

---

### Migration: `create_attendance_records_table.php`

```php
Schema::create('attendance_records', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('employee_id');
    $table->unsignedBigInteger('device_id');
    $table->string('record_id', 100)->unique()->comment('From device');
    $table->timestamp('timestamp');
    $table->enum('direction', ['check-in', 'check-out', 'break-out', 'break-in'])
          ->default('check-in');
    $table->decimal('recognition_score', 5, 2)->nullable()->comment('Face match confidence 0-100');
    $table->decimal('temperature', 4, 1)->nullable()->comment('Body temperature if available');
    $table->boolean('mask_detected')->nullable();
    $table->string('photo_path', 500)->nullable();
    $table->enum('processing_status', ['pending', 'processed', 'error'])->default('processed');
    $table->unsignedBigInteger('shift_id')->nullable();
    $table->boolean('is_late')->default(false);
    $table->boolean('is_early_departure')->default(false);
    $table->boolean('is_overtime')->default(false);
    $table->integer('late_minutes')->default(0);
    $table->integer('early_departure_minutes')->default(0);
    $table->text('remarks')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();

    $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
    $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');
    $table->foreign('shift_id')->references('id')->on('shifts')->onDelete('set null');
    $table->index(['employee_id', 'timestamp']);
    $table->index(['device_id', 'timestamp']);
    $table->index(DB::raw('DATE(timestamp)'));
    $table->index('direction');
    $table->index(['is_late', 'is_early_departure']);
});
```

**Rationale**:
- record_id from device prevents duplicate processing
- timestamp index optimized for date range queries
- Composite index (employee_id, timestamp) for employee attendance history
- Phase 1: direction defaults to 'check-in', smart detection in Phase 2
- Violation fields (is_late, late_minutes) prepared for Phase 2
- photo_path supports future photo storage (Phase 3)
- DATE(timestamp) functional index for daily grouping queries

---

### Migration: `create_users_table.php` (Tenant-specific)

```php
// Modify existing users table migration for tenant context
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->enum('role', ['tenant_admin', 'hr_manager', 'facility_manager', 'employee'])
          ->default('employee');
    $table->unsignedBigInteger('employee_id')->nullable();
    $table->boolean('is_active')->default(true);
    $table->rememberToken();
    $table->timestamps();

    $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
    $table->index('email');
    $table->index(['role', 'is_active']);
});
```

**Rationale**:
- Tenant-scoped users (separate from central super_admins)
- Role-based access control for tenant features
- employee_id links user account to employee record (optional)
- Composite index on (role, is_active) optimizes authorization queries

---

## Migration Execution Order

**Central Database** (`database/migrations/central/`):
1. `create_tenants_table.php`
2. `create_device_registry_table.php`
3. `create_super_admins_table.php`
4. `create_mqtt_broker_configs_table.php`
5. `create_tenant_usage_metrics_table.php`

**Tenant Database** (`database/migrations/tenant/`):
1. `create_departments_table.php`
2. `create_employees_table.php`
3. `add_manager_to_departments_table.php` (foreign key to employees)
4. `create_shifts_table.php`
5. `create_users_table.php` (modify existing)
6. `create_employee_shifts_table.php`
7. `create_devices_table.php`
8. `create_device_enrollments_table.php`
9. `create_attendance_records_table.php`

## Seeder Data

**Central Database Seeder** (`database/seeders/CentralDatabaseSeeder.php`):
- Create default super admin
- Create default MQTT broker configuration
- Optionally create demo tenant for testing

**Tenant Database Seeder** (`database/seeders/TenantDatabaseSeeder.php`):
- Create default departments (e.g., "Administration", "Operations")
- Create default shifts (Morning 09:00-18:00, Evening 14:00-23:00, Night 22:00-07:00)
- Create default settings in metadata table
- Create default admin user for tenant

## Data Integrity Rules

1. **Tenant Isolation**: All queries must be scoped to tenant database
2. **Soft Deletes**: Employees, shifts use soft deletes to preserve historical data
3. **Cascade Deletes**: Attendance records cascade delete with employee
4. **Set Null**: Shift assignment references use set null to preserve records
5. **Unique Constraints**:
   - device_id globally unique in device_registry
   - custom_id unique per tenant in employees
   - record_id unique per tenant in attendance_records
6. **Foreign Key Constraints**: All relationships enforced at database level
7. **Default Values**: Sensible defaults for all nullable fields
8. **Timestamps**: All tables use Laravel timestamps for audit trail

## Performance Optimizations

1. **Indexes**: Strategic indexes on frequently queried columns
2. **Composite Indexes**: Multi-column indexes for common query patterns
3. **Full-Text Search**: On employee name/email for search functionality
4. **Partitioning** (Future): Partition attendance_records by month for large datasets
5. **Archival** (Future): Move old attendance records to archive tables after 2 years

## Backup Strategy

- **Central Database**: Daily backups with 30-day retention
- **Tenant Databases**: Daily backups per tenant with 30-day retention
- **Point-in-Time Recovery**: Enable binary logging for 7-day recovery window
- **Backup Testing**: Monthly restore tests to verify backup integrity
