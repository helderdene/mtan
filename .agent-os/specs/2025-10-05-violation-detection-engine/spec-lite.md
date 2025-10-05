# Violation Detection Engine - Lite Summary

Automatically detect attendance policy violations (late arrival, early departure, missing checkout, extended breaks) in real-time and via scheduled jobs. Assigns severity levels based on magnitude, logs violations with detailed context, and integrates with notification system for manager alerts.

## Key Points
- Real-time detection during attendance event processing (late arrival, early departure, extended breaks)
- Scheduled daily job for missing checkout detection
- Severity levels (minor, moderate, major) calculated based on violation magnitude
- Configurable grace periods per tenant for late arrival and early departure
- Violation metadata includes shift times, actual times, and deviation minutes for detailed reporting
