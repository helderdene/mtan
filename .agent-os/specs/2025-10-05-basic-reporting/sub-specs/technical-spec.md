# Technical Specification

This is the technical specification for the spec detailed in @.agent-os/specs/2025-10-05-basic-reporting/spec.md

> Created: 2025-10-05
> Version: 1.0.0

## Technical Requirements

### ReportGenerator Service

**Location**: `app/Domain/Reporting/Services/ReportGenerator.php`

**Method Signatures**:
```php
public function generateAttendanceReport(
    Carbon $startDate,
    Carbon $endDate,
    ?array $employeeIds = null,
    ?array $departmentIds = null
): AttendanceReportData;

public function generateViolationReport(
    Carbon $startDate,
    Carbon $endDate,
    ?array $violationTypes = null,
    ?array $severities = null,
    ?array $employeeIds = null
): ViolationReportData;
```

### Report DTOs

**AttendanceReportData**:
```php
class AttendanceReportData
{
    public function __construct(
        public Carbon $startDate,
        public Carbon $endDate,
        public Collection $records,
        public array $summary
    ) {}
}

// Structure of $records:
[
    [
        'employee_id' => 1,
        'employee_name' => 'John Doe',
        'employee_code' => 'EMP001',
        'department' => 'Engineering',
        'date' => '2025-10-05',
        'first_check_in' => '09:05:00',
        'last_check_out' => '18:30:00',
        'total_work_hours' => 8.5,
        'total_break_hours' => 1.0,
        'overtime_hours' => 0.5,
        'status' => 'present',
    ],
    // ...
]

// Structure of $summary:
[
    'total_employees' => 50,
    'present_count' => 45,
    'absent_count' => 3,
    'half_day_count' => 2,
    'total_work_hours' => 382.5,
    'average_work_hours' => 8.5,
    'total_overtime_hours' => 12.5,
]
```

**ViolationReportData**:
```php
class ViolationReportData
{
    public function __construct(
        public Carbon $startDate,
        public Carbon $endDate,
        public Collection $violations,
        public array $summary
    ) {}
}

// Structure of $violations:
[
    [
        'violation_id' => 1,
        'employee_id' => 1,
        'employee_name' => 'John Doe',
        'employee_code' => 'EMP001',
        'department' => 'Engineering',
        'violation_date' => '2025-10-05',
        'type' => 'late-arrival',
        'severity' => 'minor',
        'minutes_deviation' => 15,
        'status' => 'pending',
    ],
    // ...
]

// Structure of $summary:
[
    'total_violations' => 25,
    'by_type' => [
        'late-arrival' => 12,
        'early-departure' => 5,
        'extended-break' => 3,
        'missing-checkout' => 5,
    ],
    'by_severity' => [
        'minor' => 15,
        'moderate' => 7,
        'major' => 3,
    ],
    'repeat_offenders' => [ // Top 5 employees by violation count
        ['employee_id' => 1, 'name' => 'John Doe', 'count' => 5],
        // ...
    ],
]
```

### Report Generation Implementation

**Daily Attendance Report**:
```php
public function generateAttendanceReport(
    Carbon $startDate,
    Carbon $endDate,
    ?array $employeeIds = null,
    ?array $departmentIds = null
): AttendanceReportData {
    $query = DailyAttendanceSummary::query()
        ->with(['employee', 'employee.department'])
        ->whereBetween('date', [$startDate, $endDate]);

    if ($employeeIds) {
        $query->whereIn('employee_id', $employeeIds);
    }

    if ($departmentIds) {
        $query->whereHas('employee', function ($q) use ($departmentIds) {
            $q->whereIn('department_id', $departmentIds);
        });
    }

    $summaries = $query->get();

    $records = $summaries->map(function ($summary) {
        return [
            'employee_id' => $summary->employee_id,
            'employee_name' => $summary->employee->name,
            'employee_code' => $summary->employee->employee_code,
            'department' => $summary->employee->department->name ?? 'N/A',
            'date' => $summary->date->format('Y-m-d'),
            'first_check_in' => $summary->first_check_in?->format('H:i:s'),
            'last_check_out' => $summary->last_check_out?->format('H:i:s'),
            'total_work_hours' => $summary->total_work_hours,
            'total_break_hours' => $summary->total_break_hours,
            'overtime_hours' => $summary->overtime_hours,
            'status' => $summary->status,
        ];
    });

    $summary = [
        'total_employees' => $summaries->unique('employee_id')->count(),
        'present_count' => $summaries->where('status', 'present')->count(),
        'absent_count' => $summaries->where('status', 'absent')->count(),
        'half_day_count' => $summaries->where('status', 'half-day')->count(),
        'total_work_hours' => $summaries->sum('total_work_minutes') / 60,
        'average_work_hours' => $summaries->avg('total_work_minutes') / 60,
        'total_overtime_hours' => $summaries->sum('overtime_minutes') / 60,
    ];

    return new AttendanceReportData($startDate, $endDate, $records, $summary);
}
```

