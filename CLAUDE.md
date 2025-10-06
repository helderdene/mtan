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

The system automatically determines attendance direction (check-in, check-out, break-start, break-end) using the `DirectionDetector` service with a multi-factor weighted scoring algorithm:

**Scoring Factors (weights - total 100 points):**

1. **Last Record Analysis (30%)**: Logical transitions based on previous direction
   - After check-in → favor check-out or break-start (100 points)
   - After check-out → favor check-in (100 points)
   - After break-start → favor break-end (100 points)
   - After break-end → favor check-out (100 points)

2. **Shift Timing Proximity (35%)**: Time-based scoring relative to shift schedule
   - Within 30 min of shift start → favor check-in
   - Within 30 min of shift end → favor check-out
   - Within 15 min of break start → favor break-start
   - Within 15 min of break end → favor break-end

3. **Work Duration (15%)**: Realistic work/break duration validation
   - < 30 min since check-in → penalize check-out (0 points)
   - ≥ 4 hours since check-in → favor check-out (100 points)
   - 1-120 min since break-start → favor break-end (100 points)

4. **Historical Pattern Analysis (20%)**: Employee's typical check-in/out times from last 30 days
   - **Within 1σ (68% of data)**: 20 points - Very consistent with pattern
   - **Within 2σ (95% of data)**: 15 points - Consistent with pattern
   - **Within 3σ (99.7% of data)**: 10 points - Acceptable variation
   - **Outside 3σ**: 5 points - Unusual for this employee
   - **Unreliable pattern (< 7 records)**: 10 points - Neutral score for new employees
   - **Caching**: Patterns cached for 24 hours, invalidated on new attendance records
   - **Performance**: < 100ms for calculation, < 5ms for cached retrieval

**Usage Example:**
```php
use App\Domain\Attendance\Services\DirectionDetector;
use Carbon\Carbon;

$detector = new DirectionDetector();
$employee = Employee::find(1);
$shift = $employee->current_shift;
$timestamp = Carbon::parse('2025-10-05 09:05:00');

$result = $detector->detect($employee, $timestamp, $shift);

// $result->direction: 'check-in' | 'check-out' | 'break-start' | 'break-end'
// $result->confidence: 0-100 integer (85 = high confidence)
// $result->getConfidenceLevel(): 'high' | 'medium' | 'low'
// $result->reason: "High confidence: Near shift start (09:00), first record of day"
// $result->scores: Array of detailed score breakdown
```

**Overnight Shift Support:**
The detector automatically handles shifts crossing midnight (e.g., 22:00-06:00) by comparing timestamp dates and adjusting shift boundaries accordingly.

**Edge Cases:**
- No shift assigned: Uses fallback time-of-day logic only
- No previous records: Strongly favors check-in (first record always check-in)
- Multiple same-direction records: Suggests opposite direction
- Events far from shift: Lower confidence but still returns best guess

This eliminates the need for separate entry/exit devices or manual direction selection.

### Shift Override System

The system supports flexible shift schedule modifications through the `OverrideService`, enabling company-wide holidays, employee-specific off-days, half-day shifts, and custom shift times.

**Override Types:**
1. **holiday** - Company-wide holiday (no work required)
2. **off-day** - Employee-specific day off
3. **half-day** - Modified shift with custom start/end times
4. **custom-shift** - One-time shift time adjustment

**Priority-Based Resolution:**
When multiple overrides exist for the same date, the system uses this priority order:

1. **Employee-specific shift override** (highest priority)
   - Targets specific employee + shift combination
   - Example: John's morning shift on Dec 25th is a half-day

2. **Employee-specific off-day**
   - Targets employee without specific shift
   - Example: Jane has personal day on Nov 15th (applies to all her shifts)

3. **Company-wide shift override** (lowest priority)
   - Targets shift without specific employee
   - Example: All morning shifts are holidays on Dec 25th

**Database Schema:**
```sql
shift_overrides
├── shift_id (nullable)           -- NULL = applies to all shifts
├── employee_id (nullable)         -- NULL = applies to all employees
├── override_date                  -- The date being overridden
├── type                           -- holiday | off-day | half-day | custom-shift
├── custom_start_time (nullable)   -- For half-day/custom-shift
├── custom_end_time (nullable)     -- For half-day/custom-shift
└── reason (nullable)              -- Optional explanation
```

**Key Services & DTOs:**

```php
// OverrideService - Main service for override management
use App\Domain\Shift\Services\OverrideService;
use Carbon\Carbon;

$overrideService = app(OverrideService::class);

// 1. Get active override for date/shift/employee
$override = $overrideService->getActiveOverride($date, $shift, $employee);

// 2. Check if work is required (false for holidays/off-days)
$workRequired = $overrideService->isWorkRequired($date, $employee);

// 3. Get effective shift times (null if no work required)
$effectiveShift = $overrideService->getEffectiveShiftTimes($date, $shift, $employee);

if ($effectiveShift) {
    echo $effectiveShift->startTime;  // Carbon instance
    echo $effectiveShift->endTime;    // Carbon instance
    echo $effectiveShift->isModified; // bool - true if override applied

    // Utility methods
    $duration = $effectiveShift->getDurationMinutes();
    $isNearStart = $effectiveShift->isNearStart($timestamp, 30); // Within 30 min
    $isWithinShift = $effectiveShift->isWithinShift($timestamp);
}
```

