# Technical Specification

This is the technical specification for the spec detailed in @.agent-os/specs/2025-10-05-smart-direction-detection/spec.md

> Created: 2025-10-05
> Version: 1.0.0

## Technical Requirements

### DirectionDetector Service

**Location**: `app/Domain/Attendance/Services/DirectionDetector.php`

**Method Signature**:
```php
public function detect(
    Employee $employee,
    Carbon $timestamp,
    ?AttendanceRecord $lastRecord,
    ?Shift $shift
): DirectionResult
```

**DirectionResult DTO**:
```php
class DirectionResult
{
    public string $direction; // 'check-in', 'check-out', 'break-start', 'break-end'
    public int $confidence; // 0-100
    public array $scores; // Breakdown of factor scores
    public string $reason; // Human-readable explanation
}
```

### Scoring Algorithm

**Factor Weights**:
- Last Record Analysis: 30%
- Shift Timing Proximity: 35%
- Work Duration Rules: 15%
- Default/Fallback: 20%

**Last Record Analysis (30 points max)**:
- If last record is null (first record of day): Check-in gets 30 points
- If last record is 'check-in': Check-out gets 25 points, Break-start gets 20 points
- If last record is 'break-start': Break-end gets 30 points
- If last record is 'break-end': Check-out gets 25 points
- If last record is 'check-out': Check-in gets 30 points

**Shift Timing Proximity (35 points max)**:
- Within 30 min of shift start: Check-in gets 35 points
- Within 30 min of shift end: Check-out gets 35 points
- During shift hours (not near boundaries): Break-start gets 20 points
- More than 1 hour after check-in: Break-start gets 25 points
- No shift assigned: All directions get 10 points (neutral)

**Work Duration Rules (15 points max)**:
- If checked in for < 30 minutes: Check-out gets 0 points (too early)
- If checked in for 30 min - 2 hours: Check-out gets 10 points
- If checked in for > 2 hours: Check-out gets 15 points
- If on break for < 1 min: Break-end gets 0 points (too early)
- If on break for 1 min - 2 hours: Break-end gets 15 points
- If on break for > 2 hours: Break-end gets 5 points (suspicious)

**Fallback/Default (20 points max)**:
- If time is before 12:00 PM: Check-in gets 20 points
- If time is after 12:00 PM: Check-out gets 20 points

**Selection Logic**:
- Calculate total score for each possible direction (check-in, check-out, break-start, break-end)
- Select direction with highest total score
- If highest score < 40: Flag as low confidence
- If top two scores differ by < 10 points: Flag as ambiguous

### Overnight Shift Handling

For shifts where `end_time < start_time`:
- Adjust shift end to next day for comparison
- Handle timestamp comparison across midnight boundary
- Example: Shift 22:00-06:00 → end_time becomes 06:00 next day

### Edge Cases

1. **No Shift Assigned**: Use fallback strategy only (time of day)
2. **First Check-in Ever**: Last record is null, heavily favor check-in
3. **Multiple Check-ins**: If last record is check-in and no break-start, favor break-start over second check-in
4. **Break Validation**: Ensure break-start/end only occur during shift hours (covered by Phase 1 break validation)

### Performance Requirements

- Direction detection must complete in < 50ms
- Cache shift data to avoid repeated database queries
- Use Carbon for all time calculations

### Testing Requirements

**Unit Tests** (`tests/Unit/DirectionDetectorTest.php`):
- Test each scoring factor independently
- Test overnight shift calculations
- Test edge cases (no shift, no last record, etc.)
- Test confidence score calculations
- Test ambiguous scenarios

**Feature Tests** (`tests/Feature/DirectionDetectionTest.php`):
- Test full workflow from MQTT message to direction detection
- Test with real employee, shift, and attendance record data
- Verify 95%+ accuracy on test dataset of 100+ scenarios

## Approach

1. Create `DirectionResult` DTO class to encapsulate detection results
2. Implement `DirectionDetector` service with scoring logic for each factor
3. Add helper methods for overnight shift normalization
4. Implement confidence calculation and direction selection logic
5. Add database columns for storing confidence scores and detection reasons
6. Write comprehensive unit and feature tests

## External Dependencies

None - uses existing Laravel/Carbon functionality
