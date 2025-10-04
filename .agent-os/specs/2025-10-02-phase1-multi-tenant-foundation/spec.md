# Spec Requirements Document

> Spec: Phase 1 - Multi-Tenant Foundation & Core Attendance
> Created: 2025-10-02

## Overview

Establish the foundational multi-tenant architecture with complete database isolation per tenant, MQTT-based attendance event processing, and core employee/shift management capabilities. This phase provides the essential infrastructure for secure, scalable attendance tracking across unlimited tenants.

## User Stories

### System Administrator Provisioning Tenants

As a system administrator, I want to provision new tenant organizations with isolated databases, so that each client's data is completely separated and secure.

**Workflow:**
1. Admin accesses super admin panel
2. Creates new tenant with company name, domain, and subscription plan
3. System automatically creates isolated database (`tenant_abc123_def456`)
4. System runs migrations and seeds default data (shifts, departments, settings)
5. System creates default admin user for tenant
6. Tenant admin receives credentials and can access their isolated system

**Problem Solved:** Eliminates manual database setup and ensures consistent tenant provisioning with complete data isolation for compliance and security.

### HR Manager Managing Employees

As an HR manager, I want to add employees to the system with automatic custom ID generation and track their device sync status, so that employees can use biometric devices for attendance tracking.

**Workflow:**
1. HR manager logs into tenant-specific portal
2. Navigates to employee management section
3. Adds employee with name, department, employee code, joining date
4. System automatically generates unique `custom_id` (e.g., EMP001)
5. HR manager assigns employee to a fixed shift
6. System queues employee sync to all registered devices via MQTT
7. HR manager monitors device enrollment status showing which devices have successfully synced the employee

**Problem Solved:** Streamlines employee onboarding with automatic ID generation and provides visibility into device sync status, reducing manual coordination with IT staff.

### Facility Manager Receiving Attendance Events

As a facility manager, I want biometric attendance events from devices to be automatically processed and stored in real-time, so that I have accurate attendance records without manual entry.

**Workflow:**
1. Employee scans face at biometric device
2. Device performs facial recognition and publishes MQTT message with `custom_id` and timestamp
3. System's MQTT consumer receives message in real-time (<2 seconds)
4. System looks up employee by `custom_id` in tenant database
5. System creates attendance record with device ID, timestamp, and recognition score
6. System updates real-time dashboard showing latest attendance event
7. Facility manager sees live attendance feed on dashboard

**Problem Solved:** Eliminates manual attendance entry and provides real-time visibility into employee attendance, enabling immediate response to attendance issues.

## Spec Scope

1. **Multi-Tenant Infrastructure** - Database-per-tenant architecture with automatic provisioning and tenant resolution middleware
2. **Central Database Schema** - Tenant registry, device mappings, super admin accounts, and MQTT broker configurations
3. **Tenant Database Schema** - Employee, department, device, shift, and attendance_records tables with proper indexes and relationships
4. **MQTT Client Integration** - Connection management with TLS support, auto-reconnection, subscription to device topics, and message routing
5. **Employee Management** - CRUD operations with custom ID generation, department assignment, and shift assignment (one fixed shift per employee)
6. **Device Registry** - Device registration in central database with tenant mapping and health monitoring
7. **Basic Shift Management** - Fixed shift creation with start/end times, grace periods, and working days configuration
8. **Attendance Record Creation** - Processing MQTT recognition events and creating attendance records with employee lookup by custom_id
9. **Queue Infrastructure** - Redis-backed queue system with multiple priority levels for attendance processing
10. **Admin Authentication** - Laravel Fortify-based authentication with tenant context and basic authorization
11. **Device Enrollment Tracking** - Track which employees are synced to which devices with enrollment status
12. **Basic MQTT Commands** - AddPerson command to sync employees to devices via MQTT

## Out of Scope

- **Smart Direction Detection** (Phase 2) - Multi-factor scoring algorithm to determine check-in/check-out/break automatically; Phase 1 defaults all attendance to 'check-in'
- **Violation Detection Engine** (Phase 2) - Automatic detection of late arrival, early departure, extended breaks with severity levels
- **Daily Attendance Summaries** (Phase 2) - Aggregated daily summaries with work hours, break time, overtime calculations
- **Violation Notifications** (Phase 2) - Real-time alerts to managers when violations occur
- **Attendance Correction Workflows** (Phase 2) - Employee requests for missing/incorrect attendance records with approval flow
- **Advanced Shift Types** (Phase 3) - Rotating shifts, flexible shifts, overnight shift support with automatic rotation scheduling
- **Shift Override System** (Phase 2) - Special dates (holidays, off days, custom shifts for specific employees/dates)
- **Leave Request Management** (Phase 3) - Leave application, approval workflow, balance tracking
- **Manager Dashboards** (Phase 3) - Real-time attendance overview, team heatmaps, drill-down analytics
- **Advanced Reporting** (Phase 2-3) - Shift-wise reports, department comparisons, trend analysis, PDF/Excel export
- **RESTful API** (Phase 4) - External API for integrations with authentication, webhooks, rate limiting
- **Photo Storage** (Phase 3) - S3 storage for attendance photos (Phase 1 receives photos but doesn't store them)
- **Break Time Tracking** (Phase 2) - break-in/break-out direction with duration validation
- **Historical Pattern Analysis** (Phase 2) - Learning employee's typical check-in/out times for direction detection
- **Device Command Acknowledgements** (Phase 1 basic only) - Full retry logic and error handling deferred to Phase 3

## Expected Deliverable

1. **Tenant Provisioning**: Can create new tenant via CLI command (`php artisan tenant:create`) with automatic database creation, migration, and seeding
2. **Employee Management UI**: Can add, edit, and list employees through web interface with automatic custom_id generation and shift assignment
3. **Device Registration**: Can register devices in central database and map them to tenants
4. **MQTT Attendance Processing**: Can receive MQTT message from device and create attendance record with correct employee and timestamp (testable by publishing test MQTT message)
5. **Basic Shift Management**: Can create fixed shifts with start/end times and assign employees to shifts
6. **Multi-Tenant Isolation**: Can switch between tenants and verify data isolation (one tenant cannot see another tenant's employees or attendance records)
7. **Device Sync Status**: Can view which employees are synced to which devices with enrollment status tracking
