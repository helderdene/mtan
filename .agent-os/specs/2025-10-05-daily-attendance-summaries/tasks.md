# Spec Tasks

These are the tasks to be completed for the spec detailed in @.agent-os/specs/2025-10-05-daily-attendance-summaries/spec.md

> Created: 2025-10-06
> Status: Planning

## Tasks

- [x] 1. Database Schema & Model Setup
  - [x] 1.1 Write tests for DailyAttendanceSummary model (relationships, accessors, unique constraints)
  - [x] 1.2 Create migration for `daily_attendance_summaries` table with all columns and indexes
  - [x] 1.3 Create `DailyAttendanceSummary` model in `app/Domain/Attendance/Models/`
  - [x] 1.4 Define relationships: `employee()`, `attendanceRecords()`
  - [x] 1.5 Add accessor methods: `total_work_hours`, `total_break_hours`, `overtime_hours`
  - [x] 1.6 Create factory for `DailyAttendanceSummary` model with states (present, absent, half-day, on-leave, holiday)
  - [x] 1.7 Run migration and verify table structure
  - [x] 1.8 Verify all model tests pass

- [x] 2. Summary Calculation Service
  - [x] 2.1 Write tests for SummaryCalculator service (work hours, break time, overnight shifts, overtime, status determination)
  - [x] 2.2 Create `SummaryCalculator` service in `app/Domain/Attendance/Services/`
  - [x] 2.3 Implement `calculateWorkHours()` method handling check-in/check-out/break pairs
  - [x] 2.4 Implement `calculateBreakTime()` method for break-start/break-end pairs
  - [x] 2.5 Implement `calculateOvertime()` method using OverrideService for effective shift times
  - [x] 2.6 Implement `determineStatus()` method with shift override integration
  - [x] 2.7 Implement `calculateForDate()` method that aggregates all calculations
  - [x] 2.8 Verify all calculation tests pass including edge cases

- [ ] 3. Real-time Summary Updates
  - [ ] 3.1 Write tests for real-time summary updates on attendance events
  - [ ] 3.2 Implement `updateSummaryFromEvent()` method in SummaryCalculator
  - [ ] 3.3 Add upsert logic (create if missing, update if exists) for daily summaries
  - [ ] 3.4 Integrate SummaryCalculator into `ProcessAttendanceEvent` job
  - [ ] 3.5 Add summary update after attendance record creation
  - [ ] 3.6 Handle incomplete days (employee still checked in) by setting `is_complete = false`
  - [ ] 3.7 Test real-time updates with various attendance scenarios
  - [ ] 3.8 Verify all integration tests pass

- [ ] 4. Bulk Recalculation Support
  - [ ] 4.1 Write tests for bulk recalculation (single employee, multiple employees, date ranges)
  - [ ] 4.2 Implement `recalculateRange()` method in SummaryCalculator
  - [ ] 4.3 Add batch processing with database transactions
  - [ ] 4.4 Create `RecalculateSummariesCommand` in `app/Console/Commands/`
  - [ ] 4.5 Add command options: --employee, --from, --to, --force
  - [ ] 4.6 Add progress indicators and summary statistics to command output
  - [ ] 4.7 Test recalculation command with various date ranges
  - [ ] 4.8 Verify all recalculation tests pass

- [ ] 5. API Endpoints & Controller
  - [ ] 5.1 Write tests for API endpoints (list, filter, recalculate endpoint)
  - [ ] 5.2 Create `AttendanceSummaryController` in `app/Http/Controllers/Api/`
  - [ ] 5.3 Implement `index()` method with filtering (employee_id, date, from/to range, status)
  - [ ] 5.4 Implement `show()` method for employee-specific summaries
  - [ ] 5.5 Implement `recalculate()` method for triggering recalculation
  - [ ] 5.6 Create Form Request for recalculate validation
  - [ ] 5.7 Add routes to `routes/api.php` with proper authentication
  - [ ] 5.8 Verify all API tests pass with proper response formats

- [ ] 6. Performance Optimization & Caching
  - [ ] 6.1 Write tests for caching behavior (cache hit, cache invalidation)
  - [ ] 6.2 Add eager loading for employee and attendance records relationships
  - [ ] 6.3 Implement monthly summary caching with tenant-specific keys
  - [ ] 6.4 Add cache invalidation on new attendance record
  - [ ] 6.5 Add cache invalidation on recalculation
  - [ ] 6.6 Optimize queries using indexes and query profiling
  - [ ] 6.7 Test performance with large datasets (1000+ summaries)
  - [ ] 6.8 Verify caching tests pass and performance targets met

- [ ] 7. Documentation & Integration Testing
  - [ ] 7.1 Update CLAUDE.md with Daily Attendance Summary section (algorithm, API, usage examples)
  - [ ] 7.2 Test integration with shift override system (holidays, half-days, custom shifts)
  - [ ] 7.3 Test edge cases: overnight shifts, multiple check-in/out pairs, incomplete days
  - [ ] 7.4 Test absence detection (no attendance records for the day)
  - [ ] 7.5 Verify summary updates when attendance corrections are made
  - [ ] 7.6 Test API endpoint pagination and filtering
  - [ ] 7.7 Run full test suite and verify >95% coverage for new code
  - [ ] 7.8 Update tasks.md status to "Complete"

## Definition of Done

- All database migrations created and tested
- DailyAttendanceSummary model with relationships and accessors implemented
- SummaryCalculator service with comprehensive calculation logic
- Real-time summary updates on attendance events
- Bulk recalculation command functional
- API endpoints with filtering and pagination
- Performance optimization with caching implemented
- Unit and feature tests passing with >95% coverage
- Integration with shift override system verified
- Documentation updated in CLAUDE.md with usage examples
- All edge cases tested and handled properly
