# API Specification

This is the API specification for the spec detailed in @.agent-os/specs/2025-10-06-advanced-shift-management/spec.md

## Endpoints

### POST /api/v1/shift-rotation-patterns

**Purpose:** Create a new shift rotation pattern

**Authentication:** Bearer token (Sanctum)

**Authorization:** Admin or HR role required

**Request:**
```json
{
  "name": "3-Week Rotation - Morning/Afternoon/Night",
  "cycle_type": "weekly",
  "rotation_sequence": [1, 2, 3],
  "description": "Standard 3-week rotation for production staff",
  "is_active": true
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "3-Week Rotation - Morning/Afternoon/Night",
    "cycle_type": "weekly",
    "rotation_sequence": [1, 2, 3],
    "description": "Standard 3-week rotation for production staff",
    "is_active": true,
    "shifts": [
      {"id": 1, "name": "Morning Shift", "start_time": "06:00:00", "end_time": "14:00:00"},
      {"id": 2, "name": "Afternoon Shift", "start_time": "14:00:00", "end_time": "22:00:00"},
      {"id": 3, "name": "Night Shift", "start_time": "22:00:00", "end_time": "06:00:00"}
    ]
  }
}
```

### POST /api/v1/employees/{employee}/assign-rotation

**Purpose:** Assign employee to rotation pattern

**Authentication:** Bearer token (Sanctum)

**Authorization:** Admin or HR role required

**Request:**
```json
{
  "rotation_pattern_id": 1,
  "start_date": "2025-10-07"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Employee assigned to rotation successfully",
  "data": {
    "id": 1,
    "employee_id": 123,
    "rotation_pattern_id": 1,
    "start_date": "2025-10-07",
    "current_position": 0,
    "current_shift": {
      "id": 1,
      "name": "Morning Shift",
      "start_time": "06:00:00",
      "end_time": "14:00:00"
    },
    "next_rotation_date": "2025-10-14"
  }
}
```

### GET /api/v1/employees/{employee}/shift-schedule

**Purpose:** Get employee's shift schedule for next 30 days

**Authentication:** Bearer token (Sanctum)

**Authorization:** Employee can view own schedule, managers can view team schedules

**Parameters:**
- `days` (optional, query) - Number of days to fetch (default: 30, max: 90)

**Response:**
```json
{
  "success": true,
  "data": {
    "employee_id": 123,
    "schedule": [
      {
        "date": "2025-10-06",
        "shift": {
          "id": 1,
          "name": "Morning Shift",
          "start_time": "06:00:00",
          "end_time": "14:00:00",
          "shift_type": "fixed"
        },
        "rotation_info": {
          "pattern_name": "3-Week Rotation",
          "cycle_position": "Week 1 of 3",
          "next_rotation_date": "2025-10-14"
        }
      },
      {
        "date": "2025-10-07",
        "shift": {
          "id": 1,
          "name": "Morning Shift",
          "start_time": "06:00:00",
          "end_time": "14:00:00",
          "shift_type": "fixed"
        },
        "rotation_info": null,
        "is_override": false
      }
    ]
  }
}
```

### POST /api/v1/shifts

**Purpose:** Create a new shift (with flexible shift support)

**Authentication:** Bearer token (Sanctum)

**Request (Flexible Shift):**
```json
{
  "name": "Flexible Day Shift",
  "shift_type": "flexible",
  "flexible_checkin_start": "08:00:00",
  "flexible_checkin_end": "10:00:00",
  "end_time": "17:00:00",
  "core_hours_required": 7.5,
  "description": "Flexible shift with 2-hour check-in window"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 10,
    "name": "Flexible Day Shift",
    "shift_type": "flexible",
    "start_time": null,
    "end_time": "17:00:00",
    "is_overnight": false,
    "flexible_checkin_start": "08:00:00",
    "flexible_checkin_end": "10:00:00",
    "core_hours_required": 7.5
  }
}
```

**Errors:**
- `422 Unprocessable Entity` - Validation errors (invalid cycle_type, shift_ids not found, etc.)
- `403 Forbidden` - User does not have admin/HR permissions