**EffectiveShift DTO:**
```php
use App\Domain\Shift\DTOs\EffectiveShift;

// Create from regular shift
$effectiveShift = EffectiveShift::fromShift($shift, $date);

// Create from override
$effectiveShift = EffectiveShift::fromOverride($override, $shift, $date);

// Properties
$effectiveShift->startTime;      // Carbon - shift start time for this date
$effectiveShift->endTime;        // Carbon - shift end time for this date
$effectiveShift->isModified;     // bool - true if override changed times
$effectiveShift->override;       // ?ShiftOverride - the override applied
$effectiveShift->originalShift;  // ?Shift - original shift definition
```

**Caching Strategy:**
- Overrides cached for 24 hours per date/shift/employee combination
- Cache keys: `override:{date}:{shift_id}:{employee_id}`
- Automatic invalidation on override create/update/delete
- Tenant-isolated keys (when multi-tenancy implemented)

**Integration with Direction Detection:**
The `DirectionDetector` automatically integrates override checks:
```php
// Before direction detection, check for overrides
$override = $this->overrideService->getActiveOverride($date, $shift, $employee);

if ($override && in_array($override->type, ['holiday', 'off-day'])) {
    Log::warning("Attendance event on {$override->type}", [...]);
    // Direction still detected but with reduced confidence (<= 50%)
}

// Use effective shift times for proximity scoring
$effectiveShift = $this->overrideService->getEffectiveShiftTimes($date, $shift, $employee);
$shiftScore = $this->calculateShiftTimingScore($timestamp, $effectiveShift);
```

**API Endpoints:**
```
GET    /api/v1/shift-overrides              # List with filters
POST   /api/v1/shift-overrides              # Create override
GET    /api/v1/shift-overrides/{id}         # Show override
PATCH  /api/v1/shift-overrides/{id}         # Update override
DELETE /api/v1/shift-overrides/{id}         # Delete override
```

**Query Filters:**
- `date` - Exact date match
- `from_date` / `to_date` - Date range
- `shift_id` - Filter by shift
- `employee_id` - Filter by employee
- `type` - Filter by override type
- `company_wide=true` - Only company-wide overrides (null employee_id)

**Usage Examples:**

```php
// Example 1: Company-wide holiday
ShiftOverride::create([
    'shift_id' => null,          // All shifts
    'employee_id' => null,       // All employees
    'override_date' => '2025-12-25',
    'type' => 'holiday',
    'reason' => 'Christmas Day'
]);

// Example 2: Employee-specific off-day
ShiftOverride::create([
    'shift_id' => null,          // All shifts for this employee
    'employee_id' => 123,
    'override_date' => '2025-11-15',
    'type' => 'off-day',
    'reason' => 'Personal day'
]);

// Example 3: Half-day shift
ShiftOverride::create([
    'shift_id' => 1,             // Specific shift
    'employee_id' => 456,        // Specific employee
    'override_date' => '2025-11-20',
    'type' => 'half-day',
    'custom_start_time' => '09:00:00',
    'custom_end_time' => '13:00:00',
    'reason' => 'Medical appointment'
]);

// Example 4: Custom shift for maintenance
ShiftOverride::create([
    'shift_id' => 2,
    'employee_id' => null,       // All employees on this shift
    'override_date' => '2025-12-01',
    'type' => 'custom-shift',
    'custom_start_time' => '10:00:00',
    'custom_end_time' => '18:00:00',
    'reason' => 'Office maintenance - delayed start'
]);
```

**Testing Overrides:**
```php
// Test override resolution priority
$employee = Employee::factory()->create();
$shift = Shift::factory()->create();

// Company-wide holiday
$companyOverride = ShiftOverride::factory()->holiday()->create([
    'override_date' => '2025-12-25',
    'shift_id' => $shift->id,
]);

// Employee-specific override should win
$employeeOverride = ShiftOverride::factory()->halfDay()->create([
    'override_date' => '2025-12-25',
    'shift_id' => $shift->id,
    'employee_id' => $employee->id,
]);

$active = $overrideService->getActiveOverride(
    Carbon::parse('2025-12-25'),
    $shift,
    $employee
);

expect($active->id)->toBe($employeeOverride->id); // Employee override wins
```

**Important Notes:**
- Always use `whereDate()` for date queries (not `where()`) due to Carbon casting
- Override cache automatically invalidated on CRUD operations
- Attendance events on holidays/off-days are logged with warnings
- Direction detection still functions on override dates but with reduced confidence
- Half-day/custom-shift overrides seamlessly modify shift times for all attendance logic

### Daily Attendance Summaries

The system automatically generates and maintains **daily attendance summaries** that aggregate attendance records into comprehensive daily reports for each employee. These summaries power dashboards, reports, and analytics.

**Core Purpose:**
- Aggregate multiple attendance events (check-in, check-out, breaks) into daily totals
- Calculate work hours, break time, overtime, and attendance status
- Provide fast query performance for reports and dashboards
- Enable bulk recalculation for data corrections

**Database Schema:**
```sql
daily_attendance_summaries
├── id
├── employee_id
├── date                        -- Summary date
├── first_check_in (time)       -- Time of first check-in (HH:MM:SS)
├── last_check_out (time)       -- Time of last check-out (HH:MM:SS)
├── total_work_minutes          -- Total work time (excluding breaks)
├── total_break_minutes         -- Total break time
├── overtime_minutes            -- Work beyond expected hours
├── status                      -- present | absent | half-day | on-leave | holiday
├── is_complete                 -- false if employee still checked in
├── created_at
├── updated_at
└── UNIQUE(employee_id, date)   -- One summary per employee per day
```

**Calculation Algorithm (`SummaryCalculator` service):**

The `SummaryCalculator` service aggregates attendance records into daily summaries:

