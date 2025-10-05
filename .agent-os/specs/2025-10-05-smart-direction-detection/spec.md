# Spec Requirements Document

> Spec: Smart Direction Detection Algorithm
> Created: 2025-10-05
> Status: Planning

## Overview

Implement an intelligent direction detection algorithm that automatically determines check-in, check-out, break-start, or break-end for attendance events using multi-factor weighted scoring. This eliminates the need for separate entry/exit devices or manual direction selection, achieving 95%+ accuracy through analysis of last records, shift timing, historical patterns, and work duration rules.

## User Stories

1. **Automatic Direction Detection**
   - As an employee, I want the system to automatically detect whether I'm checking in or out, so that I don't need to manually select my action.
   - When an employee scans their face at any device, the system analyzes their last attendance record, current shift timing, and historical patterns to determine the most likely direction with a confidence score of 0-100.

2. **Intelligent Break Detection**
   - As an employee, I want the system to recognize when I'm starting or ending a break, so that my break time is accurately tracked.
   - The system detects break-start when an employee has already checked in and the timing aligns with typical break periods, and break-end when they return within reasonable break duration limits.

3. **Shift-Aware Detection**
   - As a manager, I want the system to use shift schedules to improve accuracy, so that employees near shift boundaries are correctly classified.
   - The algorithm weighs proximity to shift start/end times heavily, giving higher confidence to check-in near shift start and check-out near shift end.

## Spec Scope

1. **Multi-Factor Scoring System** - Implement weighted scoring algorithm that evaluates last record analysis (30%), shift timing proximity (35%), historical patterns (20%), and work duration rules (15%)
2. **Direction Confidence Calculation** - Calculate confidence scores (0-100) for each possible direction and select the highest scoring direction
3. **Last Record Analysis** - Analyze the employee's most recent attendance record to determine logical next direction
4. **Shift Timing Proximity** - Calculate distance from current time to shift start/end/break times and weight accordingly
5. **Overnight Shift Support** - Handle overnight shifts where shift end is on the next day
6. **Fallback Strategy** - Define default behavior when confidence scores are too low or ambiguous

## Out of Scope

- Historical pattern analysis (covered in separate spec)
- Machine learning-based prediction
- Location-based direction detection
- Manual direction override UI (future enhancement)

## Expected Deliverable

1. A `DirectionDetector` service class that accepts employee, timestamp, last record, and shift, returning direction and confidence score
2. Direction detection works for all shift types (fixed, rotating, overnight) with 95%+ accuracy in test scenarios
3. Comprehensive unit tests covering edge cases (shift boundaries, overnight shifts, no shift assigned, first check-in of day)

## Spec Documentation

- Tasks: @.agent-os/specs/2025-10-05-smart-direction-detection/tasks.md
- Technical Specification: @.agent-os/specs/2025-10-05-smart-direction-detection/sub-specs/technical-spec.md
- Database Schema: @.agent-os/specs/2025-10-05-smart-direction-detection/sub-specs/database-schema.md
