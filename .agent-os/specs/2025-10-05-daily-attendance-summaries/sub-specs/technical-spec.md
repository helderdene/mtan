# Technical Specification

This is the technical specification for the spec detailed in @.agent-os/specs/2025-10-05-daily-attendance-summaries/spec.md

> Created: 2025-10-05
> Version: 1.0.0

## Technical Requirements

### DailyAttendanceSummary Model

**Location**: `app/Domain/Attendance/Models/DailyAttendanceSummary.php`

**Table**: `daily_attendance_summaries` (tenant database)

**Columns**:
```php
$table->id();
$table->foreignId('employee_id')->constrained()->cascadeOnDelete();
$table->date('date');
$table->time('first_check_in')->nullable();
$table->time('last_check_out')->nullable();
$table->integer('total_work_minutes')->default(0);
$table->integer('total_break_minutes')->default(0);
$table->integer('overtime_minutes')->default(0);
$table->enum('status', ['present', 'absent', 'half-day', 'on-leave', 'holiday']);
$table->boolean('is_complete')->default(false)
    ->comment('True if employee has checked out for the day');
$table->timestamps();

// Indexes
$table->unique(['employee_id', 'date']);
$table->index('date');
$table->index(['date', 'status']);
```

**Relationships**:
```php
public function employee(): BelongsTo;
public function attendanceRecords(): HasMany; // All records for this day
```

**Accessor/Mutators**:
```php
// Convert minutes to hours for display
public function getTotalWorkHoursAttribute(): float {
    return round($this->total_work_minutes / 60, 2);
}

public function getTotalBreakHoursAttribute(): float {
    return round($this->total_break_minutes / 60, 2);
}

public function getOvertimeHoursAttribute(): float {
    return round($this->overtime_minutes / 60, 2);
}
```

### SummaryCalculator Service

**Location**: `app/Domain/Attendance/Services/SummaryCalculator.php`

**Method Signatures**:
```php
public function calculateForDate(Employee $employee, Carbon $date): DailyAttendanceSummary;

public function recalculateRange(Employee $employee, Carbon $startDate, Carbon $endDate): int;

public function updateSummaryFromEvent(AttendanceRecord $record): DailyAttendanceSummary;
```

### Calculation Algorithm

**Work Hours Calculation**:
```php
// Get all records for the day
$records = AttendanceRecord::where('employee_id', $employee->id)
    ->whereDate('recorded_at', $date)
    ->orderBy('recorded_at')
    ->get();

// Calculate work time
$totalWorkMinutes = 0;
$totalBreakMinutes = 0;
$checkInTime = null;

foreach ($records as $record) {
    switch ($record->direction) {
        case 'check-in':
            $checkInTime = $record->recorded_at;
            break;

        case 'check-out':
            if ($checkInTime) {
                $totalWorkMinutes += $checkInTime->diffInMinutes($record->recorded_at);
                $checkInTime = null;
            }
            break;

        case 'break-start':
            // Work time ends when break starts
            if ($checkInTime) {
                $totalWorkMinutes += $checkInTime->diffInMinutes($record->recorded_at);
                $checkInTime = null;
            }
            $breakStartTime = $record->recorded_at;
            break;

        case 'break-end':
            if ($breakStartTime) {
                $totalBreakMinutes += $breakStartTime->diffInMinutes($record->recorded_at);
                $breakStartTime = null;
            }
            // Resume work time tracking
            $checkInTime = $record->recorded_at;
            break;
    }
}

// If still checked in at end of day, don't count ongoing work
// Wait for check-out to finalize
```

**Overtime Calculation**:
```php
// Get employee's shift for this date
$shift = $employee->getShiftForDate($date);

// Check for shift overrides
$effectiveShift = $overrideService->getEffectiveShiftTimes($date, $shift, $employee);

if ($effectiveShift) {
    // Calculate expected work minutes
    $expectedMinutes = $effectiveShift->startTime->diffInMinutes($effectiveShift->endTime);

    // Subtract break time from expected hours (unpaid breaks)
    $expectedMinutes -= $totalBreakMinutes;

    // Overtime is work beyond expected hours
    $overtimeMinutes = max(0, $totalWorkMinutes - $expectedMinutes);
} else {
    // No shift or holiday - all work is overtime
    $overtimeMinutes = $totalWorkMinutes;
}
```

