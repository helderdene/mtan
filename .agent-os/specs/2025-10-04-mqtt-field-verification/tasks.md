# Spec Tasks

These are the tasks to be completed for the spec detailed in @.agent-os/specs/2025-10-04-mqtt-field-verification/spec.md

> Created: 2025-10-04
> Status: Ready for Implementation

## Tasks

### Phase 1: Audit Current Implementation

- [ ] **Task 1.1**: Review existing `AttendanceEventDTO` class
  - Check if file exists at `app/Domain/Attendance/DTOs/AttendanceEventDTO.php`
  - List all current fields and their types
  - Compare against 11 required MQTT fields from spec
  - Document any missing fields

- [ ] **Task 1.2**: Review `MessageHandler` field extraction logic
  - Check if file exists at `app/Infrastructure/MQTT/MessageHandler.php`
  - Review `handleRecognitionEvent()` method implementation
  - Verify which fields are currently extracted from payload
  - Document extraction logic for each field

- [ ] **Task 1.3**: Review `ProcessAttendanceEvent` job field usage
  - Check if file exists at `app/Jobs/ProcessAttendanceEvent.php`
  - Identify which DTO fields are actually used in processing
  - Document unused fields and their intended purpose
  - Verify `custom_id` is used for employee lookup (NOT `personId`)

- [ ] **Task 1.4**: Create gap analysis document
  - List all missing fields from DTO
  - List all fields extracted but not used
  - List all validation gaps
  - List all logging gaps

### Phase 2: Update DTO and Extraction

- [ ] **Task 2.1**: Implement/update `AttendanceEventDTO`
  - Add all 11 MQTT fields with correct types
  - Mark required fields (custom_id, RecordID, time, similarity1, device_id)
  - Mark optional fields (personId, personName, facesluiceName, VerifyStatus, temperature, mask_status, pic)
  - Implement constructor validation for required fields
  - Implement data type validation (similarity 0-100, temperature 30-45, mask_status 0/1)

- [ ] **Task 2.2**: Implement `validateRequiredFields()` method
  - Validate `custom_id` is not empty
  - Validate `recordId` is not empty
  - Validate `similarityScore` is between 0 and 100
  - Throw `InvalidArgumentException` with descriptive messages

- [ ] **Task 2.3**: Implement `validateDataTypes()` method
  - Validate `temperature` range if present (30-45°C)
  - Validate `maskStatus` is 0 or 1 if present
  - Validate `timestamp` is valid DateTimeImmutable

- [ ] **Task 2.4**: Implement `toArray()` method
  - Convert all DTO fields to array format
  - Format timestamp as 'Y-m-d H:i:s'
  - Include all fields (required and optional)

- [ ] **Task 2.5**: Update `MessageHandler::createAttendanceEventDTO()`
  - Implement `extractRequiredField()` helper for required fields
  - Implement `extractOptionalField()` helper for optional fields
  - Extract all 11 fields from MQTT payload
  - Pass all fields to DTO constructor

- [ ] **Task 2.6**: Implement timestamp parsing with multiple formats
  - Support 'Y-m-d H:i:s' format
  - Support 'Y/m/d H:i:s' format
  - Support ISO 8601 formats
  - Fallback to strtotime() for flexibility
  - Throw exception if all parsing attempts fail

- [ ] **Task 2.7**: Implement device_id extraction from MQTT topic
  - Parse topic pattern: `mqtt/face/{device_id}/Rec`
  - Use regex to extract device_id
  - Throw exception if topic format is invalid

### Phase 3: Update Database Schema (if needed)

- [ ] **Task 3.1**: Check `attendance_records` table schema
  - Verify columns exist for all DTO fields
  - Identify missing columns (device_name, verify_status, temperature, mask_status, photo_path)

- [ ] **Task 3.2**: Create migration for missing fields (if needed)
  - Add `device_name` (string, nullable)
  - Add `verify_status` (string, nullable)
  - Add `temperature` (decimal, nullable)
  - Add `mask_status` (tinyint, nullable)
  - Add `photo_path` (string, nullable)

- [ ] **Task 3.3**: Update `AttendanceRecord` model
  - Add new fields to `$fillable` array
  - Add type casts for `temperature` (decimal), `mask_status` (integer)
  - Update factory if needed

### Phase 4: Implement Comprehensive Tests

- [ ] **Task 4.1**: Create `tests/Unit/AttendanceEventDTOTest.php`
  - Test: creates DTO with all required fields
  - Test: creates DTO with all optional fields
  - Test: throws exception for missing `custom_id`
  - Test: throws exception for missing `recordId`
  - Test: throws exception for invalid similarity score (< 0 or > 100)
  - Test: throws exception for invalid temperature (< 30 or > 45)
  - Test: throws exception for invalid mask_status (not 0 or 1)
  - Test: `toArray()` includes all fields with correct format

