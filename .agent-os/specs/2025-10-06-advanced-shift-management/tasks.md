# Spec Tasks

These are the tasks to be completed for the spec detailed in @.agent-os/specs/2025-10-06-advanced-shift-management/spec.md

> Created: 2025-10-06
> Status: Phase 1-3, 5 Completed (Core Backend Implementation)

## Tasks

- [x] 1. Database Schema and Migrations
  - [x] 1.1 Create migration for shift_rotation_patterns table
  - [x] 1.2 Create migration for employee_shift_rotations table
  - [x] 1.3 Create migration to add advanced fields to shifts table (shift_type, is_overnight, flexible_checkin_start/end, core_hours_required)
  - [x] 1.4 Write tests for new table constraints and relationships
  - [x] 1.5 Run migrations and verify schema
  - [x] 1.6 Create factories for ShiftRotationPattern and EmployeeShiftRotation models
  - [x] 1.7 Verify all database tests pass

- [x] 2. Models and Core Services
  - [x] 2.1 Write tests for ShiftRotationPattern model
  - [x] 2.2 Create ShiftRotationPattern model with relationships
  - [x] 2.3 Create EmployeeShiftRotation model with relationships
  - [x] 2.4 Update Shift model with new fields and flexible shift logic
  - [x] 2.5 Write tests for RotationScheduler service
  - [x] 2.6 Create RotationScheduler service (calculate current shift for rotation)
  - [x] 2.7 Create FlexibleShiftValidator service
  - [x] 2.8 Verify model and service tests pass

- [x] 3. Rotation Pattern Management
  - [x] 3.1 Write tests for ShiftRotationPatternController
  - [x] 3.2 Create ShiftRotationPatternController with CRUD endpoints
  - [x] 3.3 Create POST /api/v1/shift-rotation-patterns endpoint
  - [x] 3.4 Create POST /api/v1/employees/{employee}/assign-rotation endpoint
  - [x] 3.5 Create GET /api/v1/employees/{employee}/shift-schedule endpoint (30-day preview)
  - [x] 3.6 Add validation for rotation sequence (shift_ids must exist)
  - [x] 3.7 Verify rotation pattern API tests pass

- [ ] 4. Flexible Shift Support (Frontend integration deferred to Phase 2)
  - [x] 4.1 Write tests for flexible shift creation and validation
  - [ ] 4.2 Update ShiftController to support flexible shift type
  - [ ] 4.3 Update DirectionDetector to support flexible shift boundaries
  - [ ] 4.4 Update SummaryCalculator to handle flexible expected hours
  - [ ] 4.5 Update ViolationDetector for flexible shift validation
  - [ ] 4.6 Add FlexibleShiftForm.vue component
  - [ ] 4.7 Verify flexible shift tests pass

- [x] 5. Rotation Scheduler Command
  - [x] 5.1 Write tests for AdvanceShiftRotationsCommand
  - [x] 5.2 Create AdvanceShiftRotationsCommand (runs daily at midnight)
  - [x] 5.3 Implement rotation advancement logic based on cycle type
  - [x] 5.4 Add command to Laravel scheduler (routes/console.php)
  - [x] 5.5 Test command with sample rotation data
  - [x] 5.6 Add logging for rotation advancements
  - [x] 5.7 Verify command tests pass

- [ ] 6. Frontend Components
  - [ ] 6.1 Write tests for rotation pattern UI components
  - [ ] 6.2 Create resources/js/pages/Shifts/RotationPatterns/Index.vue
  - [ ] 6.3 Create resources/js/pages/Shifts/RotationPatterns/Create.vue
  - [ ] 6.4 Create RotationSequenceBuilder.vue (drag-drop shift sequence)
  - [ ] 6.5 Create ShiftCalendarPreview.vue (30-day schedule view)
  - [ ] 6.6 Create RotationBadge.vue component
  - [ ] 6.7 Update Shifts/Create.vue to support flexible shift fields
  - [ ] 6.8 Verify frontend tests pass

- [ ] 7. Performance Testing and Production Readiness
  - [ ] 7.1 Test rotation calculation (target < 50ms per employee)
  - [ ] 7.2 Test shift calendar preview load (target < 500ms)
  - [ ] 7.3 Test advance rotations command with 1000+ employees (target < 2 minutes)
  - [ ] 7.4 Verify overnight shift handling with midnight boundaries
  - [ ] 7.5 Test with multiple rotation patterns and complex sequences
  - [ ] 7.6 Add error handling for invalid rotation configurations
  - [ ] 7.7 Verify all tests pass and feature is production-ready