```php
use App\Domain\Attendance\Services\SummaryCalculator;
use Carbon\Carbon;

$calculator = app(SummaryCalculator::class);
$employee = Employee::find(1);
$date = Carbon::parse('2025-10-06');

// Calculate summary for specific date
$summary = $calculator->calculateForDate($employee, $date);

echo $summary->total_work_minutes;  // 480 (8 hours)
echo $summary->total_work_hours;    // 8.0 (calculated accessor)
echo $summary->status;               // 'present'
echo $summary->is_complete;          // true (has check-out)
```

**Calculation Components:**

1. **Work Hours Calculation:**
   - Pairs check-in → check-out or break-end → next event
   - Handles overnight shifts (check-out after midnight)
   - Excludes break time from work time
   - Returns incomplete=false if employee still checked in

2. **Break Time Calculation:**
   - Pairs break-start → break-end
   - Supports multiple breaks per day
   - Ongoing breaks not counted until break-end

3. **Overtime Calculation:**
   - Work minutes - Expected shift minutes
   - Integrates with shift override system
   - All work on holidays = overtime
   - Respects half-day/custom-shift overrides

4. **Status Determination:**
   - **holiday**: Override type is 'holiday'
   - **on-leave**: Override type is 'off-day'
   - **absent**: No work minutes recorded
   - **half-day**: Work < 50% of expected hours
   - **present**: Work >= 50% of expected hours

**Real-Time Updates:**

Summaries are automatically updated when attendance events occur:

```php
use App\Domain\Attendance\Services\SummaryCalculator;
use App\Models\Tenant\AttendanceRecord;

// Called from ProcessAttendanceEvent job
$calculator = app(SummaryCalculator::class);
$record = AttendanceRecord::find($recordId);

// Automatically determines correct date (handles overnight shifts)
$summary = $calculator->updateSummaryFromEvent($record);
```

**Bulk Recalculation:**

For data corrections or historical recalculations:

```php
// Programmatic recalculation
$calculator = app(SummaryCalculator::class);
$employee = Employee::find(1);
$startDate = Carbon::parse('2025-10-01');
$endDate = Carbon::parse('2025-10-31');

$count = $calculator->recalculateRange($employee, $startDate, $endDate);
echo "Recalculated {$count} summaries"; // 31

// Artisan command
php artisan attendance:recalculate-summaries \
    --employee=1 \
    --from=2025-10-01 \
    --to=2025-10-31
```

**RESTful API Endpoints:**

All endpoints protected by `auth:sanctum` middleware:

```bash
# List summaries with filtering
GET /api/v1/attendance-summaries
GET /api/v1/attendance-summaries?employee_id=1
GET /api/v1/attendance-summaries?from=2025-10-01&to=2025-10-31
GET /api/v1/attendance-summaries?status=present
GET /api/v1/attendance-summaries?include=employee

# Get single summary
GET /api/v1/attendance-summaries/123

# Trigger recalculation
POST /api/v1/attendance-summaries/recalculate
{
  "employee_id": 1,
  "from": "2025-10-01",
  "to": "2025-10-31"
}
```

**API Response Format:**
```json
{
  "data": [{
    "id": 1,
    "employee_id": 1,
    "employee": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com"
    },
    "date": "2025-10-06",
    "first_check_in": "09:00:00",
    "last_check_out": "17:00:00",
    "total_work_minutes": 420,
    "total_work_hours": 7.0,
    "total_break_minutes": 60,
    "total_break_hours": 1.0,
    "overtime_minutes": 0,
    "overtime_hours": 0.0,
    "status": "present",
    "is_complete": true
  }],
  "links": { ... },
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 100
  }
}
```

**Query Filters:**
- `employee_id`: Filter by specific employee
- `date`: Exact date match (YYYY-MM-DD)
- `from` / `to`: Date range (inclusive)
- `status`: Filter by attendance status
- `per_page`: Results per page (default: 15)
- `include=employee`: Include employee relationship

**Integration with Shift Override System:**

The summary calculator seamlessly integrates with shift overrides:

```php
// Holiday detection
$summary = $calculator->calculateForDate($employee, $holidayDate);
echo $summary->status; // 'holiday'
echo $summary->overtime_minutes; // All work = overtime

// Half-day override
$summary = $calculator->calculateForDate($employee, $halfDayDate);
// Expected hours automatically adjusted to half-day duration
```

**Performance Considerations:**

1. **Real-time Updates**: Summaries updated in job queue (non-blocking)
2. **Eager Loading**: Use `?include=employee` to avoid N+1 queries
3. **Indexed Queries**: Unique index on (employee_id, date)
4. **Pagination**: Default 15 per page for large datasets

**Testing:**

```php
// Feature tests cover:
// - Real-time summary creation and updates
// - Work hour calculations (single/multiple periods)
// - Break time calculations
// - Overnight shift handling
// - Status determination (present/absent/half-day/holiday)
// - Bulk recalculation
// - API endpoints with filtering
// - Validation and error handling

php artisan test tests/Feature/Attendance/
// 86 tests passing (402 assertions)
```

**Important Implementation Notes:**

- Summaries use `updateOrCreate()` - safe for concurrent updates
- Overnight shifts: check-out after midnight belongs to check-in's date
- Incomplete days marked `is_complete = false` until checkout
- Status priorities: holiday > on-leave > absent > half-day > present
- All times stored in HH:MM:SS format (24-hour)
- Calculations handle missing data gracefully (null-safe)

### Attendance Correction Workflow

The system provides a complete workflow for employees to request attendance corrections and managers to review/approve them, with full audit trails and automatic application of approved changes.

