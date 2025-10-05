# Spec Requirements Document

> Spec: Daily Attendance Summary Generation
> Created: 2025-10-05
> Status: In Progress

## Overview

Implement automatic daily attendance summary generation that aggregates individual attendance events into comprehensive daily records with calculated work hours, break duration, overtime, and attendance status. Summaries are generated in real-time as attendance events occur and can be recalculated for historical dates, providing a single source of truth for reporting and payroll integration.

## User Stories

1. **Automatic Summary Generation**
   - As an employee, I want my daily attendance to be automatically summarized, so that I can see my total work hours without manual calculation.
   - The system creates or updates a daily summary record each time an attendance event is processed, calculating cumulative work hours and break time based on all check-in/check-out records for the day.

2. **Overtime Calculation**
   - As a manager, I want overtime hours to be automatically calculated, so that I can approve overtime pay accurately.
   - The system compares total work hours against the employee's shift duration and calculates overtime hours when work exceeds the standard shift length.

3. **Attendance Status Tracking**
   - As an HR administrator, I want to see each employee's daily attendance status (present, absent, half-day, on-leave), so that I can quickly identify attendance patterns.
   - The system assigns an attendance status based on work hours and shift overrides, making it easy to filter and report on attendance.

## Spec Scope

1. **Daily Summary Model** - Create database table and model for storing daily summaries (employee, date, work hours, break duration, overtime, status)
2. **Summary Calculation Service** - Service to calculate work hours from attendance records
3. **Real-time Summary Updates** - Update summaries immediately when attendance events are processed
4. **Overtime Calculation** - Calculate overtime based on shift duration and company policies
5. **Status Determination** - Automatically determine attendance status (present, absent, half-day, on-leave)
6. **Recalculation Support** - Ability to recalculate summaries for past dates

## Out of Scope

- Weekly/monthly aggregations (future enhancement)
- Custom overtime rules per employee
- Attendance summary approval workflow
- Integration with payroll systems (future enhancement)

## Expected Deliverable

1. `DailyAttendanceSummary` model with calculated fields
2. `SummaryCalculator` service that generates summaries from attendance records
3. Integration with `ProcessAttendanceEvent` job to update summaries in real-time
4. Artisan command to recalculate summaries for date range
5. API endpoints to retrieve daily summaries with filtering
6. Unit and feature tests covering calculation logic and edge cases

## Spec Documentation

- Tasks: @.agent-os/specs/2025-10-05-daily-attendance-summaries/tasks.md
- Technical Specification: @.agent-os/specs/2025-10-05-daily-attendance-summaries/sub-specs/technical-spec.md
- Database Schema: @.agent-os/specs/2025-10-05-daily-attendance-summaries/sub-specs/database-schema.md
