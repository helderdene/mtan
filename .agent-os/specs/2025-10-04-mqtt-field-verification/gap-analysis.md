# MQTT Field Verification - Gap Analysis

> Created: 2025-10-04
> Status: Audit Complete

## Executive Summary

The current implementation handles **9 out of 11** MQTT payload fields (82% coverage). Missing fields are non-critical but should be added for complete observability and debugging capabilities.

## Field Coverage Analysis

### ✅ Currently Implemented Fields (9/11)

| Field Name | DTO Property | Source in Payload | Type | Status |
|------------|--------------|-------------------|------|--------|
| device_id | `device_id` | Extracted from topic | string | ✓ Implemented |
| custom_id | `person_id` | `info.customId` | string | ✓ Implemented |
| time | `timestamp` | `info.time` | datetime | ✓ Implemented |
| similarity1 | `similarity` | `info.similarity1` / 100 | float (0-1) | ✓ Implemented |
| temperature | `temperature` | `info.temperature` | float | ✓ Implemented |
| isNoMask | `mask` | `info.isNoMask` === '0' | boolean | ✓ Implemented |
| pic | `image_url` | `info.pic` | string (base64) | ✓ Implemented |
| VerifyStatus | `status` | `info.VerifyStatus` | string | ✓ Implemented |
| event_type | `event_type` | Extracted from topic | string | ✓ Implemented |

### ❌ Missing Fields (2/11)

| Field Name | Expected DTO Property | Source in Payload | Type | Priority | Impact |
|------------|----------------------|-------------------|------|----------|--------|
| RecordID | `record_id` | `info.RecordID` | string | HIGH | Critical for deduplication and event tracking |
| personName | `person_name` | `info.personName` | string | LOW | Nice-to-have for logging/debugging |

### 📊 Database Schema Gap Analysis

**Current `attendance_records` table columns:**
- ✓ `employee_id` (foreign key)
- ✓ `device_id` (foreign key)
- ✓ `recorded_at` (timestamp)
- ✓ `direction` (enum)
- ✓ `recognition_score` (decimal)
- ✓ `created_at`, `updated_at`

**Missing columns for full MQTT field storage:**
- ❌ `record_id` (string, nullable) - Device-generated unique ID
- ❌ `person_name` (string, nullable) - Employee name from device
- ❌ `device_name` (string, nullable) - Device location (`facesluiceName`)
- ❌ `verify_status` (string, nullable) - Device verification result
- ❌ `temperature` (decimal, nullable) - Body temperature reading
- ❌ `mask_status` (boolean, nullable) - Mask detection result
- ❌ `photo_path` (string, nullable) - Stored photo reference

## Field Mapping Issues

### 1. **Field Name Inconsistencies**

The spec uses `custom_id` but DTO uses `person_id`:
- **Spec Field**: `custom_id`
- **DTO Field**: `person_id`
- **Payload Key**: `info.customId`
- **Recommendation**: Rename DTO property to `custom_id` for consistency

### 2. **Similarity Score Normalization**

Current implementation divides `similarity1` by 100:
```php
similarity: isset($info['similarity1']) ? (float) $info['similarity1'] / 100 : null
```

- **Device Format**: 0-100 (integer or float)
- **DTO Format**: 0-1 (float)
- **Database**: `decimal(5,4)` supports 0.0000-1.0000
- **Issue**: Spec expects 0-100 range
- **Recommendation**: Keep as 0-100 in DTO, store as 0-1 in database

### 3. **Mask Status Mapping**

Current logic: `$info['isNoMask'] === '0'` (string comparison)
- **Device Field**: `isNoMask` (string "0" or "1")
- **DTO Field**: `mask` (boolean)
- **Logic**: `'0'` = has mask (true), `'1'` = no mask (false)
- **Issue**: Confusing logic - should use `mask_status` instead
- **Recommendation**: Use `info.mask_status` if available, fallback to `isNoMask`

## ProcessAttendanceEvent Job Analysis

**Fields Currently Used:**
1. ✓ `device_id` - To look up device in tenant database
2. ✓ `person_id` (custom_id) - To look up employee
3. ✓ `timestamp` - For `recorded_at`
4. ✓ `similarity` - For `recognition_score`
5. ✓ `event_type` - To determine stranger vs. recognized

**Fields NOT Used (but available in DTO):**
- ❌ `temperature` - Not stored in database
- ❌ `mask` - Not stored in database
- ❌ `image_url` - Not stored in database
- ❌ `status` - Not stored in database

