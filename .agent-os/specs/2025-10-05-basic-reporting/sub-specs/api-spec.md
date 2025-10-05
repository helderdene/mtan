# API Specification

This is the API specification for the spec detailed in @.agent-os/specs/2025-10-05-basic-reporting/spec.md

> Created: 2025-10-05
> Version: 1.0.0

## Endpoints

### Report Generation

#### Generate Attendance Report
```http
GET /api/reports/attendance
```

**Query Parameters**:
- `from` (required, date): Start date in Y-m-d format
- `to` (required, date): End date in Y-m-d format
- `employee_ids[]` (optional, array): Filter by employee IDs
- `department_ids[]` (optional, array): Filter by department IDs
- `format` (optional, string): Export format (pdf, excel, csv)

**Response** (JSON when no format specified):
```json
{
  "start_date": "2025-10-01",
  "end_date": "2025-10-05",
  "records": [
    {
      "employee_id": 1,
      "employee_name": "John Doe",
      "employee_code": "EMP001",
      "department": "Engineering",
      "date": "2025-10-01",
      "first_check_in": "09:05:00",
      "last_check_out": "18:30:00",
      "total_work_hours": 8.5,
      "total_break_hours": 1.0,
      "overtime_hours": 0.5,
      "status": "present"
    }
  ],
  "summary": {
    "total_employees": 50,
    "present_count": 45,
    "absent_count": 3,
    "half_day_count": 2,
    "total_work_hours": 382.5,
    "average_work_hours": 8.5,
    "total_overtime_hours": 12.5
  }
}
```

**Response** (File download when format specified):
- Content-Type: `application/pdf`, `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`, or `text/csv`
- Content-Disposition: `attachment; filename="attendance_report_2025-10-01_to_2025-10-05.{ext}"`

**Example Request**:
```bash
# Get JSON response
GET /api/reports/attendance?from=2025-10-01&to=2025-10-05&employee_ids[]=1&employee_ids[]=2

# Download PDF
GET /api/reports/attendance?from=2025-10-01&to=2025-10-05&format=pdf

# Download Excel
GET /api/reports/attendance?from=2025-10-01&to=2025-10-05&department_ids[]=1&format=excel
```

#### Generate Violation Report
```http
GET /api/reports/violations
```

**Query Parameters**:
- `from` (required, date): Start date in Y-m-d format
- `to` (required, date): End date in Y-m-d format
- `types[]` (optional, array): Filter by violation types (late-arrival, early-departure, extended-break, missing-checkout)
- `severities[]` (optional, array): Filter by severities (minor, moderate, major)
- `employee_ids[]` (optional, array): Filter by employee IDs
- `format` (optional, string): Export format (pdf, excel, csv)

**Response** (JSON when no format specified):
```json
{
  "start_date": "2025-10-01",
  "end_date": "2025-10-05",
  "violations": [
    {
      "violation_id": 1,
      "employee_id": 1,
      "employee_name": "John Doe",
      "employee_code": "EMP001",
      "department": "Engineering",
      "violation_date": "2025-10-05",
      "type": "late-arrival",
      "severity": "minor",
      "minutes_deviation": 15,
      "status": "pending"
    }
  ],
  "summary": {
    "total_violations": 25,
    "by_type": {
      "late-arrival": 12,
      "early-departure": 5,
      "extended-break": 3,
      "missing-checkout": 5
    },
    "by_severity": {
      "minor": 15,
      "moderate": 7,
      "major": 3
    },
    "repeat_offenders": [
      {
        "employee_id": 1,
        "name": "John Doe",
        "count": 5
      }
    ]
  }
}
```

**Example Request**:
```bash
# Get major violations only
GET /api/reports/violations?from=2025-10-01&to=2025-10-31&severities[]=major

# Download late arrival violations as Excel
GET /api/reports/violations?from=2025-10-01&to=2025-10-31&types[]=late-arrival&format=excel
```

### Report Export (Alternative POST endpoint)

#### Export Attendance Report
```http
POST /api/reports/attendance/export
```

