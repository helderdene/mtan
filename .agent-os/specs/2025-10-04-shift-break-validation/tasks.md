# Spec Tasks

These are the tasks to be completed for the spec detailed in @.agent-os/specs/2025-10-04-shift-break-validation/spec.md

> Created: 2025-10-04
> Status: Ready for Implementation

## Tasks

### Phase 1: Backend Validation (Priority: High)

- [ ] **Task 1.1: Create BreakWithinShiftHours validation rule**
  - File: `app/Rules/BreakWithinShiftHours.php`
  - Implement validation logic for standard shifts
  - Implement validation logic for overnight shifts
  - Add clear error messages with shift time context
  - Estimated time: 1 hour

- [ ] **Task 1.2: Create BreakDurationValid validation rule**
  - File: `app/Rules/BreakDurationValid.php`
  - Validate minimum break duration (1 minute)
  - Validate maximum break duration (2 hours)
  - Add duration-specific error messages
  - Estimated time: 45 minutes

- [ ] **Task 1.3: Create StoreShiftRequest form request**
  - File: `app/Http/Requests/StoreShiftRequest.php`
  - Define validation rules for all shift fields
  - Apply break validation rules
  - Customize error messages
  - Add `required_with` constraint for break completeness
  - Estimated time: 1 hour

- [ ] **Task 1.4: Create UpdateShiftRequest form request**
  - File: `app/Http/Requests/UpdateShiftRequest.php`
  - Inherit/duplicate validation logic from StoreShiftRequest
  - Ensure consistent validation behavior
  - Estimated time: 30 minutes

- [ ] **Task 1.5: Write unit tests for BreakWithinShiftHours rule**
  - File: `tests/Unit/Rules/BreakWithinShiftHoursTest.php`
  - Test standard shift validation (pass cases)
  - Test standard shift validation (fail cases)
  - Test overnight shift validation (pass cases)
  - Test overnight shift validation (fail cases)
  - Test edge cases (midnight, same time, null values)
  - Estimated time: 1.5 hours

- [ ] **Task 1.6: Write unit tests for BreakDurationValid rule**
  - File: `tests/Unit/Rules/BreakDurationValidTest.php`
  - Test valid durations (1 minute to 2 hours)
  - Test invalid durations (< 1 minute, > 2 hours)
  - Test edge cases (exactly 1 minute, exactly 2 hours)
  - Estimated time: 1 hour

- [ ] **Task 1.7: Write feature tests for shift creation with breaks**
  - File: `tests/Feature/Shift/CreateShiftWithBreakTest.php`
  - Test successful creation with valid breaks
  - Test rejection with invalid breaks
  - Test creation without breaks (null values)
  - Test all validation error scenarios
  - Estimated time: 2 hours

- [ ] **Task 1.8: Write feature tests for shift editing with breaks**
  - File: `tests/Feature/Shift/UpdateShiftWithBreakTest.php`
  - Test successful update with valid breaks
  - Test update from no breaks to with breaks
  - Test update from with breaks to no breaks
  - Test rejection with invalid breaks
  - Estimated time: 1.5 hours

### Phase 2: Frontend Validation (Priority: High)

- [ ] **Task 2.1: Create useShiftBreakValidation composable**
  - File: `resources/js/composables/useShiftBreakValidation.ts`
  - Implement all validation rules matching backend logic
  - Create reactive error state management
  - Add computed `isValid` property
  - Add helper functions for time parsing and comparison
  - Estimated time: 2.5 hours

- [ ] **Task 2.2: Update CreateShift.vue component**
  - File: `resources/js/pages/Shifts/CreateShift.vue`
  - Integrate useShiftBreakValidation composable
  - Add break time input fields (if not already present)
  - Add real-time validation on blur/input events
  - Display validation errors below input fields
  - Style error states (red borders, error text)
  - Disable submit button when validation fails
  - Estimated time: 2 hours

- [ ] **Task 2.3: Update EditShift.vue component**
  - File: `resources/js/pages/Shifts/EditShift.vue`
  - Integrate useShiftBreakValidation composable
  - Ensure validation works with pre-filled data
  - Handle transition from no breaks to with breaks
  - Handle transition from with breaks to no breaks
  - Estimated time: 1.5 hours

- [ ] **Task 2.4: Add visual error indicators**
  - Update input field styling for error states
  - Add error message components
  - Ensure accessibility (ARIA labels, color contrast)
  - Test with keyboard navigation
  - Estimated time: 1 hour