### Export Service

**Location**: `app/Domain/Reporting/Services/ReportExporter.php`

**Method Signatures**:
```php
public function exportToPdf(ReportData $data, string $template): string; // Returns file path

public function exportToExcel(ReportData $data): string; // Returns file path

public function exportToCsv(Collection $records): string; // Returns file path
```

**PDF Export** (using DomPDF or similar):
```php
use Barryvdh\DomPDF\Facade\Pdf;

public function exportToPdf(AttendanceReportData $data, string $template): string
{
    $pdf = Pdf::loadView("reports.{$template}", [
        'data' => $data,
        'generated_at' => now(),
    ]);

    $filename = "attendance_report_{$data->startDate->format('Y-m-d')}_to_{$data->endDate->format('Y-m-d')}.pdf";
    $path = storage_path("app/reports/{$filename}");

    $pdf->save($path);

    return $path;
}
```

**Excel Export** (using Laravel Excel):
```php
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AttendanceReportExport;

public function exportToExcel(AttendanceReportData $data): string
{
    $filename = "attendance_report_{$data->startDate->format('Y-m-d')}_to_{$data->endDate->format('Y-m-d')}.xlsx";
    $path = "reports/{$filename}";

    Excel::store(new AttendanceReportExport($data), $path, 'local');

    return storage_path("app/{$path}");
}
```

**CSV Export**:
```php
public function exportToCsv(Collection $records): string
{
    $filename = "report_" . now()->format('Y-m-d_His') . ".csv";
    $path = storage_path("app/reports/{$filename}");

    $file = fopen($path, 'w');

    // Write headers
    if ($records->isNotEmpty()) {
        fputcsv($file, array_keys($records->first()));
    }

    // Write data
    foreach ($records as $record) {
        fputcsv($file, $record);
    }

    fclose($file);

    return $path;
}
```

### Excel Export Class

**Location**: `app/Exports/AttendanceReportExport.php`

```php
<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AttendanceReportExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        protected AttendanceReportData $data
    ) {}

    public function collection()
    {
        return $this->data->records;
    }

    public function headings(): array
    {
        return [
            'Employee ID',
            'Employee Name',
            'Employee Code',
            'Department',
            'Date',
            'First Check-in',
            'Last Check-out',
            'Work Hours',
            'Break Hours',
            'Overtime Hours',
            'Status',
        ];
    }

    public function map($row): array
    {
        return [
            $row['employee_id'],
            $row['employee_name'],
            $row['employee_code'],
            $row['department'],
            $row['date'],
            $row['first_check_in'],
            $row['last_check_out'],
            $row['total_work_hours'],
            $row['total_break_hours'],
            $row['overtime_hours'],
            ucfirst($row['status']),
        ];
    }
}
```

### Report Templates

**Location**: `resources/views/reports/attendance-report.blade.php`

```blade
<!DOCTYPE html>
<html>
<head>
    <title>Attendance Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .header { text-align: center; margin-bottom: 20px; }
        .summary { margin-top: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Daily Attendance Report</h1>
        <p>{{ $data->startDate->format('M d, Y') }} to {{ $data->endDate->format('M d, Y') }}</p>
        <p>Generated: {{ $generated_at->format('M d, Y H:i:s') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th>Code</th>
                <th>Department</th>
                <th>Date</th>
                <th>Check-in</th>
                <th>Check-out</th>
                <th>Work Hours</th>
                <th>Overtime</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data->records as $record)
            <tr>
                <td>{{ $record['employee_name'] }}</td>
                <td>{{ $record['employee_code'] }}</td>
                <td>{{ $record['department'] }}</td>
                <td>{{ $record['date'] }}</td>
                <td>{{ $record['first_check_in'] ?? 'N/A' }}</td>
                <td>{{ $record['last_check_out'] ?? 'N/A' }}</td>
                <td>{{ number_format($record['total_work_hours'], 2) }}</td>
                <td>{{ number_format($record['overtime_hours'], 2) }}</td>
                <td>{{ ucfirst($record['status']) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary">
        <h2>Summary</h2>
        <p><strong>Total Employees:</strong> {{ $data->summary['total_employees'] }}</p>
        <p><strong>Present:</strong> {{ $data->summary['present_count'] }}</p>
        <p><strong>Absent:</strong> {{ $data->summary['absent_count'] }}</p>
        <p><strong>Half-day:</strong> {{ $data->summary['half_day_count'] }}</p>
        <p><strong>Total Work Hours:</strong> {{ number_format($data->summary['total_work_hours'], 2) }}</p>
        <p><strong>Average Work Hours:</strong> {{ number_format($data->summary['average_work_hours'], 2) }}</p>
        <p><strong>Total Overtime:</strong> {{ number_format($data->summary['total_overtime_hours'], 2) }}</p>
    </div>
</body>
</html>
```