**Status Determination**:
```php
// Check for override first
$override = $overrideService->getActiveOverride($date, $shift, $employee);

if ($override) {
    if ($override->type === 'holiday') return 'holiday';
    if ($override->type === 'off-day') return 'on-leave';
}

// Determine based on work hours
if ($totalWorkMinutes == 0) {
    return 'absent';
}

// Get expected work hours
$expectedMinutes = $shift ? $shift->getDurationMinutes() : 480; // Default 8 hours

// Half day threshold (< 50% of expected hours)
if ($totalWorkMinutes < $expectedMinutes * 0.5) {
    return 'half-day';
}

return 'present';
```

### Real-time Integration

**Update ProcessAttendanceEvent Job**:

**Location**: `app/Jobs/ProcessAttendanceEvent.php`

```php
public function handle()
{
    // ... existing direction detection and record creation ...

    // Create/save attendance record
    $record = AttendanceRecord::create([...]);

    // NEW: Update daily summary
    $this->summaryCalculator->updateSummaryFromEvent($record);

    // ... existing violation detection ...
}
```

### Recalculation Command

**Location**: `app/Console/Commands/RecalculateSummariesCommand.php`

```php
php artisan attendance:recalculate-summaries
    --employee={employee_id}     # Optional: specific employee
    --from={YYYY-MM-DD}          # Start date
    --to={YYYY-MM-DD}            # End date
    --force                      # Recalculate even if summary exists
```

**Implementation**:
```php
public function handle()
{
    $employees = $this->option('employee')
        ? Employee::find($this->option('employee'))
        : Employee::all();

    $startDate = Carbon::parse($this->option('from'));
    $endDate = Carbon::parse($this->option('to'));

    foreach ($employees as $employee) {
        $count = $this->summaryCalculator->recalculateRange(
            $employee,
            $startDate,
            $endDate
        );

        $this->info("Recalculated {$count} summaries for {$employee->name}");
    }
}
```

### API Endpoints

**Routes**: `routes/api.php`

```php
GET /api/attendance/summaries
    ?employee_id=1
    &date=2025-10-05
    &from=2025-10-01
    &to=2025-10-31
    &status=present|absent|half-day|on-leave|holiday

GET /api/employees/{employee}/summaries
    ?month=2025-10
    &year=2025

POST /api/attendance/summaries/recalculate
    {
        "employee_id": 1,
        "from": "2025-10-01",
        "to": "2025-10-31"
    }
```

**Controller**: `app/Http/Controllers/Api/AttendanceSummaryController.php`

### Performance Optimization

**Eager Loading**:
```php
DailyAttendanceSummary::with(['employee', 'attendanceRecords'])
    ->whereBetween('date', [$startDate, $endDate])
    ->get();
```

**Batch Recalculation**:
- Process in chunks of 100 summaries
- Use database transactions for consistency
- Queue recalculation for large date ranges

**Caching**:
- Cache monthly summaries: `tenant:{$tenantId}:summaries:{$employeeId}:{$month}`
- Invalidate on new attendance record or recalculation

### Testing Requirements

**Unit Tests** (`tests/Unit/SummaryCalculatorTest.php`):
- Test work hours calculation with multiple check-in/check-out pairs
- Test break time calculation
- Test overnight shift work hours (check-in 23:00, check-out 06:00)
- Test overtime calculation with different shift durations
- Test status determination logic
- Test incomplete day (still checked in)

**Feature Tests** (`tests/Feature/DailySummaryTest.php`):
- Test real-time summary update on attendance event
- Test recalculation command
- Test API endpoint filtering and pagination
- Test summary with shift overrides (holiday, half-day)
- Test edge case: employee works on holiday

## External Dependencies

None - uses existing Laravel/Carbon functionality
