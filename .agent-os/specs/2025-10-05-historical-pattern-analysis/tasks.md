# Spec Tasks

These are the tasks to be completed for the spec detailed in @.agent-os/specs/2025-10-05-historical-pattern-analysis/spec.md

> Created: 2025-10-05
> Status: Ready for Implementation

## Tasks

### 1. Create EmployeePattern DTO
- [ ] Create `app/Domain/Attendance/DTOs/EmployeePattern.php`
- [ ] Add properties: avgCheckInTime, checkInStdDev, avgCheckOutTime, checkOutStdDev, recordCount, analyzedAt, reliable
- [ ] Add constructor and getter methods
- [ ] Add validation for reliable threshold (>= 7 records)

### 2. Implement PatternAnalyzer Service
- [ ] Create `app/Domain/Attendance/Services/PatternAnalyzer.php`
- [ ] Implement `analyzePatterns(Employee $employee): EmployeePattern` method
- [ ] Implement pattern calculation algorithm (average time and standard deviation)
- [ ] Implement `calculatePatternFromDatabase()` private method
- [ ] Handle edge cases (overnight shifts, insufficient data)

### 3. Implement Pattern Scoring
- [ ] Add `scorePattern(Employee $employee, Carbon $timestamp, string $expectedDirection): int` method
- [ ] Implement scoring logic based on standard deviation proximity
- [ ] Handle unreliable patterns (< 7 records) with neutral score
- [ ] Handle direction mismatch (return 0 points)
- [ ] Handle new employees (return neutral 10 points)

### 4. Implement Caching Strategy
- [ ] Add cache integration to `analyzePatterns()` method
- [ ] Use cache key format: `tenant:{$tenantId}:employee:{$employeeId}:pattern`
- [ ] Set 24-hour cache duration
- [ ] Create cache invalidation method: `invalidatePatternCache(Employee $employee)`

### 5. Integrate with DirectionDetector
- [ ] Inject `PatternAnalyzer` into `DirectionDetector` constructor
- [ ] Update `detect()` method to include pattern scoring
- [ ] Add pattern score to total score calculation (now 100 points total)
- [ ] Update score thresholds if needed to account for new 100-point scale

### 6. Add Cache Invalidation Triggers
- [ ] Update `ProcessAttendanceEvent` job to invalidate pattern cache after record creation
- [ ] Ensure invalidation happens after successful record save
- [ ] Add error handling for cache operations

### 7. Write Unit Tests
- [ ] Create `tests/Unit/PatternAnalyzerTest.php`
- [ ] Test pattern calculation with 30 days of consistent data
- [ ] Test standard deviation calculation accuracy
- [ ] Test reliability threshold (< 7 records returns unreliable)
- [ ] Test pattern scoring at 1σ, 2σ, 3σ, and outside
- [ ] Test cache hit and miss scenarios
- [ ] Test new employee handling (no history)
- [ ] Test irregular schedules

### 8. Write Feature Tests
- [ ] Create `tests/Feature/PatternAnalysisTest.php`
- [ ] Test end-to-end pattern analysis with DirectionDetector
- [ ] Test pattern cache invalidation on new attendance records
- [ ] Test pattern adaptation over time (rolling 30-day window)
- [ ] Test performance (< 100ms for calculation, < 5ms for cached)

### 9. Performance Optimization
- [ ] Verify database query uses existing indexes
- [ ] Test query performance with large datasets
- [ ] Ensure cache warming doesn't impact real-time processing
- [ ] Profile pattern calculation performance

### 10. Documentation
- [ ] Add PHPDoc comments to PatternAnalyzer service
- [ ] Add PHPDoc comments to EmployeePattern DTO
- [ ] Update DirectionDetector documentation to mention pattern scoring
- [ ] Add inline code comments for complex calculations
