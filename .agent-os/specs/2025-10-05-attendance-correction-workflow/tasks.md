# Spec Tasks

These are the tasks to be completed for the spec detailed in @.agent-os/specs/2025-10-05-attendance-correction-workflow/spec.md

> Created: 2025-10-05
> Status: Ready for Implementation

## Tasks

### Phase 1: Database and Models

- [ ] Create `audit_logs` migration and model
- [ ] Create `attendance_corrections` migration and model
- [ ] Add correction tracking fields to `attendance_records` migration
- [ ] Run migrations on test tenant database
- [ ] Create factory for `AttendanceCorrection` model
- [ ] Add relationships to `AttendanceCorrection` model (employee, attendanceRecord, reviewedBy)
- [ ] Add relationships to `AttendanceRecord` model (correction)
- [ ] Create `AuditLog` model with relationships

### Phase 2: Core Services

- [ ] Create `CorrectionApplicator` service class
- [ ] Implement `applyMissingCheckout()` method
- [ ] Implement `applyWrongTime()` method
- [ ] Implement `applyDuplicateRecord()` method
- [ ] Implement `applyMissingRecord()` method
- [ ] Implement main `apply()` method with transaction handling
- [ ] Add summary recalculation after correction application
- [ ] Add violation recalculation/removal after correction application
- [ ] Implement audit logging in correction application

### Phase 3: API Endpoints and Validation

- [ ] Create `AttendanceCorrectionController` for employee endpoints
- [ ] Create `ManagerCorrectionController` for manager endpoints
- [ ] Implement `CreateCorrectionRequest` form request validator
- [ ] Implement `UpdateCorrectionRequest` form request validator
- [ ] Implement `ApproveRejectRequest` form request validator
- [ ] Add routes for employee correction endpoints in `routes/api.php`
- [ ] Add routes for manager correction endpoints in `routes/api.php`
- [ ] Implement file upload handling for supporting documents
- [ ] Implement signed URL generation for viewing documents

### Phase 4: Workflows and Business Logic

- [ ] Create correction request creation workflow (employee)
- [ ] Create correction request listing with filters (employee)
- [ ] Create correction request update workflow (employee, pending only)
- [ ] Create correction request cancellation workflow (employee, pending only)
- [ ] Create manager pending requests listing with filters
- [ ] Create manager approval workflow
- [ ] Create manager rejection workflow
- [ ] Add automatic application trigger on approval
- [ ] Implement status transition validation

### Phase 5: Notifications

- [ ] Create `CorrectionRequestNotification` for managers
- [ ] Create `CorrectionDecisionNotification` for employees
- [ ] Implement notification dispatch on correction creation
- [ ] Implement notification dispatch on approval/rejection
- [ ] Create notification templates (email/database)

### Phase 6: UI Components (Future)

- [ ] Create employee correction request form page
- [ ] Create employee correction requests list page
- [ ] Create manager correction requests queue page
- [ ] Create manager correction review modal/page
- [ ] Add supporting document upload component
- [ ] Add original vs. proposed data comparison view
- [ ] Add correction status badges and indicators

### Phase 7: Testing

- [ ] Write unit tests for `CorrectionApplicator::applyMissingCheckout()`
- [ ] Write unit tests for `CorrectionApplicator::applyWrongTime()`
- [ ] Write unit tests for `CorrectionApplicator::applyDuplicateRecord()`
- [ ] Write unit tests for `CorrectionApplicator::applyMissingRecord()`
- [ ] Write unit tests for rollback on failure
- [ ] Write feature tests for correction request creation
- [ ] Write feature tests for manager approval workflow
- [ ] Write feature tests for manager rejection workflow
- [ ] Write feature tests for automatic application
- [ ] Write feature tests for violation removal
- [ ] Write feature tests for summary recalculation
- [ ] Write feature tests for file upload handling
- [ ] Write feature tests for audit logging
- [ ] Write feature tests for notification dispatch
- [ ] Test status transition validation
- [ ] Test authorization (employees can only edit their own, managers can review team)

### Phase 8: Documentation and Refinement

- [ ] Add API documentation for correction endpoints
- [ ] Update employee handbook with correction request process
- [ ] Update manager handbook with approval guidelines
- [ ] Create admin documentation for audit log access
- [ ] Add code comments for complex correction application logic
- [ ] Create example correction payloads for each type
- [ ] Document file storage and retention policies
