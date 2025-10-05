# Technical Specification

This is the technical specification for the spec detailed in @.agent-os/specs/2025-10-05-violation-detection-engine/spec.md

> Created: 2025-10-05
> Version: 1.0.0

## Technical Requirements

### AttendanceViolation Model

**Location**: `app/Domain/Attendance/Models/AttendanceViolation.php`

**Table**: `attendance_violations` (tenant database)

**Columns**:
```php
$table->id();
$table->foreignId('employee_id')->constrained()->cascadeOnDelete();
$table->foreignId('attendance_record_id')->nullable()->constrained()->nullOnDelete();
$table->foreignId('daily_summary_id')->nullable()->constrained('daily_attendance_summaries')->nullOnDelete();
$table->date('violation_date');
$table->enum('type', ['late-arrival', 'early-departure', 'missing-checkout', 'extended-break']);
$table->enum('severity', ['minor', 'moderate', 'major']);
$table->integer('minutes_deviation')->nullable()
    ->comment('Minutes late/early/over for quantifiable violations');
$table->json('metadata')->nullable()
    ->comment('Additional context (shift times, grace period, etc.)');
$table->enum('status', ['pending', 'acknowledged', 'disputed', 'resolved'])->default('pending');
$table->text('notes')->nullable();
$table->timestamps();

// Indexes
$table->index(['employee_id', 'violation_date']);
$table->index(['violation_date', 'type']);
$table->index(['status', 'severity']);
```

**Relationships**:
```php
public function employee(): BelongsTo;
public function attendanceRecord(): BelongsTo;
public function dailySummary(): BelongsTo;
```

### ViolationDetector Service

**Location**: `app/Domain/Attendance/Services/ViolationDetector.php`

**Method Signatures**:
```php
public function detectFromRecord(AttendanceRecord $record): array; // Returns array of violations

public function detectMissingCheckouts(Carbon $date): Collection; // Returns collection of violations

public function detectForDate(Employee $employee, Carbon $date): Collection;

public function calculateSeverity(string $type, int $minutesDeviation): string;
```

### Violation Detection Logic

#### 1. Late Arrival Detection

**Trigger**: On check-in event

**Logic**:
```php
public function detectLateArrival(AttendanceRecord $checkIn): ?AttendanceViolation
{
    // Only check on check-in records
    if ($checkIn->direction !== 'check-in') return null;

    // Get employee's shift for this date
    $shift = $checkIn->employee->getShiftForDate($checkIn->recorded_at);
    if (!$shift) return null;

    // Check for shift overrides (holidays, off-days)
    $override = $this->overrideService->getActiveOverride(
        $checkIn->recorded_at->toDateString(),
        $shift,
        $checkIn->employee
    );

    if ($override && in_array($override->type, ['holiday', 'off-day'])) {
        return null; // No violation on non-working days
    }

    // Get effective shift times (accounting for half-day overrides)
    $effectiveShift = $this->overrideService->getEffectiveShiftTimes(
        $checkIn->recorded_at->toDateString(),
        $shift,
        $checkIn->employee
    );

    // Get grace period from tenant settings (default 15 minutes)
    $gracePeriod = $checkIn->employee->tenant->settings['late_arrival_grace_minutes'] ?? 15;

    // Calculate allowed check-in time
    $allowedCheckIn = $effectiveShift->startTime->copy()->addMinutes($gracePeriod);

    // Check if late
    if ($checkIn->recorded_at->gt($allowedCheckIn)) {
        $minutesLate = $effectiveShift->startTime->diffInMinutes($checkIn->recorded_at);

        return AttendanceViolation::create([
            'employee_id' => $checkIn->employee_id,
            'attendance_record_id' => $checkIn->id,
            'violation_date' => $checkIn->recorded_at->toDateString(),
            'type' => 'late-arrival',
            'severity' => $this->calculateSeverity('late-arrival', $minutesLate),
            'minutes_deviation' => $minutesLate,
            'metadata' => [
                'shift_start' => $effectiveShift->startTime->format('H:i:s'),
                'actual_check_in' => $checkIn->recorded_at->format('H:i:s'),
                'grace_period' => $gracePeriod,
            ],
        ]);
    }

    return null;
}
```

#### 2. Early Departure Detection

**Trigger**: On check-out event

