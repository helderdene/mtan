# Database Schema

This is the database schema implementation for the spec detailed in @.agent-os/specs/2025-10-06-advanced-shift-management/spec.md

## New Tables

### shift_rotation_patterns

Stores rotation pattern definitions with cycle configuration.

```sql
CREATE TABLE shift_rotation_patterns (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    cycle_type ENUM('weekly', 'bi_weekly', 'monthly') NOT NULL,
    rotation_sequence JSON NOT NULL COMMENT 'Array of shift_ids: [1, 2, 3]',
    description TEXT,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_active (is_active),
    INDEX idx_cycle_type (cycle_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Rationale:**
- `rotation_sequence` JSON stores ordered array of shift IDs for rotation cycle
- `cycle_type` determines rotation frequency (weekly, bi-weekly, monthly)
- `is_active` allows soft-disable without affecting existing assignments

### employee_shift_rotations

Tracks employee assignments to rotation patterns with current position.

```sql
CREATE TABLE employee_shift_rotations (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    rotation_pattern_id BIGINT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    current_position INT NOT NULL DEFAULT 0 COMMENT 'Current index in rotation_sequence',
    last_rotated_at DATE COMMENT 'Last date rotation advanced',
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (rotation_pattern_id) REFERENCES shift_rotation_patterns(id) ON DELETE RESTRICT,

    INDEX idx_employee (employee_id),
    INDEX idx_rotation_pattern (rotation_pattern_id),
    INDEX idx_active (is_active),
    UNIQUE KEY unique_active_rotation (employee_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Rationale:**
- `current_position` tracks which shift in sequence employee is currently on
- `last_rotated_at` enables daily command to advance only when cycle period elapsed
- Unique constraint on `(employee_id, is_active)` ensures one active rotation per employee

## Modified Tables

### shifts

Add columns for flexible shifts and overnight flag.

```sql
ALTER TABLE shifts
ADD COLUMN shift_type ENUM('fixed', 'flexible', 'rotating') DEFAULT 'fixed' AFTER name,
ADD COLUMN is_overnight BOOLEAN DEFAULT false AFTER end_time,
ADD COLUMN flexible_checkin_start TIME AFTER is_overnight,
ADD COLUMN flexible_checkin_end TIME AFTER flexible_checkin_start,
ADD COLUMN core_hours_required DECIMAL(4,2) AFTER flexible_checkin_end,
ADD COLUMN description TEXT AFTER core_hours_required;
```

**Rationale:**
- `shift_type` differentiates fixed, flexible, and rotating shift types
- `is_overnight` explicitly marks shifts crossing midnight for proper date handling
- `flexible_checkin_start/end` defines check-in window for flexible shifts
- `core_hours_required` specifies minimum work hours for flexible shifts
- `description` provides additional context for complex shift configurations

## Migration Files

**Migration 1:** `2025_10_06_000001_create_shift_rotation_patterns_table.php`
**Migration 2:** `2025_10_06_000002_create_employee_shift_rotations_table.php`
**Migration 3:** `2025_10_06_000003_add_advanced_shift_fields_to_shifts_table.php`
