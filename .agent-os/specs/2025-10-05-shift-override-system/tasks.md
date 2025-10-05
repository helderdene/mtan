# Spec Tasks

These are the tasks to be completed for the spec detailed in @.agent-os/specs/2025-10-05-shift-override-system/spec.md

> Created: 2025-10-05
> Status: Ready for Implementation

## Tasks

### Phase 1: Database & Model Setup

- [ ] Create migration for `shift_overrides` table with all columns, indexes, and constraints
- [ ] Create `ShiftOverride` model in `app/Domain/Shift/Models/`
- [ ] Define relationships: `shift()` and `employee()` BelongsTo relationships
- [ ] Add validation rules and accessors/mutators as needed
- [ ] Create factory for `ShiftOverride` model for testing
- [ ] Run migration and verify table structure

### Phase 2: Service Layer Implementation

- [ ] Create `OverrideService` in `app/Domain/Shift/Services/`
- [ ] Implement `getActiveOverride()` method with priority resolution logic
- [ ] Implement `isWorkRequired()` method to check if work is expected on date
- [ ] Implement `getEffectiveShiftTimes()` method to get modified shift times
- [ ] Create `EffectiveShift` DTO in `app/Domain/Shift/DTOs/`
- [ ] Add caching layer with tenant-specific cache keys
- [ ] Implement cache invalidation on override create/update/delete

### Phase 3: Integration with Existing Services

- [ ] Update `DirectionDetector` to inject and use `OverrideService`
- [ ] Add override check before direction detection scoring
- [ ] Handle holiday/off-day overrides with warning logs
- [ ] Handle half-day/custom-shift overrides by modifying shift times
- [ ] Update violation detection logic to check `isWorkRequired()`
- [ ] Update violation detection to use `getEffectiveShiftTimes()`
- [ ] Test direction detection with various override scenarios

### Phase 4: API Endpoints & Controller

- [ ] Create `ShiftOverrideController` in `app/Http/Controllers/Api/`
- [ ] Implement `index()` method with filtering (date, shift, employee, type)
- [ ] Implement `store()` method with validation
- [ ] Implement `show()` method for override details
- [ ] Implement `update()` method with validation
- [ ] Implement `destroy()` method with cache invalidation
- [ ] Create Form Request classes for validation
- [ ] Add routes to `routes/api.php` or `routes/web.php`
- [ ] Add authorization policies for override management

### Phase 5: Testing

- [ ] Create `OverrideServiceTest.php` in `tests/Unit/`
- [ ] Test override resolution priority logic
- [ ] Test `isWorkRequired()` for all override types
- [ ] Test `getEffectiveShiftTimes()` for half-day and custom-shift
- [ ] Test edge cases (null shift, null employee, multiple overrides)
- [ ] Create `ShiftOverrideTest.php` in `tests/Feature/`
- [ ] Test CRUD API endpoints (create, read, update, delete)
- [ ] Test company-wide holiday prevents violations
- [ ] Test employee-specific off-day prevents violations
- [ ] Test half-day modifies shift times correctly
- [ ] Test integration with direction detection
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
