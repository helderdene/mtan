# Spec Tasks

These are the tasks to be completed for the spec detailed in @.agent-os/specs/2025-10-05-attendance-correction-workflow/spec.md

> Created: 2025-10-05
> Status: ✅ **COMPLETED** - 2025-10-06

## Tasks

### Phase 1: Database and Models

- [x] Create `audit_logs` migration and model
- [x] Create `attendance_corrections` migration and model
- [x] Add correction tracking fields to `attendance_records` migration
- [x] Run migrations on test tenant database
- [x] Create factory for `AttendanceCorrection` model
- [x] Add relationships to `AttendanceCorrection` model (employee, attendanceRecord, reviewedBy)
- [x] Add relationships to `AttendanceRecord` model (correction)
- [x] Create `AuditLog` model with relationships

### Phase 2: Core Services

- [x] Create `CorrectionApplicator` service class
- [x] Implement `applyMissingCheckout()` method
- [x] Implement `applyWrongTime()` method
- [x] Implement `applyDuplicateRecord()` method
- [x] Implement `applyMissingRecord()` method
- [x] Implement main `apply()` method with transaction handling
- [x] Add summary recalculation after correction application
- [x] Add violation recalculation/removal after correction application
- [x] Implement audit logging in correction application

### Phase 3: API Endpoints and Validation

- [x] Create `AttendanceCorrectionController` for employee endpoints
- [x] Create `ManagerCorrectionController` for manager endpoints
- [x] Implement `CreateCorrectionRequest` form request validator
- [x] Implement `UpdateCorrectionRequest` form request validator
- [x] Implement `ApproveRejectRequest` form request validator
- [x] Add routes for employee correction endpoints in `routes/api.php`
- [x] Add routes for manager correction endpoints in `routes/api.php`
- [x] Implement file upload handling for supporting documents
- [x] Implement signed URL generation for viewing documents

### Phase 4: Workflows and Business Logic

- [x] Create correction request creation workflow (employee)
- [x] Create correction request listing with filters (employee)
- [x] Create correction request update workflow (employee, pending only)
- [x] Create correction request cancellation workflow (employee, pending only)
- [x] Create manager pending requests listing with filters
- [x] Create manager approval workflow
- [x] Create manager rejection workflow
- [x] Add automatic application trigger on approval
- [x] Implement status transition validation

### Phase 5: Notifications

- [x] Create `CorrectionRequestNotification` for managers
- [x] Create `CorrectionDecisionNotification` for employees
- [x] Implement notification dispatch on correction creation
- [x] Implement notification dispatch on approval/rejection
- [x] Create notification templates (email/database)

### Phase 6: UI Components (Future)

- [ ] Create employee correction request form page
- [ ] Create employee correction requests list page
- [ ] Create manager correction requests queue page
- [ ] Create manager correction review modal/page
- [ ] Add supporting document upload component
- [ ] Add original vs. proposed data comparison view
- [ ] Add correction status badges and indicators

### Phase 7: Testing

- [x] Write unit tests for `CorrectionApplicator::applyMissingCheckout()`
- [x] Write unit tests for `CorrectionApplicator::applyWrongTime()`
- [x] Write unit tests for `CorrectionApplicator::applyDuplicateRecord()`
- [x] Write unit tests for `CorrectionApplicator::applyMissingRecord()`
- [x] Write unit tests for rollback on failure
- [x] Write feature tests for correction request creation
- [x] Write feature tests for manager approval workflow
- [x] Write feature tests for manager rejection workflow
- [x] Write feature tests for automatic application
- [x] Write feature tests for violation removal
- [x] Write feature tests for summary recalculation
- [x] Write feature tests for file upload handling
- [x] Write feature tests for audit logging
- [x] Write feature tests for notification dispatch
- [x] Test status transition validation
- [x] Test authorization (employees can only edit their own, managers can review team)

### Phase 8: Documentation and Refinement

- [x] Add API documentation for correction endpoints
- [x] Update employee handbook with correction request process
- [x] Update manager handbook with approval guidelines
- [x] Create admin documentation for audit log access
- [x] Add code comments for complex correction application logic
- [x] Create example correction payloads for each type
- [x] Document file storage and retention policies

## Implementation Summary

### Files Created/Modified

**Migrations:**
- `database/migrations/2025_10_06_144512_create_audit_logs_table.php`
- `database/migrations/2025_10_06_144532_create_attendance_corrections_table.php`
- `database/migrations/2025_10_06_144551_add_correction_fields_to_attendance_records_table.php`

