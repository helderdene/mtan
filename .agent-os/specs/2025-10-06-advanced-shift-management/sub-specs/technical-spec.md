# Technical Specification

This is the technical specification for the spec detailed in @.agent-os/specs/2025-10-06-advanced-shift-management/spec.md

## Technical Requirements

### Database Schema Changes

**New Table: `shift_rotation_patterns`**
```sql
CREATE TABLE shift_rotation_patterns (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    cycle_type ENUM('weekly', 'bi_weekly', 'monthly') NOT NULL,
    rotation_sequence JSON NOT NULL COMMENT 'Array of shift_ids in rotation order',
    description TEXT,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**New Table: `employee_shift_rotations`**
```sql
CREATE TABLE employee_shift_rotations (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    rotation_pattern_id BIGINT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    current_position INT NOT NULL DEFAULT 0 COMMENT 'Current index in rotation sequence',
    last_rotated_at DATE COMMENT 'Last rotation date',
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (rotation_pattern_id) REFERENCES shift_rotation_patterns(id) ON DELETE RESTRICT
);
```

**Modify Table: `shifts`**
```sql
ALTER TABLE shifts
ADD COLUMN shift_type ENUM('fixed', 'flexible', 'rotating') DEFAULT 'fixed' AFTER name,
ADD COLUMN is_overnight BOOLEAN DEFAULT false AFTER end_time,
ADD COLUMN flexible_checkin_start TIME AFTER is_overnight,
ADD COLUMN flexible_checkin_end TIME AFTER flexible_checkin_start,
ADD COLUMN core_hours_required DECIMAL(4,2) AFTER flexible_checkin_end,
ADD COLUMN description TEXT AFTER core_hours_required;
```

### Backend Requirements

- **Models:**
  - `ShiftRotationPattern` - Rotation pattern model with relationships
  - `EmployeeShiftRotation` - Employee rotation assignment with current position tracking
  - Update `Shift` model with new fields and flexible shift logic
- **Services:**
  - `RotationScheduler` - Calculate current shift for employee based on rotation pattern
  - `FlexibleShiftValidator` - Validate check-in within flexible window
  - Update `DirectionDetector` to support flexible shift boundaries
  - Update `SummaryCalculator` to handle flexible expected hours
- **Commands:**
  - `AdvanceShiftRotationsCommand` - Daily command to advance rotations (runs at midnight)
- **Controllers:**
  - `ShiftRotationPatternController` - CRUD for rotation patterns
  - Update `ShiftController` with flexible shift support

### Frontend Requirements

- **Page Components:**
  - `resources/js/pages/Shifts/RotationPatterns/Index.vue` - List rotation patterns
  - `resources/js/pages/Shifts/RotationPatterns/Create.vue` - Create rotation pattern
  - `resources/js/pages/Shifts/RotationPatterns/Edit.vue` - Edit rotation pattern
  - Update `resources/js/pages/Shifts/Create.vue` - Add flexible shift fields
- **UI Components:**
  - `RotationSequenceBuilder.vue` - Drag-drop shift sequence builder
  - `FlexibleShiftForm.vue` - Form for flexible shift configuration
  - `ShiftCalendarPreview.vue` - 30-day shift schedule preview
  - `RotationBadge.vue` - Badge showing rotation status

### UI/UX Specifications

- **Rotation Pattern Builder:**
  - Drag-and-drop interface to arrange shift sequence
  - Cycle type selector (weekly/bi-weekly/monthly)
  - Visual preview of rotation cycle
- **Flexible Shift Form:**
  - Time range pickers for check-in window (start-end)
  - Core hours input with validation (must be <= shift duration)
  - Visual indicator showing flexible vs fixed portions
- **Shift Calendar Preview (Employee View):**
  - 30-day calendar grid showing assigned shifts
  - Different colors for shift types (morning/afternoon/night)
  - Rotation indicator showing "Week 2 of 3" or similar
  - Overnight shifts clearly marked with moon icon

### Performance Requirements

- Rotation calculation completes in < 50ms per employee
- Shift calendar preview loads in < 500ms
- Advance rotations command processes 1000+ employees in < 2 minutes

## External Dependencies

None - uses existing Laravel, Vue 3, Inertia.js stack.