**Core Purpose:**
- Allow employees to request corrections for attendance errors (wrong time, missing records, duplicates)
- Enable manager review with approval/rejection workflow
- Automatically apply approved corrections to attendance data
- Recalculate daily summaries and violations after corrections
- Maintain complete audit trail for compliance

**Database Schema:**

```sql
attendance_corrections
├── id
├── employee_id                     -- Employee requesting correction
├── attendance_record_id (nullable) -- Record being corrected (null for missing records)
├── type                            -- Enum: missing_checkout | wrong_time | duplicate_record | missing_record | other
├── status                          -- Enum: pending | approved | rejected | applied
├── original_data (json, nullable)  -- Snapshot of original data
├── proposed_data (json)            -- Requested changes
├── reason (text)                   -- Employee's explanation (required, min 10 chars)
├── supporting_document_path        -- Optional file upload (PDF/JPG/PNG, max 5MB)
├── reviewed_by (nullable)          -- Manager who reviewed
├── reviewed_at (nullable)          -- Timestamp of review
├── review_notes (nullable)         -- Manager's notes
├── applied_at (nullable)           -- Timestamp when applied
├── created_at
├── updated_at
└── INDEXES: status, (employee_id, status)

audit_logs (polymorphic for all correction actions)
├── id
├── user_id                         -- Who performed the action
├── auditable_type                  -- AttendanceCorrection | AttendanceRecord
├── auditable_id
├── action                          -- created | updated | approved | rejected | applied
├── old_values (json)               -- Before state
├── new_values (json)               -- After state
├── ip_address
├── user_agent
├── notes
├── created_at
└── INDEXES: (auditable_type, auditable_id), user_id, action

attendance_records (updated)
├── ... existing fields ...
├── is_manual_correction (boolean)  -- TRUE if created/modified by correction
├── correction_id (nullable)        -- Link to correction that created/modified this
```

**Correction Types:**

1. **missing_checkout** - Employee forgot to check out
   ```json
   { "proposed_data": { "check_out_time": "17:00:00" } }
   ```

2. **wrong_time** - Device recorded incorrect time
   ```json
   { "proposed_data": { "check_in_time": "09:00:00" } }
   ```

3. **duplicate_record** - Multiple records created by error
   ```json
   { "proposed_data": { "duplicate_record_id": 123 } }
   ```

4. **missing_record** - No record but employee was present
   ```json
   {
     "proposed_data": {
       "date": "2025-10-06",
       "check_in_time": "09:00:00",
       "check_out_time": "17:00:00"  // Optional
     }
   }
   ```

**Workflow States:**

```
pending → approved → applied
       ↘ rejected
```

- **pending**: Initial state when employee submits request
- **approved**: Manager approved, ready for application
- **rejected**: Manager rejected with required notes
- **applied**: Correction successfully applied to attendance data

**Core Services:**

```php
// CorrectionApplicator - Applies approved corrections with transaction safety
use App\Domain\Attendance\Services\CorrectionApplicator;

$applicator = app(CorrectionApplicator::class);

// Apply correction (transaction-wrapped, auto-rollback on error)
$applicator->apply($correction);

// What happens:
// 1. Validates correction is approved
// 2. Applies changes based on type:
//    - missing_checkout: Creates check-out record
//    - wrong_time: Updates record timestamp
//    - duplicate_record: Deletes duplicate
//    - missing_record: Creates check-in and/or check-out records
// 3. Marks records as is_manual_correction = true
// 4. Links records to correction via correction_id
// 5. Updates correction.status = 'applied'
// 6. Recalculates daily summary for affected date
// 7. Re-runs violation detection (removes old, detects new)
// 8. Dispatches CorrectionApplied event
// 9. Logs all changes in audit_logs
```

**RESTful API Endpoints:**

All endpoints protected by `auth:sanctum` middleware:

```bash
# Employee Endpoints
GET    /api/v1/corrections                           # List employee's corrections (filter by status, type)
POST   /api/v1/corrections                           # Create correction request
GET    /api/v1/corrections/{correction}              # View correction details
PUT    /api/v1/corrections/{correction}              # Update pending correction
DELETE /api/v1/corrections/{correction}              # Cancel pending correction
GET    /api/v1/corrections/{correction}/document     # Download supporting document

# Manager Endpoints
GET    /api/v1/manager/corrections                   # List team's pending corrections
POST   /api/v1/manager/corrections/{correction}/approve  # Approve and auto-apply correction
POST   /api/v1/manager/corrections/{correction}/reject   # Reject with required notes
```

**API Request/Response Examples:**

```php
// Create correction request
POST /api/v1/corrections
{
  "employee_id": 1,
  "attendance_record_id": 123,  // null for missing_record type
  "type": "wrong_time",
  "proposed_data": {
    "check_in_time": "09:00:00"
  },
  "reason": "Device was offline, manually verified with security",
  "supporting_document": <file>  // Optional PDF/JPG/PNG
}

// Response 201
{
  "id": 1,
  "employee_id": 1,
  "attendance_record_id": 123,
  "type": "wrong_time",
  "status": "pending",
  "proposed_data": {"check_in_time": "09:00:00"},
  "reason": "Device was offline...",
  "supporting_document_path": "corrections/documents/abc123.pdf",
  "created_at": "2025-10-06T10:00:00Z",
  "employee": { ... },
  "attendanceRecord": { ... }
}

// Manager approve
POST /api/v1/manager/corrections/1/approve
{
  "notes": "Verified with security logs"  // Optional
}

// Response 200
{
  "message": "Correction approved and applied successfully",
  "correction": {
    "id": 1,
    "status": "applied",
    "reviewed_by": 5,
    "reviewed_at": "2025-10-06T11:00:00Z",
    "review_notes": "Verified with security logs",
    "applied_at": "2025-10-06T11:00:01Z",
    ...
  }
}

// Manager reject
POST /api/v1/manager/corrections/1/reject
{
  "notes": "Cannot verify your claim with available records"  // REQUIRED (min 10 chars)
}
```

