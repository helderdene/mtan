# Multi-Tenant Attendance Monitoring System
## Enhanced Technical Specification v3.0

---

## Document Control

**Version:** 3.0  
**Status:** Production Ready  
**Classification:** Internal - Technical  
**Last Updated:** October 2025  
**Next Review:** January 2026

---

## Table of Contents

1. [Executive Overview](#1-executive-overview)
2. [Detailed Architecture](#2-detailed-architecture)
3. [Database Design](#3-database-design)
4. [MQTT Integration Deep Dive](#4-mqtt-integration-deep-dive)
5. [Smart Direction Detection Algorithm](#5-smart-direction-detection-algorithm)
6. [Shift Management System](#6-shift-management-system)
7. [Attendance Processing Engine](#7-attendance-processing-engine)
8. [Reporting Framework](#8-reporting-framework)
9. [API Specifications](#9-api-specifications)
10. [Security & Compliance](#10-security--compliance)
11. [Performance Optimization](#11-performance-optimization)
12. [DevOps & Deployment](#12-devops--deployment)
13. [Testing Strategy](#13-testing-strategy)
14. [Monitoring & Observability](#14-monitoring--observability)
15. [Error Handling & Recovery](#15-error-handling--recovery)

---

## 1. Executive Overview

### 1.1 System Purpose

Enterprise-grade biometric attendance system supporting unlimited tenants with complete data isolation, real-time facial recognition processing via MQTT protocol, intelligent shift management, and comprehensive analytics.

### 1.2 Key Differentiators

- **True Multi-Tenancy**: Complete database isolation per tenant
- **Device-Based Biometric Processing**: All facial recognition performed on edge devices, system only validates IDs
- **Smart Direction Detection**: Context-aware check-in/out determination based on timing and patterns
- **Real-time Processing**: Sub-2-second MQTT message processing
- **Scalable Architecture**: Handles 10,000+ employees per tenant
- **Flexible Shift System**: Supports fixed, rotating, and flexible schedules
- **Zero Downtime Deployment**: Rolling updates with health checks

### 1.3 Technical Highlights

- Laravel 12+ with PHP 8.3+ (JIT compilation enabled)
- Multi-database tenancy with automatic provisioning
- MQTT 5.0 with QoS 2 for critical messages
- Redis cluster for distributed caching and queues
- Horizontal scaling across all tiers
- 99.95% uptime SLA with automated failover

---

## 2. Detailed Architecture

### 2.1 System Components

#### 2.1.1 Web Application Tier

```php
// Application Structure
app/
├── Console/
│   ├── Commands/
│   │   ├── MQTTConsumerCommand.php
│   │   ├── ProcessAttendanceQueue.php
│   │   └── GenerateReportsCommand.php
│   └── Kernel.php
├── Domain/
│   ├── Attendance/
│   │   ├── Actions/
│   │   │   ├── ProcessAttendanceAction.php
│   │   │   ├── DetermineDirectionAction.php
│   │   │   └── UpdateDailySummaryAction.php
│   │   ├── Models/
│   │   │   ├── AttendanceRecord.php
│   │   │   ├── DailyAttendanceSummary.php
│   │   │   └── AttendanceViolation.php
│   │   ├── Services/
│   │   │   ├── AttendanceProcessor.php
│   │   │   ├── DirectionDetector.php
│   │   │   └── ViolationChecker.php
│   │   └── DTOs/
│   │       └── AttendanceEventDTO.php
│   ├── Employee/
│   │   ├── Models/
│   │   │   ├── Employee.php
│   │   │   ├── EmployeeShift.php
│   │   │   └── Department.php
│   │   └── Services/
│   │       └── FaceEnrollmentService.php
│   ├── Shift/
│   │   ├── Models/
│   │   │   ├── Shift.php
│   │   │   ├── ShiftOverride.php
│   │   │   └── ShiftRotation.php
│   │   └── Services/
│   │       └── ShiftAssignmentService.php
│   └── Device/
│       ├── Models/
│       │   └── Device.php
│       └── Services/
│           ├── DeviceSyncService.php
│           └── DeviceCommandService.php
├── Infrastructure/
│   ├── MQTT/
│   │   ├── MQTTClient.php
│   │   ├── MessageHandler.php
│   │   └── TopicSubscriber.php
│   ├── Multitenancy/
│   │   ├── TenantResolver.php
│   │   ├── TenantDatabaseManager.php
│   │   └── TenantMiddleware.php
│   └── Cache/
│       └── CacheKeyGenerator.php
└── Http/
    ├── Controllers/
    │   ├── Api/V1/
    │   │   ├── AttendanceController.php
    │   │   ├── EmployeeController.php
    │   │   ├── ShiftController.php
    │   │   └── ReportController.php
    │   └── Dashboard/
    │       └── DashboardController.php
    └── Middleware/
        ├── EnsureTenantExists.php
        ├── RateLimitMiddleware.php
        └── ValidateWebhookSignature.php
```

#### 2.1.2 Queue Configuration

```php
// config/queue.php
return [
    'default' => env('QUEUE_CONNECTION', 'redis'),
    
    'connections' => [
        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => 90,
            'block_for' => null,
        ],
        
        'attendance-high' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => 'attendance-high-priority',
            'retry_after' => 60,
            'block_for' => 5,
        ],
        
        'attendance-default' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => 'attendance-default',
            'retry_after' => 90,
        ],
        
        'reporting' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => 'reporting',
            'retry_after' => 300,
        ],
        
        'notifications' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => 'notifications',
            'retry_after' => 60,
        ],
    ],
    
    'failed' => [
        'driver' => 'database-uuids',
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],
];
```

### 2.2 Multi-Tenancy Implementation

#### 2.2.1 Tenant Bootstrap Process

```php
// app/Infrastructure/Multitenancy/TenantResolver.php
namespace App\Infrastructure\Multitenancy;

use Illuminate\Http\Request;
use Stancl\Tenancy\Contracts\TenantResolver as TenantResolverContract;

class TenantResolver implements TenantResolverContract
{
    public function resolve(Request $request): ?string
    {
        // Try domain resolution first
        if ($tenantId = $this->resolveByDomain($request)) {
            return $tenantId;
        }
        
        // Fallback to API token for API requests
        if ($request->is('api/*')) {
            return $this->resolveByApiToken($request);
        }
        
        // For MQTT messages
        if ($deviceId = $request->input('device_id')) {
            return $this->resolveByDevice($deviceId);
        }
        
        return null;
    }
    
    protected function resolveByDomain(Request $request): ?string
    {
        $hostname = $request->getHost();
        
        return Cache::remember(
            "tenant:domain:{$hostname}",
            3600,
            fn() => Tenant::where('domain', $hostname)->first()?->id
        );
    }
    
    protected function resolveByDevice(string $deviceId): ?string
    {
        return Cache::remember(
            "tenant:device:{$deviceId}",
            3600,
            function() use ($deviceId) {
                return DB::connection('central')
                    ->table('device_registry')
                    ->where('device_id', $deviceId)
                    ->value('tenant_id');
            }
        );
    }
    
    protected function resolveByApiToken(Request $request): ?string
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return null;
        }
        
        return Cache::remember(
            "tenant:token:{$token}",
            1800,
            fn() => PersonalAccessToken::findToken($token)?->tenant_id
        );
    }
}
```

#### 2.2.2 Automatic Tenant Provisioning

```php
// app/Infrastructure/Multitenancy/TenantDatabaseManager.php
namespace App\Infrastructure\Multitenancy;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class TenantDatabaseManager
{
    public function createTenantDatabase(Tenant $tenant): void
    {
        $databaseName = "tenant_{$tenant->id}";
        
        DB::statement("CREATE DATABASE IF NOT EXISTS `{$databaseName}` 
                       CHARACTER SET utf8mb4 
                       COLLATE utf8mb4_unicode_ci");
        
        // Update tenant record with database name
        $tenant->update([
            'database_name' => $databaseName,
            'database_host' => config('database.connections.mysql.host'),
        ]);
        
        // Run migrations for new tenant
        $this->runTenantMigrations($tenant);
        
        // Seed default data
        $this->seedTenantDefaults($tenant);
        
        // Create default admin user
        $this->createDefaultAdmin($tenant);
    }
    
    protected function runTenantMigrations(Tenant $tenant): void
    {
        tenancy()->initialize($tenant);
        
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--force' => true,
            '--path' => 'database/migrations/tenant',
        ]);
        
        tenancy()->end();
    }
    
    protected function seedTenantDefaults(Tenant $tenant): void
    {
        tenancy()->initialize($tenant);
        
        // Create default shifts
        $this->createDefaultShifts();
        
        // Create default departments
        $this->createDefaultDepartments();
        
        // Set up default settings
        $this->createDefaultSettings();
        
        tenancy()->end();
    }
    
    protected function createDefaultShifts(): void
    {
        $defaultShifts = [
            [
                'name' => 'Morning Shift',
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
                'break_start' => '12:00:00',
                'break_end' => '13:00:00',
                'grace_period_minutes' => 15,
                'working_days' => [1, 2, 3, 4, 5],
                'shift_type' => 'fixed',
            ],
            [
                'name' => 'Evening Shift',
                'start_time' => '14:00:00',
                'end_time' => '23:00:00',
                'break_start' => '18:00:00',
                'break_end' => '19:00:00',
                'grace_period_minutes' => 15,
                'working_days' => [1, 2, 3, 4, 5],
                'shift_type' => 'fixed',
            ],
            [
                'name' => 'Night Shift',
                'start_time' => '22:00:00',
                'end_time' => '07:00:00',
                'break_start' => '02:00:00',
                'break_end' => '03:00:00',
                'grace_period_minutes' => 15,
                'working_days' => [1, 2, 3, 4, 5],
                'shift_type' => 'fixed',
            ],
        ];
        
        foreach ($defaultShifts as $shift) {
            Shift::create($shift);
        }
    }
}
```

---

## 3. Database Design

### 3.1 Central Database Schema

```sql
-- Central database for tenant management
CREATE DATABASE attendance_central CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE attendance_central;

-- Tenants registry
CREATE TABLE tenants (
    id CHAR(36) PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    domain VARCHAR(255) UNIQUE NOT NULL,
    subdomain VARCHAR(100) UNIQUE NOT NULL,
    database_name VARCHAR(100) NOT NULL,
    database_host VARCHAR(255) NOT NULL,
    subscription_plan ENUM('trial', 'basic', 'professional', 'enterprise') NOT NULL DEFAULT 'trial',
    max_employees INT UNSIGNED NOT NULL DEFAULT 50,
    max_devices INT UNSIGNED NOT NULL DEFAULT 5,
    features JSON,
    is_active BOOLEAN DEFAULT TRUE,
    trial_ends_at TIMESTAMP NULL,
    subscription_starts_at TIMESTAMP NULL,
    subscription_ends_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_domain (domain),
    INDEX idx_active (is_active),
    INDEX idx_subscription (subscription_plan, subscription_ends_at)
) ENGINE=InnoDB;

-- Device to tenant mapping
CREATE TABLE device_registry (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    device_id VARCHAR(100) UNIQUE NOT NULL,
    device_name VARCHAR(255) NOT NULL,
    device_type VARCHAR(50) NOT NULL,
    location VARCHAR(255),
    ip_address VARCHAR(45),
    mac_address VARCHAR(17),
    firmware_version VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    last_seen_at TIMESTAMP NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant_device (tenant_id, device_id),
    INDEX idx_device_active (device_id, is_active)
) ENGINE=InnoDB;

-- System administrators
CREATE TABLE super_admins (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email_verified_at TIMESTAMP NULL,
    two_factor_secret TEXT,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    last_login_at TIMESTAMP NULL,
    last_login_ip VARCHAR(45),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- MQTT broker configurations
CREATE TABLE mqtt_broker_configs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    host VARCHAR(255) NOT NULL,
    port INT UNSIGNED NOT NULL DEFAULT 1883,
    protocol ENUM('tcp', 'tls', 'ws', 'wss') DEFAULT 'tcp',
    username VARCHAR(255),
    password VARCHAR(255),
    client_id VARCHAR(255),
    clean_session BOOLEAN DEFAULT TRUE,
    keep_alive INT UNSIGNED DEFAULT 60,
    qos TINYINT UNSIGNED DEFAULT 1,
    is_primary BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    priority INT UNSIGNED DEFAULT 0,
    max_connections INT UNSIGNED DEFAULT 1000,
    certificate_path VARCHAR(500),
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active_priority (is_active, priority)
) ENGINE=InnoDB;

-- Tenant usage metrics
CREATE TABLE tenant_usage_metrics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    metric_date DATE NOT NULL,
    total_employees INT UNSIGNED DEFAULT 0,
    active_employees INT UNSIGNED DEFAULT 0,
    total_devices INT UNSIGNED DEFAULT 0,
    active_devices INT UNSIGNED DEFAULT 0,
    attendance_records_count INT UNSIGNED DEFAULT 0,
    storage_used_mb DECIMAL(12,2) DEFAULT 0,
    api_calls_count INT UNSIGNED DEFAULT 0,
    webhook_calls_count INT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY unique_tenant_date (tenant_id, metric_date),
    INDEX idx_tenant_date (tenant_id, metric_date)
) ENGINE=InnoDB;
```

### 3.2 Tenant Database Schema

```sql
-- Template for each tenant database
-- Example: CREATE DATABASE tenant_xxx_yyy_zzz

-- Employees
CREATE TABLE employees (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_code VARCHAR(50) UNIQUE NOT NULL,
    custom_id VARCHAR(50) UNIQUE NOT NULL COMMENT 'System-generated unique ID synced to devices',
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE,
    phone VARCHAR(20),
    department_id BIGINT UNSIGNED,
    designation VARCHAR(100),
    employee_type ENUM('full-time', 'part-time', 'contractor', 'intern') DEFAULT 'full-time',
    card_number VARCHAR(50) UNIQUE,
    joining_date DATE NOT NULL,
    leaving_date DATE NULL,
    reporting_manager_id BIGINT UNSIGNED,
    is_active BOOLEAN DEFAULT TRUE,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (reporting_manager_id) REFERENCES employees(id) ON DELETE SET NULL,
    INDEX idx_employee_code (employee_code),
    INDEX idx_custom_id (custom_id),
    INDEX idx_active (is_active),
    INDEX idx_department (department_id),
    FULLTEXT idx_name_search (name, email)
) ENGINE=InnoDB COMMENT='Employees registered in system, then synced to devices. No biometric data stored.';

-- Device enrollment tracking
CREATE TABLE device_enrollments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    device_id BIGINT UNSIGNED NOT NULL,
    sync_requested_at TIMESTAMP NULL COMMENT 'When system sent sync command',
    enrolled_at TIMESTAMP NULL COMMENT 'When device confirmed face enrollment',
    is_enrolled BOOLEAN DEFAULT FALSE,
    enrollment_quality DECIMAL(5,2) COMMENT 'Quality score from device',
    sync_status ENUM('pending', 'synced', 'enrolled', 'failed') DEFAULT 'pending',
    sync_attempts INT DEFAULT 0,
    last_sync_error TEXT,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    UNIQUE KEY unique_employee_device (employee_id, device_id),
    INDEX idx_sync_status (sync_status),
    INDEX idx_enrolled (is_enrolled)
) ENGINE=InnoDB COMMENT='Tracks employee sync and enrollment status on each device';


-- Departments
CREATE TABLE departments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) UNIQUE NOT NULL,
    parent_id BIGINT UNSIGNED,
    manager_id BIGINT UNSIGNED,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES departments(id) ON DELETE SET NULL,
    INDEX idx_parent (parent_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- Shifts
CREATE TABLE shifts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) UNIQUE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    break_start TIME,
    break_end TIME,
    grace_period_minutes INT UNSIGNED DEFAULT 15,
    early_departure_threshold_minutes INT UNSIGNED DEFAULT 15,
    overtime_threshold_minutes INT UNSIGNED DEFAULT 30,
    half_day_threshold_minutes INT UNSIGNED DEFAULT 240,
    working_days JSON COMMENT '[1,2,3,4,5] for Mon-Fri',
    shift_type ENUM('fixed', 'flexible', 'rotating') DEFAULT 'fixed',
    is_overnight BOOLEAN DEFAULT FALSE COMMENT 'Shift crosses midnight',
    color_code VARCHAR(7) DEFAULT '#3498db',
    is_active BOOLEAN DEFAULT TRUE,
    description TEXT,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_type (shift_type)
) ENGINE=InnoDB;

-- Employee shift assignments
CREATE TABLE employee_shifts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    shift_id BIGINT UNSIGNED NOT NULL,
    effective_from DATE NOT NULL,
    effective_to DATE,
    assigned_by BIGINT UNSIGNED,
    assignment_reason VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_employee_date (employee_id, effective_from, effective_to),
    INDEX idx_shift (shift_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- Shift overrides (for special dates)
CREATE TABLE shift_overrides (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    override_date DATE NOT NULL,
    shift_id BIGINT UNSIGNED,
    is_off_day BOOLEAN DEFAULT FALSE,
    is_holiday BOOLEAN DEFAULT FALSE,
    reason VARCHAR(500),
    created_by BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_employee_date (employee_id, override_date),
    INDEX idx_override_date (override_date)
) ENGINE=InnoDB;

-- Devices
CREATE TABLE devices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(100) UNIQUE NOT NULL COMMENT 'Matches central registry',
    name VARCHAR(255) NOT NULL,
    location VARCHAR(255),
    device_type VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45),
    mac_address VARCHAR(17),
    firmware_version VARCHAR(50),
    capacity INT UNSIGNED COMMENT 'Max face templates',
    current_count INT UNSIGNED DEFAULT 0,
    is_entry_device BOOLEAN DEFAULT TRUE,
    is_exit_device BOOLEAN DEFAULT TRUE,
    timezone VARCHAR(50) DEFAULT 'UTC',
    settings JSON,
    is_active BOOLEAN DEFAULT TRUE,
    last_sync_at TIMESTAMP NULL,
    last_heartbeat_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_device_id (device_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- Attendance records
CREATE TABLE attendance_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    device_id BIGINT UNSIGNED NOT NULL,
    record_id VARCHAR(100) UNIQUE COMMENT 'From device',
    timestamp TIMESTAMP NOT NULL,
    direction ENUM('check-in', 'check-out', 'break-out', 'break-in') NOT NULL,
    recognition_score DECIMAL(5,2) COMMENT 'Face match confidence 0-100',
    temperature DECIMAL(4,1) COMMENT 'Body temperature if available',
    mask_detected BOOLEAN,
    photo_path VARCHAR(500),
    processing_status ENUM('pending', 'processed', 'error') DEFAULT 'processed',
    shift_id BIGINT UNSIGNED,
    is_late BOOLEAN DEFAULT FALSE,
    is_early_departure BOOLEAN DEFAULT FALSE,
    is_overtime BOOLEAN DEFAULT FALSE,
    late_minutes INT DEFAULT 0,
    early_departure_minutes INT DEFAULT 0,
    remarks TEXT,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE SET NULL,
    INDEX idx_employee_timestamp (employee_id, timestamp DESC),
    INDEX idx_device_timestamp (device_id, timestamp DESC),
    INDEX idx_date (DATE(timestamp)),
    INDEX idx_direction (direction),
    INDEX idx_violations (is_late, is_early_departure)
) ENGINE=InnoDB;

-- Daily attendance summaries
CREATE TABLE daily_attendance_summaries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    attendance_date DATE NOT NULL,
    shift_id BIGINT UNSIGNED,
    first_check_in TIMESTAMP,
    last_check_out TIMESTAMP,
    total_work_minutes INT UNSIGNED DEFAULT 0,
    total_break_minutes INT UNSIGNED DEFAULT 0,
    total_overtime_minutes INT DEFAULT 0,
    check_in_count TINYINT UNSIGNED DEFAULT 0,
    check_out_count TINYINT UNSIGNED DEFAULT 0,
    status ENUM('present', 'absent', 'half-day', 'leave', 'holiday', 'weekend', 'on-duty') NOT NULL,
    is_late BOOLEAN DEFAULT FALSE,
    late_minutes INT DEFAULT 0,
    is_early_departure BOOLEAN DEFAULT FALSE,
    early_departure_minutes INT DEFAULT 0,
    remarks TEXT,
    approved_by BIGINT UNSIGNED,
    approved_at TIMESTAMP NULL,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_employee_date (employee_id, attendance_date),
    INDEX idx_date (attendance_date),
    INDEX idx_status (status),
    INDEX idx_violations (is_late, is_early_departure)
) ENGINE=InnoDB;

-- Attendance violations
CREATE TABLE attendance_violations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    attendance_record_id BIGINT UNSIGNED,
    summary_id BIGINT UNSIGNED,
    violation_date DATE NOT NULL,
    violation_type ENUM('late-arrival', 'early-departure', 'absent', 'extended-break', 'missing-checkout', 'unauthorized-overtime') NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    violation_minutes INT,
    description TEXT,
    manager_notified_at TIMESTAMP NULL,
    employee_notified_at TIMESTAMP NULL,
    is_excused BOOLEAN DEFAULT FALSE,
    excused_reason TEXT,
    excused_by BIGINT UNSIGNED,
    excused_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (attendance_record_id) REFERENCES attendance_records(id) ON DELETE SET NULL,
    FOREIGN KEY (summary_id) REFERENCES daily_attendance_summaries(id) ON DELETE SET NULL,
    FOREIGN KEY (excused_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_employee_date (employee_id, violation_date),
    INDEX idx_type (violation_type),
    INDEX idx_severity (severity),
    INDEX idx_excused (is_excused)
) ENGINE=InnoDB;

-- Stranger logs
CREATE TABLE stranger_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id BIGINT UNSIGNED NOT NULL,
    timestamp TIMESTAMP NOT NULL,
    photo_path VARCHAR(500),
    recognition_attempts JSON COMMENT 'Attempted matches',
    alert_sent BOOLEAN DEFAULT FALSE,
    alert_sent_at TIMESTAMP NULL,
    investigated BOOLEAN DEFAULT FALSE,
    investigated_by BIGINT UNSIGNED,
    investigated_at TIMESTAMP NULL,
    investigation_notes TEXT,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    FOREIGN KEY (investigated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_device_timestamp (device_id, timestamp DESC),
    INDEX idx_alert_sent (alert_sent),
    INDEX idx_investigated (investigated)
) ENGINE=InnoDB;

-- Device acknowledgements
CREATE TABLE device_acknowledgements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id VARCHAR(100) UNIQUE NOT NULL,
    device_id BIGINT UNSIGNED NOT NULL,
    operator VARCHAR(100) NOT NULL,
    command_type ENUM('AddPerson', 'EditPerson', 'DeletePerson', 'SyncTime', 'Reboot', 'UpdateFirmware') NOT NULL,
    status_code VARCHAR(10) NOT NULL,
    result ENUM('success', 'error', 'pending') NOT NULL,
    request_payload JSON,
    response_payload JSON,
    sent_at TIMESTAMP NULL,
    received_at TIMESTAMP NULL,
    retry_count TINYINT UNSIGNED DEFAULT 0,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    INDEX idx_device_status (device_id, status_code),
    INDEX idx_message (message_id)
) ENGINE=InnoDB;

-- Leave requests
CREATE TABLE leave_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    leave_type ENUM('casual', 'sick', 'earned', 'unpaid', 'compensatory', 'maternity', 'paternity') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days DECIMAL(4,2) NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by BIGINT UNSIGNED,
    reviewed_at TIMESTAMP NULL,
    review_remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_employee_dates (employee_id, start_date, end_date),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Attendance correction requests
CREATE TABLE attendance_corrections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    attendance_date DATE NOT NULL,
    correction_type ENUM('missing-checkin', 'missing-checkout', 'wrong-time', 'forgot-swipe') NOT NULL,
    requested_check_in TIMESTAMP,
    requested_check_out TIMESTAMP,
    reason TEXT NOT NULL,
    supporting_document VARCHAR(500),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by BIGINT UNSIGNED,
    reviewed_at TIMESTAMP NULL,
    review_remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_employee_date (employee_id, attendance_date),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Audit logs
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id VARCHAR(100),
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_action (action)
) ENGINE=InnoDB;
```

---

## 4. MQTT Integration Deep Dive

### 4.1 Connection Management

```php
// app/Infrastructure/MQTT/MQTTClient.php
namespace App\Infrastructure\MQTT;

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

class MQTTClient
{
    protected MqttClient $client;
    protected array $config;
    protected array $subscribedTopics = [];
    
    public function __construct()
    {
        $this->config = $this->loadConfig();
        $this->initializeClient();
    }
    
    protected function loadConfig(): array
    {
        return DB::connection('central')
            ->table('mqtt_broker_configs')
            ->where('is_active', true)
            ->where('is_primary', true)
            ->first();
    }
    
    protected function initializeClient(): void
    {
        $settings = (new ConnectionSettings)
            ->setKeepAliveInterval($this->config['keep_alive'])
            ->setLastWillTopic('mqtt/system/status')
            ->setLastWillMessage(json_encode([
                'status' => 'offline',
                'timestamp' => now()->toIso8601String()
            ]))
            ->setLastWillQualityOfService(1)
            ->setUseTls($this->config['protocol'] === 'tls')
            ->setTlsSelfSignedAllowed(false);
        
        if ($this->config['protocol'] === 'tls') {
            $settings
                ->setTlsCertificateAuthorityFile($this->config['ca_file'])
                ->setTlsClientCertificateFile($this->config['cert_file'])
                ->setTlsClientCertificateKeyFile($this->config['key_file']);
        }
        
        $this->client = new MqttClient(
            $this->config['host'],
            $this->config['port'],
            $this->generateClientId(),
            MqttClient::MQTT_3_1_1
        );
        
        $this->client->connect($settings);
        
        Log::info('MQTT Client connected', [
            'broker' => $this->config['host'],
            'port' => $this->config['port']
        ]);
    }
    
    public function subscribe(string $topic, callable $callback, int $qos = 1): void
    {
        $this->client->subscribe(
            $topic,
            function (string $topic, string $message, bool $retained) use ($callback) {
                try {
                    $payload = json_decode($message, true);
                    
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        Log::error('Invalid JSON in MQTT message', [
                            'topic' => $topic,
                            'message' => $message,
                            'error' => json_last_error_msg()
                        ]);
                        return;
                    }
                    
                    $callback($topic, $payload, $retained);
                    
                } catch (\Throwable $e) {
                    Log::error('Error processing MQTT message', [
                        'topic' => $topic,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            },
            $qos
        );
        
        $this->subscribedTopics[$topic] = $qos;
        
        Log::info('Subscribed to MQTT topic', [
            'topic' => $topic,
            'qos' => $qos
        ]);
    }
    
    public function publish(string $topic, array $payload, int $qos = 1, bool $retain = false): void
    {
        $message = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        $this->client->publish($topic, $message, $qos, $retain);
        
        Log::debug('Published MQTT message', [
            'topic' => $topic,
            'payload_size' => strlen($message),
            'qos' => $qos
        ]);
    }
    
    public function loop(int $allowedSleepSeconds = 1): void
    {
        while (true) {
            try {
                $this->client->loop(true, true);
                
                // Periodic health check
                if ($this->shouldRunHealthCheck()) {
                    $this->performHealthCheck();
                }
                
                sleep($allowedSleepSeconds);
                
            } catch (\Throwable $e) {
                Log::error('MQTT loop error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                $this->handleConnectionError($e);
            }
        }
    }
    
    protected function handleConnectionError(\Throwable $e): void
    {
        Log::warning('MQTT connection lost, attempting reconnection...');
        
        sleep(5); // Wait before reconnecting
        
        try {
            $this->disconnect();
            $this->initializeClient();
            $this->resubscribeTopics();
            
            Log::info('Successfully reconnected to MQTT broker');
            
        } catch (\Throwable $reconnectError) {
            Log::critical('Failed to reconnect to MQTT broker', [
                'error' => $reconnectError->getMessage()
            ]);
            
            // Notify administrators
            dispatch(new SendCriticalAlert(
                'MQTT Connection Failed',
                'Unable to maintain connection to MQTT broker. Attendance processing is affected.'
            ));
        }
    }
    
    protected function resubscribeTopics(): void
    {
        foreach ($this->subscribedTopics as $topic => $qos) {
            $this->client->subscribe($topic, null, $qos);
        }
        
        Log::info('Resubscribed to all topics', [
            'topic_count' => count($this->subscribedTopics)
        ]);
    }
    
    protected function generateClientId(): string
    {
        return sprintf(
            'attendance_system_%s_%s',
            config('app.env'),
            Str::random(8)
        );
    }
    
    public function disconnect(): void
    {
        if ($this->client) {
            $this->client->disconnect();
        }
    }
    
    protected function shouldRunHealthCheck(): bool
    {
        $lastCheck = Cache::get('mqtt:last_health_check', 0);
        return (time() - $lastCheck) > 60; // Every 60 seconds
    }
    
    protected function performHealthCheck(): void
    {
        $this->publish('mqtt/system/health', [
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'subscribed_topics' => array_keys($this->subscribedTopics)
        ]);
        
        Cache::put('mqtt:last_health_check', time(), 120);
    }
}
```

### 4.2 Message Handler Implementation

```php
// app/Infrastructure/MQTT/MessageHandler.php
namespace App\Infrastructure\MQTT;

use App\Domain\Attendance\Services\AttendanceProcessor;
use App\Domain\Device\Services\DeviceCommandService;
use Illuminate\Support\Facades\DB;

class MessageHandler
{
    public function __construct(
        protected AttendanceProcessor $attendanceProcessor,
        protected DeviceCommandService $deviceCommandService
    ) {}
    
    public function handleRecognitionEvent(string $topic, array $payload): void
    {
        $deviceId = $this->extractDeviceId($topic);
        
        // Record raw message for debugging
        $this->logRawMessage('recognition', $deviceId, $payload);
        
        // Dispatch to queue for processing
        dispatch(new ProcessAttendanceEvent($deviceId, $payload))
            ->onQueue('attendance-high-priority');
    }
    
    public function handleStrangerEvent(string $topic, array $payload): void
    {
        $deviceId = $this->extractDeviceId($topic);
        
        $this->logRawMessage('stranger', $deviceId, $payload);
        
        dispatch(new ProcessStrangerEvent($deviceId, $payload))
            ->onQueue('attendance-default');
    }
    
    public function handleAcknowledgement(string $topic, array $payload): void
    {
        $deviceId = $this->extractDeviceId($topic);
        
        $this->logRawMessage('acknowledgement', $deviceId, $payload);
        
        $this->deviceCommandService->processAcknowledgement($deviceId, $payload);
    }
    
    public function handleDeviceStatus(string $topic, array $payload): void
    {
        $deviceId = $this->extractDeviceId($topic);
        
        dispatch(new UpdateDeviceStatus($deviceId, $payload))
            ->onQueue('attendance-default');
    }
    
    public function handleHeartbeat(string $topic, array $payload): void
    {
        $deviceId = $this->extractDeviceId($topic);
        
        // Update device last seen timestamp
        $this->updateDeviceHeartbeat($deviceId);
    }
    
    protected function extractDeviceId(string $topic): string
    {
        // Topic format: mqtt/face/{device_id}/Rec
        preg_match('/mqtt\/face\/([^\/]+)\//', $topic, $matches);
        
        return $matches[1] ?? throw new \InvalidArgumentException('Invalid topic format');
    }
    
    protected function logRawMessage(string $type, string $deviceId, array $payload): void
    {
        if (config('app.debug') || config('mqtt.log_raw_messages')) {
            Log::channel('mqtt')->debug('MQTT message received', [
                'type' => $type,
                'device_id' => $deviceId,
                'payload' => $payload
            ]);
        }
    }
    
    protected function updateDeviceHeartbeat(string $deviceId): void
    {
        Cache::put(
            "device:heartbeat:{$deviceId}",
            now()->toDateTimeString(),
            now()->addMinutes(5)
        );
    }
}
```

---

## 5. Smart Direction Detection Algorithm

### 5.1 Implementation

```php
// app/Domain/Attendance/Services/DirectionDetector.php
namespace App\Domain\Attendance\Services;

use App\Domain\Employee\Models\Employee;
use App\Domain\Shift\Models\Shift;
use Carbon\Carbon;

class DirectionDetector
{
    protected const SHIFT_START_WINDOW = 60; // minutes
    protected const SHIFT_END_WINDOW = 60; // minutes
    protected const BREAK_WINDOW = 30; // minutes
    
    public function detect(
        Employee $employee,
        Carbon $timestamp,
        ?array $lastRecord,
        ?Shift $shift
    ): string {
        // Calculate confidence scores for each direction
        $scores = [
            'check-in' => $this->calculateCheckInScore($employee, $timestamp, $lastRecord, $shift),
            'check-out' => $this->calculateCheckOutScore($employee, $timestamp, $lastRecord, $shift),
            'break-out' => $this->calculateBreakOutScore($employee, $timestamp, $lastRecord, $shift),
            'break-in' => $this->calculateBreakInScore($employee, $timestamp, $lastRecord, $shift),
        ];
        
        // Get direction with highest confidence
        $direction = array_keys($scores, max($scores))[0];
        
        Log::info('Direction determined', [
            'employee_id' => $employee->id,
            'timestamp' => $timestamp->toDateTimeString(),
            'direction' => $direction,
            'scores' => $scores,
            'last_record' => $lastRecord
        ]);
        
        return $direction;
    }
    
    protected function calculateCheckInScore(
        Employee $employee,
        Carbon $timestamp,
        ?array $lastRecord,
        ?Shift $shift
    ): float {
        $score = 0.0;
        
        // Rule 1: No previous record today
        if (!$lastRecord || !$this->isSameDay($lastRecord['timestamp'], $timestamp)) {
            $score += 40.0;
        }
        
        // Rule 2: Last record was check-out
        if ($lastRecord && $lastRecord['direction'] === 'check-out') {
            $score += 30.0;
        }
        
        // Rule 3: Time is near shift start
        if ($shift && $this->isNearShiftStart($timestamp, $shift)) {
            $score += 25.0;
        }
        
        // Rule 4: Historical pattern (morning check-in)
        if ($this->isTypicalCheckInTime($employee, $timestamp)) {
            $score += 5.0;
        }
        
        return $score;
    }
    
    protected function calculateCheckOutScore(
        Employee $employee,
        Carbon $timestamp,
        ?array $lastRecord,
        ?Shift $shift
    ): float {
        $score = 0.0;
        
        // Rule 1: Last record was check-in
        if ($lastRecord && $lastRecord['direction'] === 'check-in') {
            $score += 35.0;
        }
        
        // Rule 2: Time is near shift end
        if ($shift && $this->isNearShiftEnd($timestamp, $shift)) {
            $score += 30.0;
        }
        
        // Rule 3: Break is complete (returned from break)
        if ($lastRecord && $lastRecord['direction'] === 'break-in') {
            $score += 20.0;
        }
        
        // Rule 4: Time since check-in > minimum work duration
        if ($lastRecord && $this->hasMinimumWorkDuration($lastRecord, $timestamp)) {
            $score += 10.0;
        }
        
        // Rule 5: Historical pattern
        if ($this->isTypicalCheckOutTime($employee, $timestamp)) {
            $score += 5.0;
        }
        
        return $score;
    }
    
    protected function calculateBreakOutScore(
        Employee $employee,
        Carbon $timestamp,
        ?array $lastRecord,
        ?Shift $shift
    ): float {
        $score = 0.0;
        
        // Rule 1: Last record was check-in
        if ($lastRecord && $lastRecord['direction'] === 'check-in') {
            $score += 25.0;
        }
        
        // Rule 2: Time is near break start
        if ($shift && $this->isNearBreakStart($timestamp, $shift)) {
            $score += 40.0;
        }
        
        // Rule 3: Has been working for at least 2 hours
        if ($lastRecord && $this->hasWorkedEnoughForBreak($lastRecord, $timestamp)) {
            $score += 20.0;
        }
        
        // Rule 4: No break taken today yet
        if (!$this->hasBreakToday($employee, $timestamp)) {
            $score += 15.0;
        }
        
        return $score;
    }
    
    protected function calculateBreakInScore(
        Employee $employee,
        Carbon $timestamp,
        ?array $lastRecord,
        ?Shift $shift
    ): float {
        $score = 0.0;
        
        // Rule 1: Last record was break-out
        if ($lastRecord && $lastRecord['direction'] === 'break-out') {
            $score += 50.0;
        }
        
        // Rule 2: Time is near break end
        if ($shift && $this->isNearBreakEnd($timestamp, $shift)) {
            $score += 30.0;
        }
        
        // Rule 3: Break duration is reasonable (15-90 minutes)
        if ($lastRecord && $this->isReasonableBreakDuration($lastRecord, $timestamp)) {
            $score += 20.0;
        }
        
        return $score;
    }
    
    protected function isSameDay(string $timestamp1, Carbon $timestamp2): bool
    {
        return Carbon::parse($timestamp1)->isSameDay($timestamp2);
    }
    
    protected function isNearShiftStart(Carbon $timestamp, Shift $shift): bool
    {
        $shiftStart = Carbon::parse($shift->start_time);
        $shiftStart->setDateFrom($timestamp);
        
        $diffInMinutes = abs($timestamp->diffInMinutes($shiftStart));
        
        return $diffInMinutes <= self::SHIFT_START_WINDOW;
    }
    
    protected function isNearShiftEnd(Carbon $timestamp, Shift $shift): bool
    {
        $shiftEnd = Carbon::parse($shift->end_time);
        $shiftEnd->setDateFrom($timestamp);
        
        // Handle overnight shifts
        if ($shift->is_overnight && $shiftEnd < $shiftStart) {
            $shiftEnd->addDay();
        }
        
        $diffInMinutes = abs($timestamp->diffInMinutes($shiftEnd));
        
        return $diffInMinutes <= self::SHIFT_END_WINDOW;
    }
    
    protected function isNearBreakStart(Carbon $timestamp, Shift $shift): bool
    {
        if (!$shift->break_start) {
            return false;
        }
        
        $breakStart = Carbon::parse($shift->break_start);
        $breakStart->setDateFrom($timestamp);
        
        $diffInMinutes = abs($timestamp->diffInMinutes($breakStart));
        
        return $diffInMinutes <= self::BREAK_WINDOW;
    }
    
    protected function isNearBreakEnd(Carbon $timestamp, Shift $shift): bool
    {
        if (!$shift->break_end) {
            return false;
        }
        
        $breakEnd = Carbon::parse($shift->break_end);
        $breakEnd->setDateFrom($timestamp);
        
        $diffInMinutes = abs($timestamp->diffInMinutes($breakEnd));
        
        return $diffInMinutes <= self::BREAK_WINDOW;
    }
    
    protected function hasMinimumWorkDuration(?array $lastRecord, Carbon $timestamp): bool
    {
        if (!$lastRecord || $lastRecord['direction'] !== 'check-in') {
            return false;
        }
        
        $checkInTime = Carbon::parse($lastRecord['timestamp']);
        $workDuration = $checkInTime->diffInMinutes($timestamp);
        
        return $workDuration >= 60; // At least 1 hour
    }
    
    protected function hasWorkedEnoughForBreak(?array $lastRecord, Carbon $timestamp): bool
    {
        if (!$lastRecord) {
            return false;
        }
        
        $lastTime = Carbon::parse($lastRecord['timestamp']);
        $workDuration = $lastTime->diffInMinutes($timestamp);
        
        return $workDuration >= 120; // At least 2 hours
    }
    
    protected function isReasonableBreakDuration(?array $lastRecord, Carbon $timestamp): bool
    {
        if (!$lastRecord || $lastRecord['direction'] !== 'break-out') {
            return false;
        }
        
        $breakOutTime = Carbon::parse($lastRecord['timestamp']);
        $breakDuration = $breakOutTime->diffInMinutes($timestamp);
        
        return $breakDuration >= 15 && $breakDuration <= 90;
    }
    
    protected function hasBreakToday(Employee $employee, Carbon $timestamp): bool
    {
        return DB::table('attendance_records')
            ->where('employee_id', $employee->id)
            ->whereDate('timestamp', $timestamp->toDateString())
            ->whereIn('direction', ['break-out', 'break-in'])
            ->exists();
    }
    
    protected function isTypicalCheckInTime(Employee $employee, Carbon $timestamp): bool
    {
        // Analyze historical check-in patterns
        $typicalTime = DB::table('attendance_records')
            ->where('employee_id', $employee->id)
            ->where('direction', 'check-in')
            ->whereDate('timestamp', '>=', now()->subDays(30))
            ->selectRaw('AVG(HOUR(timestamp)) as avg_hour, AVG(MINUTE(timestamp)) as avg_minute')
            ->first();
        
        if (!$typicalTime) {
            return false;
        }
        
        $typicalTimeCarbon = Carbon::createFromTime($typicalTime->avg_hour, $typicalTime->avg_minute);
        $typicalTimeCarbon->setDateFrom($timestamp);
        
        return abs($timestamp->diffInMinutes($typicalTimeCarbon)) <= 60;
    }
    
    protected function isTypicalCheckOutTime(Employee $employee, Carbon $timestamp): bool
    {
        $typicalTime = DB::table('attendance_records')
            ->where('employee_id', $employee->id)
            ->where('direction', 'check-out')
            ->whereDate('timestamp', '>=', now()->subDays(30))
            ->selectRaw('AVG(HOUR(timestamp)) as avg_hour, AVG(MINUTE(timestamp)) as avg_minute')
            ->first();
        
        if (!$typicalTime) {
            return false;
        }
        
        $typicalTimeCarbon = Carbon::createFromTime($typicalTime->avg_hour, $typicalTime->avg_minute);
        $typicalTimeCarbon->setDateFrom($timestamp);
        
        return abs($timestamp->diffInMinutes($typicalTimeCarbon)) <= 60;
    }
}
```

---

## 6. Shift Management System

### 6.1 Advanced Shift Assignment

```php
// app/Domain/Shift/Services/ShiftAssignmentService.php
namespace App\Domain\Shift\Services;

use App\Domain\Employee\Models\Employee;
use App\Domain\Shift\Models\Shift;
use App\Domain\Shift\Models\ShiftRotation;
use Carbon\Carbon;

class ShiftAssignmentService
{
    public function assignShift(
        Employee $employee,
        Shift $shift,
        Carbon $effectiveFrom,
        ?Carbon $effectiveTo = null,
        ?string $reason = null
    ): void {
        // Deactivate current assignment if exists
        $this->deactivateCurrentShift($employee, $effectiveFrom);
        
        // Create new assignment
        DB::table('employee_shifts')->insert([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'effective_from' => $effectiveFrom->toDateString(),
            'effective_to' => $effectiveTo?->toDateString(),
            'assigned_by' => auth()->id(),
            'assignment_reason' => $reason,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Clear cache
        Cache::forget("employee:{$employee->id}:current_shift");
        
        // Log assignment
        activity()
            ->performedOn($employee)
            ->causedBy(auth()->user())
            ->withProperties([
                'shift_id' => $shift->id,
                'shift_name' => $shift->name,
                'effective_from' => $effectiveFrom->toDateString(),
                'effective_to' => $effectiveTo?->toDateString(),
            ])
            ->log('shift_assigned');
    }
    
    public function createRotatingShift(
        array $employees,
        array $shifts,
        Carbon $startDate,
        int $rotationDays,
        ?Carbon $endDate = null
    ): ShiftRotation {
        $rotation = ShiftRotation::create([
            'name' => "Rotation starting {$startDate->format('Y-m-d')}",
            'start_date' => $startDate,
            'end_date' => $endDate,
            'rotation_days' => $rotationDays,
            'is_active' => true,
        ]);
        
        $rotation->employees()->attach($employees);
        $rotation->shifts()->attach($shifts, ['sequence' => DB::raw('ROW_NUMBER() OVER ()')]);
        
        // Generate initial assignments
        $this->generateRotationAssignments($rotation);
        
        return $rotation;
    }
    
    protected function generateRotationAssignments(ShiftRotation $rotation): void
    {
        $employees = $rotation->employees;
        $shifts = $rotation->shifts()->orderBy('sequence')->get();
        $currentDate = $rotation->start_date->copy();
        $endDate = $rotation->end_date ?? now()->addYear();
        
        $employeeShiftMap = [];
        foreach ($employees as $index => $employee) {
            $employeeShiftMap[$employee->id] = $index % $shifts->count();
        }
        
        while ($currentDate <= $endDate) {
            foreach ($employees as $employee) {
                $shiftIndex = $employeeShiftMap[$employee->id];
                $shift = $shifts[$shiftIndex];
                
                $this->assignShift(
                    $employee,
                    $shift,
                    $currentDate->copy(),
                    $currentDate->copy()->addDays($rotation->rotation_days - 1),
                    "Rotating shift assignment"
                );
                
                // Rotate to next shift
                $employeeShiftMap[$employee->id] = ($shiftIndex + 1) % $shifts->count();
            }
            
            $currentDate->addDays($rotation->rotation_days);
        }
    }
    
    public function createShiftOverride(
        Employee $employee,
        Carbon $date,
        ?Shift $shift = null,
        bool $isOffDay = false,
        bool $isHoliday = false,
        ?string $reason = null
    ): void {
        DB::table('shift_overrides')->updateOrInsert(
            [
                'employee_id' => $employee->id,
                'override_date' => $date->toDateString(),
            ],
            [
                'shift_id' => $shift?->id,
                'is_off_day' => $isOffDay,
                'is_holiday' => $isHoliday,
                'reason' => $reason,
                'created_by' => auth()->id(),
                'updated_at' => now(),
            ]
        );
        
        // Clear cache
        Cache::forget("employee:{$employee->id}:shift:{$date->toDateString()}");
    }
    
    public function getEffectiveShift(Employee $employee, Carbon $date): ?Shift
    {
        return Cache::remember(
            "employee:{$employee->id}:shift:{$date->toDateString()}",
            3600,
            function() use ($employee, $date) {
                // Check for override first
                $override = DB::table('shift_overrides')
                    ->where('employee_id', $employee->id)
                    ->where('override_date', $date->toDateString())
                    ->first();
                
                if ($override) {
                    if ($override->is_off_day || $override->is_holiday) {
                        return null;
                    }
                    
                    if ($override->shift_id) {
                        return Shift::find($override->shift_id);
                    }
                }
                
                // Get regular shift assignment
                return $employee->shifts()
                    ->wherePivot('effective_from', '<=', $date->toDateString())
                    ->where(function($query) use ($date) {
                        $query->wherePivot('effective_to', '>=', $date->toDateString())
                              ->orWherePivot('effective_to', null);
                    })
                    ->wherePivot('is_active', true)
                    ->first();
            }
        );
    }
    
    protected function deactivateCurrentShift(Employee $employee, Carbon $effectiveFrom): void
    {
        DB::table('employee_shifts')
            ->where('employee_id', $employee->id)
            ->where('is_active', true)
            ->where(function($query) use ($effectiveFrom) {
                $query->whereNull('effective_to')
                      ->orWhere('effective_to', '>=', $effectiveFrom->toDateString());
            })
            ->update([
                'effective_to' => $effectiveFrom->subDay()->toDateString(),
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }
}
```

---

## 7. Attendance Processing Engine

### 7.1 Core Processing Logic

```php
// app/Domain/Attendance/DTOs/AttendanceEventDTO.php
namespace App\Domain\Attendance\DTOs;

use Carbon\Carbon;

class AttendanceEventDTO
{
    public function __construct(
        public string $customId,      // Custom ID from MQTT message to match employee
        public int $deviceId,          // Device database ID
        public string $recordId,       // Unique record ID from device
        public Carbon $timestamp,      // Event timestamp
        public float $recognitionScore, // Face match score from device (0-100)
        public ?float $temperature = null,
        public ?bool $maskDetected = null,
        public ?string $photo = null,  // Base64 photo for audit trail only
        public ?array $metadata = null
    ) {}
    
    public static function fromMqttPayload(array $payload, int $deviceId): self
    {
        $info = $payload['info'];
        
        return new self(
            customId: $info['custom_id'], // Primary identifier
            deviceId: $deviceId,
            recordId: $info['RecordID'],
            timestamp: Carbon::parse($info['time']),
            recognitionScore: (float) $info['similarity1'],
            temperature: isset($info['temperature']) ? (float) $info['temperature'] : null,
            maskDetected: isset($info['mask_status']) ? (bool) $info['mask_status'] : null,
            photo: $info['pic'] ?? null,
            metadata: [
                'person_id' => $info['personId'], // Device internal ID (not used for matching)
                'person_name' => $info['personName'],
                'device_name' => $info['facesluiceName'],
                'verify_status' => $info['VerifyStatus'],
            ]
        );
    }
}

// app/Domain/Attendance/Services/AttendanceProcessor.php
namespace App\Domain\Attendance\Services;

use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Attendance\DTOs\AttendanceEventDTO;
use Illuminate\Support\Facades\DB;

class AttendanceProcessor
{
    public function __construct(
        protected DirectionDetector $directionDetector,
        protected ViolationChecker $violationChecker
    ) {}
    
    public function process(AttendanceEventDTO $event): AttendanceRecord
    {
        return DB::transaction(function() use ($event) {
            // 1. Check for duplicates
            if ($this->isDuplicate($event)) {
                Log::info('Duplicate attendance record ignored', [
                    'custom_id' => $event->customId,
                    'timestamp' => $event->timestamp,
                ]);
                throw new DuplicateAttendanceException();
            }
            
            // 2. Find employee by custom_id from MQTT message
            $employee = Employee::with('department')
                ->where('custom_id', $event->customId)
                ->where('is_active', true)
                ->first();
            
            if (!$employee) {
                Log::warning('Employee not found for custom_id', [
                    'custom_id' => $event->customId,
                    'device_id' => $event->deviceId,
                    'timestamp' => $event->timestamp,
                ]);
                throw new EmployeeNotFoundException("No active employee found with custom_id: {$event->customId}");
            }
            
            // 3. Get shift information
            $shift = $this->getShift($employee, $event->timestamp);
            
            // 4. Get last attendance record
            $lastRecord = $this->getLastRecord($employee, $event->timestamp);
            
            // 5. Determine direction using smart algorithm
            $direction = $this->directionDetector->detect(
                $employee,
                $event->timestamp,
                $lastRecord,
                $shift
            );
            
            // 6. Create attendance record
            $record = $this->createRecord($event, $employee, $shift, $direction);
            
            // 7. Update daily summary
            $this->updateDailySummary($record);
            
            // 8. Check for violations
            $violations = $this->violationChecker->check($record, $shift);
            
            if (!empty($violations)) {
                $this->createViolations($record, $violations);
            }
            
            // 9. Broadcast real-time update
            broadcast(new AttendanceRecorded($record));
            
            // 10. Trigger webhooks
            dispatch(new TriggerAttendanceWebhooks($record))->afterCommit();
            
            return $record;
        });
    }
    
    protected function isDuplicate(AttendanceEventDTO $event): bool
    {
        $duplicateWindow = 5; // minutes
        
        // Find employee by custom_id first
        $employee = Employee::where('custom_id', $event->customId)->first();
        
        if (!$employee) {
            return false; // Will be handled as not found in main process
        }
        
        return AttendanceRecord::where('employee_id', $employee->id)
            ->where('timestamp', '>=', $event->timestamp->copy()->subMinutes($duplicateWindow))
            ->where('timestamp', '<=', $event->timestamp->copy()->addMinutes($duplicateWindow))
            ->exists();
    }
    
    protected function getLastRecord(Employee $employee, Carbon $timestamp): ?array
    {
        $record = AttendanceRecord::where('employee_id', $employee->id)
            ->where('timestamp', '<', $timestamp)
            ->orderBy('timestamp', 'desc')
            ->first();
        
        if (!$record) {
            return null;
        }
        
        return [
            'id' => $record->id,
            'timestamp' => $record->timestamp->toDateTimeString(),
            'direction' => $record->direction,
        ];
    }
    
    protected function getShift(Employee $employee, Carbon $timestamp): ?Shift
    {
        return app(ShiftAssignmentService::class)
            ->getEffectiveShift($employee, $timestamp);
    }
    
    protected function createRecord(
        AttendanceEventDTO $event,
        Employee $employee,
        ?Shift $shift,
        string $direction
    ): AttendanceRecord {
        $record = new AttendanceRecord([
            'employee_id' => $employee->id,
            'device_id' => $event->deviceId,
            'record_id' => $event->recordId,
            'timestamp' => $event->timestamp,
            'direction' => $direction,
            'recognition_score' => $event->recognitionScore, // Score from device only
            'temperature' => $event->temperature,
            'mask_detected' => $event->maskDetected,
            'shift_id' => $shift?->id,
            'processing_status' => 'processed',
        ]);
        
        // Calculate violations
        if ($shift && $direction === 'check-in') {
            $record->is_late = $this->isLateArrival($event->timestamp, $shift);
            $record->late_minutes = $this->calculateLateMinutes($event->timestamp, $shift);
        }
        
        if ($shift && $direction === 'check-out') {
            $record->is_early_departure = $this->isEarlyDeparture($event->timestamp, $shift);
            $record->early_departure_minutes = $this->calculateEarlyDepartureMinutes($event->timestamp, $shift);
        }
        
        // Save photo if available (for audit trail only, not for facial recognition)
        if ($event->photo) {
            $record->photo_path = $this->savePhoto($event->photo, $employee, $event->timestamp);
        }
        
        $record->save();
        
        return $record;
    }
    
    protected function updateDailySummary(AttendanceRecord $record): void
    {
        $summary = DailyAttendanceSummary::firstOrNew([
            'employee_id' => $record->employee_id,
            'attendance_date' => $record->timestamp->toDateString(),
        ]);
        
        switch ($record->direction) {
            case 'check-in':
                if (!$summary->first_check_in || $record->timestamp < $summary->first_check_in) {
                    $summary->first_check_in = $record->timestamp;
                }
                $summary->check_in_count = ($summary->check_in_count ?? 0) + 1;
                $summary->status = 'present';
                break;
                
            case 'check-out':
                if (!$summary->last_check_out || $record->timestamp > $summary->last_check_out) {
                    $summary->last_check_out = $record->timestamp;
                }
                $summary->check_out_count = ($summary->check_out_count ?? 0) + 1;
                break;
        }
        
        // Calculate work hours
        if ($summary->first_check_in && $summary->last_check_out) {
            $summary->total_work_minutes = $summary->first_check_in
                ->diffInMinutes($summary->last_check_out);
            
            // Subtract break time
            $breakMinutes = $this->calculateBreakMinutes($record->employee_id, $record->timestamp->toDateString());
            $summary->total_break_minutes = $breakMinutes;
            $summary->total_work_minutes -= $breakMinutes;
        }
        
        // Update violation flags
        $summary->is_late = $summary->is_late || $record->is_late;
        if ($record->is_late) {
            $summary->late_minutes = max($summary->late_minutes ?? 0, $record->late_minutes);
        }
        
        $summary->is_early_departure = $summary->is_early_departure || $record->is_early_departure;
        if ($record->is_early_departure) {
            $summary->early_departure_minutes = max(
                $summary->early_departure_minutes ?? 0,
                $record->early_departure_minutes
            );
        }
        
        $summary->shift_id = $record->shift_id;
        $summary->save();
    }
    
    protected function calculateBreakMinutes(int $employeeId, string $date): int
    {
        $breakRecords = AttendanceRecord::where('employee_id', $employeeId)
            ->whereDate('timestamp', $date)
            ->whereIn('direction', ['break-out', 'break-in'])
            ->orderBy('timestamp')
            ->get();
        
        $totalBreakMinutes = 0;
        $breakOutTime = null;
        
        foreach ($breakRecords as $record) {
            if ($record->direction === 'break-out') {
                $breakOutTime = $record->timestamp;
            } elseif ($record->direction === 'break-in' && $breakOutTime) {
                $totalBreakMinutes += $breakOutTime->diffInMinutes($record->timestamp);
                $breakOutTime = null;
            }
        }
        
        return $totalBreakMinutes;
    }
    
    protected function createViolations(AttendanceRecord $record, array $violations): void
    {
        foreach ($violations as $violation) {
            AttendanceViolation::create([
                'employee_id' => $record->employee_id,
                'attendance_record_id' => $record->id,
                'violation_date' => $record->timestamp->toDateString(),
                'violation_type' => $violation['type'],
                'severity' => $violation['severity'],
                'violation_minutes' => $violation['minutes'] ?? null,
                'description' => $violation['description'],
            ]);
        }
        
        // Send notifications
        dispatch(new SendViolationNotifications($record, $violations));
    }
    
    protected function isLateArrival(Carbon $timestamp, Shift $shift): bool
    {
        $shiftStart = Carbon::parse($shift->start_time);
        $shiftStart->setDateFrom($timestamp);
        $shiftStart->addMinutes($shift->grace_period_minutes);
        
        return $timestamp > $shiftStart;
    }
    
    protected function calculateLateMinutes(Carbon $timestamp, Shift $shift): int
    {
        $shiftStart = Carbon::parse($shift->start_time);
        $shiftStart->setDateFrom($timestamp);
        $shiftStart->addMinutes($shift->grace_period_minutes);
        
        return max(0, $shiftStart->diffInMinutes($timestamp, false));
    }
    
    protected function isEarlyDeparture(Carbon $timestamp, Shift $shift): bool
    {
        $shiftEnd = Carbon::parse($shift->end_time);
        $shiftEnd->setDateFrom($timestamp);
        
        if ($shift->is_overnight && $shiftEnd < $shiftStart) {
            $shiftEnd->addDay();
        }
        
        $shiftEnd->subMinutes($shift->early_departure_threshold_minutes);
        
        return $timestamp < $shiftEnd;
    }
    
    protected function calculateEarlyDepartureMinutes(Carbon $timestamp, Shift $shift): int
    {
        $shiftEnd = Carbon::parse($shift->end_time);
        $shiftEnd->setDateFrom($timestamp);
        
        if ($shift->is_overnight && $shiftEnd < $shiftStart) {
            $shiftEnd->addDay();
        }
        
        $shiftEnd->subMinutes($shift->early_departure_threshold_minutes);
        
        return max(0, $timestamp->diffInMinutes($shiftEnd, false));
    }
    
    protected function savePhoto(string $base64Photo, Employee $employee, Carbon $timestamp): string
    {
        // Photo saved for audit trail only, NOT for facial recognition
        $photoData = base64_decode($base64Photo);
        $fileName = sprintf(
            'attendance/%s/%s/%s.jpg',
            $employee->id,
            $timestamp->format('Y-m'),
            $timestamp->format('YmdHis')
        );
        
        Storage::disk('s3')->put($fileName, $photoData, 'private');
        
        return $fileName;
    }
}
```

### 7.2 Violation Checker

```php
// app/Domain/Attendance/Services/ViolationChecker.php
namespace App\Domain\Attendance\Services;

class ViolationChecker
{
    public function check(AttendanceRecord $record, ?Shift $shift): array
    {
        $violations = [];
        
        if (!$shift) {
            return $violations;
        }
        
        // Check late arrival
        if ($record->direction === 'check-in' && $record->is_late) {
            $violations[] = [
                'type' => 'late-arrival',
                'severity' => $this->calculateLateSeverity($record->late_minutes),
                'minutes' => $record->late_minutes,
                'description' => "Employee arrived {$record->late_minutes} minutes late",
            ];
        }
        
        // Check early departure
        if ($record->direction === 'check-out' && $record->is_early_departure) {
            $violations[] = [
                'type' => 'early-departure',
                'severity' => $this->calculateEarlySeverity($record->early_departure_minutes),
                'minutes' => $record->early_departure_minutes,
                'description' => "Employee left {$record->early_departure_minutes} minutes early",
            ];
        }
        
        // Check extended break
        if ($record->direction === 'break-in') {
            $extendedBreak = $this->checkExtendedBreak($record, $shift);
            if ($extendedBreak) {
                $violations[] = $extendedBreak;
            }
        }
        
        return $violations;
    }
    
    protected function calculateLateSeverity(int $minutes): string
    {
        return match(true) {
            $minutes <= 15 => 'low',
            $minutes <= 30 => 'medium',
            $minutes <= 60 => 'high',
            default => 'critical',
        };
    }
    
    protected function calculateEarlySeverity(int $minutes): string
    {
        return match(true) {
            $minutes <= 15 => 'low',
            $minutes <= 30 => 'medium',
            $minutes <= 60 => 'high',
            default => 'critical',
        };
    }
    
    protected function checkExtendedBreak(AttendanceRecord $record, Shift $shift): ?array
    {
        $breakOut = AttendanceRecord::where('employee_id', $record->employee_id)
            ->where('direction', 'break-out')
            ->where('timestamp', '<', $record->timestamp)
            ->whereDate('timestamp', $record->timestamp->toDateString())
            ->orderBy('timestamp', 'desc')
            ->first();
        
        if (!$breakOut) {
            return null;
        }
        
        $breakDuration = $breakOut->timestamp->diffInMinutes($record->timestamp);
        $allowedBreak = Carbon::parse($shift->break_start)->diffInMinutes(Carbon::parse($shift->break_end));
        $extendedMinutes = $breakDuration - $allowedBreak;
        
        if ($extendedMinutes > 10) {
            return [
                'type' => 'extended-break',
                'severity' => $extendedMinutes > 30 ? 'high' : 'medium',
                'minutes' => $extendedMinutes,
                'description' => "Break extended by {$extendedMinutes} minutes",
            ];
        }
        
        return null;
    }
}
```

---

## 8. Reporting Framework

### 8.1 Report Generator

```php
// app/Domain/Reporting/Services/ReportGenerator.php
namespace App\Domain\Reporting\Services;

use App\Domain\Reporting\DTOs\ReportRequest;
use App\Domain\Reporting\Exports\AttendanceExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportGenerator
{
    public function generate(ReportRequest $request): string
    {
        $data = $this->fetchData($request);
        $processed = $this->processData($data, $request);
        
        return match($request->format) {
            'pdf' => $this->generatePdf($processed, $request),
            'excel' => $this->generateExcel($processed, $request),
            'csv' => $this->generateCsv($processed, $request),
            default => throw new \InvalidArgumentException('Unsupported format'),
        };
    }
    
    protected function fetchData(ReportRequest $request): Collection
    {
        $query = DailyAttendanceSummary::query()
            ->with(['employee.department', 'shift'])
            ->whereBetween('attendance_date', [$request->startDate, $request->endDate]);
        
        if ($request->employeeIds) {
            $query->whereIn('employee_id', $request->employeeIds);
        }
        
        if ($request->departmentIds) {
            $query->whereHas('employee', function($q) use ($request) {
                $q->whereIn('department_id', $request->departmentIds);
            });
        }
        
        if ($request->status) {
            $query->where('status', $request->status);
        }
        
        return $query->get();
    }
    
    protected function processData(Collection $data, ReportRequest $request): array
    {
        $grouped = $data->groupBy('employee_id');
        
        $processed = [];
        
        foreach ($grouped as $employeeId => $records) {
            $employee = $records->first()->employee;
            
            $stats = [
                'employee_code' => $employee->employee_code,
                'employee_name' => $employee->name,
                'department' => $employee->department->name,
                'total_days' => $records->count(),
                'present_days' => $records->where('status', 'present')->count(),
                'absent_days' => $records->where('status', 'absent')->count(),
                'half_days' => $records->where('status', 'half-day')->count(),
                'late_count' => $records->where('is_late', true)->count(),
                'early_departure_count' => $records->where('is_early_departure', true)->count(),
                'total_work_hours' => round($records->sum('total_work_minutes') / 60, 2),
                'total_overtime_hours' => round($records->sum('total_overtime_minutes') / 60, 2),
                'attendance_percentage' => round(
                    ($records->where('status', 'present')->count() / $records->count()) * 100,
                    2
                ),
            ];
            
            $processed[] = $stats;
        }
        
        return $processed;
    }
    
    protected function generatePdf(array $data, ReportRequest $request): string
    {
        $pdf = Pdf::loadView('reports.attendance-pdf', [
            'data' => $data,
            'request' => $request,
            'generated_at' => now(),
        ]);
        
        $fileName = "attendance_report_{$request->startDate}_{$request->endDate}.pdf";
        $path = "reports/{$fileName}";
        
        Storage::disk('s3')->put($path, $pdf->output());
        
        return $path;
    }
    
    protected function generateExcel(array $data, ReportRequest $request): string
    {
        $fileName = "attendance_report_{$request->startDate}_{$request->endDate}.xlsx";
        $path = "reports/{$fileName}";
        
        Excel::store(
            new AttendanceExport($data, $request),
            $path,
            's3'
        );
        
        return $path;
    }
}
```

---

## 9. API Specifications

### 9.1 REST API Endpoints

```php
// routes/api.php
Route::prefix('v1')->middleware(['auth:sanctum', 'tenant'])->group(function () {
    
    // Employee Management
    Route::apiResource('employees', EmployeeController::class);
    Route::post('employees/{employee}/enroll-face', [EmployeeController::class, 'enrollFace']);
    Route::post('employees/bulk-import', [EmployeeController::class, 'bulkImport']);
    Route::get('employees/{employee}/attendance-history', [EmployeeController::class, 'attendanceHistory']);
    
    // Shift Management
    Route::apiResource('shifts', ShiftController::class);
    Route::post('shifts/{shift}/assign-employees', [ShiftController::class, 'assignEmployees']);
    Route::post('shift-rotations', [ShiftRotationController::class, 'store']);
    Route::post('shift-overrides', [ShiftOverrideController::class, 'store']);
    
    // Attendance Operations
    Route::prefix('attendance')->group(function () {
        Route::get('summary', [AttendanceController::class, 'summary']);
        Route::get('daily/{date}', [AttendanceController::class, 'daily']);
        Route::get('employee/{employee}', [AttendanceController::class, 'employeeRecords']);
        Route::post('corrections', [AttendanceCorrectionController::class, 'store']);
        Route::patch('corrections/{correction}/review', [AttendanceCorrectionController::class, 'review']);
    });
    
    // Device Management
    Route::apiResource('devices', DeviceController::class);
    Route::post('devices/{device}/sync-employees', [DeviceController::class, 'syncEmployees']);
    Route::post('devices/{device}/send-command', [DeviceController::class, 'sendCommand']);
    Route::get('devices/{device}/status', [DeviceController::class, 'status']);
    Route::get('devices/{device}/logs', [DeviceController::class, 'logs']);
    
    // Reports
    Route::prefix('reports')->group(function () {
        Route::post('generate', [ReportController::class, 'generate']);
        Route::get('{report}/download', [ReportController::class, 'download']);
        Route::get('{report}/status', [ReportController::class, 'status']);
        Route::post('schedule', [ReportController::class, 'schedule']);
    });
    
    // Dashboard & Analytics
    Route::prefix('dashboard')->group(function () {
        Route::get('stats', [DashboardController::class, 'stats']);
        Route::get('real-time-attendance', [DashboardController::class, 'realTime']);
        Route::get('violations', [DashboardController::class, 'violations']);
        Route::get('trends', [DashboardController::class, 'trends']);
    });
    
    // Webhooks
    Route::apiResource('webhooks', WebhookController::class);
    Route::post('webhooks/{webhook}/test', [WebhookController::class, 'test']);
});
```

### 9.2 API Response Format

```php
// app/Http/Resources/AttendanceRecordResource.php
namespace App\Http\Resources;

class AttendanceRecordResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'employee' => [
                'id' => $this->employee->id,
                'code' => $this->employee->employee_code,
                'name' => $this->employee->name,
                'department' => $this->employee->department->name,
            ],
            'device' => [
                'id' => $this->device->id,
                'name' => $this->device->name,
                'location' => $this->device->location,
            ],
            'timestamp' => $this->timestamp->toIso8601String(),
            'direction' => $this->direction,
            'recognition_score' => $this->recognition_score,
            'shift' => $this->shift ? [
                'id' => $this->shift->id,
                'name' => $this->shift->name,
            ] : null,
            'violations' => [
                'is_late' => $this->is_late,
                'late_minutes' => $this->late_minutes,
                'is_early_departure' => $this->is_early_departure,
                'early_departure_minutes' => $this->early_departure_minutes,
            ],
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
```

---

## 11. Performance Optimization

### 11.1 Caching Strategy

```php
// app/Services/CacheService.php
namespace App\Services;

class CacheService
{
    protected const CACHE_PREFIXES = [
        'employee' => 'emp',
        'shift' => 'shf',
        'device' => 'dev',
        'attendance' => 'att',
    ];
    
    protected const CACHE_TTL = [
        'employee_shift' => 3600,      // 1 hour
        'device_status' => 300,         // 5 minutes
        'daily_summary' => 1800,        // 30 minutes
        'reports' => 7200,              // 2 hours
    ];
    
    public function rememberEmployeeShift(Employee $employee, Carbon $date, callable $callback)
    {
        $key = $this->generateKey('employee_shift', [
            $employee->id,
            $date->toDateString()
        ]);
        
        return Cache::remember($key, self::CACHE_TTL['employee_shift'], $callback);
    }
    
    public function invalidateEmployeeCache(int $employeeId): void
    {
        $pattern = $this->generateKey('employee', [$employeeId, '*']);
        
        $keys = Redis::keys($pattern);
        
        if (!empty($keys)) {
            Redis::del($keys);
        }
    }
    
    protected function generateKey(string $type, array $identifiers): string
    {
        $prefix = self::CACHE_PREFIXES[$type] ?? $type;
        return implode(':', array_merge([$prefix], $identifiers));
    }
}
```

### 11.2 Database Query Optimization

```php
// app/Domain/Attendance/Repositories/AttendanceRepository.php
namespace App\Domain\Attendance\Repositories;

class AttendanceRepository
{
    public function getDailySummary(Carbon $date, array $filters = []): Collection
    {
        return DailyAttendanceSummary::query()
            ->with(['employee:id,employee_code,name,department_id', 'employee.department:id,name'])
            ->select([
                'id',
                'employee_id',
                'attendance_date',
                'status',
                'first_check_in',
                'last_check_out',
                'total_work_minutes',
                'is_late',
                'late_minutes',
                'is_early_departure',
                'early_departure_minutes'
            ])
            ->where('attendance_date', $date->toDateString())
            ->when(isset($filters['department_id']), function($query) use ($filters) {
                $query->whereHas('employee', function($q) use ($filters) {
                    $q->where('department_id', $filters['department_id']);
                });
            })
            ->when(isset($filters['status']), function($query) use ($filters) {
                $query->where('status', $filters['status']);
            })
            ->orderBy('employee_id')
            ->get();
    }
    
    public function getEmployeeAttendanceStats(
        int $employeeId,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        $result = DailyAttendanceSummary::where('employee_id', $employeeId)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total_days,
                SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent_days,
                SUM(CASE WHEN is_late = 1 THEN 1 ELSE 0 END) as late_count,
                SUM(total_work_minutes) as total_work_minutes,
                SUM(total_overtime_minutes) as total_overtime_minutes,
                AVG(CASE WHEN status = "present" THEN total_work_minutes ELSE NULL END) as avg_work_minutes
            ')
            ->first();
        
        return [
            'total_days' => $result->total_days,
            'present_days' => $result->present_days,
            'absent_days' => $result->absent_days,
            'late_count' => $result->late_count,
            'total_work_hours' => round($result->total_work_minutes / 60, 2),
            'total_overtime_hours' => round($result->total_overtime_minutes / 60, 2),
            'average_work_hours' => round($result->avg_work_minutes / 60, 2),
            'attendance_percentage' => $result->total_days > 0 
                ? round(($result->present_days / $result->total_days) * 100, 2)
                : 0,
        ];
    }
}
```

### 11.3 Queue Optimization

```php
// config/horizon.php (for Laravel Horizon)
return [
    'environments' => [
        'production' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['attendance-high-priority'],
                'balance' => 'auto',
                'processes' => 10,
                'tries' => 3,
                'timeout' => 60,
            ],
            'supervisor-2' => [
                'connection' => 'redis',
                'queue' => ['attendance-default'],
                'balance' => 'auto',
                'processes' => 5,
                'tries' => 3,
                'timeout' => 90,
            ],
            'supervisor-3' => [
                'connection' => 'redis',
                'queue' => ['reporting'],
                'balance' => 'auto',
                'processes' => 3,
                'tries' => 2,
                'timeout' => 300,
            ],
            'supervisor-4' => [
                'connection' => 'redis',
                'queue' => ['notifications'],
                'balance' => 'auto',
                'processes' => 5,
                'tries' => 5,
                'timeout' => 30,
            ],
        ],
    ],
];
```

---

## 12. DevOps & Deployment

### 12.1 Docker Configuration

```dockerfile
# Dockerfile
FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    git \
    zip \
    unzip \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        gd \
        zip \
        intl \
        opcache \
        pcntl \
        bcmath

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Copy configuration files
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/php/php.ini /usr/local/etc/php/php.ini
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Expose port
EXPOSE 80

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```

```yaml
# docker-compose.yml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: attendance_app
    restart: unless-stopped
    working_dir: /var/www/html
    volumes:
      - ./:/var/www/html
      - ./storage:/var/www/html/storage
    networks:
      - attendance_network
    depends_on:
      - mysql
      - redis
      - mosquitto

  mysql:
    image: mysql:8.0
    container_name: attendance_mysql
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_PASSWORD}
      MYSQL_DATABASE: ${DB_DATABASE}
    volumes:
      - mysql_data:/var/lib/mysql
      - ./docker/mysql/my.cnf:/etc/mysql/conf.d/my.cnf
    networks:
      - attendance_network

  redis:
    image: redis:7-alpine
    container_name: attendance_redis
    restart: unless-stopped
    command: redis-server --appendonly yes --requirepass ${REDIS_PASSWORD}
    volumes:
      - redis_data:/data
    networks:
      - attendance_network

  mosquitto:
    image: eclipse-mosquitto:2
    container_name: attendance_mosquitto
    restart: unless-stopped
    ports:
      - "1883:1883"
      - "9001:9001"
    volumes:
      - ./docker/mosquitto/mosquitto.conf:/mosquitto/config/mosquitto.conf
      - ./docker/mosquitto/certs:/mosquitto/certs
      - mosquitto_data:/mosquitto/data
      - mosquitto_logs:/mosquitto/log
    networks:
      - attendance_network

  nginx:
    image: nginx:alpine
    container_name: attendance_nginx
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./:/var/www/html
      - ./docker/nginx/nginx.conf:/etc/nginx/nginx.conf
      - ./docker/nginx/ssl:/etc/nginx/ssl
    networks:
      - attendance_network
    depends_on:
      - app

volumes:
  mysql_data:
  redis_data:
  mosquitto_data:
  mosquitto_logs:

networks:
  attendance_network:
    driver: bridge
```

### 12.2 CI/CD Pipeline

```yaml
# .github/workflows/deploy.yml
name: Deploy to Production

on:
  push:
    branches: [ main ]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: testing
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s
      
      redis:
        image: redis:7-alpine
        ports:
          - 6379:6379
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.3
          extensions: mbstring, pdo, pdo_mysql, redis
          coverage: xdebug
      
      - name: Install Composer Dependencies
        run: composer install --prefer-dist --no-progress
      
      - name: Copy .env
        run: cp .env.example .env
      
      - name: Generate Application Key
        run: php artisan key:generate
      
      - name: Run Migrations
        run: php artisan migrate --force
      
      - name: Run Tests
        run: php artisan test --coverage --min=80
      
      - name: Run Static Analysis
        run: ./vendor/bin/phpstan analyse
  
  deploy:
    needs: test
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Deploy to Server
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.SERVER_HOST }}
          username: ${{ secrets.SERVER_USER }}
          key: ${{ secrets.SSH_PRIVATE_KEY }}
          script: |
            cd /var/www/attendance
            git pull origin main
            composer install --no-dev --optimize-autoloader
            php artisan migrate --force
            php artisan config:cache
            php artisan route:cache
            php artisan view:cache
            php artisan queue:restart
            sudo systemctl reload php8.3-fpm
```

---

## 13. Testing Strategy

### 13.1 Unit Tests

```php
// tests/Unit/DirectionDetectorTest.php
namespace Tests\Unit;

use Tests\TestCase;
use App\Domain\Attendance\Services\DirectionDetector;
use App\Domain\Employee\Models\Employee;
use App\Domain\Shift\Models\Shift;

class DirectionDetectorTest extends TestCase
{
    protected DirectionDetector $detector;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = app(DirectionDetector::class);
    }
    
    /** @test */
    public function it_detects_check_in_at_shift_start()
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create([
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
        ]);
        
        $timestamp = now()->setTime(9, 5);
        
        $direction = $this->detector->detect($employee, $timestamp, null, $shift);
        
        $this->assertEquals('check-in', $direction);
    }
    
    /** @test */
    public function it_detects_check_out_after_work_hours()
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create([
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
        ]);
        
        $lastRecord = [
            'timestamp' => now()->setTime(9, 0)->toDateTimeString(),
            'direction' => 'check-in',
        ];
        
        $timestamp = now()->setTime(18, 0);
        
        $direction = $this->detector->detect($employee, $timestamp, $lastRecord, $shift);
        
        $this->assertEquals('check-out', $direction);
    }
    
    /** @test */
    public function it_detects_break_out_near_break_time()
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create([
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);
        
        $lastRecord = [
            'timestamp' => now()->setTime(9, 0)->toDateTimeString(),
            'direction' => 'check-in',
        ];
        
        $timestamp = now()->setTime(12, 5);
        
        $direction = $this->detector->detect($employee, $timestamp, $lastRecord, $shift);
        
        $this->assertEquals('break-out', $direction);
    }
}
```

### 13.2 Integration Tests

```php
// tests/Integration/AttendanceProcessingTest.php
namespace Tests\Integration;

use Tests\TestCase;
use App\Domain\Attendance\Services\AttendanceProcessor;
use App\Domain\Attendance\DTOs\AttendanceEventDTO;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AttendanceProcessingTest extends TestCase
{
    use RefreshDatabase;
    
    /** @test */
    public function it_processes_complete_attendance_cycle()
    {
        $employee = Employee::factory()->create();
        $device = Device::factory()->create();
        $shift = Shift::factory()->create([
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
        ]);
        
        $employee->shifts()->attach($shift, [
            'effective_from' => now()->toDateString(),
        ]);
        
        $processor = app(AttendanceProcessor::class);
        
        // Check-in
        $checkInEvent = new AttendanceEventDTO(
            employeeId: $employee->id,
            deviceId: $device->id,
            recordId: 'REC001',
            timestamp: now()->setTime(9, 10),
            recognitionScore: 95.5
        );
        
        $checkInRecord = $processor->process($checkInEvent);
        
        $this->assertEquals('check-in', $checkInRecord->direction);
        $this->assertTrue($checkInRecord->is_late);
        $this->assertEquals(10, $checkInRecord->late_minutes);
        
        // Check-out
        $checkOutEvent = new AttendanceEventDTO(
            employeeId: $employee->id,
            deviceId: $device->id,
            recordId: 'REC002',
            timestamp: now()->setTime(18, 0),
            recognitionScore: 96.2
        );
        
        $checkOutRecord = $processor->process($checkOutEvent);
        
        $this->assertEquals('check-out', $checkOutRecord->direction);
        $this->assertFalse($checkOutRecord->is_early_departure);
        
        // Verify summary
        $summary = DailyAttendanceSummary::where('employee_id', $employee->id)
            ->where('attendance_date', now()->toDateString())
            ->first();
        
        $this->assertNotNull($summary);
        $this->assertEquals('present', $summary->status);
        $this->assertEquals(2, $summary->check_in_count);
    }
}
```

### 13.3 Load Testing

```javascript
// k6 load test script
import http from 'k6/http';
import { check, sleep } from 'k6';

export let options = {
    stages: [
        { duration: '2m', target: 100 },  // Ramp up
        { duration: '5m', target: 100 },  // Stay at 100 users
        { duration: '2m', target: 200 },  // Ramp to 200 users
        { duration: '5m', target: 200 },  // Stay at 200 users
        { duration: '2m', target: 0 },    // Ramp down
    ],
    thresholds: {
        http_req_duration: ['p(95)<500'], // 95% of requests under 500ms
        http_req_failed: ['rate<0.01'],   // Less than 1% error rate
    },
};

export default function() {
    const BASE_URL = 'https://api.attendance.example.com';
    const TOKEN = 'your_api_token';
    
    const headers = {
        'Authorization': `Bearer ${TOKEN}`,
        'Content-Type': 'application/json',
    };
    
    // Get daily summary
    let res = http.get(`${BASE_URL}/v1/attendance/daily/${new Date().toISOString().split('T')[0]}`, {
        headers: headers,
    });
    
    check(res, {
        'status is 200': (r) => r.status === 200,
        'response time < 500ms': (r) => r.timings.duration < 500,
    });
    
    sleep(1);
}
```

---

## 14. Monitoring & Observability

### 14.1 Prometheus Metrics

```php
// app/Http/Middleware/MetricsMiddleware.php
namespace App\Http\Middleware;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis;

class MetricsMiddleware
{
    protected CollectorRegistry $registry;
    
    public function __construct()
    {
        Redis::setDefaultOptions(['host' => config('database.redis.default.host')]);
        $this->registry = CollectorRegistry::getDefault();
    }
    
    public function handle($request, Closure $next)
    {
        $start = microtime(true);
        
        $response = $next($request);
        
        $duration = microtime(true) - $start;
        
        // Record request metrics
        $histogram = $this->registry->getOrRegisterHistogram(
            'app',
            'http_request_duration_seconds',
            'HTTP request duration',
            ['method', 'route', 'status']
        );
        
        $histogram->observe(
            $duration,
            [$request->method(), $request->route()?->getName() ?? 'unknown', $response->status()]
        );
        
        // Record tenant metrics
        if ($tenant = tenant()) {
            $counter = $this->registry->getOrRegisterCounter(
                'app',
                'tenant_requests_total',
                'Total tenant requests',
                ['tenant_id']
            );
            
            $counter->inc([$tenant->id]);
        }
        
        return $response;
    }
}
```

### 14.2 Logging Configuration

```php
// config/logging.php
return [
    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily', 'elasticsearch'],
        ],
        
        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
        ],
        
        'elasticsearch' => [
            'driver' => 'custom',
            'via' => App\Logging\ElasticsearchLogger::class,
            'index' => 'attendance-logs',
            'hosts' => [env('ELASTICSEARCH_HOST', 'localhost:9200')],
        ],
        
        'mqtt' => [
            'driver' => 'daily',
            'path' => storage_path('logs/mqtt.log'),
            'level' => 'debug',
            'days' => 30,
        ],
        
        'attendance' => [
            'driver' => 'daily',
            'path' => storage_path('logs/attendance.log'),
            'level' => 'info',
            'days' => 90,
        ],
    ],
];
```

---

## 15. Error Handling & Recovery

### 15.1 Exception Handler

```php
// app/Exceptions/Handler.php
namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [
        DuplicateAttendanceException::class,
    ];
    
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            if (app()->bound('sentry')) {
                app('sentry')->captureException($e);
            }
        });
        
        $this->renderable(function (DuplicateAttendanceException $e, $request) {
            return response()->json([
                'error' => 'Duplicate attendance record',
                'message' => $e->getMessage(),
            ], 409);
        });
        
        $this->renderable(function (MQTTConnectionException $e, $request) {
            Log::critical('MQTT connection error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            dispatch(new SendCriticalAlert(
                'MQTT Connection Failed',
                $e->getMessage()
            ));
            
            return response()->json([
                'error' => 'Service temporarily unavailable',
                'message' => 'Attendance processing system is experiencing issues',
            ], 503);
        });
    }
}
```

### 15.2 Circuit Breaker Pattern

```php
// app/Services/CircuitBreaker.php
namespace App\Services;

class CircuitBreaker
{
    protected const FAILURE_THRESHOLD = 5;
    protected const TIMEOUT = 60; // seconds
    protected const RETRY_TIMEOUT = 300; // 5 minutes
    
    public function call(string $service, callable $callback)
    {
        $state = $this->getState($service);
        
        if ($state === 'open') {
            if ($this->shouldAttemptReset($service)) {
                $this->setState($service, 'half-open');
            } else {
                throw new CircuitBreakerOpenException("Circuit breaker is open for {$service}");
            }
        }
        
        try {
            $result = $callback();
            
            if ($state === 'half-open') {
                $this->setState($service, 'closed');
                $this->resetFailureCount($service);
            }
            
            return $result;
            
        } catch (\Throwable $e) {
            $this->recordFailure($service);
            
            if ($this->getFailureCount($service) >= self::FAILURE_THRESHOLD) {
                $this->setState($service, 'open');
                $this->setRetryTimeout($service);
            }
            
            throw $e;
        }
    }
    
    protected function getState(string $service): string
    {
        return Cache::get("circuit_breaker:{$service}:state", 'closed');
    }
    
    protected function setState(string $service, string $state): void
    {
        Cache::put("circuit_breaker:{$service}:state", $state, self::RETRY_TIMEOUT);
    }
    
    protected function recordFailure(string $service): void
    {
        Cache::increment("circuit_breaker:{$service}:failures");
    }
    
    protected function getFailureCount(string $service): int
    {
        return Cache::get("circuit_breaker:{$service}:failures", 0);
    }
    
    protected function resetFailureCount(string $service): void
    {
        Cache::forget("circuit_breaker:{$service}:failures");
    }
}
```

---

## Appendix A: Configuration Files

### Mosquitto Configuration

```conf
# mosquitto.conf
listener 1883
protocol mqtt

listener 9001
protocol websockets

allow_anonymous false
password_file /mosquitto/config/passwd

# TLS Configuration
listener 8883
protocol mqtt
cafile /mosquitto/certs/ca.crt
certfile /mosquitto/certs/server.crt
keyfile /mosquitto/certs/server.key
require_certificate false

# Logging
log_dest file /mosquitto/log/mosquitto.log
log_type all
log_timestamp true

# Persistence
persistence true
persistence_location /mosquitto/data/

# Connection limits
max_connections 10000
max_queued_messages 1000

# Message size
message_size_limit 10240
```

### Nginx Configuration

```nginx
# nginx.conf
user www-data;
worker_processes auto;
pid /run/nginx.pid;

events {
    worker_connections 4096;
    use epoll;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;
    
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;
    
    client_max_body_size 50M;
    
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml+rss text/javascript;
    
    upstream php-fpm {
        server app:9000;
    }
    
    server {
        listen 80;
        server_name _;
        root /var/www/html/public;
        index index.php;
        
        location / {
            try_files $uri $uri/ /index.php?$query_string;
        }
        
        location ~ \.php$ {
            fastcgi_pass php-fpm;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            include fastcgi_params;
            
            fastcgi_buffer_size 128k;
            fastcgi_buffers 256 16k;
            fastcgi_busy_buffers_size 256k;
            fastcgi_temp_file_write_size 256k;
        }
        
        location ~ /\.ht {
            deny all;
        }
    }
}
```

---

## Document Change Log

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 3.0 | 2025-10-01 | System Architect | Complete detailed specification with all components |
| 2.0 | 2025-09-25 | System | Complete refinement and restructuring |
| 1.0 | 2025-09-03 | Original | Initial specification |

---

## Conclusion

This comprehensive specification provides a complete blueprint for building a production-ready multi-tenant attendance monitoring system. The document covers:

- **Enterprise Architecture**: Scalable, distributed system design with proper separation of concerns
- **Multi-Tenancy**: Complete database isolation with automatic provisioning and management
- **Real-time Processing**: MQTT integration with intelligent direction detection algorithms
- **Advanced Features**: Smart shift management, comprehensive reporting, and violation tracking
- **Security**: End-to-end encryption, GDPR compliance, and audit trails
- **Performance**: Caching strategies, query optimization, and horizontal scaling capabilities
- **DevOps**: Docker containerization, CI/CD pipelines, and automated deployment
- **Monitoring**: Comprehensive observability with metrics, logging, and alerting
- **Testing**: Unit, integration, and load testing strategies for quality assurance

The system is designed to handle enterprise-scale deployments with thousands of employees per tenant while maintaining sub-second response times and 99.95% uptime.

---

## Appendix B: Quick Start Guide

### Prerequisites

- PHP 8.3+ with required extensions
- MySQL 8.0+ or PostgreSQL 14+
- Redis 7.0+
- Eclipse Mosquitto 2.0+
- Composer 2.x
- Node.js 18+ (for frontend assets)

### Installation Steps

```bash
# 1. Clone repository
git clone https://github.com/your-org/attendance-system.git
cd attendance-system

# 2. Install PHP dependencies
composer install

# 3. Install JavaScript dependencies
npm install

# 4. Configure environment
cp .env.example .env
php artisan key:generate

# 5. Configure database
# Edit .env with your database credentials

# 6. Run migrations
php artisan migrate
php artisan tenancy:migrate

# 7. Seed default data
php artisan db:seed

# 8. Build frontend assets
npm run build

# 9. Start MQTT consumer
php artisan mqtt:consume &

# 10. Start queue workers
php artisan queue:work --queue=attendance-high-priority &
php artisan queue:work --queue=attendance-default &
php artisan queue:work --queue=notifications &

# 11. Start scheduler
php artisan schedule:work &

# 12. Serve application (development)
php artisan serve
```

---

## Appendix C: API Quick Reference

### Authentication

```bash
# Login
curl -X POST https://api.attendance.example.com/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "password"
  }'

# Response
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "expires_in": 3600
}
```

### Employee Management

```bash
# Create employee
curl -X POST https://api.attendance.example.com/v1/employees \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "employee_code": "EMP001",
    "name": "John Doe",
    "email": "john@example.com",
    "department_id": 1,
    "joining_date": "2025-01-15"
  }'

# Get employee attendance history
curl -X GET "https://api.attendance.example.com/v1/employees/1/attendance-history?from=2025-01-01&to=2025-01-31" \
  -H "Authorization: Bearer {token}"
```

### Attendance Operations

```bash
# Get daily summary
curl -X GET https://api.attendance.example.com/v1/attendance/daily/2025-10-01 \
  -H "Authorization: Bearer {token}"

# Generate report
curl -X POST https://api.attendance.example.com/v1/reports/generate \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "report_type": "monthly",
    "start_date": "2025-09-01",
    "end_date": "2025-09-30",
    "format": "pdf",
    "filters": {
      "department_ids": [1, 2, 3]
    }
  }'
```

### Device Management

```bash
# Sync employees to device
curl -X POST https://api.attendance.example.com/v1/devices/1/sync-employees \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "employee_ids": [1, 2, 3, 4, 5]
  }'

# Send command to device
curl -X POST https://api.attendance.example.com/v1/devices/1/send-command \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "command": "SyncTime",
    "parameters": {
      "timestamp": "2025-10-01T12:00:00Z"
    }
  }'
```

---

## Appendix D: Troubleshooting Guide

### Common Issues and Solutions

#### MQTT Connection Issues

**Problem**: MQTT consumer not receiving messages

**Solutions**:
```bash
# Check MQTT broker status
systemctl status mosquitto

# Test MQTT connection
mosquitto_sub -h localhost -p 1883 -t "mqtt/face/#" -u username -P password

# Check Laravel logs
tail -f storage/logs/mqtt.log

# Restart MQTT consumer
php artisan mqtt:consume --reset
```

#### Database Connection Errors

**Problem**: Tenant database not accessible

**Solutions**:
```bash
# Check tenant database exists
php artisan tenancy:list

# Recreate tenant database
php artisan tenancy:migrate-fresh --tenant=tenant_id

# Clear configuration cache
php artisan config:clear
php artisan cache:clear
```

#### Queue Processing Delays

**Problem**: Attendance records processing slowly

**Solutions**:
```bash
# Check queue status (if using Horizon)
php artisan horizon:status

# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Increase queue workers
# Edit supervisor configuration to add more workers
```

#### Memory Issues

**Problem**: PHP memory exhausted during report generation

**Solutions**:
```php
// Increase memory limit in php.ini
memory_limit = 512M

// Or in specific command
php -d memory_limit=512M artisan report:generate

// Use chunking for large datasets
DB::table('attendance_records')
    ->where('employee_id', $employeeId)
    ->orderBy('timestamp')
    ->chunk(1000, function ($records) {
        // Process records
    });
```

#### Face Recognition Accuracy Issues

**Problem**: Low recognition scores or frequent mismatches

**Solutions**:
- Ensure proper lighting conditions during enrollment
- Capture face from multiple angles (minimum 3)
- Update device firmware to latest version
- Adjust recognition threshold in device settings
- Re-enroll employee with better quality images

---

## Appendix E: Performance Tuning Checklist

### Database Optimization

- [ ] Enable query caching
- [ ] Add appropriate indexes on frequently queried columns
- [ ] Implement database connection pooling
- [ ] Enable MySQL/PostgreSQL query cache
- [ ] Partition large tables by date
- [ ] Archive old attendance records (older than 2 years)
- [ ] Configure slow query log and optimize slow queries

### Redis Optimization

- [ ] Enable Redis persistence (AOF + RDB)
- [ ] Configure appropriate maxmemory policy
- [ ] Use Redis Cluster for horizontal scaling
- [ ] Monitor Redis memory usage
- [ ] Implement cache warming for frequently accessed data
- [ ] Set appropriate TTL values for cached data

### PHP-FPM Optimization

```ini
# /etc/php/8.3/fpm/pool.d/www.conf
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500

# Enable OPcache
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.fast_shutdown=1
```

### Laravel Optimization

```bash
# Production optimization commands
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
composer install --optimize-autoloader --no-dev

# Enable Laravel Octane for better performance
php artisan octane:start --workers=4 --max-requests=500
```

### MQTT Optimization

```conf
# mosquitto.conf performance tuning
max_inflight_messages 100
max_queued_messages 10000
message_size_limit 10240
persistent_client_expiration 1h
autosave_interval 1800
autosave_on_changes false
```

---

## Appendix F: Security Hardening Checklist

### Application Security

- [ ] Enable HTTPS only (TLS 1.3)
- [ ] Implement rate limiting on all API endpoints
- [ ] Use strong password hashing (bcrypt with cost factor 12)
- [ ] Enable CSRF protection on all forms
- [ ] Sanitize all user inputs
- [ ] Implement API token rotation
- [ ] Enable two-factor authentication for admin users
- [ ] Set secure cookie flags (HttpOnly, Secure, SameSite)
- [ ] Implement Content Security Policy headers
- [ ] Regular security audits and penetration testing

### Database Security

- [ ] Use separate database user per tenant
- [ ] Enable database encryption at rest
- [ ] Use SSL/TLS for database connections
- [ ] Implement database access logging
- [ ] Regular database backups with encryption
- [ ] Restrict database access by IP whitelist
- [ ] Enable database audit logging

### MQTT Security

- [ ] Enable TLS with client certificates
- [ ] Implement ACL for topic-based permissions
- [ ] Use strong passwords for MQTT authentication
- [ ] Disable anonymous connections
- [ ] Regular MQTT logs review
- [ ] Implement device whitelisting
- [ ] Monitor for suspicious connection patterns

### Infrastructure Security

```bash
# Firewall configuration
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 22/tcp from trusted_ip
ufw deny 3306/tcp  # Don't expose MySQL
ufw deny 6379/tcp  # Don't expose Redis
ufw enable

# SSH hardening
# Edit /etc/ssh/sshd_config
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
Port 2222  # Change default port

# Automatic security updates
apt install unattended-upgrades
dpkg-reconfigure -plow unattended-upgrades
```

---

## Appendix G: Backup and Recovery Procedures

### Database Backup

```bash
#!/bin/bash
# backup-database.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/attendance"
RETENTION_DAYS=30

# Backup central database
mysqldump -u root -p${DB_PASSWORD} attendance_central | \
  gzip > ${BACKUP_DIR}/central_${DATE}.sql.gz

# Backup all tenant databases
mysql -u root -p${DB_PASSWORD} -e "SHOW DATABASES LIKE 'tenant_%'" | \
  grep -v Database | \
  while read db; do
    mysqldump -u root -p${DB_PASSWORD} $db | \
      gzip > ${BACKUP_DIR}/${db}_${DATE}.sql.gz
  done

# Upload to S3
aws s3 sync ${BACKUP_DIR} s3://attendance-backups/databases/ \
  --storage-class STANDARD_IA

# Clean old backups
find ${BACKUP_DIR} -type f -mtime +${RETENTION_DAYS} -delete

echo "Backup completed: ${DATE}"
```

### Application Backup

```bash
#!/bin/bash
# backup-application.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/attendance"
APP_DIR="/var/www/attendance"

# Backup application files
tar -czf ${BACKUP_DIR}/app_${DATE}.tar.gz \
  ${APP_DIR} \
  --exclude='${APP_DIR}/node_modules' \
  --exclude='${APP_DIR}/storage/logs' \
  --exclude='${APP_DIR}/vendor'

# Backup .env file separately (encrypted)
gpg --encrypt --recipient backup@example.com \
  ${APP_DIR}/.env > ${BACKUP_DIR}/env_${DATE}.gpg

# Upload to S3
aws s3 cp ${BACKUP_DIR}/app_${DATE}.tar.gz \
  s3://attendance-backups/application/

aws s3 cp ${BACKUP_DIR}/env_${DATE}.gpg \
  s3://attendance-backups/config/

echo "Application backup completed: ${DATE}"
```

### Recovery Procedures

```bash
#!/bin/bash
# restore-database.sh

BACKUP_FILE=$1

if [ -z "$BACKUP_FILE" ]; then
  echo "Usage: ./restore-database.sh <backup_file>"
  exit 1
fi

# Extract database name from filename
DB_NAME=$(basename $BACKUP_FILE | cut -d'_' -f1)

# Restore database
gunzip < $BACKUP_FILE | mysql -u root -p${DB_PASSWORD} $DB_NAME

# Verify restore
mysql -u root -p${DB_PASSWORD} -e "SELECT COUNT(*) FROM employees" $DB_NAME

echo "Database restored: $DB_NAME"
```

---

## Appendix H: Monitoring Dashboards

### Grafana Dashboard Configuration

```json
{
  "dashboard": {
    "title": "Attendance System Overview",
    "panels": [
      {
        "title": "Request Rate",
        "targets": [
          {
            "expr": "rate(http_requests_total[5m])"
          }
        ]
      },
      {
        "title": "Response Time (P95)",
        "targets": [
          {
            "expr": "histogram_quantile(0.95, rate(http_request_duration_seconds_bucket[5m]))"
          }
        ]
      },
      {
        "title": "MQTT Messages/sec",
        "targets": [
          {
            "expr": "rate(mqtt_messages_received_total[1m])"
          }
        ]
      },
      {
        "title": "Queue Depth",
        "targets": [
          {
            "expr": "redis_queue_size"
          }
        ]
      },
      {
        "title": "Active Devices",
        "targets": [
          {
            "expr": "count(device_last_seen_timestamp > (time() - 300))"
          }
        ]
      },
      {
        "title": "Error Rate",
        "targets": [
          {
            "expr": "rate(http_requests_total{status=~\"5..\"}[5m])"
          }
        ]
      }
    ]
  }
}
```

### Alert Rules

```yaml
# prometheus-alerts.yml
groups:
  - name: attendance_system
    interval: 30s
    rules:
      - alert: HighErrorRate
        expr: rate(http_requests_total{status=~"5.."}[5m]) > 0.05
        for: 5m
        labels:
          severity: critical
        annotations:
          summary: "High error rate detected"
          description: "Error rate is {{ $value }} requests/second"
      
      - alert: MQTTBrokerDown
        expr: up{job="mosquitto"} == 0
        for: 1m
        labels:
          severity: critical
        annotations:
          summary: "MQTT broker is down"
          description: "Mosquitto broker is not responding"
      
      - alert: HighQueueDepth
        expr: redis_queue_size > 10000
        for: 10m
        labels:
          severity: warning
        annotations:
          summary: "Queue depth is high"
          description: "Queue has {{ $value }} pending jobs"
      
      - alert: DatabaseReplicationLag
        expr: mysql_slave_lag_seconds > 60
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "Database replication lag detected"
          description: "Replication is {{ $value }} seconds behind"
      
      - alert: DeviceOffline
        expr: (time() - device_last_heartbeat_timestamp) > 600
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "Device {{ $labels.device_id }} is offline"
          description: "No heartbeat received for 10 minutes"
```

---

## Appendix I: Scaling Guidelines

### Horizontal Scaling Strategy

#### Application Tier Scaling

**When to scale**:
- CPU usage consistently > 70%
- Memory usage > 80%
- Request queue depth > 100
- Response time P95 > 500ms

**How to scale**:
```bash
# Using Docker Swarm
docker service scale attendance_app=5

# Using Kubernetes
kubectl scale deployment attendance-app --replicas=5

# Using AWS Auto Scaling
aws autoscaling set-desired-capacity \
  --auto-scaling-group-name attendance-app-asg \
  --desired-capacity 5
```

#### Database Scaling

**Read Replicas**:
```sql
-- Configure read replica
CREATE DATABASE tenant_xxx_replica;

-- Application configuration
// config/database.php
'mysql' => [
    'read' => [
        'host' => [
            env('DB_READ_HOST_1'),
            env('DB_READ_HOST_2'),
        ],
    ],
    'write' => [
        'host' => [env('DB_WRITE_HOST')],
    ],
]
```

**Sharding Strategy**:
- Shard by tenant_id (hash-based)
- Each shard contains subset of tenants
- Route queries based on tenant identifier

#### Redis Scaling

```bash
# Redis Cluster setup
redis-cli --cluster create \
  127.0.0.1:7000 \
  127.0.0.1:7001 \
  127.0.0.1:7002 \
  127.0.0.1:7003 \
  127.0.0.1:7004 \
  127.0.0.1:7005 \
  --cluster-replicas 1
```

#### MQTT Scaling

```conf
# Bridge configuration for MQTT cluster
connection bridge-01
address broker2.example.com:1883
topic mqtt/face/# both 2
```

### Vertical Scaling Guidelines

| Component | Current | 1K Users | 5K Users | 10K Users |
|-----------|---------|----------|----------|-----------|
| Web Servers | 2 CPU, 4GB | 4 CPU, 8GB | 8 CPU, 16GB | 16 CPU, 32GB |
| Database | 4 CPU, 8GB | 8 CPU, 16GB | 16 CPU, 32GB | 32 CPU, 64GB |
| Redis | 2 CPU, 4GB | 4 CPU, 8GB | 8 CPU, 16GB | 16 CPU, 32GB |
| MQTT Broker | 2 CPU, 2GB | 4 CPU, 4GB | 8 CPU, 8GB | 16 CPU, 16GB |

---

## Appendix J: Maintenance Schedule

### Daily Tasks

- [ ] Monitor system health dashboard
- [ ] Review error logs for critical issues
- [ ] Check queue depth and processing rates
- [ ] Verify backup completion
- [ ] Monitor device connectivity
- [ ] Review attendance processing metrics

### Weekly Tasks

- [ ] Analyze slow query logs
- [ ] Review and optimize database indexes
- [ ] Check disk space usage
- [ ] Review security logs
- [ ] Update blacklist/whitelist rules
- [ ] Generate and review weekly reports
- [ ] Performance baseline comparison

### Monthly Tasks

- [ ] Apply security patches
- [ ] Update SSL certificates (if needed)
- [ ] Database optimization (ANALYZE, OPTIMIZE)
- [ ] Review and archive old logs
- [ ] Capacity planning review
- [ ] Review and update documentation
- [ ] Conduct disaster recovery drill
- [ ] Review user access and permissions
- [ ] Generate compliance reports

### Quarterly Tasks

- [ ] Major version updates (Laravel, PHP)
- [ ] Security audit
- [ ] Performance testing
- [ ] Review and update SLA metrics
- [ ] Infrastructure cost optimization
- [ ] Database schema review
- [ ] API versioning strategy review

### Annual Tasks

- [ ] Complete system audit
- [ ] Disaster recovery test
- [ ] Review and update business continuity plan
- [ ] Compliance certification renewals
- [ ] Major infrastructure upgrades
- [ ] Long-term capacity planning
- [ ] Archive historical data

---

## Appendix K: Glossary of Terms

**Attendance Record**: A single timestamp event capturing an employee's interaction with a biometric device

**Check-In**: Employee arrival at workplace, marking the start of work period

**Check-Out**: Employee departure from workplace, marking the end of work period

**Circuit Breaker**: Design pattern that prevents cascading failures by stopping requests to failing services

**Daily Summary**: Aggregated attendance data for an employee for a single day

**Direction Detection**: Algorithm that determines whether an attendance event is check-in, check-out, break-out, or break-in

**Face Template**: Encrypted biometric data representing an employee's facial features

**Grace Period**: Additional time allowed after shift start before marking late arrival

**MQTT**: Message Queuing Telemetry Transport protocol for lightweight messaging

**Multi-Tenancy**: Architecture pattern allowing single application instance to serve multiple customers

**QoS**: Quality of Service level in MQTT (0=at most once, 1=at least once, 2=exactly once)

**Recognition Score**: Confidence percentage of face match (0-100%)

**Shift Override**: Temporary change to employee's regular shift schedule

**Stranger Log**: Record of unrecognized face captured by device

**Tenant**: Independent customer organization using the system

**Violation**: Rule breach such as late arrival or early departure

---

## Contact Information

**Project Lead**: project.lead@company.com  
**Technical Support**: support@attendance.example.com  
**Security Issues**: security@attendance.example.com  
**Sales Inquiries**: sales@attendance.example.com

**Documentation**: https://docs.attendance.example.com  
**API Reference**: https://api.attendance.example.com/docs  
**Status Page**: https://status.attendance.example.com

---

*End of Detailed Specification Document v3.0*
```

---

## 10. Security & Compliance

### 10.1 Data Encryption

```php
// config/encryption.php
return [
    'face_templates' => [
        'algorithm' => 'aes-256-gcm',
        'key_rotation_days' => 90,
    ],
    
    'database' => [
        'at_rest' => env('DB_ENCRYPTION_ENABLED', true),
    ],
    
    'storage' => [
        'encryption' => env('STORAGE_ENCRYPTION_ENABLED', true),
        'algorithm' => 'AES256',
    ],
];
```

### 10.2 GDPR Compliance

```php
// app/Services/GDPRComplianceService.php
namespace App\Services;

class GDPRComplianceService
{
    public function anonymizeEmployee(Employee $employee): void
    {
        DB::transaction(function() use ($employee) {
            // Anonymize personal data
            $employee->update([
                'name' => 'ANONYMIZED_' . $employee->id,
                'email' => null,
                'phone' => null,
                'custom_id' => 'ANON_' . $employee->id, // Anonymize custom_id
            ]);
            
            // Delete photos (audit trail images)
            $this->deleteEmployeePhotos($employee);
            
            // Keep attendance records for legal requirements (anonymized)
            $this->anonymizeAttendanceRecords($employee);
            
            // Note: No face template data to delete as it only exists on device
            
            // Log GDPR action
            AuditLog::create([
                'action' => 'gdpr_anonymization',
                'entity_type' => 'Employee',
                'entity_id' => $employee->id,
                'description' => 'Employee data anonymized per GDPR request. Device face templates must be manually removed.',
            ]);
        });
    }
    
    public function exportEmployeeData(Employee $employee): array
    {
        return [
            'personal_info' => $employee->only([
                'employee_code', 'custom_id', 'name', 'email', 'phone',
                'designation', 'joining_date'
            ]),
            'attendance_records' => $employee->attendanceRecords()
                ->with('device', 'shift')
                ->get()
                ->map(function($record) {
                    // Note: No face template data to export
                    return [
                        'timestamp' => $record->timestamp,
                        'direction' => $record->direction,
                        'device' => $record->device->name,
                        'recognition_score' => $record->recognition_score, // Score from device
                    ];
                })
                ->toArray(),
            'leave_requests' => $employee->leaveRequests()->get()->toArray(),
            'violations' => $employee->violations()->get()->toArray(),
            'note' => 'Face biometric data is stored only on physical devices and must be requested separately from device administrators.'
        ];
    }
    
    protected function deleteEmployeePhotos(Employee $employee): void
    {
        // Delete audit trail photos only
        $photos = AttendanceRecord::where('employee_id', $employee->id)
            ->whereNotNull('photo_path')
            ->pluck('photo_path');
        
        foreach ($photos as $photo) {
            Storage::disk('s3')->delete($photo);
        }
        
        // Clear photo paths
        AttendanceRecord::where('employee_id', $employee->id)
            ->update(['photo_path' => null]);
    }
}