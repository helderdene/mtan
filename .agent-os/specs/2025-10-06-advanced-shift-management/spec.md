# Spec Requirements Document

> Spec: Advanced Shift Management (Rotating, Flexible, Overnight Shifts)
> Created: 2025-10-06

## Overview

Extend the existing shift system to support rotating shifts (weekly/monthly rotation patterns), flexible shifts (variable start/end times), and enhanced overnight shift handling with proper boundary management and pattern-based scheduling.

## User Stories

### Rotating Shift Patterns

As an HR admin, I want to create rotating shift patterns where employees automatically cycle through different shifts (morning, afternoon, night) on a weekly or monthly basis, so that I don't have to manually reassign shifts repeatedly.

The system allows creating rotation patterns with defined sequences (e.g., Week 1: Morning, Week 2: Afternoon, Week 3: Night, repeat) and assigning employees to these patterns. The rotation automatically advances based on the configured cycle (weekly, bi-weekly, monthly) and employees see their current and upcoming shifts in their portal.

### Flexible Shift Configuration

As an HR admin, I want to create flexible shifts with variable start/end times or core hours requirements, so that I can accommodate employees with flexible work arrangements while maintaining attendance tracking.

The system supports flexible shift types where employees can check-in within a time window (e.g., 8:00-10:00 AM) and the expected work duration is calculated from their actual check-in time. Violation detection adjusts to flexible boundaries and work hour calculations use actual check-in as the reference point.

### Enhanced Overnight Shift Management

As an HR admin, I want to properly configure overnight shifts that cross midnight with clear date boundaries, so that attendance records are correctly assigned to the right day and work hours are calculated accurately.

The system extends existing overnight shift support with explicit overnight flag, proper date assignment rules (check-in date owns the record), and violation detection that respects the shift crossing midnight boundaries. Reports show overnight shift attendance clearly marked with correct date attribution.

## Spec Scope

1. **Rotating Shift Patterns** - Create rotation patterns with cycle configuration (weekly, bi-weekly, monthly) and shift sequences
2. **Employee Rotation Assignment** - Assign employees to rotation patterns with start date and automatic advancement
3. **Flexible Shift Types** - New shift type with check-in window, core hours, and flexible boundaries
4. **Overnight Shift Enhancements** - Add `is_overnight` flag, improve date boundary handling, enhance violation detection
5. **Shift Calendar View** - Visual calendar showing employee's shift schedule for next 30 days with rotation preview

## Out of Scope

- Shift swap/trade functionality between employees (Phase 4)
- Automatic shift assignment based on business rules/AI (Phase 5)
- Shift bidding system (Phase 5)
- Integration with external workforce management systems (Phase 4)

## Expected Deliverable

1. Admin can create rotating shift patterns and assign employees with automatic rotation advancement
2. Admin can create flexible shifts with variable check-in windows and proper work hour calculation
3. System properly handles overnight shifts with correct date attribution and violation detection
4. Employees can view their shift schedule for next 30 days including rotation preview