### Scheduled Report Command

**Command**: `app/Console/Commands/SendScheduledReportsCommand.php`

**Signature**: `reports:send-scheduled {--type=daily}`

```php
public function handle()
{
    $type = $this->option('type');

    // Get users who want scheduled reports
    $recipients = User::whereHas('notificationPreferences', function ($query) use ($type) {
        $query->where('notification_type', 'scheduled_report')
              ->where('enabled', true)
              ->whereJsonContains("settings->report_frequency", $type);
    })->get();

    foreach ($recipients as $recipient) {
        // Generate report for yesterday
        $data = $this->reportGenerator->generateAttendanceReport(
            Carbon::yesterday(),
            Carbon::yesterday(),
            null, // All employees under this manager
            null
        );

        // Export to PDF
        $pdfPath = $this->exporter->exportToPdf($data, 'attendance-report');

        // Send email with attachment
        $recipient->notify(new ScheduledReportNotification($data, $pdfPath));

        $this->info("Sent report to {$recipient->email}");
    }
}
```

**Schedule** (in `app/Console/Kernel.php`):
```php
// Daily reports at 8 AM
$schedule->command('reports:send-scheduled --type=daily')
    ->dailyAt('08:00');

// Weekly reports on Monday at 8 AM
$schedule->command('reports:send-scheduled --type=weekly')
    ->weeklyOn(1, '08:00');
```

### Testing Requirements

**Unit Tests** (`tests/Unit/ReportGeneratorTest.php`):
- Test attendance report data structure
- Test violation report data structure
- Test filtering logic
- Test summary calculations

**Feature Tests** (`tests/Feature/ReportingTest.php`):
- Test API endpoint for attendance report
- Test API endpoint for violation report
- Test PDF export generation
- Test Excel export generation
- Test CSV export generation
- Test scheduled report command

**Example Test**:
```php
test('attendance report includes all summaries in date range', function () {
    $employee = Employee::factory()->create();

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

    $generator = app(ReportGenerator::class);
    $report = $generator->generateAttendanceReport(
        Carbon::parse('2025-10-01'),
        Carbon::parse('2025-10-05')
    );

    expect($report->records)->toHaveCount(2);
    expect($report->summary['present_count'])->toBe(2);
});
```

## Approach

1. **Phase 1: Core Services**
   - Implement `ReportGenerator` service with attendance and violation report methods
   - Create DTOs for `AttendanceReportData` and `ViolationReportData`
   - Implement filtering and aggregation logic
   - Write unit tests for report generation

2. **Phase 2: Export Functionality**
   - Implement `ReportExporter` service with PDF, Excel, CSV methods
   - Create Blade templates for PDF reports
   - Implement Excel export classes with proper formatting
   - Implement CSV export with proper headers
   - Write unit tests for export methods

3. **Phase 3: API Endpoints**
   - Create API routes for report generation and download
   - Implement `ReportController` with validation
   - Add response formatting for JSON and file downloads
   - Write feature tests for API endpoints

4. **Phase 4: Scheduled Reports**
   - Implement `SendScheduledReportsCommand` with type option
   - Create notification class for report delivery
   - Configure scheduled tasks in Kernel
   - Add user preference model for report subscriptions
   - Write feature tests for scheduled reports

5. **Phase 5: Additional Templates**
   - Create violation report Blade template
   - Create violation export classes
   - Add styling and branding to PDF templates
   - Implement email templates with attachments

## External Dependencies

**Required Packages**:
```bash
# PDF generation
composer require barryvdh/laravel-dompdf

# Excel export
composer require maatwebsite/excel
```

**Configuration**:
```env
# PDF Configuration (config/dompdf.php will be published)
DOMPDF_ENABLE_REMOTE=false

# Excel temp directory
EXCEL_TEMP_PATH=storage/app/excel
```