**Logic**:
```php
public function detectEarlyDeparture(AttendanceRecord $checkOut): ?AttendanceViolation
{
    // Only check on check-out records
    if ($checkOut->direction !== 'check-out') return null;

    // Get shift and check for overrides (same as late arrival)
    // ...

    // Get grace period (default 15 minutes)
    $gracePeriod = $checkOut->employee->tenant->settings['early_departure_grace_minutes'] ?? 15;

    // Calculate allowed check-out time
    $allowedCheckOut = $effectiveShift->endTime->copy()->subMinutes($gracePeriod);

    // Check if early
    if ($checkOut->recorded_at->lt($allowedCheckOut)) {
        $minutesEarly = $checkOut->recorded_at->diffInMinutes($effectiveShift->endTime);

        return AttendanceViolation::create([
            'employee_id' => $checkOut->employee_id,
            'attendance_record_id' => $checkOut->id,
            'violation_date' => $checkOut->recorded_at->toDateString(),
            'type' => 'early-departure',
            'severity' => $this->calculateSeverity('early-departure', $minutesEarly),
            'minutes_deviation' => $minutesEarly,
            'metadata' => [
                'shift_end' => $effectiveShift->endTime->format('H:i:s'),
                'actual_check_out' => $checkOut->recorded_at->format('H:i:s'),
                'grace_period' => $gracePeriod,
            ],
        ]);
    }

    return null;
}
```

#### 3. Extended Break Detection

**Trigger**: On break-end event

**Logic**:
```php
public function detectExtendedBreak(AttendanceRecord $breakEnd): ?AttendanceViolation
{
    // Only check on break-end records
    if ($breakEnd->direction !== 'break-end') return null;

    // Find corresponding break-start
    $breakStart = AttendanceRecord::where('employee_id', $breakEnd->employee_id)
        ->whereDate('recorded_at', $breakEnd->recorded_at)
        ->where('direction', 'break-start')
        ->where('recorded_at', '<', $breakEnd->recorded_at)
        ->orderBy('recorded_at', 'desc')
        ->first();

    if (!$breakStart) return null;

    // Calculate break duration
    $breakMinutes = $breakStart->recorded_at->diffInMinutes($breakEnd->recorded_at);

    // Get maximum allowed break (default 120 minutes = 2 hours)
    $maxBreakMinutes = $breakEnd->employee->tenant->settings['max_break_minutes'] ?? 120;

    // Check if extended
    if ($breakMinutes > $maxBreakMinutes) {
        $minutesOver = $breakMinutes - $maxBreakMinutes;

        return AttendanceViolation::create([
            'employee_id' => $breakEnd->employee_id,
            'attendance_record_id' => $breakEnd->id,
            'violation_date' => $breakEnd->recorded_at->toDateString(),
            'type' => 'extended-break',
            'severity' => $this->calculateSeverity('extended-break', $minutesOver),
            'minutes_deviation' => $minutesOver,
            'metadata' => [
                'break_start' => $breakStart->recorded_at->format('H:i:s'),
                'break_end' => $breakEnd->recorded_at->format('H:i:s'),
                'break_duration' => $breakMinutes,
                'max_allowed' => $maxBreakMinutes,
            ],
        ]);
    }

    return null;
}
```

#### 4. Missing Checkout Detection

**Trigger**: Scheduled daily (e.g., midnight or 2 AM)

**Logic**:
```php
public function detectMissingCheckouts(Carbon $date): Collection
{
    // Find all employees with check-in but no check-out for the date
    $violations = collect();

    $incompleteRecords = AttendanceRecord::select('employee_id')
        ->whereDate('recorded_at', $date)
        ->where('direction', 'check-in')
        ->whereNotExists(function ($query) use ($date) {
            $query->select(DB::raw(1))
                ->from('attendance_records as ar2')
                ->whereColumn('ar2.employee_id', 'attendance_records.employee_id')
                ->whereDate('ar2.recorded_at', $date)
                ->where('ar2.direction', 'check-out');
        })
        ->groupBy('employee_id')
        ->get();

    foreach ($incompleteRecords as $record) {
        $employee = Employee::find($record->employee_id);

        // Check if it's a working day
        $shift = $employee->getShiftForDate($date);
        $override = $this->overrideService->getActiveOverride($date, $shift, $employee);

        if ($override && in_array($override->type, ['holiday', 'off-day'])) {
            continue; // Skip non-working days
        }

        $violation = AttendanceViolation::create([
            'employee_id' => $employee->id,
            'violation_date' => $date->toDateString(),
            'type' => 'missing-checkout',
            'severity' => 'moderate', // Always moderate
            'metadata' => [
                'last_check_in' => AttendanceRecord::where('employee_id', $employee->id)
                    ->whereDate('recorded_at', $date)
                    ->where('direction', 'check-in')
                    ->latest('recorded_at')
                    ->value('recorded_at'),
            ],
        ]);

        $violations->push($violation);
    }

    return $violations;
}
```

