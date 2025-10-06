# Spec Requirements Document

> Spec: Employee Self-Service Portal for Attendance History
> Created: 2025-10-06

## Overview

Build a comprehensive self-service portal where employees can view their attendance history, check work hours, review violations, submit correction requests, and view leave balances with calendar visualization and detailed records.

## User Stories

### Attendance History Viewing

As an employee, I want to view my complete attendance history with daily summaries, so that I can track my work hours, identify any discrepancies, and understand my attendance patterns.

The portal displays a calendar view showing attendance status for each day (present, absent, on-leave, half-day) with color coding. Clicking any date shows detailed breakdown including check-in time, check-out time, break duration, total work hours, overtime hours, and any violations or corrections applied.

### Violation and Correction Management

As an employee, I want to see all violations assigned to me and submit correction requests, so that I can dispute incorrect violations or fix attendance errors.

The portal includes a violations tab showing all violations with severity, type, date, and status (pending, acknowledged, disputed). Each violation has an "Acknowledge" or "Dispute" action. The corrections tab shows submitted correction requests with status tracking (pending, approved, rejected) and allows creating new correction requests with supporting document uploads.

### Work Hours Summary

As an employee, I want to see my work hours summary for the current month, so that I can track my total work time, overtime, and ensure accurate payroll processing.

The portal displays summary cards showing total work hours this month, total overtime hours, average daily work hours, and days present vs absent with progress bars and comparison to expected hours based on assigned shifts.

## Spec Scope

1. **Calendar View** - Monthly calendar showing attendance status for each day with color-coded indicators
2. **Daily Detail View** - Detailed attendance breakdown for selected date (check-in/out times, breaks, work hours)
3. **Violations Dashboard** - List of all violations with filtering by date range, type, severity, status
4. **Correction Request Form** - Create correction requests with type selection, reason input, and document upload
5. **Work Hours Summary** - Monthly summary cards with total work hours, overtime, attendance statistics
6. **Leave Balance Display** - Show available leave balance and upcoming approved leaves

## Out of Scope

- Leave request submission (covered in separate leave request system spec)
- Manager-specific features (covered in manager dashboard spec)
- Mobile app version (Phase 5)
- Payroll integration (Phase 5)

## Expected Deliverable

1. Employee can view attendance history in calendar format with daily detail breakdown
2. Employee can view and acknowledge/dispute violations from the portal
3. Employee can submit correction requests with supporting documents
4. Employee can view monthly work hours summary and leave balance
