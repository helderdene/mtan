# API Specification

This is the API specification for the spec detailed in @.agent-os/specs/2025-10-04-shift-break-validation/spec.md

> Created: 2025-10-04
> Version: 1.0.0

## Endpoints

### POST /api/v1/shifts

Creates a new shift with optional break times.

**Authentication**: Required (Bearer token)

**Request Headers**:
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

**Request Body**:
```json
{
  "name": "Morning Shift",
  "start_time": "09:00:00",
  "end_time": "17:00:00",
  "break_start": "12:00:00",
  "break_end": "13:00:00",
  "grace_period_minutes": 15,
  "type": "fixed"
}
```

**Request Schema**:
| Field | Type | Required | Constraints | Description |
|-------|------|----------|-------------|-------------|
| name | string | Yes | max:255 | Shift name |
| start_time | string | Yes | format: H:i:s | Shift start time (24-hour format) |
| end_time | string | Yes | format: H:i:s | Shift end time (24-hour format) |
| break_start | string | No | format: H:i:s, required_with: break_end | Break start time |
| break_end | string | No | format: H:i:s, required_with: break_start | Break end time |
| grace_period_minutes | integer | No | min:0, max:60 | Grace period for late check-in |
| type | string | Yes | in: fixed, rotating, flexible | Shift type |

**Break Validation Rules**:
1. If `break_start` is provided, `break_end` must also be provided (and vice versa)
2. `break_start` must be before `break_end`
3. Break duration must be at least 1 minute
4. Break duration must not exceed 2 hours
5. Both `break_start` and `break_end` must fall within `start_time` and `end_time`
6. For overnight shifts (end_time < start_time), breaks cannot span across midnight

**Success Response (201 Created)**:
```json
{
  "data": {
    "id": 1,
    "name": "Morning Shift",
    "start_time": "09:00:00",
    "end_time": "17:00:00",
    "break_start": "12:00:00",
    "break_end": "13:00:00",
    "grace_period_minutes": 15,
    "type": "fixed",
    "created_at": "2025-10-04T10:00:00.000000Z",
    "updated_at": "2025-10-04T10:00:00.000000Z"
  }
}
```

**Error Response (422 Unprocessable Entity) - Invalid Break Times**:
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "break_start": [
      "Break times must fall within shift working hours (09:00:00 - 17:00:00)."
    ]
  }
}
```

**Error Response (422 Unprocessable Entity) - Missing Break End**:
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "break_end": [
      "Both break start and end times are required. Please provide both or leave both empty."
    ]
  }
}
```

**Error Response (422 Unprocessable Entity) - Invalid Break Order**:
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "break_start": [
      "Break start time must be before break end time."
    ]
  }
}
```

**Error Response (422 Unprocessable Entity) - Invalid Break Duration**:
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "break_end": [
      "Break duration cannot exceed 2 hours. Please verify your break times."
    ]
  }
}
```

**Error Response (401 Unauthorized)**:
```json
{
  "message": "Unauthenticated."
}
```

---

### PUT /api/v1/shifts/{id}

Updates an existing shift with optional break times.

**Authentication**: Required (Bearer token)

**Request Headers**:
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

**URL Parameters**:
| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | Shift ID to update |

**Request Body**:
```json
{
  "name": "Afternoon Shift",
  "start_time": "13:00:00",
  "end_time": "21:00:00",
  "break_start": "17:00:00",
  "break_end": "17:30:00",
  "grace_period_minutes": 10,
  "type": "fixed"
}
```

**Request Schema**: Same as POST /api/v1/shifts

**Break Validation Rules**: Same as POST /api/v1/shifts

**Success Response (200 OK)**:
```json
{
  "data": {
    "id": 1,
    "name": "Afternoon Shift",
    "start_time": "13:00:00",
    "end_time": "21:00:00",
    "break_start": "17:00:00",
    "break_end": "17:30:00",
    "grace_period_minutes": 10,
    "type": "fixed",
    "created_at": "2025-10-04T10:00:00.000000Z",
    "updated_at": "2025-10-04T11:30:00.000000Z"
  }
}
```

**Error Response (422 Unprocessable Entity)**: Same validation errors as POST endpoint

**Error Response (404 Not Found)**:
```json
{
  "message": "Shift not found."
}
```

**Error Response (401 Unauthorized)**:
```json
{
  "message": "Unauthenticated."
}
```

---

### GET /api/v1/shifts/{id}

Retrieves a single shift including break time information.

**Authentication**: Required (Bearer token)

**Request Headers**:
```
Authorization: Bearer {token}
Accept: application/json
```

**URL Parameters**:
| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | Shift ID to retrieve |

**Success Response (200 OK)**:
```json
{
  "data": {
    "id": 1,
    "name": "Morning Shift",
    "start_time": "09:00:00",
    "end_time": "17:00:00",
    "break_start": "12:00:00",
    "break_end": "13:00:00",
    "break_duration_minutes": 60,
    "grace_period_minutes": 15,
    "type": "fixed",
    "is_overnight": false,
    "created_at": "2025-10-04T10:00:00.000000Z",
    "updated_at": "2025-10-04T10:00:00.000000Z"
  }
}
```

