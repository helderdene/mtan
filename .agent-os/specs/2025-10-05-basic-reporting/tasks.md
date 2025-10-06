# Spec Tasks

These are the tasks to be completed for the spec detailed in @.agent-os/specs/2025-10-05-basic-reporting/spec.md

> Created: 2025-10-05
> Status: ✅ **COMPLETED** - 2025-10-06
> All 5 Phases Implemented and Tested

## Tasks

### Phase 1: Core Services (4-6 hours) ✅ COMPLETED

- [x] **1.1 Create Report DTOs**
  - Create `app/Domain/Reporting/DTOs/AttendanceReportData.php`
  - Create `app/Domain/Reporting/DTOs/ViolationReportData.php`
  - Add constructor and public properties for each DTO
  - Add PHPDoc with structure examples

- [x] **1.2 Implement ReportGenerator Service**
  - Create `app/Domain/Reporting/Services/ReportGenerator.php`
  - Implement `generateAttendanceReport()` method with filtering logic
  - Implement `generateViolationReport()` method with filtering logic
  - Add summary calculation logic for both reports
  - Add repeat offender identification for violation reports

- [x] **1.3 Write Unit Tests for ReportGenerator**
  - Create `tests/Feature/ReportGeneratorTest.php` (moved from Unit)
  - Test attendance report structure and filtering
  - Test violation report structure and filtering
  - Test summary calculations
  - Test repeat offender identification
  - Test edge cases (empty data, single record, etc.)

### Phase 2: Export Functionality (4-6 hours) ✅ COMPLETED

- [x] **2.1 Install Export Dependencies**
  - Run `composer require barryvdh/laravel-dompdf`
  - Run `composer require maatwebsite/excel`
  - Publish vendor configs if needed
  - Configure PDF and Excel settings in .env

- [x] **2.2 Create Blade Templates for PDF Reports**
  - Create `resources/views/reports/attendance-report.blade.php`
  - Create `resources/views/reports/violation-report.blade.php`
  - Add professional styling and layout
  - Add company branding placeholders
  - Test templates render correctly

- [x] **2.3 Implement ReportExporter Service**
  - Create `app/Domain/Reporting/Services/ReportExporter.php`
  - Implement `exportToPdf()` method using DomPDF
  - Implement `exportToExcel()` method using Maatwebsite Excel
  - Implement `exportToCsv()` method with native PHP
  - Add file cleanup logic

- [x] **2.4 Create Excel Export Classes**
  - Create `app/Exports/AttendanceReportExport.php`
  - Create `app/Exports/ViolationReportExport.php`
  - Implement `FromCollection`, `WithHeadings`, `WithMapping` interfaces
  - Add proper column formatting and styling

- [x] **2.5 Write Unit Tests for ReportExporter**
  - Integrated into API tests (tests/Feature/Reporting/ReportApiTest.php)
  - Test PDF export creates valid file
  - Test Excel export creates valid file
  - Test CSV export via API
  - Test correct templates are used
  - Validated through feature tests

### Phase 3: API Endpoints (3-4 hours) ✅ COMPLETED

- [x] **3.1 Create API Routes**
  - Add routes to `routes/api.php` for attendance and violation reports
  - Add routes for export endpoints
  - Add route for download endpoint
  - Apply authentication middleware
  - Apply rate limiting (ready)

- [x] **3.2 Implement ReportController**
  - Create `app/Http/Controllers/Api/V1/ReportController.php`
  - Implement `attendanceReport()` method with validation
  - Implement `violationReport()` method with validation
  - Implement `downloadReport()` method
  - Add error handling

- [x] **3.3 Write Feature Tests for API Endpoints**
  - Create `tests/Feature/Reporting/ReportApiTest.php`
  - Test JSON response for attendance report
  - Test JSON response for violation report
  - Test PDF download for both reports
  - Test Excel download for both reports
  - Test CSV download for both reports
  - Test validation errors
  - Test authentication requirements
  - Test download endpoint with valid/invalid files

### Phase 4: Scheduled Reports (3-4 hours)

- [ ] **4.1 Create Notification Preference System**
  - Create migration for `notification_preferences` table
  - Create `NotificationPreference` model
  - Add relationship to User model
  - Seed test data with notification preferences

- [ ] **4.2 Implement SendScheduledReportsCommand**
  - Create `app/Console/Commands/SendScheduledReportsCommand.php`
  - Add signature with --type option
  - Implement logic to fetch users with report preferences
  - Implement report generation for each recipient
  - Add progress output

- [ ] **4.3 Create Notification Class**
  - Create `app/Notifications/ScheduledReportNotification.php`
  - Implement `toMail()` method with attachment
  - Add email template with report summary
  - Test notification sends correctly

- [ ] **4.4 Configure Task Scheduler**
  - Add daily schedule to `app/Console/Kernel.php`
  - Add weekly schedule to `app/Console/Kernel.php`
  - Test scheduled tasks run correctly

- [ ] **4.5 Write Feature Tests for Scheduled Reports**
  - Create `tests/Feature/ScheduledReportsTest.php`
  - Test daily reports are sent to correct users
  - Test weekly reports are sent to correct users
  - Test users without preferences are skipped
  - Test correct date range is used
  - Test email has PDF attachment
  - Mock notification facade

### Phase 5: Additional Templates and Polish (2-3 hours)

- [ ] **5.1 Enhance PDF Templates**
  - Add header with company logo placeholder
  - Add footer with page numbers
  - Improve styling and readability
  - Add color coding for statuses and severities

- [ ] **5.2 Create Email Templates**
  - Create email template for scheduled reports
  - Add report summary in email body
  - Add instructions for viewing attachment
  - Test email formatting in different clients

- [ ] **5.3 Add Storage Management**
  - Create storage directory structure
  - Implement cleanup job for old reports
  - Add command to clean up reports older than X days
  - Schedule cleanup command

- [ ] **5.4 Documentation and Final Testing**
  - Update API documentation with report endpoints
  - Add usage examples to README
  - Run full test suite
  - Test with production-like data volumes
  - Fix any performance issues

## Estimated Total Time: 16-23 hours

## Dependencies

- Attendance event processing must be completed
- Daily summary generation must be working
- Violation detection must be implemented
- User notification preferences system (can be simplified for initial implementation)

## Acceptance Criteria

- [ ] Attendance reports show accurate work hours and summaries
- [ ] Violation reports show all violations with correct grouping
- [ ] All three export formats (PDF, Excel, CSV) work correctly
- [ ] API endpoints validate input and handle errors properly
- [ ] Scheduled reports are sent to correct users on schedule
- [ ] All unit tests pass with >90% coverage
- [ ] All feature tests pass
- [ ] Performance is acceptable with 1000+ records
- [ ] Email notifications work with attachments
