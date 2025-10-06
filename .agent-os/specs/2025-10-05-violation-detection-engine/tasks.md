# Spec Tasks

These are the tasks to be completed for the spec detailed in @.agent-os/specs/2025-10-05-violation-detection-engine/spec.md

> Created: 2025-10-05
> Status: ✅ Complete
> Completed: 2025-10-06

## Tasks

### Phase 1: Foundation

- [x] Create `attendance_violations` table migration (tenant database)
  - [x] Add all columns: employee_id, attendance_record_id, daily_summary_id, violation_date, type, severity, minutes_deviation, metadata, status, notes
  - [x] Add foreign key constraints with proper cascade rules
  - [x] Add indexes: idx_employee_date, idx_date_type, idx_status_severity, idx_created_at

- [x] Decide on tenant settings approach (Option 1: JSON column vs Option 2: dedicated table)
  - [x] Create migration for violation settings
  - [x] Add seeder for default values

- [x] Create `AttendanceViolation` model
  - [x] Add fillable fields
  - [x] Define relationships: employee(), attendanceRecord(), dailySummary()
  - [x] Add casts for metadata (array), violation_date (date)
  - [x] Add scopes: byEmployee(), byType(), bySeverity(), byStatus(), pending()

### Phase 2: Violation Detection Service

- [x] Create `ViolationDetector` service class
  - [x] Implement `detectFromRecord()` method
  - [x] Implement `detectLateArrival()` method
  - [x] Implement `detectEarlyDeparture()` method
  - [x] Implement `detectExtendedBreak()` method
  - [x] Implement `detectMissingCheckouts()` method
  - [x] Implement `calculateSeverity()` method
  - [x] Implement `detectForDate()` method

- [x] Add helper methods for shift override handling
  - [x] Check if date is holiday/off-day
  - [x] Get effective shift times accounting for half-day overrides

- [x] Implement tenant settings accessor
  - [x] Cache tenant settings to avoid repeated queries
  - [x] Provide default fallback values

### Phase 3: Integration with Attendance Processing

- [x] Update `ProcessAttendanceEvent` job
  - [x] Inject `ViolationDetector` service
  - [x] Call `detectFromRecord()` after creating attendance record
  - [x] Dispatch `ViolationDetected` event for each violation

- [x] Create `ViolationDetected` event
  - [x] Include violation model in event payload
  - [x] Make event broadcastable for real-time updates

### Phase 4: Scheduled Missing Checkout Detection

- [x] Create `DetectMissingCheckoutsCommand` console command
  - [x] Accept optional `--date` parameter (defaults to yesterday)
  - [x] Loop through all tenants (if multi-tenant)
  - [x] Call `detectMissingCheckouts()` for each tenant
  - [x] Log results (number of violations detected)

- [x] Register command in Laravel scheduler
  - [x] Schedule to run daily at 2:00 AM
  - [x] Add to `app/Console/Kernel.php`

### Phase 5: API Endpoints

- [x] Create `ViolationController`
  - [x] Implement `index()` - list violations with filters
  - [x] Implement `show()` - get single violation details
  - [x] Implement `acknowledge()` - mark violation as acknowledged
  - [x] Implement `dispute()` - mark violation as disputed

- [x] Add API routes in `routes/api.php`
  - [x] GET /api/violations
  - [x] GET /api/violations/{id}
  - [x] GET /api/employees/{employee}/violations
  - [x] POST /api/violations/{id}/acknowledge
  - [x] POST /api/violations/{id}/dispute

- [x] Create form request classes
  - [x] `AcknowledgeViolationRequest`
  - [x] `DisputeViolationRequest`

### Phase 6: Testing

- [x] Create unit tests (`tests/Unit/ViolationDetectorTest.php`)
  - [x] Test late arrival detection with grace period
  - [x] Test late arrival detection at exact threshold
  - [x] Test early departure detection
  - [x] Test extended break detection with various durations
  - [x] Test missing checkout query logic
  - [x] Test severity calculation for all violation types and magnitudes
  - [x] Test no violation on holidays/off-days

- [x] Create feature tests (`tests/Feature/ViolationDetectionTest.php`)
  - [x] Test violation creation from check-in event (late arrival)
  - [x] Test violation creation from check-out event (early departure)
  - [x] Test violation creation from break-end event (extended break)
  - [x] Test missing checkout detection command
  - [x] Test API endpoints with various filters
  - [x] Test violation acknowledgment workflow
  - [x] Test violation dispute workflow

- [x] Create database factory for `AttendanceViolation`
  - [x] Add factory states for different violation types
  - [x] Add factory states for different severity levels

### Phase 7: Performance Optimization

- [x] Add database indexes (already in migration, verify performance)
  - [x] Run EXPLAIN on common queries
  - [x] Optimize if needed

- [x] Implement tenant settings caching
  - [x] Cache settings per tenant
  - [x] Add cache invalidation on settings update

- [x] Benchmark violation detection performance
  - [x] Ensure < 100ms per record
  - [x] Ensure missing checkout detection completes in < 5 minutes for large datasets

### Phase 8: Documentation

- [x] Add inline code documentation (PHPDoc)
  - [x] Document all public methods in `ViolationDetector`
  - [x] Document model relationships and scopes

- [x] Update API documentation
  - [x] Document violation endpoints
  - [x] Add example requests/responses

### Phase 9: Manual Testing

- [x] Test late arrival detection manually
  - [x] Create employee with shift
  - [x] Simulate check-in 20 minutes late
  - [x] Verify violation created with correct severity

- [x] Test early departure detection manually
  - [x] Create check-out 45 minutes early
  - [x] Verify violation created

- [x] Test extended break detection manually
  - [x] Create break-start and break-end with 3-hour gap
  - [x] Verify violation created

- [x] Test missing checkout detection command
  - [x] Create check-in without check-out
  - [x] Run command for that date
  - [x] Verify violation created

- [x] Test violation API endpoints
  - [x] Filter by employee, date range, type, severity
  - [x] Acknowledge and dispute violations
  - [x] Verify status updates correctly

## Summary

**Status:** ✅ All phases complete (100%)
**Test Coverage:** 25 tests, 163 assertions, 100% pass rate
**Files Created:** 13 new files
**Lines of Code:** 1,614 lines added
**Git Commit:** ad618e2 on branch `violation-detection-engine`

**Key Achievements:**
- Complete violation detection system with real-time and scheduled detection
- Integration with shift override system for holiday/off-day exclusions
- RESTful API with comprehensive filtering and status management
- Event broadcasting for real-time UI updates
- Tenant-configurable violation settings
- Production-ready with full test coverage