- [ ] **Task 2.5: Add optional break time tooltips/help text**
  - Add help text explaining break time constraints
  - Add tooltip on shift hours showing valid break range
  - Add example valid break configurations
  - Estimated time: 1 hour

### Phase 3: API Implementation (Priority: Medium)

- [ ] **Task 3.1: Create API ShiftController**
  - File: `app/Http/Controllers/Api/V1/ShiftController.php`
  - Implement index() method (list shifts)
  - Implement store() method (create shift)
  - Implement show() method (get shift)
  - Implement update() method (update shift)
  - Implement destroy() method (delete shift)
  - Apply StoreShiftRequest/UpdateShiftRequest validation
  - Estimated time: 2 hours

- [ ] **Task 3.2: Create ShiftResource for API responses**
  - File: `app/Http/Resources/ShiftResource.php`
  - Define resource transformation
  - Add computed fields (break_duration_minutes, is_overnight)
  - Format timestamps as ISO 8601
  - Estimated time: 45 minutes

- [ ] **Task 3.3: Add API routes**
  - File: `routes/api.php`
  - Register shift resource routes
  - Apply authentication middleware
  - Apply rate limiting middleware
  - Estimated time: 30 minutes

- [ ] **Task 3.4: Write API feature tests**
  - File: `tests/Feature/Api/ShiftApiTest.php`
  - Test all CRUD operations
  - Test validation error responses
  - Test successful responses with breaks
  - Test successful responses without breaks
  - Test authentication requirement
  - Estimated time: 2.5 hours

### Phase 4: Documentation (Priority: Medium)

- [ ] **Task 4.1: Update API documentation**
  - Document POST /api/v1/shifts endpoint
  - Document PUT /api/v1/shifts/{id} endpoint
  - Document GET /api/v1/shifts endpoint
  - Document GET /api/v1/shifts/{id} endpoint
  - Document DELETE /api/v1/shifts/{id} endpoint
  - Add request/response examples for all validation errors
  - Estimated time: 1.5 hours

- [ ] **Task 4.2: Add inline code comments**
  - Document validation rules in custom rule classes
  - Document composable functions
  - Add examples of valid/invalid configurations
  - Estimated time: 1 hour

- [ ] **Task 4.3: Create troubleshooting guide**
  - Document common validation errors
  - Provide solutions for each error type
  - Add FAQ section for break time configuration
  - Estimated time: 1 hour

### Phase 5: Testing & Refinement (Priority: Low)

- [ ] **Task 5.1: Manual testing**
  - Test all validation scenarios in UI
  - Test overnight shift break validation
  - Test error message clarity
  - Test accessibility (keyboard, screen reader)
  - Estimated time: 2 hours

- [ ] **Task 5.2: Performance testing**
  - Measure frontend validation performance
  - Ensure no UI lag on input
  - Optimize if necessary (debouncing)
  - Estimated time: 1 hour

- [ ] **Task 5.3: Cross-browser testing**
  - Test in Chrome, Firefox, Safari, Edge
  - Ensure consistent validation behavior
  - Fix browser-specific issues if any
  - Estimated time: 1.5 hours

- [ ] **Task 5.4: Error message review**
  - Review all error messages for clarity
  - Ensure consistent tone and format
  - Test with non-technical users
  - Refine wording if needed
  - Estimated time: 1 hour

## Estimated Total Time

- **Phase 1 (Backend)**: ~9.25 hours
- **Phase 2 (Frontend)**: ~8 hours
- **Phase 3 (API)**: ~6 hours
- **Phase 4 (Documentation)**: ~3.5 hours
- **Phase 5 (Testing)**: ~5.5 hours

**Total Estimated Time**: ~32.25 hours (~4 working days)

## Dependencies

- Database migration `2025_10_03_111728_add_break_times_to_shifts_table.php` must be applied
- Laravel validation system (built-in)
- Carbon date/time library (built-in)
- Vue 3 Composition API (already in use)
- TypeScript (already in use)

## Success Criteria

- All unit tests pass (100% coverage for validation rules)
- All feature tests pass (API and web routes)
- Frontend validation matches backend validation exactly
- No invalid break configurations can be saved
- Clear, actionable error messages for all validation failures
- API documentation complete with examples
- Manual testing confirms good UX
