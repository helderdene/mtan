# Spec Requirements Document

> Spec: Shift Break Time Validation
> Created: 2025-10-04
> Status: Planning

## Overview

Add comprehensive validation logic for break times in shift creation and editing to ensure data integrity and prevent invalid shift configurations. The system must validate that break periods fall within shift working hours, don't overlap with shift boundaries, and follow logical time constraints. This includes both frontend (real-time user feedback) and backend (data integrity enforcement) validation layers.

Currently, the database schema supports `break_start` and `break_end` columns in the shifts table (added via migration `2025_10_03_111728_add_break_times_to_shifts_table.php`), but no validation exists to prevent invalid break configurations such as breaks outside shift hours, inverted break times, or zero-duration breaks.

## User Stories

**As a tenant administrator**, I want to create shifts with break periods so that I can accurately track employee work hours and break compliance.

**As a tenant administrator**, I want the system to prevent me from setting invalid break times (e.g., break ending before it starts, break outside shift hours) so that I don't create unusable shift configurations.

**As a tenant administrator**, I want to see clear, actionable error messages when I enter invalid break times so that I can quickly correct my input and proceed.

**As a system administrator**, I want break time validation enforced at both frontend and backend layers so that data integrity is guaranteed regardless of how the data is submitted (web UI, API, direct database access via console).

**As a developer**, I want consistent validation rules across frontend and backend so that maintenance is simplified and user experience is predictable.

## Spec Scope

This spec covers the following validation requirements:

1. **Break Time Range Validation**
   - Break start time must be after or equal to shift start time
   - Break end time must be before or equal to shift end time
   - Both break start and end must fall within shift working hours

2. **Break Duration Validation**
   - Break start time must be before break end time (no inverted times)
   - Minimum break duration of 1 minute (no zero-duration breaks)
   - Maximum break duration validation (configurable, default 2 hours)

3. **Overnight Shift Handling**
   - Breaks in overnight shifts (end_time < start_time) must be validated correctly
   - Break cannot span across midnight in overnight shifts

4. **Optional Break Support**
   - Both `break_start` and `break_end` are optional
   - If one is provided, both must be provided (no partial break configuration)
   - Null/empty breaks are valid (shifts without breaks)

5. **Frontend Validation**
   - Real-time validation in Vue.js shift creation/edit forms
   - Visual feedback (error messages, field highlighting)
   - Disable submit button until validation passes

6. **Backend Validation**
   - Laravel form request validation rules
   - Custom validation rules for complex break logic
   - Consistent error message formatting

7. **Error Message Specifications**
   - Clear, user-friendly error messages
   - Specific to the validation failure (not generic)
   - Actionable guidance on how to fix the issue

## Out of Scope

The following features are explicitly excluded from this spec:

1. **Multiple Break Periods**
   - System supports only one break period per shift
   - Multiple breaks require schema changes and are deferred to future specs

2. **Break Tracking in Attendance**
   - This spec focuses only on shift configuration validation
   - Actual break tracking during attendance (check-in/out for breaks) is handled separately

3. **Break Policy Management**
   - No configurable break policies (e.g., mandatory breaks after X hours)
   - Break duration limits are hardcoded defaults

4. **Break Scheduling Optimization**
   - No automatic break time suggestions
   - No conflict detection with other employees' breaks

5. **Historical Break Data Migration**
   - Validation applies to new/edited shifts only
   - Existing invalid break data (if any) is not automatically corrected

6. **Localization of Error Messages**
   - Error messages in English only
   - Multi-language support deferred to localization spec

## Expected Deliverable

Upon completion of this spec, the system shall deliver:

1. **Validated Shift Creation/Editing**
   - Shifts with invalid break configurations are rejected
   - Only valid break times are saved to the database
   - No data corruption from invalid break entries

2. **User-Friendly Error Feedback**
   - Real-time validation feedback in frontend forms
   - Clear, specific error messages indicating the exact validation failure
   - Visual indicators (red borders, error text) on invalid fields

3. **Comprehensive Test Coverage**
   - Unit tests for all validation rules
   - Feature tests for shift creation/editing with breaks
   - Edge case testing (overnight shifts, boundary values, null breaks)

4. **Consistent Validation Logic**
   - Identical validation rules in frontend (Vue) and backend (Laravel)
   - Single source of truth for validation constraints
   - No discrepancies between client and server validation

5. **API Documentation**
   - Updated API documentation for shift endpoints
   - Break validation error examples
   - Request/response schemas with break fields

6. **Developer Documentation**
   - Code comments explaining validation logic
   - Examples of valid and invalid break configurations
   - Troubleshooting guide for common validation errors

## Spec Documentation

- Tasks: @.agent-os/specs/2025-10-04-shift-break-validation/tasks.md
- Technical Specification: @.agent-os/specs/2025-10-04-shift-break-validation/sub-specs/technical-spec.md
- API Specification: @.agent-os/specs/2025-10-04-shift-break-validation/sub-specs/api-spec.md
