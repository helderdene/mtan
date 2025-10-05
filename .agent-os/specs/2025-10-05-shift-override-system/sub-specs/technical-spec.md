# Technical Specification

This is the technical specification for the spec detailed in @.agent-os/specs/2025-10-05-shift-override-system/spec.md

> Created: 2025-10-05
> Version: 1.0.0

## Technical Requirements

### ShiftOverride Model

**Location**: `app/Domain/Shift/Models/ShiftOverride.php`

**Table**: `shift_overrides` (tenant database)

**Columns**:
```php
$table->id();
$table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
$table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
$table->date('override_date');
$table->enum('type', ['holiday', 'off-day', 'half-day', 'custom-shift']);
$table->time('custom_start_time')->nullable();
$table->time('custom_end_time')->nullable();
$table->string('reason')->nullable();
$table->timestamps();

// Indexes
$table->index(['override_date', 'shift_id']);
$table->index(['override_date', 'employee_id']);
```

**Relationships**:
```php
public function shift(): BelongsTo; // The shift this override applies to
public function employee(): BelongsTo; // Null = company-wide, else specific employee
```

**Override Types**:
- `holiday`: No work required, no violations
- `off-day`: Specific employee off, no violations
- `half-day`: Modified shift times (custom_start_time, custom_end_time required)
- `custom-shift`: Temporary shift time modification

**Validation Rules**:
- `override_date`: Required, date
- `type`: Required, one of enum values
- `shift_id`: Nullable for employee-specific off-days
- `employee_id`: Nullable for company-wide overrides
- `custom_start_time` & `custom_end_time`: Required if type is 'half-day' or 'custom-shift'
- Cannot have duplicate overrides for same date + shift + employee combination

### OverrideService

**Location**: `app/Domain/Shift/Services/OverrideService.php`

**Method Signatures**:
```php
public function getActiveOverride(
    Carbon $date,
    ?Shift $shift,
    ?Employee $employee
): ?ShiftOverride;

public function isWorkRequired(Carbon $date, Employee $employee): bool;

public function getEffectiveShiftTimes(
    Carbon $date,
    Shift $shift,
    ?Employee $employee
): ?EffectiveShift; // Returns null if no work required
```

**EffectiveShift DTO**:
```php
class EffectiveShift
{
    public Carbon $startTime;
    public Carbon $endTime;
    public bool $isModified; // True if override applied
    public ?ShiftOverride $override; // The override if applicable
}
```

**Override Resolution Logic**:
```php
// Priority order (highest to lowest):
// 1. Employee-specific override for this shift
// 2. Employee-specific off-day (no shift specified)
// 3. Company-wide override for this shift
// 4. No override (use regular shift)

public function getActiveOverride($date, $shift, $employee): ?ShiftOverride
{
    // Check employee-specific shift override
    $override = ShiftOverride::where('override_date', $date)
        ->where('shift_id', $shift?->id)
        ->where('employee_id', $employee?->id)
        ->first();

    if ($override) return $override;

    // Check employee-specific off-day
    $override = ShiftOverride::where('override_date', $date)
        ->where('employee_id', $employee?->id)
        ->whereNull('shift_id')
        ->first();

    if ($override) return $override;

    // Check company-wide shift override
    $override = ShiftOverride::where('override_date', $date)
        ->where('shift_id', $shift?->id)
        ->whereNull('employee_id')
        ->first();

    return $override;
}
```

### Integration with DirectionDetector

**Update**: `app/Domain/Attendance/Services/DirectionDetector.php`

```php
public function detect(...): DirectionResult
{
    // NEW: Check for shift overrides
    $override = $this->overrideService->getActiveOverride(
        $timestamp->toDateString(),
        $shift,
        $employee
    );

    // If holiday or off-day, should not receive attendance events
    // Log warning and return low-confidence result
    if ($override && in_array($override->type, ['holiday', 'off-day'])) {
        Log::warning("Attendance event on {$override->type}", [
            'employee' => $employee->id,
            'date' => $timestamp->toDateString()
        ]);
    }

    // If half-day or custom-shift, use override times
    if ($override && in_array($override->type, ['half-day', 'custom-shift'])) {
        $shift = $this->createTemporaryShift(
            $override->custom_start_time,
            $override->custom_end_time
        );
    }

    // Continue with normal direction detection using effective shift
    // ...
}
```

### Integration with Violation Detection

**Update**: Violation detection logic to check overrides before flagging violations

```php
public function checkViolations(Employee $employee, Carbon $date): array
{
    // Check if work is required on this date
    if (!$this->overrideService->isWorkRequired($date, $employee)) {
        // No violations on holidays/off days
        return [];
    }

    // Get effective shift times (accounting for half-day/custom overrides)
    $effectiveShift = $this->overrideService->getEffectiveShiftTimes(
        $date,
        $employee->currentShift(),
        $employee
    );

    // Continue with violation detection using effective shift times
    // ...
}
```

### API Endpoints

**Routes**: `routes/api.php` or `routes/web.php`

```php
// Shift Override Management
GET    /api/shift-overrides                 // List all overrides
POST   /api/shift-overrides                 // Create new override
GET    /api/shift-overrides/{id}            // Get override details
PUT    /api/shift-overrides/{id}            // Update override
DELETE /api/shift-overrides/{id}            // Delete override

// Query Parameters for listing:
// ?date=2025-10-05 (filter by date)
// ?shift_id=1 (filter by shift)
// ?employee_id=1 (filter by employee)
// ?type=holiday (filter by type)
```

**Controller**: `app/Http/Controllers/Api/ShiftOverrideController.php`

### Caching Strategy

**Cache Key**: `tenant:{$tenantId}:override:{$date}:{$shiftId}:{$employeeId}`

**Cache Duration**: 24 hours

**Cache Invalidation**:
- Invalidate when override is created, updated, or deleted
- Invalidate all overrides for a date: `tenant:{$tenantId}:override:{$date}:*`

### Testing Requirements

**Unit Tests** (`tests/Unit/OverrideServiceTest.php`):
- Test override resolution priority (employee-specific > company-wide)
- Test isWorkRequired for different override types
- Test getEffectiveShiftTimes for half-day and custom shifts
- Test edge cases (null shift, null employee)

**Feature Tests** (`tests/Feature/ShiftOverrideTest.php`):
- Test CRUD operations via API endpoints
- Test integration with direction detection
- Test integration with violation detection
- Test company-wide holiday prevents violations
- Test employee-specific off-day

## Approach

1. **Phase 1: Database & Model** - Create migration and ShiftOverride model with relationships
2. **Phase 2: Service Layer** - Implement OverrideService with override resolution logic
3. **Phase 3: Integration** - Update DirectionDetector and violation detection to use OverrideService
4. **Phase 4: API & UI** - Build CRUD endpoints and management interface
5. **Phase 5: Testing** - Comprehensive test coverage for all scenarios

## External Dependencies

None - uses existing Laravel functionality
