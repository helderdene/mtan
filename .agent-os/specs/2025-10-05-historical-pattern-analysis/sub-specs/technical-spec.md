# Technical Specification

This is the technical specification for the spec detailed in @.agent-os/specs/2025-10-05-historical-pattern-analysis/spec.md

> Created: 2025-10-05
> Version: 1.0.0

## Technical Requirements

### PatternAnalyzer Service

**Location**: `app/Domain/Attendance/Services/PatternAnalyzer.php`

**Method Signatures**:
```php
public function analyzePatterns(Employee $employee): EmployeePattern;

public function scorePattern(
    Employee $employee,
    Carbon $timestamp,
    string $expectedDirection
): int; // Returns 0-20 (pattern weight in direction detection)
```

**EmployeePattern DTO**:
```php
class EmployeePattern
{
    public ?Carbon $avgCheckInTime; // Average check-in time
    public ?int $checkInStdDev; // Standard deviation in minutes
    public ?Carbon $avgCheckOutTime; // Average check-out time
    public ?int $checkOutStdDev; // Standard deviation in minutes
    public int $recordCount; // Number of records analyzed
    public Carbon $analyzedAt; // When pattern was calculated
    public bool $reliable; // True if >= 7 records in last 30 days
}
```

### Pattern Calculation Algorithm

**Data Collection**:
- Query last 30 days of attendance records for the employee
- Group by direction: check-in and check-out records
- Extract time-of-day from `recorded_at` timestamp (ignore date)

**Average Time Calculation**:
```php
// Convert times to minutes since midnight
// Example: 09:15 AM = 555 minutes
$checkInMinutes = $records->map(fn($r) =>
    $r->recorded_at->hour * 60 + $r->recorded_at->minute
);

// Calculate average (handle overnight edge case)
$avgMinutes = $checkInMinutes->average();

// Convert back to Carbon time
$avgCheckInTime = Carbon::today()->addMinutes($avgMinutes);
```

**Standard Deviation Calculation**:
```php
// Calculate standard deviation in minutes
$mean = $checkInMinutes->average();
$variance = $checkInMinutes->map(fn($m) => pow($m - $mean, 2))->average();
$stdDev = sqrt($variance);
```

**Reliability Threshold**:
- Pattern is reliable if >= 7 records in last 30 days
- If < 7 records, set `reliable = false` and return neutral scores

### Pattern Scoring Algorithm

**Scoring Logic** (max 20 points):
```php
// Calculate difference between current time and average pattern time
$timeDiff = abs($timestamp->diffInMinutes($avgTime));

// Score based on proximity to average
if ($timeDiff <= $stdDev) {
    // Within 1 standard deviation
    $score = 20;
} elseif ($timeDiff <= $stdDev * 2) {
    // Within 2 standard deviations
    $score = 15;
} elseif ($timeDiff <= $stdDev * 3) {
    // Within 3 standard deviations
    $score = 10;
} else {
    // Outside typical pattern
    $score = 5;
}
```

**Edge Cases**:
- If pattern is not reliable (< 7 records): Return 10 points (neutral)
- If direction doesn't match pattern type (e.g., check-out pattern for check-in event): Return 0 points
- If no pattern exists (new employee): Return 10 points (neutral)

### Caching Strategy

**Cache Key**: `tenant:{$tenantId}:employee:{$employeeId}:pattern`

**Cache Duration**: 24 hours

**Cache Invalidation**:
- Invalidate when new attendance record is created for employee
- Invalidate in `ProcessAttendanceEvent` job after record is saved

**Implementation**:
```php
public function analyzePatterns(Employee $employee): EmployeePattern
{
    $cacheKey = "tenant:{$employee->tenant_id}:employee:{$employee->id}:pattern";

    return Cache::remember($cacheKey, now()->addDay(), function () use ($employee) {
        // Calculate pattern from database
        return $this->calculatePatternFromDatabase($employee);
    });
}
```

### Integration with DirectionDetector

**Update DirectionDetector::detect()** to include pattern scoring:

```php
// Existing factors (total 80 points)
$lastRecordScore = $this->scoreLastRecord(...); // max 30
$shiftTimingScore = $this->scoreShiftTiming(...); // max 35
$durationScore = $this->scoreWorkDuration(...); // max 15

// NEW: Add pattern scoring (max 20)
$patternScore = $this->patternAnalyzer->scorePattern(
    $employee,
    $timestamp,
    $direction
);

// Total score = 100 points possible
$totalScore = $lastRecordScore + $shiftTimingScore + $durationScore + $patternScore;
```

### Performance Requirements

- Pattern analysis must complete in < 100ms (includes DB query)
- Use cached patterns when available (< 5ms)
- Efficient queries with proper indexes

### Database Query Optimization

**Query for pattern calculation**:
```php
AttendanceRecord::where('employee_id', $employee->id)
    ->where('recorded_at', '>=', now()->subDays(30))
    ->whereIn('direction', ['check-in', 'check-out'])
    ->orderBy('recorded_at')
    ->get(['direction', 'recorded_at']);
```

**Required Index**: Already exists from Phase 1
```php
$table->index(['employee_id', 'recorded_at']);
```

### Testing Requirements

**Unit Tests** (`tests/Unit/PatternAnalyzerTest.php`):
- Test pattern calculation with 30 days of data
- Test standard deviation calculation
- Test reliability threshold (< 7 records)
- Test pattern scoring at different time differences
- Test cache functionality

**Feature Tests** (`tests/Feature/PatternAnalysisTest.php`):
- Test integration with DirectionDetector
- Test pattern invalidation on new records
- Test new employee handling
- Test employees with irregular schedules

## External Dependencies

None - uses existing Laravel Cache and Carbon functionality
