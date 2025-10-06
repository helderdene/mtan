# API Specification

This is the API specification for the spec detailed in @.agent-os/specs/2025-10-06-employee-self-service-portal/spec.md

## Endpoints

### GET /api/v1/employee/attendance/calendar

**Purpose:** Fetch calendar data for employee's attendance in a specific month

**Authentication:** Bearer token (Sanctum)

**Authorization:** Employee can only access their own data

**Parameters:**
- `month` (optional, query) - Month to fetch (YYYY-MM format), defaults to current month

**Response:**
```json
{
  "success": true,
  "data": {
    "month": "2025-10",
    "days": [
      {
        "date": "2025-10-01",
        "status": "present",
        "work_hours": 8.0,
        "has_violation": false,
        "has_correction": false,
        "is_holiday": false
      },
      {
        "date": "2025-10-02",
        "status": "absent",
        "work_hours": 0,
        "has_violation": true,
        "has_correction": false,
        "is_holiday": false
      }
    ],
    "summary": {
      "total_work_hours": 160.5,
      "total_overtime_hours": 12.5,
      "days_present": 20,
      "days_absent": 2,
      "days_on_leave": 1,
      "violation_count": 5
    }
  }
}
```

### GET /api/v1/employee/attendance/daily/{date}

**Purpose:** Fetch detailed attendance data for a specific date

**Authentication:** Bearer token (Sanctum)

**Parameters:**
- `date` (path) - Date in YYYY-MM-DD format

**Response:**
```json
{
  "success": true,
  "data": {
    "date": "2025-10-06",
    "status": "present",
    "shift": {
      "id": 1,
      "name": "Morning Shift",
      "start_time": "09:00:00",
      "end_time": "17:00:00",
      "expected_work_hours": 8.0
    },
    "attendance_records": [
      {
        "id": 1,
        "direction": "check-in",
        "timestamp": "2025-10-06T09:05:00Z",
        "device_name": "Main Entrance"
      },
      {
        "id": 2,
        "direction": "break-start",
        "timestamp": "2025-10-06T12:00:00Z",
        "device_name": "Main Entrance"
      },
      {
        "id": 3,
        "direction": "break-end",
        "timestamp": "2025-10-06T12:30:00Z",
        "device_name": "Main Entrance"
      },
      {
        "id": 4,
        "direction": "check-out",
        "timestamp": "2025-10-06T17:00:00Z",
        "device_name": "Main Entrance"
      }
    ],
    "summary": {
      "check_in_time": "09:05:00",
      "check_out_time": "17:00:00",
      "total_work_hours": 7.5,
      "total_break_hours": 0.5,
      "overtime_hours": 0,
      "is_late": true,
      "late_by_minutes": 5
    },
    "violations": [
      {
        "id": 123,
        "type": "late_arrival",
        "severity": "minor",
        "status": "pending",
        "metadata": { "late_by_minutes": 5 }
      }
    ],
    "corrections": []
  }
}
```

### POST /api/v1/employee/violations/{id}/acknowledge

**Purpose:** Acknowledge a violation

**Authentication:** Bearer token (Sanctum)

**Parameters:**
- `id` (path) - Violation ID

**Response:**
```json
{
  "success": true,
  "message": "Violation acknowledged successfully",
  "data": {
    "id": 123,
    "status": "acknowledged"
  }
}
```

### POST /api/v1/employee/violations/{id}/dispute

**Purpose:** Dispute a violation

**Authentication:** Bearer token (Sanctum)

**Parameters:**
- `id` (path) - Violation ID
- `reason` (body, required) - Reason for dispute (min 10 chars)

**Request:**
```json
{
  "reason": "I arrived on time but the device was offline. Security logs confirm my arrival at 8:58 AM."
}
```

**Response:**
```json
{
  "success": true,
  "message": "Violation disputed successfully",
  "data": {
    "id": 123,
    "status": "disputed",
    "dispute_reason": "I arrived on time..."
  }
}
```

**Errors:**
- `401 Unauthorized` - Invalid or missing token
- `403 Forbidden` - Violation does not belong to authenticated employee
- `404 Not Found` - Violation not found
- `422 Unprocessable Entity` - Validation errors (reason too short, already disputed, etc.)
