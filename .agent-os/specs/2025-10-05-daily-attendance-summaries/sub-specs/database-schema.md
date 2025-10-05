# Database Schema

This is the database schema implementation for the spec detailed in @.agent-os/specs/2025-10-05-daily-attendance-summaries/spec.md

> Created: 2025-10-05
> Version: 1.0.0

## Schema Changes

### New Table: `daily_attendance_summaries`

**Migration**: `database/migrations/tenant/YYYY_MM_DD_HHMMSS_create_daily_attendance_summaries_table.php`

```php
Schema::create('daily_attendance_summaries', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employee_id')->constrained()->cascadeOnDelete()
        ->comment('Employee this summary belongs to');
    $table->date('date')
        ->comment('Date of attendance (single day)');
    $table->time('first_check_in')->nullable()
        ->comment('First check-in time of the day');
    $table->time('last_check_out')->nullable()
        ->comment('Last check-out time of the day');
    $table->integer('total_work_minutes')->default(0)
        ->comment('Total work time in minutes (excluding breaks)');
    $table->integer('total_break_minutes')->default(0)
        ->comment('Total break time in minutes');
    $table->integer('overtime_minutes')->default(0)
        ->comment('Overtime work in minutes');
    $table->enum('status', ['present', 'absent', 'half-day', 'on-leave', 'holiday'])
        ->comment('Daily attendance status');
    $table->boolean('is_complete')->default(false)
        ->comment('True when employee has checked out (day is finalized)');
    $table->timestamps();

    // Indexes for efficient querying
    $table->unique(['employee_id', 'date'], 'uq_employee_date');
    $table->index('date', 'idx_date');
    $table->index(['date', 'status'], 'idx_date_status');
    $table->index(['employee_id', 'status'], 'idx_employee_status');
});
```

**Column Details**:
- `employee_id`: Foreign key to employees table, cascades on delete
- `date`: The calendar date for this summary (not datetime)
- `first_check_in` & `last_check_out`: Time values for the day's boundaries
- `total_work_minutes`: Calculated work time excluding breaks (integer for precision)
- `total_break_minutes`: Total break time taken
- `overtime_minutes`: Work beyond expected shift hours
- `status`: Enum with 5 possible values:
  - `present`: Normal working day with sufficient hours
  - `absent`: No attendance records for the day
  - `half-day`: Work hours < 50% of expected hours
  - `on-leave`: Approved leave (from shift override)
  - `holiday`: Company holiday (from shift override)
- `is_complete`: False if employee is still checked in, true after check-out

**Indexes**:
- `uq_employee_date`: Unique constraint ensures one summary per employee per day
- `idx_date`: Fast filtering by date (common for daily reports)
- `idx_date_status`: Efficient queries like "all absent employees on 2025-10-05"
- `idx_employee_status`: Useful for employee-specific status filtering

**Rationale**:
- Stores minutes as integers to avoid floating-point precision issues
- Separate work and break time enables accurate overtime calculation
- `is_complete` flag allows distinguishing ongoing days from finalized days
- Status enum provides quick filtering for reports without recalculation
- Unique constraint prevents duplicate summaries and enables upsert pattern

### Relationship Impact

**AttendanceRecord Model** - Add relationship:
```php
public function dailySummary(): BelongsTo
{
    return $this->belongsTo(DailyAttendanceSummary::class, 'employee_id', 'employee_id')
        ->whereDate('date', $this->recorded_at->toDateString());
}
```

**Employee Model** - Add relationship:
```php
public function dailySummaries(): HasMany
{
    return $this->hasMany(DailyAttendanceSummary::class);
}

public function getSummaryForDate(Carbon $date): ?DailyAttendanceSummary
{
    return $this->dailySummaries()->where('date', $date->toDateString())->first();
}
```

## Migrations

### Migration Order

1. Ensure `employees` table exists (prerequisite)
2. Create `daily_attendance_summaries` table
3. No data migration needed (summaries will be generated on-demand)

### Rollback Strategy

```php
public function down()
{
    Schema::dropIfExists('daily_attendance_summaries');
}
```

**Impact**: Rolling back will delete all summary records but not affect source attendance records. Summaries can be regenerated using the recalculation command.