- [ ] **Task 4.2**: Create `tests/Unit/MessageHandlerTest.php`
  - Test: extracts all 11 fields from valid MQTT payload
  - Test: handles missing optional fields gracefully (sets to null)
  - Test: throws exception for missing `custom_id`
  - Test: throws exception for missing `RecordID`
  - Test: throws exception for missing `time`
  - Test: throws exception for missing `similarity1`
  - Test: parses multiple timestamp formats correctly
  - Test: extracts device_id from MQTT topic
  - Test: throws exception for invalid topic format

- [ ] **Task 4.3**: Create `tests/Feature/MQTTRecognitionFlowTest.php`
  - Test: processes complete MQTT event with all 11 fields
  - Test: creates `AttendanceRecord` with all fields populated
  - Test: handles missing optional fields in end-to-end flow
  - Test: logs warning for unrecognized employee (custom_id not found)
  - Test: logs stranger event for high similarity unrecognized face
  - Test: verifies `custom_id` used for employee lookup (NOT `personId`)

- [ ] **Task 4.4**: Create edge case tests
  - Test: payload with empty string fields
  - Test: payload with null fields
  - Test: payload with wrong data types (string instead of float)
  - Test: payload with extra unknown fields (should be ignored)
  - Test: payload with special characters in fields

- [ ] **Task 4.5**: Run all tests and verify 100% pass rate
  - Execute: `composer test`
  - Fix any failing tests
  - Verify code coverage for new classes

### Phase 5: Implement Logging

- [ ] **Task 5.1**: Add error logging for missing required fields
  - Log level: `error`
  - Include: field name, available fields, full payload
  - Location: `MessageHandler::extractRequiredField()`

- [ ] **Task 5.2**: Add debug logging for missing optional fields
  - Log level: `debug`
  - Include: field name, device_id
  - Location: `MessageHandler::extractOptionalField()`

- [ ] **Task 5.3**: Add warning logging for invalid field values
  - Log level: `warning`
  - Include: field name, invalid value, validation rule
  - Location: DTO validation methods

- [ ] **Task 5.4**: Add info logging for successful processing
  - Log level: `info`
  - Include: employee_id, custom_id, similarity_score, optional field presence flags
  - Location: `ProcessAttendanceEvent::handle()`

- [ ] **Task 5.5**: Add context to all log messages
  - Include `device_id` in all MQTT-related logs
  - Include `tenant_id` in all processing logs
  - Include `record_id` for event tracking

### Phase 6: Documentation

- [ ] **Task 6.1**: Create MQTT field mapping reference table
  - Document all 11 fields with types and examples
  - Mark required vs. optional
  - Document validation rules for each field
  - Add to technical spec or separate doc

- [ ] **Task 6.2**: Add inline code comments
  - Document why `custom_id` is used instead of `personId`
  - Explain timestamp parsing strategy
  - Document optional field handling logic

- [ ] **Task 6.3**: Update `CLAUDE.md` if needed
  - Add field usage guidelines
  - Update MQTT integration section with field details
  - Add examples of proper field extraction

- [ ] **Task 6.4**: Create troubleshooting guide
  - Common MQTT payload issues and solutions
  - How to debug missing fields
  - How to interpret field validation errors

### Phase 7: Code Review and Validation

- [ ] **Task 7.1**: Self code review
  - Verify all 11 fields are extracted
  - Verify no fields are silently dropped
  - Verify error handling covers all edge cases
  - Verify logging provides sufficient observability

- [ ] **Task 7.2**: Run code formatter
  - Execute: `./vendor/bin/pint`
  - Commit formatted code

- [ ] **Task 7.3**: Verify in staging environment
  - Deploy to staging
  - Monitor logs for MQTT messages
  - Verify all fields appear in attendance records
  - Test with real device messages if available

- [ ] **Task 7.4**: Performance verification
  - Ensure field extraction doesn't add significant latency
  - Verify queue processing time remains < 2 seconds
  - Check for memory leaks with photo field (base64 images)

## Definition of Done

- [ ] All 11 MQTT fields properly extracted and mapped to DTO
- [ ] All required field validation implemented
- [ ] All optional fields handled gracefully (null if missing)
- [ ] Unit tests achieving 100% coverage for field extraction logic
- [ ] Integration tests covering end-to-end MQTT processing with all fields
- [ ] Edge case tests for missing/invalid fields (5+ scenarios)
- [ ] Comprehensive logging for all validation failures
- [ ] Documentation updated with field mapping reference
- [ ] All tests passing: `composer test`
- [ ] Code formatted: `./vendor/bin/pint`
- [ ] Staging verification complete with real device messages
- [ ] No regression in existing attendance processing functionality
- [ ] Code review approved by team lead
