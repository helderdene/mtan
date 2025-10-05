# Database Schema

This is the database schema implementation for the spec detailed in @.agent-os/specs/2025-10-05-smart-direction-detection/spec.md

> Created: 2025-10-05
> Version: 1.0.0

## Schema Changes

### Add Columns to `attendance_records` Table

**Migration**: `database/migrations/tenant/YYYY_MM_DD_HHMMSS_add_direction_confidence_to_attendance_records.php`

```php
Schema::table('attendance_records', function (Blueprint $table) {
    $table->integer('confidence_score')->nullable()->after('direction')
        ->comment('Direction detection confidence score (0-100)');
    $table->text('detection_reason')->nullable()->after('confidence_score')
        ->comment('Human-readable explanation of direction detection');
});
```

**Columns**:
- `confidence_score` (integer, nullable): Stores the confidence score (0-100) from direction detection
- `detection_reason` (text, nullable): Stores human-readable explanation like "Near shift start (9:05 AM), last record was check-out"

**Rationale**:
- Enables audit trail of how direction was determined
- Helps identify low-confidence detections for manual review
- Supports future ML training by providing labeled confidence data

### Index Optimization

No new indexes required. Existing indexes on `employee_id` and `recorded_at` are sufficient for last record lookups.

**Existing indexes used by direction detection**:
```php
$table->index(['employee_id', 'recorded_at']); // For finding last record
$table->index(['employee_id', 'direction', 'recorded_at']); // For break validation
```

## Migrations

1. Create migration to add `confidence_score` and `detection_reason` columns to `attendance_records` table
2. Run migration on all existing tenant databases
3. Backfill confidence scores for existing records (optional, can be null for historical data)
