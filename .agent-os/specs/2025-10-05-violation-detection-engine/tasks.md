# Spec Tasks

These are the tasks to be completed for the spec detailed in @.agent-os/specs/2025-10-05-violation-detection-engine/spec.md

> Created: 2025-10-05
> Status: Ready for Implementation

## Tasks

### Phase 1: Foundation

- [ ] Create `attendance_violations` table migration (tenant database)
  - [ ] Add all columns: employee_id, attendance_record_id, daily_summary_id, violation_date, type, severity, minutes_deviation, metadata, status, notes
  - [ ] Add foreign key constraints with proper cascade rules
  - [ ] Add indexes: idx_employee_date, idx_date_type, idx_status_severity, idx_created_at

- [ ] Decide on tenant settings approach (Option 1: JSON column vs Option 2: dedicated table)
  - [ ] Create migration for violation settings
  - [ ] Add seeder for default values

- [ ] Create `AttendanceViolation` model
  - [ ] Add fillable fields
  - [ ] Define relationships: employee(), attendanceRecord(), dailySummary()
  - [ ] Add casts for metadata (array), violation_date (date)
  - [ ] Add scopes: byEmployee(), byType(), bySeverity(), byStatus(), pending()

### Phase 2: Violation Detection Service

- [ ] Create `ViolationDetector` service class
  - [ ] Implement `detectFromRecord()` method
  - [ ] Implement `detectLateArrival()` method
  - [ ] Implement `detectEarlyDeparture()` method
  - [ ] Implement `detectExtendedBreak()` method
  - [ ] Implement `detectMissingCheckouts()` method
  - [ ] Implement `calculateSeverity()` method
  - [ ] Implement `detectForDate()` method

- [ ] Add helper methods for shift override handling
  - [ ] Check if date is holiday/off-day
  - [ ] Get effective shift times accounting for half-day overrides

- [ ] Implement tenant settings accessor
  - [ ] Cache tenant settings to avoid repeated queries
  - [ ] Provide default fallback values

### Phase 3: Integration with Attendance Processing

- [ ] Update `ProcessAttendanceEvent` job
  - [ ] Inject `ViolationDetector` service
  - [ ] Call `detectFromRecord()` after creating attendance record
  - [ ] Dispatch `ViolationDetected` event for each violation

- [ ] Create `ViolationDetected` event
  - [ ] Include violation model in event payload
  - [ ] Make event broadcastable for real-time updates

### Phase 4: Scheduled Missing Checkout Detection

- [ ] Create `DetectMissingCheckoutsCommand` console command
  - [ ] Accept optional `--date` parameter (defaults to yesterday)
  - [ ] Loop through all tenants (if multi-tenant)
  - [ ] Call `detectMissingCheckouts()` for each tenant
  - [ ] Log results (number of violations detected)

- [ ] Register command in Laravel scheduler
  - [ ] Schedule to run daily at 2:00 AM
  - [ ] Add to `app/Console/Kernel.php`

### Phase 5: API Endpoints

- [ ] Create `ViolationController`
  - [ ] Implement `index()` - list violations with filters
  - [ ] Implement `show()` - get single violation details
  - [ ] Implement `acknowledge()` - mark violation as acknowledged
  - [ ] Implement `dispute()` - mark violation as disputed

- [ ] Add API routes in `routes/api.php`
  - [ ] GET /api/violations
  - [ ] GET /api/violations/{id}
  - [ ] GET /api/employees/{employee}/violations
  - [ ] POST /api/violations/{id}/acknowledge
  - [ ] POST /api/violations/{id}/dispute

- [ ] Create form request classes
  - [ ] `AcknowledgeViolationRequest`
  - [ ] `DisputeViolationRequest`

### Phase 6: Testing

- [ ] Create unit tests (`tests/Unit/ViolationDetectorTest.php`)
  - [ ] Test late arrival detection with grace period
  - [ ] Test late arrival detection at exact threshold
  - [ ] Test early departure detection
  - [ ] Test extended break detection with various durations
  - [ ] Test missing checkout query logic
  - [ ] Test severity calculation for all violation types and magnitudes
  - [ ] Test no violation on holidays/off-days

- [ ] Create feature tests (`tests/Feature/ViolationDetectionTest.php`)
  - [ ] Test violation creation from check-in event (late arrival)
  - [ ] Test violation creation from check-out event (early departure)
  - [ ] Test violation creation from break-end event (extended break)
  - [ ] Test missing checkout detection command
  - [ ] Test API endpoints with various filters
  - [ ] Test violation acknowledgment workflow
  - [ ] Test violation dispute workflow

- [ ] Create database factory for `AttendanceViolation`
  - [ ] Add factory states for different violation types
  - [ ] Add factory states for different severity levels

### Phase 7: Performance Optimization

- [ ] Add database indexes (already in migration, verify performance)
  - [ ] Run EXPLAIN on common queries
  - [ ] Optimize if needed

- [ ] Implement tenant settings caching
  - [ ] Cache settings per tenant
  - [ ] Add cache invalidation on settings update

- [ ] Benchmark violation detection performance
  - [ ] Ensure < 100ms per record
  - [ ] Ensure missing checkout detection completes in < 5 minutes for large datasets

### Phase 8: Documentation

- [ ] Add inline code documentation (PHPDoc)
  - [ ] Document all public methods in `ViolationDetector`
  - [ ] Document model relationships and scopes

- [ ] Update API documentation
  - [ ] Document violation endpoints
  - [ ] Add example requests/responses

### Phase 9: Manual Testing

- [ ] Test late arrival detection manually
  - [ ] Create employee with shift
  - [ ] Simulate check-in 20 minutes late
  - [ ] Verify violation created with correct severity

- [ ] Test early departure detection manually
  - [ ] Create check-out 45 minutes early
  - [ ] Verify violation created

- [ ] Test extended break detection manually
  - [ ] Create break-start and break-end with 3-hour gap
  - [ ] Verify violation created

- [ ] Test missing checkout detection command
  - [ ] Create check-in without check-out
  - [ ] Run command for that date
  - [ ] Verify violation created

- [ ] Test violation API endpoints
  - [ ] Filter by employee, date range, type, severity
  - [ ] Acknowledge and dispute violations
  - [ ] Verify status updates correctly