**Response Schema**:
| Field | Type | Description |
|-------|------|-------------|
| id | integer | Shift ID |
| name | string | Shift name |
| start_time | string | Shift start time (H:i:s format) |
| end_time | string | Shift end time (H:i:s format) |
| break_start | string\|null | Break start time (H:i:s format) |
| break_end | string\|null | Break end time (H:i:s format) |
| break_duration_minutes | integer\|null | Calculated break duration in minutes |
| grace_period_minutes | integer | Grace period for late check-in |
| type | string | Shift type (fixed, rotating, flexible) |
| is_overnight | boolean | Whether shift spans across midnight |
| created_at | string | ISO 8601 timestamp |
| updated_at | string | ISO 8601 timestamp |

**Error Response (404 Not Found)**:
```json
{
  "message": "Shift not found."
}
```

**Error Response (401 Unauthorized)**:
```json
{
  "message": "Unauthenticated."
}
```

---

### GET /api/v1/shifts

Retrieves a list of all shifts with break time information.

**Authentication**: Required (Bearer token)

**Request Headers**:
```
Authorization: Bearer {token}
Accept: application/json
```

**Query Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| per_page | integer | No | Number of results per page (default: 15, max: 100) |
| page | integer | No | Page number (default: 1) |
| type | string | No | Filter by shift type (fixed, rotating, flexible) |
| has_break | boolean | No | Filter shifts with/without breaks (true/false) |

**Success Response (200 OK)**:
```json
{
  "data": [
    {
      "id": 1,
      "name": "Morning Shift",
      "start_time": "09:00:00",
      "end_time": "17:00:00",
      "break_start": "12:00:00",
      "break_end": "13:00:00",
      "break_duration_minutes": 60,
      "grace_period_minutes": 15,
      "type": "fixed",
      "is_overnight": false,
      "created_at": "2025-10-04T10:00:00.000000Z",
      "updated_at": "2025-10-04T10:00:00.000000Z"
    },
    {
      "id": 2,
      "name": "Night Shift",
      "start_time": "22:00:00",
      "end_time": "06:00:00",
      "break_start": "02:00:00",
      "break_end": "02:30:00",
      "break_duration_minutes": 30,
      "grace_period_minutes": 15,
      "type": "fixed",
      "is_overnight": true,
      "created_at": "2025-10-04T10:00:00.000000Z",
      "updated_at": "2025-10-04T10:00:00.000000Z"
    }
  ],
  "links": {
    "first": "http://localhost/api/v1/shifts?page=1",
    "last": "http://localhost/api/v1/shifts?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "per_page": 15,
    "to": 2,
    "total": 2
  }
}
```

**Error Response (401 Unauthorized)**:
```json
{
  "message": "Unauthenticated."
}
```

---

### DELETE /api/v1/shifts/{id}

Deletes an existing shift.

**Authentication**: Required (Bearer token)

**Request Headers**:
```
Authorization: Bearer {token}
Accept: application/json
```

**URL Parameters**:
| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | Shift ID to delete |

**Success Response (204 No Content)**:
```
(empty response body)
```

**Error Response (404 Not Found)**:
```json
{
  "message": "Shift not found."
}
```

**Error Response (409 Conflict) - Shift in Use**:
```json
{
  "message": "Cannot delete shift. It is currently assigned to employees."
}
```

**Error Response (401 Unauthorized)**:
```json
{
  "message": "Unauthenticated."
}
```

## Controllers

### ShiftController

**Namespace**: `App\Http\Controllers\Api\V1\ShiftController`

**Methods**:

#### index()
- **Purpose**: List all shifts with pagination and filtering
- **Request**: GET /api/v1/shifts
- **Authorization**: Authenticated user
- **Response**: Paginated collection of shifts

#### store(StoreShiftRequest $request)
- **Purpose**: Create a new shift
- **Request**: POST /api/v1/shifts
- **Authorization**: Authenticated user with shift creation permission
- **Validation**: StoreShiftRequest (includes break time validation)
- **Response**: 201 Created with shift resource

#### show(Shift $shift)
- **Purpose**: Retrieve a single shift
- **Request**: GET /api/v1/shifts/{id}
- **Authorization**: Authenticated user
- **Response**: 200 OK with shift resource

#### update(UpdateShiftRequest $request, Shift $shift)
- **Purpose**: Update an existing shift
- **Request**: PUT /api/v1/shifts/{id}
- **Authorization**: Authenticated user with shift update permission
- **Validation**: UpdateShiftRequest (includes break time validation)
- **Response**: 200 OK with updated shift resource

#### destroy(Shift $shift)
- **Purpose**: Delete a shift
- **Request**: DELETE /api/v1/shifts/{id}
- **Authorization**: Authenticated user with shift deletion permission
- **Business Logic**: Check if shift is assigned to employees before deletion
- **Response**: 204 No Content

### Request Classes

#### StoreShiftRequest

**Namespace**: `App\Http\Request\StoreShiftRequest`

