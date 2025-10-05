# Tests Specification

This is the tests coverage details for the spec detailed in @.agent-os/specs/2025-10-05-basic-reporting/spec.md

> Created: 2025-10-05
> Version: 1.0.0

## Test Coverage

### Unit Tests

#### ReportGeneratorTest.php (`tests/Unit/ReportGeneratorTest.php`)

**Test Cases**:

1. **test_generates_attendance_report_with_correct_structure**
   - Verify `AttendanceReportData` DTO structure
   - Ensure all required fields are present in records
   - Ensure summary contains all expected keys

2. **test_attendance_report_filters_by_date_range**
   - Create summaries for different dates
   - Generate report for specific date range
   - Verify only summaries within range are included

3. **test_attendance_report_filters_by_employee_ids**
   - Create summaries for multiple employees
   - Filter by specific employee IDs
   - Verify only specified employees are included

4. **test_attendance_report_filters_by_department_ids**
   - Create employees in different departments
   - Filter by specific department IDs
   - Verify only employees from specified departments are included

5. **test_attendance_report_calculates_correct_summary**
   - Create multiple summaries with known values
   - Generate report
   - Verify summary totals and averages are correct

6. **test_generates_violation_report_with_correct_structure**
   - Verify `ViolationReportData` DTO structure
   - Ensure all required fields are present in violations
   - Ensure summary contains all expected keys

7. **test_violation_report_filters_by_type**
   - Create violations of different types
   - Filter by specific types
   - Verify only specified types are included

8. **test_violation_report_filters_by_severity**
   - Create violations with different severities
   - Filter by specific severities
   - Verify only specified severities are included

9. **test_violation_report_identifies_repeat_offenders**
   - Create multiple violations for same employees
   - Generate report
   - Verify top repeat offenders are correctly identified

#### ReportExporterTest.php (`tests/Unit/ReportExporterTest.php`)

**Test Cases**:

1. **test_exports_attendance_report_to_pdf**
   - Generate attendance report data
   - Export to PDF
   - Verify file exists and is valid PDF

2. **test_exports_attendance_report_to_excel**
   - Generate attendance report data
   - Export to Excel
   - Verify file exists and contains correct sheets

3. **test_exports_attendance_report_to_csv**
   - Generate attendance report data
   - Export to CSV
   - Verify file exists and contains correct headers and data

4. **test_pdf_export_uses_correct_template**
   - Mock Pdf facade
   - Export report
   - Verify correct Blade template is used

5. **test_excel_export_has_correct_headings**
   - Export report to Excel
   - Read Excel file
   - Verify column headings match expected values

6. **test_csv_export_handles_empty_records**
   - Create report with no records
   - Export to CSV
   - Verify file is created with headers only

### Feature Tests

#### ReportingTest.php (`tests/Feature/ReportingTest.php`)

**Test Cases**:

1. **test_attendance_report_endpoint_returns_json**
   - Create test data
   - Call GET /api/reports/attendance with date range
   - Verify 200 status and JSON structure

2. **test_attendance_report_endpoint_validates_required_fields**
   - Call endpoint without required fields
   - Verify 422 validation error

3. **test_attendance_report_endpoint_validates_date_range**
   - Call endpoint with end date before start date
   - Verify 422 validation error

4. **test_attendance_report_endpoint_downloads_pdf**
   - Call endpoint with format=pdf
   - Verify file download response
   - Verify Content-Type is application/pdf

5. **test_attendance_report_endpoint_downloads_excel**
   - Call endpoint with format=excel
   - Verify file download response
   - Verify Content-Type is Excel format

6. **test_attendance_report_endpoint_downloads_csv**
   - Call endpoint with format=csv
   - Verify file download response
   - Verify Content-Type is text/csv

7. **test_violation_report_endpoint_returns_json**
   - Create test violations
   - Call GET /api/reports/violations with date range
   - Verify 200 status and JSON structure

8. **test_violation_report_filters_by_type**
   - Create violations of different types
   - Call endpoint with type filter
   - Verify response contains only filtered types

9. **test_violation_report_filters_by_severity**
   - Create violations with different severities
   - Call endpoint with severity filter
   - Verify response contains only filtered severities

10. **test_attendance_report_requires_authentication**
    - Call endpoint without authentication
    - Verify 401 unauthorized response

11. **test_violation_report_requires_authentication**
    - Call endpoint without authentication
    - Verify 401 unauthorized response

12. **test_download_report_endpoint_returns_file**
    - Generate and save a report
    - Call download endpoint with filename
    - Verify file is downloaded

13. **test_download_report_endpoint_returns_404_for_invalid_file**
    - Call download endpoint with non-existent filename
    - Verify 404 response

#### ScheduledReportsTest.php (`tests/Feature/ScheduledReportsTest.php`)