**Event-Driven Notifications:**

```php
// Events
CorrectionRequested  → Dispatched when employee creates request
CorrectionApproved   → Dispatched when manager approves
CorrectionRejected   → Dispatched when manager rejects
CorrectionApplied    → Dispatched when correction successfully applied

// Listeners (queued on 'notifications' queue)
NotifyManagerOfCorrectionRequest     → Sends email to employee's manager
NotifyEmployeeOfCorrectionDecision   → Sends approval/rejection email to employee

// Notifications
CorrectionRequestedNotification  → "John Doe submitted a wrong_time correction request"
CorrectionDecisionNotification   → "Your correction request has been Approved/Rejected"
```

**Validation Rules:**

```php
// CreateCorrectionRequest
'employee_id' => 'required|exists:employees,id',
'attendance_record_id' => 'nullable|exists:attendance_records,id',
'type' => 'required|in:missing_checkout,wrong_time,duplicate_record,missing_record,other',
'proposed_data' => 'required|array',
'reason' => 'required|string|min:10|max:1000',
'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',

// UpdateCorrectionRequest
// - Only pending corrections can be updated
// - All fields optional (sometimes)
// - Authorization: $correction->canBeUpdatedByEmployee()

// ApproveRejectRequest
// - Only pending corrections can be reviewed
// - Rejection requires notes (min 10 chars)
// - Approval notes optional
```

**Model Methods:**

```php
use App\Domain\Attendance\Models\AttendanceCorrection;

// State checks
$correction->isPending();     // status === 'pending'
$correction->isApproved();    // status === 'approved'
$correction->isRejected();    // status === 'rejected'
$correction->isApplied();     // status === 'applied'

// Employee permissions
$correction->canBeUpdatedByEmployee();   // isPending()
$correction->canBeCancelledByEmployee(); // isPending()

// Manager actions (triggers events and audit logs)
$correction->approve($manager, $notes);
$correction->reject($manager, $notes);
$correction->markAsApplied();  // Called by CorrectionApplicator

// Scopes
AttendanceCorrection::pending()->get();
AttendanceCorrection::approved()->get();
AttendanceCorrection::forEmployee($employeeId)->get();
AttendanceCorrection::ofType('wrong_time')->get();
```

**Audit Trail:**

Every correction action is automatically logged:

```php
use App\Models\AuditLog;

// Automatic logging on:
// - Correction created
// - Correction updated
// - Correction approved
// - Correction rejected
// - Correction applied
// - Related attendance records modified

// Query audit history
$correction->auditLogs()->orderBy('created_at', 'desc')->get();

// Each log contains:
// - Who performed the action (user_id)
// - What was changed (old_values, new_values)
// - When it happened (created_at)
// - Where (ip_address)
// - Why (notes)
```

**File Upload Handling:**

```php
// Storage location: storage/app/corrections/documents/
// Disk: 'local' (Laravel Storage)

// Upload document
$file = $request->file('supporting_document');
$path = $file->store('corrections/documents', 'local');
$correction->supporting_document_path = $path;

// Download document
GET /api/v1/corrections/{correction}/document
// Returns file download response

// Document deleted when:
// - Employee cancels pending correction
// - Employee replaces document during update
```

**Testing:**

```php
// Feature tests (30 tests covering):
// - Employee CRUD operations
// - Manager review workflow
// - File upload/download
// - Validation rules
// - Permission checks
// - Event dispatching
// - Notification sending

// Unit tests (8 tests covering):
// - CorrectionApplicator for all types
// - Transaction rollback on errors
// - Daily summary recalculation
// - Violation re-detection

php artisan test tests/Feature/Attendance/CorrectionWorkflowTest.php
php artisan test tests/Unit/Attendance/CorrectionApplicatorTest.php
php artisan test tests/Feature/Attendance/CorrectionNotificationTest.php
```

**Important Implementation Notes:**

- All correction applications wrapped in database transactions for safety
- Failed corrections automatically roll back with error logging
- Daily summaries recalculated after corrections applied
- Violations re-detected after corrections (old removed, new added)
- Supporting documents stored securely with Laravel Storage
- Complete audit trail for regulatory compliance
- Manager's employee relationship required for permission checks
- Rejection requires detailed notes (min 10 chars) for transparency
- Correction status cannot go backwards (no unapproving)
- Employee model requires Notifiable trait for notifications

### Real-Time Violation Notifications

The system automatically sends email notifications to managers when violations are detected, with configurable preferences and daily digest support.

**Core Components:**

1. **NotificationPreference Model** (`app/Models/NotificationPreference.php`)
   - Stores per-user notification settings
   - Supports severity filtering (minimum threshold)
   - Two notification types: `violation_immediate` and `violation_digest`
   - Auto-created with default settings when user first needs it

2. **ViolationNotification** (`app/Notifications/ViolationNotification.php`)
   - Queued notification sent when violation detected
   - Type-specific email content (late arrival, early departure, extended break, missing checkout)
   - Severity-based email subject with emoji indicators
   - Includes violation details and "View Details" action button

3. **DailyViolationDigest** (`app/Notifications/DailyViolationDigest.php`)
   - Daily summary email with statistics
   - Breakdown by type and severity
   - Lists up to 20 violations with "...and X more" indicator
   - Scheduled to run daily at 8:00 AM

