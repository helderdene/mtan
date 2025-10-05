# Spec Requirements Document

> Spec: Violation Detection Engine
> Created: 2025-10-05
> Status: Planning

## Overview

Implement a comprehensive violation detection engine that automatically identifies attendance policy violations in real-time, including late arrival, early departure, missing checkout, and extended breaks. The system assigns severity levels based on violation magnitude, logs all violations with detailed context, and integrates with the notification system to alert managers of policy breaches.

## User Stories

1. **Automatic Late Arrival Detection**
   - As a manager, I want the system to automatically flag when employees arrive late, so that I can address attendance issues promptly.
   - The system compares check-in time against shift start time (with grace period) and creates a late arrival violation when threshold is exceeded, storing the number of minutes late.

2. **Missing Checkout Detection**
   - As an HR administrator, I want to be notified when employees forget to check out, so that attendance records can be corrected.
   - The system runs a daily job to detect employees who checked in but never checked out, creating missing checkout violations that require resolution.

3. **Extended Break Violations**
   - As a manager, I want to track when employees take breaks longer than allowed, so that break time policies are enforced.
   - The system monitors break duration and flags violations when breaks exceed the maximum allowed time (e.g., > 2 hours or > shift break allocation).

4. **Early Departure Tracking**
   - As a manager, I want to know when employees leave before their shift ends, so that I can understand attendance patterns.
   - The system detects check-outs that occur before shift end time (accounting for grace period) and records early departure violations.

## Spec Scope

1. **Violation Detection Service** - Core service that checks attendance records and summaries for policy violations
2. **Violation Types** - Support for late-arrival, early-departure, missing-checkout, and extended-break violations
3. **Severity Levels** - Assign severity (minor, moderate, major) based on violation magnitude
4. **Grace Period Support** - Configurable grace periods for late arrival and early departure
5. **Real-time Detection** - Detect violations as attendance events occur
6. **Scheduled Detection** - Daily job to detect missing checkouts and end-of-day violations
7. **Violation Model** - Database table to store violation records with context

## Out of Scope

- Violation approval/dispute workflow (separate spec)
- Custom violation types per tenant
- Violation point system or progressive discipline
- Automatic penalties or actions based on violations

## Expected Deliverable

1. `AttendanceViolation` model with violation type, severity, and metadata
2. `ViolationDetector` service that checks for all violation types
3. Integration with `ProcessAttendanceEvent` job for real-time detection
4. Scheduled command `DetectMissingCheckoutsCommand` for daily detection
5. Configurable grace periods in tenant settings
6. API endpoints to query violations with filtering
7. Comprehensive unit and feature tests for all violation types

## Spec Documentation

- Tasks: @.agent-os/specs/2025-10-05-violation-detection-engine/tasks.md
- Technical Specification: @.agent-os/specs/2025-10-05-violation-detection-engine/sub-specs/technical-spec.md
- Database Schema: @.agent-os/specs/2025-10-05-violation-detection-engine/sub-specs/database-schema.md