**Test Cases**:

1. **test_send_scheduled_reports_command_sends_daily_reports**
   - Create users with daily report preferences
   - Run command with --type=daily
   - Verify emails are sent with attachments

2. **test_send_scheduled_reports_command_sends_weekly_reports**
   - Create users with weekly report preferences
   - Run command with --type=weekly
   - Verify emails are sent with attachments

3. **test_send_scheduled_reports_command_skips_users_without_preference**
   - Create users without report preferences
   - Run command
   - Verify no emails are sent to those users

4. **test_send_scheduled_reports_generates_correct_date_range**
   - Run command
   - Verify report is generated for yesterday (for daily)

5. **test_send_scheduled_reports_attaches_pdf_to_email**
   - Mock notification
   - Run command
   - Verify notification contains PDF attachment

6. **test_scheduled_command_is_registered**
   - Verify command is registered in Kernel
   - Verify schedule is configured correctly

## Mocking Requirements

### PDF Generation Mock

```php
use Barryvdh\DomPDF\Facade\Pdf;

Pdf::shouldReceive('loadView')
    ->once()
    ->with('reports.attendance-report', Mockery::any())
    ->andReturnSelf();

Pdf::shouldReceive('save')
    ->once()
    ->with(Mockery::type('string'))
    ->andReturn(true);
```

### Excel Export Mock

```php
use Maatwebsite\Excel\Facades\Excel;

Excel::shouldReceive('store')
    ->once()
    ->with(
        Mockery::type(AttendanceReportExport::class),
        Mockery::type('string'),
        'local'
    )
    ->andReturn(true);
```

### Notification Mock

```php
use Illuminate\Support\Facades\Notification;

Notification::fake();

// After command runs
Notification::assertSentTo(
    $user,
    ScheduledReportNotification::class,
    function ($notification, $channels) use ($user) {
        return $notification->hasAttachment();
    }
);
```

## Test Data Factories

### DailyAttendanceSummary Factory Updates

```php
// Add state for different statuses
public function present()
{
    return $this->state(function (array $attributes) {
        return [
            'status' => 'present',
            'total_work_minutes' => 480, // 8 hours
            'first_check_in' => '09:00:00',
            'last_check_out' => '17:00:00',
        ];
    });
}

public function absent()
{
    return $this->state(function (array $attributes) {
        return [
            'status' => 'absent',
            'total_work_minutes' => 0,
            'first_check_in' => null,
            'last_check_out' => null,
        ];
    });
}
```

### AttendanceViolation Factory Updates

```php
// Add states for different types and severities
public function lateArrival()
{
    return $this->state(['type' => 'late-arrival']);
}

public function earlyDeparture()
{
    return $this->state(['type' => 'early-departure']);
}

public function minor()
{
    return $this->state(['severity' => 'minor']);
}

public function major()
{
    return $this->state(['severity' => 'major']);
}
```

## Example Test Implementation

```php
<?php

use App\Domain\Reporting\Services\ReportGenerator;
use App\Models\DailyAttendanceSummary;
use App\Models\Employee;
use Carbon\Carbon;

test('attendance report includes all summaries in date range', function () {
    $employee = Employee::factory()->create();

    // Create summaries for different dates
    DailyAttendanceSummary::factory()->create([
        'employee_id' => $employee->id,
        'date' => '2025-10-01',
        'status' => 'present',
    ]);

    DailyAttendanceSummary::factory()->create([
        'employee_id' => $employee->id,
        'date' => '2025-10-05',
        'status' => 'present',
    ]);

    DailyAttendanceSummary::factory()->create([
        'employee_id' => $employee->id,
        'date' => '2025-10-10', // Outside range
        'status' => 'present',
    ]);

    $generator = app(ReportGenerator::class);
    $report = $generator->generateAttendanceReport(
        Carbon::parse('2025-10-01'),
        Carbon::parse('2025-10-05')
    );

    expect($report->records)->toHaveCount(2);
    expect($report->summary['present_count'])->toBe(2);
});

test('attendance report API endpoint requires authentication', function () {
    $response = $this->getJson('/api/reports/attendance?from=2025-10-01&to=2025-10-05');

    $response->assertStatus(401);
});

test('attendance report API endpoint returns PDF download', function () {
    $user = User::factory()->create();
    $employee = Employee::factory()->create();

    DailyAttendanceSummary::factory()->create([
        'employee_id' => $employee->id,
        'date' => '2025-10-01',
    ]);

    $response = $this->actingAs($user)
        ->getJson('/api/reports/attendance?from=2025-10-01&to=2025-10-01&format=pdf');

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/pdf');
    $response->assertHeader('Content-Disposition', 'attachment; filename=attendance_report_2025-10-01_to_2025-10-01.pdf');
});
```