**Validation Rules**:
```php
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'start_time' => ['required', 'date_format:H:i:s'],
        'end_time' => ['required', 'date_format:H:i:s'],
        'break_start' => [
            'nullable',
            'date_format:H:i:s',
            'required_with:break_end',
            'before:break_end',
            new BreakWithinShiftHours($this->input('start_time'), $this->input('end_time')),
        ],
        'break_end' => [
            'nullable',
            'date_format:H:i:s',
            'required_with:break_start',
            'after:break_start',
            new BreakDurationValid($this->input('break_start')),
            new BreakWithinShiftHours($this->input('start_time'), $this->input('end_time')),
        ],
        'grace_period_minutes' => ['nullable', 'integer', 'min:0', 'max:60'],
        'type' => ['required', 'string', 'in:fixed,rotating,flexible'],
    ];
}
```

**Custom Error Messages**: See technical-spec.md for detailed error messages

#### UpdateShiftRequest

**Namespace**: `App\Http\Request\UpdateShiftRequest`

**Validation Rules**: Identical to StoreShiftRequest

**Additional Logic**: May include authorization check to ensure user can update shifts

### API Resource Classes

#### ShiftResource

**Namespace**: `App\Http\Resources\ShiftResource`

**Purpose**: Transform Shift model to API response format

**Implementation**:
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class ShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'break_start' => $this->break_start,
            'break_end' => $this->break_end,
            'break_duration_minutes' => $this->getBreakDurationMinutes(),
            'grace_period_minutes' => $this->grace_period_minutes,
            'type' => $this->type,
            'is_overnight' => $this->isOvernightShift(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }

    /**
     * Calculate break duration in minutes
     */
    protected function getBreakDurationMinutes(): ?int
    {
        if (!$this->break_start || !$this->break_end) {
            return null;
        }

        $start = Carbon::createFromFormat('H:i:s', $this->break_start);
        $end = Carbon::createFromFormat('H:i:s', $this->break_end);

        return $end->diffInMinutes($start);
    }

    /**
     * Check if shift is overnight (spans midnight)
     */
    protected function isOvernightShift(): bool
    {
        return $this->end_time < $this->start_time;
    }
}
```

## API Error Handling

### Standard Error Response Format

All API errors follow Laravel's standard validation error format:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": [
      "Error message 1",
      "Error message 2"
    ]
  }
}
```

### HTTP Status Codes

| Status Code | Description | Use Case |
|-------------|-------------|----------|
| 200 OK | Success | GET, PUT requests |
| 201 Created | Resource created | POST requests |
| 204 No Content | Success with no response body | DELETE requests |
| 401 Unauthorized | Authentication required | Missing/invalid token |
| 403 Forbidden | Permission denied | Insufficient permissions |
| 404 Not Found | Resource not found | Invalid shift ID |
| 409 Conflict | Business logic conflict | Deleting assigned shift |
| 422 Unprocessable Entity | Validation failed | Invalid break times |
| 500 Internal Server Error | Server error | Unexpected exceptions |

### Break Validation Error Examples

**Example 1: Partial Break Configuration**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "break_end": [
      "Both break start and end times are required. Please provide both or leave both empty."
    ]
  }
}
```

**Example 2: Break Outside Shift Hours**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "break_start": [
      "Break times must fall within shift working hours (09:00:00 - 17:00:00)."
    ]
  }
}
```

**Example 3: Invalid Break Duration**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "break_end": [
      "Break duration must be at least 1 minute."
    ]
  }
}
```

**Example 4: Multiple Validation Errors**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "break_start": [
      "Break start time must be before break end time."
    ],
    "break_end": [
      "Break duration cannot exceed 2 hours. Please verify your break times."
    ]
  }
}
```

## Rate Limiting

All API endpoints are subject to rate limiting:

- **Authenticated Requests**: 60 requests per minute per user
- **Rate Limit Headers**:
  - `X-RateLimit-Limit`: Maximum requests allowed
  - `X-RateLimit-Remaining`: Remaining requests in window
  - `Retry-After`: Seconds until rate limit resets (on 429 response)

**Rate Limit Exceeded Response (429 Too Many Requests)**:
```json
{
  "message": "Too many requests. Please try again later."
}
```

## API Versioning

- **Current Version**: v1
- **Base URL**: `/api/v1`
- **Future Versions**: Breaking changes will be introduced in v2, v3, etc.
- **Deprecation Policy**: v1 endpoints will be supported for at least 12 months after v2 release

## Authentication

All endpoints require authentication via Laravel Sanctum:

**Request Header**:
```
Authorization: Bearer {token}
```

**Obtaining Token**: See authentication API documentation (out of scope for this spec)

## CORS Configuration

API endpoints support CORS for web applications:

- **Allowed Origins**: Configurable per tenant subdomain
- **Allowed Methods**: GET, POST, PUT, DELETE, OPTIONS
- **Allowed Headers**: Content-Type, Authorization, Accept, X-Requested-With
- **Max Age**: 86400 seconds (24 hours)