**Manager Assignment:**

Employees can be assigned a manager via the `manager_id` foreign key:

```php
$employee = Employee::find(1);
$employee->manager_id = $managerUser->id;
$employee->save();

// Access manager
$manager = $employee->manager; // User instance
$managedEmployees = $managerUser->employees; // Collection of employees
```

**Notification Flow:**

```
Violation Created
    ↓
ViolationDetected Event Dispatched
    ↓
SendViolationNotification Listener
    ↓
Check Manager Assignment
    ↓
Check Notification Preferences
    ↓
Filter by Severity Threshold
    ↓
Send ViolationNotification (queued)
```

**Notification Preferences:**

```php
use App\Models\NotificationPreference;

// Create preference for immediate notifications
NotificationPreference::create([
    'user_id' => $manager->id,
    'notification_type' => 'violation_immediate',
    'settings' => [
        'minimum_severity' => 'major', // Only major and critical
    ],
    'enabled' => true,
]);

// Create preference for daily digest
NotificationPreference::create([
    'user_id' => $manager->id,
    'notification_type' => 'violation_digest',
    'settings' => [
        'minimum_severity' => 'moderate', // Moderate, major, critical
    ],
    'enabled' => true,
]);

// Check if notification should be sent
$preference = NotificationPreference::where('user_id', $manager->id)
    ->where('notification_type', 'violation_immediate')
    ->first();

if ($preference && $preference->shouldNotifyForSeverity('minor')) {
    // Send notification
}
```

**Daily Digest Command:**

```bash
# Send daily digest for yesterday (default)
php artisan notifications:send-daily-violation-digest

# Send digest for specific date
php artisan notifications:send-daily-violation-digest --date=2025-10-06

# Scheduled automatically at 8:00 AM (see routes/console.php)
```

**Email Configuration:**

Production email setup in `.env`:

```env
# AWS SES
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=your-ses-key
AWS_SECRET_ACCESS_KEY=your-ses-secret
AWS_DEFAULT_REGION=us-east-1

# Or Postmark
MAIL_MAILER=postmark
POSTMARK_TOKEN=your-postmark-token

# Or SMTP (Gmail, Outlook, etc.)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls

# From address
MAIL_FROM_ADDRESS="noreply@example.com"
MAIL_FROM_NAME="Attendance Monitor"
```

**Queue Configuration:**

Notifications are queued on the `notifications` queue:

```bash
# Start queue worker for notifications
php artisan queue:work redis --queue=notifications --tries=3 --timeout=60

# Or use the notifications queue in priority
php artisan queue:work redis --queue=attendance-high-priority,attendance-default,notifications
```

**Testing Notifications:**

```php
// Feature tests cover:
// - Manager receives notification on violation creation
// - Severity filtering respects minimum thresholds
// - Notifications disabled when preferences disabled
// - No notification when employee has no manager
// - Default preference auto-creation
// - Daily digest command sends to correct managers
// - Digest filters violations by date and severity
// - Multiple managers receive separate digests

php artisan test tests/Feature/Notifications/
// 10 tests passing (20 assertions)
```

**Integration Example:**

```php
use App\Events\ViolationDetected;
use App\Domain\Attendance\Models\AttendanceViolation;

// After violation is created
$violation = AttendanceViolation::create([...]);

// Dispatch event - listener handles notification logic
event(new ViolationDetected($violation));

// Listener automatically:
// 1. Finds employee's manager
// 2. Checks notification preferences
// 3. Filters by severity threshold
// 4. Sends queued notification if appropriate
```

**Important Notes:**

- Notification preferences auto-created with enabled=true defaults
- Severity hierarchy: minor < moderate < major < critical
- Missing manager assignment = no notification (logged as info)
- Preferences disabled = no notification
- Notifications queued for async processing (non-blocking)
- Email templates use Laravel's beautiful default mail template
- Scheduled digest runs daily at 8:00 AM via Laravel scheduler

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

The system uses **Redis-based queue priority processing** with Supervisor workers for handling different workload types efficiently.

**Queue Priority Levels**:
- `attendance-high-priority` - Real-time attendance events from MQTT (30s timeout, 3 workers)
- `attendance-default` - Device sync, stranger logs (60s timeout, 2 workers)
- `reporting` - Long-running report generation (300s timeout, 1 worker)
- `notifications` - Email/SMS notifications (30s timeout, 2 workers)

**Redis Configuration**:
```php
// config/database.php - Dedicated Redis database for queues
'queue' => [
    'host' => env('REDIS_HOST', '127.0.0.1'),
    'port' => env('REDIS_PORT', '6379'),
    'database' => env('REDIS_QUEUE_DB', '2'),  // Separate from cache (DB 1)
    'max_retries' => 3,
    'backoff_algorithm' => 'decorrelated_jitter',
]

// config/queue.php - Redis connection settings
'redis' => [
    'driver' => 'redis',
    'connection' => env('REDIS_QUEUE_CONNECTION', 'queue'),
    'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
    'block_for' => (int) env('REDIS_QUEUE_BLOCK_FOR', 5),
]
```

**Supervisor Configuration** (`supervisor/` directory):