**Request Body**:
```json
{
  "from": "2025-10-01",
  "to": "2025-10-05",
  "employee_ids": [1, 2, 3],
  "department_ids": [1],
  "format": "pdf"
}
```

**Response**: File download

#### Export Violation Report
```http
POST /api/reports/violations/export
```

**Request Body**:
```json
{
  "from": "2025-10-01",
  "to": "2025-10-31",
  "types": ["late-arrival", "early-departure"],
  "severities": ["major", "moderate"],
  "employee_ids": [1, 2],
  "format": "excel"
}
```

**Response**: File download

### Report Download

#### Download Generated Report
```http
GET /api/reports/download/{filename}
```

**Path Parameters**:
- `filename` (required, string): Report filename

**Response**: File download or 404 if not found

**Example Request**:
```bash
GET /api/reports/download/attendance_report_2025-10-01_to_2025-10-05.pdf
```

## Controllers

### ReportController

**Location**: `app/Http/Controllers/Api/ReportController.php`

**Methods**:

#### attendanceReport(Request $request)
```php
public function attendanceReport(Request $request)
{
    $validated = $request->validate([
        'from' => 'required|date',
        'to' => 'required|date|after_or_equal:from',
        'employee_ids' => 'nullable|array',
        'department_ids' => 'nullable|array',
        'format' => 'nullable|in:pdf,excel,csv',
    ]);

    $data = $this->reportGenerator->generateAttendanceReport(
        Carbon::parse($validated['from']),
        Carbon::parse($validated['to']),
        $validated['employee_ids'] ?? null,
        $validated['department_ids'] ?? null
    );

    if ($format = $validated['format'] ?? null) {
        $path = match ($format) {
            'pdf' => $this->exporter->exportToPdf($data, 'attendance-report'),
            'excel' => $this->exporter->exportToExcel($data),
            'csv' => $this->exporter->exportToCsv($data->records),
        };

        return response()->download($path)->deleteFileAfterSend();
    }

    return response()->json($data);
}
```

#### violationReport(Request $request)
```php
public function violationReport(Request $request)
{
    $validated = $request->validate([
        'from' => 'required|date',
        'to' => 'required|date|after_or_equal:from',
        'types' => 'nullable|array',
        'types.*' => 'in:late-arrival,early-departure,extended-break,missing-checkout',
        'severities' => 'nullable|array',
        'severities.*' => 'in:minor,moderate,major',
        'employee_ids' => 'nullable|array',
        'format' => 'nullable|in:pdf,excel,csv',
    ]);

    $data = $this->reportGenerator->generateViolationReport(
        Carbon::parse($validated['from']),
        Carbon::parse($validated['to']),
        $validated['types'] ?? null,
        $validated['severities'] ?? null,
        $validated['employee_ids'] ?? null
    );

    if ($format = $validated['format'] ?? null) {
        $path = match ($format) {
            'pdf' => $this->exporter->exportToPdf($data, 'violation-report'),
            'excel' => $this->exporter->exportToExcel($data),
            'csv' => $this->exporter->exportToCsv($data->violations),
        };

        return response()->download($path)->deleteFileAfterSend();
    }

    return response()->json($data);
}
```

#### downloadReport(string $filename)
```php
public function downloadReport(string $filename)
{
    $path = storage_path("app/reports/{$filename}");

    if (!file_exists($path)) {
        abort(404, 'Report not found');
    }

    return response()->download($path)->deleteFileAfterSend();
}
```

## Error Responses

**Validation Error** (422):
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "from": ["The from field is required."],
    "to": ["The to field must be a date after or equal to from."]
  }
}
```

**Not Found** (404):
```json
{
  "message": "Report not found"
}
```

**Server Error** (500):
```json
{
  "message": "Failed to generate report",
  "error": "Error details..."
}
```

## Authentication

All endpoints require authentication using Laravel Sanctum:

```http
Authorization: Bearer {token}
```

## Rate Limiting

Report generation endpoints are rate-limited to:
- 60 requests per minute for JSON responses
- 10 requests per minute for file exports