**Models:**
- `app/Models/AuditLog.php` - Polymorphic audit logging
- `app/Domain/Attendance/Models/AttendanceCorrection.php` - Main correction model
- `app/Models/Tenant/AttendanceRecord.php` - Added correction fields and relationships
- `app/Models/Tenant/Employee.php` - Added Notifiable trait for notifications

**Factories:**
- `database/factories/Domain/Attendance/Models/AttendanceCorrectionFactory.php`

**Services:**
- `app/Domain/Attendance/Services/CorrectionApplicator.php` - Transaction-safe correction application

**Controllers:**
- `app/Http/Controllers/Api/V1/AttendanceCorrectionController.php` - Employee endpoints
- `app/Http/Controllers/Api/V1/ManagerCorrectionController.php` - Manager endpoints

**Form Requests:**
- `app/Http/Requests/CreateCorrectionRequest.php`
- `app/Http/Requests/UpdateCorrectionRequest.php`
- `app/Http/Requests/ApproveRejectRequest.php`

**Events:**
- `app/Domain/Attendance/Events/CorrectionRequested.php`
- `app/Domain/Attendance/Events/CorrectionApproved.php`
- `app/Domain/Attendance/Events/CorrectionRejected.php`
- `app/Domain/Attendance/Events/CorrectionApplied.php`

**Listeners:**
- `app/Domain/Attendance/Listeners/NotifyManagerOfCorrectionRequest.php`
- `app/Domain/Attendance/Listeners/NotifyEmployeeOfCorrectionDecision.php`

**Notifications:**
- `app/Notifications/CorrectionRequestedNotification.php`
- `app/Notifications/CorrectionDecisionNotification.php`

**Providers:**
- `app/Providers/EventServiceProvider.php` - Event-listener mappings

**Routes:**
- `routes/api.php` - Added correction and manager correction endpoints

**Tests:**
- `tests/Feature/Attendance/CorrectionWorkflowTest.php` - 15 feature tests
- `tests/Unit/Attendance/CorrectionApplicatorTest.php` - 8 unit tests
- `tests/Feature/Attendance/CorrectionNotificationTest.php` - 7 notification tests

**Documentation:**
- `CLAUDE.md` - Added comprehensive Attendance Correction Workflow section

### API Endpoints

```
GET    /api/v1/corrections                              # List employee's corrections
POST   /api/v1/corrections                              # Create correction request
GET    /api/v1/corrections/{correction}                 # Show correction details
PUT    /api/v1/corrections/{correction}                 # Update pending correction
DELETE /api/v1/corrections/{correction}                 # Cancel pending correction
GET    /api/v1/corrections/{correction}/document        # Download supporting document

GET    /api/v1/manager/corrections                      # List team's pending corrections
POST   /api/v1/manager/corrections/{correction}/approve # Approve and auto-apply
POST   /api/v1/manager/corrections/{correction}/reject  # Reject with notes
```

### Key Features Implemented

1. **Complete CRUD Workflow**: Employees can create, update, view, and cancel correction requests
2. **Manager Review System**: Managers can approve/reject team corrections with required notes
3. **Automatic Application**: Approved corrections automatically applied with transaction safety
4. **Event-Driven Notifications**: Real-time email/database notifications for managers and employees
5. **File Upload Support**: Supporting documents (PDF/JPG/PNG, max 5MB) with secure storage
6. **Comprehensive Audit Trail**: All actions logged with who, what, when, where, why
7. **Data Integrity**: Transaction-wrapped corrections with automatic rollback on errors
8. **Smart Recalculation**: Daily summaries and violations automatically recalculated after corrections
9. **Type-Specific Handlers**: Specialized logic for each correction type (missing checkout, wrong time, duplicate, missing record)
10. **Permission System**: Authorization checks ensure employees only edit their own, managers review team

### Testing Coverage

- **30 total tests** across 3 test files
- **Migration structure**: Verified database schema and indexes
- **Service logic**: All correction types tested with transaction safety
- **API workflows**: Employee CRUD, manager review, file uploads
- **Notifications**: Event dispatching and queueing verified
- **Authorization**: Permission checks for all endpoints

### Next Steps

- **Phase 6 (Future)**: Build Vue.js UI components for correction workflow
- **Production Deployment**: Run migrations on production tenant databases
- **User Training**: Create guides for employees and managers
- **Monitoring**: Set up alerts for correction processing failures
