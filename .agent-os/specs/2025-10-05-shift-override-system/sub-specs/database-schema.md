# Database Schema

This is the database schema implementation for the spec detailed in @.agent-os/specs/2025-10-05-shift-override-system/spec.md

> Created: 2025-10-05
> Version: 1.0.0

## Schema Changes

### New Table: `shift_overrides`

**Migration**: `database/migrations/tenant/YYYY_MM_DD_HHMMSS_create_shift_overrides_table.php`

```php
Schema::create('shift_overrides', function (Blueprint $table) {
    $table->id();
    $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete()
        ->comment('Shift this override applies to. Null for employee off-days.');
    $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete()
        ->comment('Specific employee. Null for company-wide overrides.');
    $table->date('override_date')
        ->comment('Date when override is active');
    $table->enum('type', ['holiday', 'off-day', 'half-day', 'custom-shift'])
        ->comment('Type of override');
    $table->time('custom_start_time')->nullable()
        ->comment('Custom shift start time (for half-day/custom-shift)');
    $table->time('custom_end_time')->nullable()
        ->comment('Custom shift end time (for half-day/custom-shift)');
    $table->string('reason')->nullable()
        ->comment('Reason for override (e.g., "New Year Holiday")');
    $table->timestamps();

    // Indexes for efficient querying
    $table->index(['override_date', 'shift_id'], 'idx_override_date_shift');
    $table->index(['override_date', 'employee_id'], 'idx_override_date_employee');

    // Prevent duplicate overrides
    $table->unique(['override_date', 'shift_id', 'employee_id'], 'uq_override_date_shift_employee');
});
```

**Column Details**:
- `shift_id`: Nullable - null means override applies regardless of shift (e.g., employee off-day)
- `employee_id`: Nullable - null means company-wide override
- `override_date`: The specific date when override is active
- `type`: Enum with 4 values:
  - `holiday`: Company holiday, no work expected
  - `off-day`: Individual employee off (approved leave)
  - `half-day`: Modified shift with shorter hours
  - `custom-shift`: Temporary shift time change
- `custom_start_time` & `custom_end_time`: Required for half-day and custom-shift types
- `reason`: Optional human-readable description

**Indexes**:
- `idx_override_date_shift`: Fast lookup for company-wide shift overrides
- `idx_override_date_employee`: Fast lookup for employee-specific overrides
- `uq_override_date_shift_employee`: Prevents duplicate override definitions

**Rationale**:
- Supports both company-wide and employee-specific overrides with nullable foreign keys
- Enum type ensures only valid override types are stored
- Unique constraint prevents conflicting override definitions
- Indexes optimize the override resolution queries (most common access pattern)

### Data Migration Considerations

**Seeder** (optional): `database/seeders/ShiftOverrideSeeder.php`

Example seed data for testing:
```php
// New Year 2025 - Company holiday
ShiftOverride::create([
    'override_date' => '2025-01-01',
    'type' => 'holiday',
    'reason' => 'New Year Holiday',
    // shift_id and employee_id are null (company-wide)
]);

// Half-day before Christmas
ShiftOverride::create([
    'shift_id' => 1, // Morning shift
    'override_date' => '2025-12-24',
    'type' => 'half-day',
    'custom_start_time' => '09:00:00',
    'custom_end_time' => '13:00:00',
    'reason' => 'Christmas Eve Half-Day',
]);
```

## Migrations

**Migration File**: Create a new migration in `database/migrations/tenant/`

```bash
php artisan make:migration create_shift_overrides_table
```

**Migration Execution**:
```bash
# Run for all tenants
php artisan tenants:migrate

# Run for specific tenant
php artisan tenants:migrate --tenant={tenant_id}
```

**Rollback Strategy**:
```php
public function down(): void
{
    Schema::dropIfExists('shift_overrides');
}
```
