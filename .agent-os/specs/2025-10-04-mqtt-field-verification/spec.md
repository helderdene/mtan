# Spec Requirements Document

> Spec: MQTT Recognition Event Field Verification
> Created: 2025-10-04
> Status: Planning

## Overview

This spec ensures all MQTT recognition event fields sent from biometric attendance devices are properly mapped, validated, and handled throughout the attendance processing pipeline. The system must correctly extract all fields from incoming MQTT payloads, map them to appropriate DTOs, process them in the attendance logic, and handle missing or invalid fields gracefully with proper logging.

Currently, the MQTT integration receives recognition events with multiple fields (employee identification, timing, biometric scores, environmental data, device metadata), but we need to verify that every field is:
- Properly extracted from the MQTT payload
- Mapped to the AttendanceEventDTO structure
- Utilized in the ProcessAttendanceEvent job where applicable
- Validated for presence and format
- Logged when missing, invalid, or unexpected

This verification ensures no data loss between device and application, proper error handling for malformed messages, and complete observability of the MQTT integration layer.

## User Stories

**As a system administrator**, I want all MQTT recognition event fields to be properly captured and logged, so that I can debug device integration issues and ensure no data is lost during processing.

**As a developer**, I want comprehensive tests covering all MQTT field mappings, so that I can confidently refactor the attendance processing logic without introducing field mapping bugs.

**As a compliance officer**, I want all biometric recognition metadata (similarity scores, verification status, timestamps) to be properly stored, so that attendance records have complete audit trails.

**As a system operator**, I want clear error messages when MQTT messages have missing or invalid fields, so that I can identify device configuration issues or firmware problems quickly.

**As a product owner**, I want optional fields (temperature, mask status, photo) to be handled gracefully without breaking attendance processing, so that the system works with various device models and configurations.

## Spec Scope

**In Scope:**

1. **Field Mapping Verification**
   - Audit all fields defined in MQTT payload structure (CLAUDE.md reference)
   - Verify AttendanceEventDTO contains corresponding properties
   - Ensure MessageHandler extracts all fields from JSON payload
   - Validate ProcessAttendanceEvent job uses all relevant fields

2. **DTO Completeness**
   - Review AttendanceEventDTO field definitions
   - Add missing fields from MQTT payload specification
   - Define proper types for all fields (string, int, float, datetime, etc.)
   - Document which fields are required vs. optional

3. **Test Coverage**
   - Unit tests for AttendanceEventDTO creation with all fields
   - Unit tests for MessageHandler field extraction
   - Integration tests for complete MQTT → DTO → Job flow
   - Tests for missing field scenarios
   - Tests for invalid field type scenarios

4. **Error Handling & Logging**
   - Log warnings for missing optional fields
   - Log errors for missing required fields
   - Log validation failures (invalid timestamps, negative similarity scores, etc.)
   - Add contextual information (device_id, tenant_id) to all logs

5. **Documentation**
   - Document expected MQTT payload structure
   - Document AttendanceEventDTO field mapping
   - Document which fields are used where in the processing pipeline
   - Add inline comments for field extraction logic

## Out of Scope

**Not Included:**

1. **New MQTT Fields** - Adding fields not currently sent by devices (future feature)
2. **Protocol Changes** - Modifying MQTT topic structure or QoS levels
3. **Device Firmware** - Changes to device-side message generation
4. **Field Transformations** - Complex data transformations or enrichment (keep as simple mapping)
5. **Database Schema Changes** - Modifying attendance_records table structure (unless absolutely necessary)
6. **API Endpoints** - REST API changes or webhook payload modifications
7. **UI Changes** - Frontend display of additional fields

## Expected Deliverable

**Acceptance Criteria:**

1. ✅ All 11 MQTT payload fields properly mapped to AttendanceEventDTO
2. ✅ Unit tests achieving 100% coverage for field extraction logic
3. ✅ Integration tests covering complete MQTT message processing with all fields
4. ✅ Error handling tests for 5+ edge cases (missing fields, invalid types, null values)
5. ✅ Logging implemented for all validation failures with contextual data
6. ✅ Documentation updated with field mapping reference table
7. ✅ Code review passed with no field mapping gaps identified

**Definition of Done:**

- All tests passing (`composer test`)
- Code formatted (`./vendor/bin/pint`)
- Documentation reviewed and approved
- No regression in existing attendance processing functionality
- Logs verified in staging environment with real device messages

## Spec Documentation

- Tasks: @.agent-os/specs/2025-10-04-mqtt-field-verification/tasks.md
- Technical Specification: @.agent-os/specs/2025-10-04-mqtt-field-verification/sub-specs/technical-spec.md
