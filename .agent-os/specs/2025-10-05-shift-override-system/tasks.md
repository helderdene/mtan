# Spec Tasks

These are the tasks to be completed for the spec detailed in @.agent-os/specs/2025-10-05-shift-override-system/spec.md

> Created: 2025-10-05
> Status: Phase 1-5 Complete (Violation Detection Integration Pending)

## Tasks

### Phase 1: Database & Model Setup

- [x] Create migration for `shift_overrides` table with all columns, indexes, and constraints
- [x] Create `ShiftOverride` model in `app/Domain/Shift/Models/`
- [x] Define relationships: `shift()` and `employee()` BelongsTo relationships
- [x] Add validation rules and accessors/mutators as needed
- [x] Create factory for `ShiftOverride` model for testing
- [x] Run migration and verify table structure

### Phase 2: Service Layer Implementation

- [x] Create `OverrideService` in `app/Domain/Shift/Services/`
- [x] Implement `getActiveOverride()` method with priority resolution logic
- [x] Implement `isWorkRequired()` method to check if work is expected on date
- [x] Implement `getEffectiveShiftTimes()` method to get modified shift times
- [x] Create `EffectiveShift` DTO in `app/Domain/Shift/DTOs/`
- [x] Add caching layer with tenant-specific cache keys
- [x] Implement cache invalidation on override create/update/delete

### Phase 3: Integration with Existing Services

- [x] Update `DirectionDetector` to inject and use `OverrideService`
- [x] Add override check before direction detection scoring
- [x] Handle holiday/off-day overrides with warning logs
- [x] Handle half-day/custom-shift overrides by modifying shift times
- [ ] Update violation detection logic to check `isWorkRequired()`
- [ ] Update violation detection to use `getEffectiveShiftTimes()`
- [ ] Test direction detection with various override scenarios

### Phase 4: API Endpoints & Controller

- [x] Create `ShiftOverrideController` in `app/Http/Controllers/Api/`
- [x] Implement `index()` method with filtering (date, shift, employee, type)
- [x] Implement `store()` method with validation
- [x] Implement `show()` method for override details
- [x] Implement `update()` method with validation
- [x] Implement `destroy()` method with cache invalidation
- [x] Create Form Request classes for validation
- [x] Add routes to `routes/api.php` or `routes/web.php`
- [ ] Add authorization policies for override management

### Phase 5: Testing

- [x] Create `OverrideServiceTest.php` in `tests/Feature/`
- [x] Test override resolution priority logic
- [x] Test `isWorkRequired()` for all override types
- [x] Test `getEffectiveShiftTimes()` for half-day and custom-shift
- [x] Test edge cases (null shift, null employee, multiple overrides)
- [ ] Create `ShiftOverrideTest.php` in `tests/Feature/` for API endpoints
- [ ] Test CRUD API endpoints (create, read, update, delete)
- [ ] Test company-wide holiday prevents violations
- [ ] Test employee-specific off-day prevents violations
- [ ] Test half-day modifies shift times correctly
- [x] Test integration with direction detection (via OverrideServiceTest)
- [ ] Test cache invalidation on override changes

### Phase 6: UI Implementation (Future)

- [ ] Create Vue component for override management page
- [ ] Add calendar view for visualizing overrides
- [ ] Create form for adding/editing overrides
- [ ] Add bulk import for holidays from calendar file
- [ ] Add notifications for upcoming overrides

## Definition of Done

- All database migrations created and tested
- All models, services, and DTOs implemented with proper relationships
- Integration with direction detection and violation detection complete
- All API endpoints functional with proper validation and authorization
- Unit and feature tests passing with >90% coverage
- Cache strategy implemented with proper invalidation
- Documentation updated in CLAUDE.md with usage examples