**Missing Critical Field:**
- ❌ `record_id` - Should be used for deduplication instead of 1-minute window

## Validation Gaps

### Required Field Validation

**Current Validation:**
- ✓ Topic format validation
- ✓ JSON parsing validation
- ✓ Similarity range validation (0-1)
- ❌ Missing `custom_id` validation (allows null)
- ❌ Missing `RecordID` validation
- ❌ Missing timestamp format validation
- ❌ Missing temperature range validation (30-45°C)

### Optional Field Handling

**Current Behavior:**
- ✓ Returns `null` for missing optional fields
- ✓ No exceptions thrown for missing optional fields
- ❌ No logging for missing optional fields

### Data Type Validation

**Missing Validations:**
- Temperature range: 30-45°C
- Mask status: 0 or 1 only
- Similarity score: 0-100 range (currently validates 0-1)
- Timestamp: Multiple format support needed

## Logging Gaps

### Error Logging
- ✓ Invalid JSON payload
- ✓ Invalid topic format
- ❌ Missing required fields (not specific)
- ❌ Invalid field values (e.g., temperature out of range)

### Debug Logging
- ❌ No logging for missing optional fields
- ❌ No logging for field extraction process
- ❌ No logging for validation results

### Info Logging
- ✓ Event dispatched to queue
- ✓ Basic payload structure
- ❌ No logging for full field mapping

## Recommended Changes Priority

### 🔴 HIGH Priority (Required for Spec Completion)

1. **Add `record_id` to DTO**
   - Extract from `info.RecordID`
   - Use for deduplication instead of timestamp window
   - Add to database schema

2. **Rename `person_id` to `custom_id` in DTO**
   - Align with spec and CLAUDE.md documentation
   - Update all references

3. **Add required field validation**
   - Validate `custom_id` is not null
   - Validate `RecordID` is not null
   - Throw descriptive exceptions

4. **Update similarity score handling**
   - Keep 0-100 range in DTO (don't divide by 100)
   - Validate 0-100 range
   - Convert to 0-1 when storing in database

5. **Add comprehensive logging**
   - Log missing required fields (error level)
   - Log missing optional fields (debug level)
   - Log invalid field values (warning level)

### 🟡 MEDIUM Priority (Important for Observability)

6. **Add database columns for metadata**
   - `record_id` (unique index for deduplication)
   - `verify_status` (for audit trail)
   - `temperature` (for health screening compliance)
   - `mask_status` (for compliance tracking)
   - `photo_path` (for incident investigation)

7. **Add `person_name` to DTO**
   - Extract from `info.personName`
   - Log for debugging employee sync issues
   - Add to database for audit trail

8. **Improve timestamp parsing**
   - Support multiple formats: 'Y-m-d H:i:s', 'Y/m/d H:i:s', ISO 8601
   - Fallback to `strtotime()`
   - Validate timestamp is not in future

### 🟢 LOW Priority (Nice-to-Have)

9. **Add `device_name` field**
   - Extract from `info.facesluiceName`
   - Store for reporting purposes
   - Denormalize for query performance

10. **Add photo storage handling**
    - Decode base64 from `info.pic`
    - Store in S3/disk storage
    - Save path/URL in database
    - Implement cleanup policy (30 days retention)

## Test Coverage Gaps

### Unit Tests Needed
- [ ] DTO creation with all 11 fields
- [ ] DTO validation for required fields
- [ ] DTO validation for field ranges
- [ ] MessageHandler field extraction for all fields
- [ ] Timestamp parsing with multiple formats

### Integration Tests Needed
- [ ] End-to-end MQTT → DTO → Job → Database with all fields
- [ ] Missing required field handling
- [ ] Missing optional field handling
- [ ] Invalid field value handling
- [ ] Duplicate `record_id` detection

### Edge Case Tests Needed
- [ ] Empty string vs. null fields
- [ ] Wrong data types in payload
- [ ] Extra unknown fields in payload
- [ ] Special characters in string fields
- [ ] Very large base64 images

## Conclusion

**Overall Implementation Status**: **82% Complete**

**Critical Gaps**:
1. Missing `RecordID` field (needed for proper deduplication)
2. Missing `custom_id` validation (allows null values)
3. Incomplete database schema (missing metadata columns)
4. Limited validation (no range checks for temperature, similarity)
5. Limited logging (no debug logs for field extraction)

**Next Steps**:
1. Complete Phase 2: Update DTO with all 11 fields
2. Complete Phase 3: Add missing database columns
3. Complete Phase 4: Add comprehensive tests
4. Complete Phase 5: Add comprehensive logging