```bash
# High Priority Workers (3 processes)
supervisor/attendance-high-priority-worker.conf
  - Handles: ProcessAttendanceEvent jobs
  - Timeout: 30 seconds
  - Tries: 3
  - Workers: 3 concurrent processes
  - Priority: Highest (real-time processing)

# Default Priority Workers (2 processes)
supervisor/attendance-default-worker.conf
  - Handles: SyncEmployeeToDevices, device status
  - Timeout: 60 seconds
  - Tries: 3
  - Workers: 2 concurrent processes

# Notification Workers (2 processes)
supervisor/notification-worker.conf
  - Handles: ViolationNotification, CorrectionNotification, DailyDigest
  - Timeout: 30 seconds
  - Tries: 3
  - Workers: 2 concurrent processes

# Reporting Workers (1 process)
supervisor/reporting-worker.conf
  - Handles: Long-running reports
  - Timeout: 300 seconds (5 minutes)
  - Tries: 1
  - Workers: 1 process
```

**Job Assignment**:
```php
// ProcessAttendanceEvent.php
public $tries = 3;
public $timeout = 30;
$this->onQueue(config('queue.connections.redis.queue', 'attendance-high-priority'));

// SyncEmployeeToDevices.php
public $tries = 3;
public $timeout = 60;
$this->onQueue(env('QUEUE_DEFAULT', 'attendance-default'));

// ViolationNotification.php (and all notifications)
public $tries = 3;
public $timeout = 30;
$this->onQueue(env('QUEUE_NOTIFICATIONS', 'notifications'));
```

**Queue Monitoring**:

```bash
# Manual monitoring
php artisan queue:monitor --alert

# Output:
Queue Monitoring Report - 2025-10-06 15:00:00

attendance-high-priority  | Size: 5      | Status: ✓ OK
attendance-default        | Size: 12     | Status: ✓ OK
notifications             | Size: 150    | Status: ⚠ WARNING
reporting                 | Size: 2      | Status: ✓ OK

# Scheduled monitoring (every 5 minutes)
# app/Console/Kernel.php
$schedule->command('queue:monitor --alert')->everyFiveMinutes();
```

**Queue Metrics API**:

```bash
# Get all queue metrics
GET /api/v1/queue/metrics

# Response:
{
  "metrics": [
    {
      "queue": "attendance-high-priority",
      "size": 5,
      "failed_jobs": 0,
      "status": "healthy"
    },
    {
      "queue": "notifications",
      "size": 150,
      "status": "warning"
    }
  ],
  "timestamp": "2025-10-06T15:00:00Z",
  "total_jobs": 169,
  "total_failed": 0
}

# Get specific queue metrics
GET /api/v1/queue/metrics/attendance-high-priority
```

**Environment Variables**:

```bash
# Queue Configuration
QUEUE_CONNECTION=redis
QUEUE_HIGH_PRIORITY=attendance-high-priority
QUEUE_DEFAULT=attendance-default
QUEUE_REPORTING=reporting
QUEUE_NOTIFICATIONS=notifications

# Redis Queue Settings
REDIS_QUEUE_CONNECTION=queue
REDIS_QUEUE_DB=2
REDIS_QUEUE_RETRY_AFTER=90
REDIS_QUEUE_BLOCK_FOR=5

# Worker Settings
QUEUE_HIGH_PRIORITY_WORKERS=3
QUEUE_HIGH_PRIORITY_TIMEOUT=30
QUEUE_DEFAULT_WORKERS=2
QUEUE_DEFAULT_TIMEOUT=60
QUEUE_NOTIFICATION_WORKERS=2
QUEUE_REPORTING_WORKERS=1
QUEUE_REPORTING_TIMEOUT=300

# Monitoring
QUEUE_SIZE_WARNING_THRESHOLD=100
QUEUE_SIZE_CRITICAL_THRESHOLD=500
```

**Deployment** (Production):

```bash
# Deploy Supervisor configuration
cd /var/www/html
sudo bash supervisor/deploy.sh

# Verify workers are running
supervisorctl status

# View logs
supervisorctl tail -f attendance-high-priority-worker:attendance-high-priority-worker_00

# Restart workers after code deployment
supervisorctl restart attendance-high-priority-worker:*
supervisorctl restart attendance-default-worker:*
supervisorctl restart notification-worker:*
supervisorctl restart reporting-worker:*
```

**Critical Jobs**:
- `ProcessAttendanceEvent` - Main attendance processing logic (high-priority)
- `SyncEmployeeToDevices` - Device sync operations (default)
- `ViolationNotification` - Alerts managers of violations (notifications)
- `CorrectionRequestedNotification` - Manager correction alerts (notifications)
- `DailyViolationDigest` - Daily digest emails (notifications)
- Report generation jobs - Long-running reports (reporting)

### Failed Job Handling and Retry Mechanism

The system implements comprehensive failed job handling with automatic retry, exponential backoff, admin notifications, and management APIs.

**Retry Configuration**:

All critical jobs are configured with automatic retry and exponential backoff:

```php
// ProcessAttendanceEvent.php
public $tries = 3;                    // Maximum retry attempts
public $timeout = 30;                 // Job timeout in seconds
public $maxExceptions = 3;            // Max unhandled exceptions

public function backoff(): array
{
    return [60, 300, 900];            // Exponential backoff: 1min, 5min, 15min
}

public function failed(\Throwable $exception): void
{
    Log::channel('failed_jobs')->critical('Job permanently failed', [
        'job_id' => $this->job->getJobId(),
        'attempts' => $this->attempts(),
        'error' => $exception->getMessage(),
    ]);

    // Notify admin
    Notification::route('mail', config('mail.admin_email'))
        ->notify(new CriticalJobFailedNotification(
            jobType: 'ProcessAttendanceEvent',
            jobId: $this->job->getJobId(),
            attempts: $this->attempts(),
            exception: $exception,
            payload: $this->event->toArray()
        ));
}
```

