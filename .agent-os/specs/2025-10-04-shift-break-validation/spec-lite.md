# Shift Break Time Validation - Lite Summary

Add validation to shift creation/editing to ensure break times fall within shift hours and follow logical constraints (start < end, minimum duration). Prevent invalid break configurations through real-time frontend validation and enforced backend validation.

## Key Points
- Validate break times are within shift hours (including overnight shift support)
- Enforce break_start < break_end with minimum 1-minute duration
- Both frontend (Vue form validation) and backend (Laravel request validation) layers
- Clear error messages guide users to fix invalid inputs
- Optional breaks supported (both null or both provided, no partial configuration)
- Database schema already exists - validation logic only