### Severity Calculation

```php
public function calculateSeverity(string $type, int $minutesDeviation): string
{
    switch ($type) {
        case 'late-arrival':
        case 'early-departure':
            if ($minutesDeviation <= 30) return 'minor';      // <= 30 min
            if ($minutesDeviation <= 60) return 'moderate';   // 31-60 min
            return 'major';                                    // > 60 min

        case 'extended-break':
            if ($minutesDeviation <= 15) return 'minor';      // <= 15 min over
            if ($minutesDeviation <= 30) return 'moderate';   // 16-30 min over
            return 'major';                                    // > 30 min over

        case 'missing-checkout':
            return 'moderate'; // Always moderate

        default:
            return 'moderate';
    }
}
```

### Integration with ProcessAttendanceEvent

**Update**: `app/Jobs/ProcessAttendanceEvent.php`

```php
public function handle()
{
    // ... existing direction detection and record creation ...

    $record = AttendanceRecord::create([...]);

    // Update daily summary
    $this->summaryCalculator->updateSummaryFromEvent($record);

    // NEW: Detect violations
    $violations = $this->violationDetector->detectFromRecord($record);

    // NEW: Trigger notifications for violations
    foreach ($violations as $violation) {
        event(new ViolationDetected($violation));
    }
}
```

### Scheduled Command

**Location**: `app/Console/Commands/DetectMissingCheckoutsCommand.php`

**Signature**: `attendance:detect-missing-checkouts {--date=}`

**Schedule** (in `app/Console/Kernel.php`):
```php
$schedule->command('attendance:detect-missing-checkouts')
    ->dailyAt('02:00'); // Run at 2 AM
```

### Tenant Settings

**Add to tenant settings** (stored in `tenants` table or `tenant_settings` table):

```php
[
    'late_arrival_grace_minutes' => 15,      // Default grace period for late arrival
    'early_departure_grace_minutes' => 15,   // Default grace period for early departure
    'max_break_minutes' => 120,              // Maximum break duration (2 hours)
]
```

### API Endpoints

**Routes**: `routes/api.php`

```php
GET /api/violations
    ?employee_id=1
    &date=2025-10-05
    &from=2025-10-01
    &to=2025-10-31
    &type=late-arrival|early-departure|missing-checkout|extended-break
    &severity=minor|moderate|major
    &status=pending|acknowledged|disputed|resolved

GET /api/employees/{employee}/violations
    ?month=2025-10
    &type=late-arrival

POST /api/violations/{id}/acknowledge
    { "notes": "Discussed with employee" }

POST /api/violations/{id}/dispute
    { "notes": "Traffic accident on highway" }
```

**Controller**: `app/Http/Controllers/Api/ViolationController.php`

### Performance Requirements

- Violation detection must complete in < 100ms per record
- Missing checkout detection must process all employees in < 5 minutes
- Use database indexes for efficient querying
- Cache tenant settings to avoid repeated database queries

### Testing Requirements

**Unit Tests** (`tests/Unit/ViolationDetectorTest.php`):
- Test late arrival detection with various grace periods
- Test early departure detection
- Test extended break detection
- Test missing checkout detection query
- Test severity calculation for all violation types
- Test grace period edge cases (exactly at threshold)

**Feature Tests** (`tests/Feature/ViolationDetectionTest.php`):
- Test violation creation from real attendance events
- Test integration with shift overrides (no violation on holidays)
- Test scheduled command execution
- Test API endpoint filtering and pagination
- Test notification triggering on violation creation

## Approach

1. **Phase 1: Foundation**
   - Create `AttendanceViolation` model and migration
   - Implement `ViolationDetector` service with basic detection logic
   - Add tenant settings for grace periods

2. **Phase 2: Real-time Detection**
   - Integrate violation detection into `ProcessAttendanceEvent` job
   - Implement late arrival and early departure detection
   - Implement extended break detection

3. **Phase 3: Scheduled Detection**
   - Create `DetectMissingCheckoutsCommand`
   - Add to Laravel scheduler
   - Test with historical data

4. **Phase 4: API and Testing**
   - Build API endpoints for violation queries
   - Write comprehensive unit and feature tests
   - Performance optimization and indexing

## External Dependencies

None - uses existing Laravel functionality
