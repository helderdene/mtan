# Spec Requirements Document

> Spec: Real-time Violation Notifications
> Created: 2025-10-05
> Status: Planning

## Overview

Implement real-time notification system that alerts managers and HR administrators immediately when attendance violations are detected. The system sends email notifications with violation details, supports configurable notification preferences per manager, and integrates with the existing violation detection engine to ensure timely awareness of policy breaches.

## User Stories

1. **Immediate Manager Notification**
   - As a manager, I want to receive email notifications when my team members violate attendance policies, so that I can address issues promptly.
   - When a violation is created, the system immediately sends an email to the employee's direct manager with violation details (type, severity, time, employee name).

2. **Severity-Based Filtering**
   - As a manager, I want to configure which violation severities trigger notifications, so that I'm only alerted for significant violations.
   - Managers can set notification preferences (e.g., only major violations, or moderate and above) to reduce notification fatigue.

3. **Daily Violation Digest**
   - As an HR administrator, I want to receive a daily summary of all violations, so that I can monitor attendance patterns across the organization.
   - The system sends a daily digest email at configurable time (e.g., 8 AM) with aggregated violation statistics and details.

## Spec Scope

1. **Notification Classes** - Create Laravel Notification classes for violation alerts and daily digests
2. **Email Templates** - Design markdown email templates for violation notifications
3. **Manager Resolution** - Logic to identify employee's manager for notification routing
4. **Notification Preferences** - Database table and model for manager notification settings
5. **Event Integration** - Listen to `ViolationDetected` event and dispatch notifications
6. **Production Email Setup** - Configure production email service (SES/Postmark/SMTP)
7. **Daily Digest Command** - Scheduled command to send daily violation summaries

## Out of Scope

- SMS notifications (future enhancement)
- Push notifications (future enhancement)
- Slack/Teams integration (future enhancement)
- In-app notification center (future enhancement)
- Notification acknowledgment tracking

## Expected Deliverable

1. `ViolationNotification` and `DailyViolationDigest` notification classes
2. Markdown email templates with violation details
3. `NotificationPreference` model for manager settings
4. Event listener for `ViolationDetected` event
5. Production email service configuration instructions
6. Scheduled command for daily digest
7. Unit and feature tests for notification dispatch

## Spec Documentation

- Tasks: @.agent-os/specs/2025-10-05-violation-notifications/tasks.md
- Technical Specification: @.agent-os/specs/2025-10-05-violation-notifications/sub-specs/technical-spec.md