**Retry Strategy**:
1. **First retry**: 60 seconds after initial failure
2. **Second retry**: 300 seconds (5 minutes) after second failure
3. **Third retry**: 900 seconds (15 minutes) after third failure
4. **Permanent failure**: After 3 attempts, job marked as failed and admin notified

**Logging**:

All retry attempts and failures are logged with comprehensive context:

```php
// Enhanced error logging in handle() method
catch (\Exception $e) {
    $attempt = $this->attempts();
    $maxTries = $this->tries;

    Log::channel('mqtt')->error('Failed to process attendance event', [
        'attempt' => $attempt,
        'max_tries' => $maxTries,
        'will_retry' => $attempt < $maxTries,
        'next_retry_in' => $this->backoff()[$attempt - 1] ?? 60,
        'error' => $e->getMessage(),
        'error_class' => get_class($e),
        'trace' => $e->getTraceAsString(),
        'event' => $this->event->toArray(),
    ]);

    throw $e;
}
```

**Failed Job Model**:

The `FailedJob` model provides programmatic access to failed jobs:

```php
use App\Models\FailedJob;

// Query failed jobs
$recentFailures = FailedJob::failedBetween(
    Carbon::now()->subDays(7),
    Carbon::now()
)->get();

// Filter by queue
$highPriorityFailures = FailedJob::queue('attendance-high-priority')->get();

// Access job details
foreach ($recentFailures as $job) {
    echo "Job: {$job->job_class}\n";
    echo "Failed: {$job->failed_time_ago}\n";
    echo "Queue: {$job->queue}\n";
    echo "Error: {$job->exception}\n";
    print_r($job->job_data);
}
```

**CLI Commands**:

Retry failed jobs via Artisan commands:

```bash
# Retry specific job by UUID
php artisan queue:retry-failed abc123-def456-...

# Retry all jobs for a specific queue (with confirmation)
php artisan queue:retry-failed --queue=attendance-high-priority

# Retry all failed jobs (with confirmation)
php artisan queue:retry-failed --all
```

**API Endpoints**:

Manage failed jobs via REST API (admin only):

```bash
# List failed jobs with filtering
GET /api/v1/failed-jobs?queue=attendance-high-priority&from=2025-10-01&to=2025-10-06

# Response:
{
  "success": true,
  "data": [
    {
      "id": "abc123-def456-...",
      "queue": "attendance-high-priority",
      "job_class": "App\\Jobs\\ProcessAttendanceEvent",
      "job_data": {...},
      "exception": "Connection timeout...",
      "failed_at": "2025-10-06T10:30:00Z",
      "failed_time_ago": "2 hours ago"
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 15,
    "per_page": 15
  }
}

# View specific failed job
GET /api/v1/failed-jobs/{id}

# Retry specific job
POST /api/v1/failed-jobs/{id}/retry

# Retry all jobs (or queue-specific with ?queue=name)
POST /api/v1/failed-jobs/retry-all

# Delete failed job
DELETE /api/v1/failed-jobs/{id}

# Prune old failed jobs
POST /api/v1/failed-jobs/prune?hours=168  # 7 days
```

**Admin Notifications**:

When a job permanently fails after all retries, administrators receive an email alert:

```
Subject: 🚨 Critical Job Failure: ProcessAttendanceEvent

Critical Job Failure Alert

A critical job has permanently failed after 3 retry attempts.

Job Details:
- Type: ProcessAttendanceEvent
- Job ID: abc123-def456-...
- Attempts: 3
- Error: Illuminate\Database\QueryException

Error Message:
```
SQLSTATE[HY000]: Connection timeout
```

Payload:
```json
{
  "custom_id": "EMP001",
  "device_id": "DEV123",
  "timestamp": "2025-10-06T10:30:00Z"
}
```

Please investigate this failure immediately. Attendance data may have been lost.

[View Failed Jobs] http://app.example.com/admin/failed-jobs

You can retry failed jobs via:
php artisan queue:retry-failed abc123-def456-...
```

**Scheduled Maintenance**:

Old failed jobs are automatically pruned weekly:

```php
// routes/console.php
Schedule::command('queue:prune-failed --hours=168')->weeklyOn(0, '02:00');
```

This removes failed jobs older than 7 days (168 hours) every Sunday at 2:00 AM.

**Configuration**:

```bash
# .env
MAIL_ADMIN_EMAIL=admin@example.com

# config/mail.php
'admin_email' => env('MAIL_ADMIN_EMAIL', null),

# config/logging.php
'failed_jobs' => [
    'driver' => 'daily',
    'path' => storage_path('logs/failed-jobs.log'),
    'level' => env('LOG_LEVEL', 'debug'),
    'days' => 14,
],

# config/queue.php
'failed' => [
    'driver' => 'database-uuids',
    'database' => env('DB_CONNECTION', 'mysql'),
    'table' => 'failed_jobs',
],
```

**Monitoring**:

```bash
# View failed jobs log
tail -f storage/logs/failed-jobs.log

# Check failed jobs count
php artisan queue:failed

# Monitor specific queue failures
watch -n 5 'php artisan queue:failed | grep attendance-high-priority'
```

**Best Practices**:

1. **Always configure retry**: Set `$tries`, `$timeout`, `$maxExceptions` on all jobs
2. **Implement backoff**: Use exponential backoff to avoid overwhelming external services
3. **Add failed() method**: Handle permanent failures gracefully with logging and notifications
4. **Monitor regularly**: Check failed_jobs table and logs daily
5. **Investigate patterns**: If same job fails repeatedly, investigate root cause
6. **Prune old jobs**: Prevent database bloat with scheduled pruning
7. **Test failure scenarios**: Ensure jobs fail gracefully and retry correctly

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
