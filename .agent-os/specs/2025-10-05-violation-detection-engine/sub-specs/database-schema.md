# Database Schema

This is the database schema implementation for the spec detailed in @.agent-os/specs/2025-10-05-violation-detection-engine/spec.md

> Created: 2025-10-05
> Version: 1.0.0

## Schema Changes

### New Table: `attendance_violations`

**Migration**: `database/migrations/tenant/YYYY_MM_DD_HHMMSS_create_attendance_violations_table.php`

```php
Schema::create('attendance_violations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employee_id')->constrained()->cascadeOnDelete()
        ->comment('Employee who committed the violation');
    $table->foreignId('attendance_record_id')->nullable()->constrained()->nullOnDelete()
        ->comment('Related attendance record (null for missing checkout)');
    $table->foreignId('daily_summary_id')->nullable()
        ->constrained('daily_attendance_summaries')->nullOnDelete()
        ->comment('Related daily summary');
    $table->date('violation_date')
        ->comment('Date when violation occurred');
    $table->enum('type', ['late-arrival', 'early-departure', 'missing-checkout', 'extended-break'])
        ->comment('Type of violation');
    $table->enum('severity', ['minor', 'moderate', 'major'])
        ->comment('Severity level based on deviation magnitude');
    $table->integer('minutes_deviation')->nullable()
        ->comment('Minutes late/early/over (null for missing checkout)');
    $table->json('metadata')->nullable()
        ->comment('Additional context: shift times, grace period, break duration, etc.');
    $table->enum('status', ['pending', 'acknowledged', 'disputed', 'resolved'])
        ->default('pending')
        ->comment('Current status of the violation');
    $table->text('notes')->nullable()
        ->comment('Manager or employee notes about the violation');
    $table->timestamps();

    // Indexes for efficient querying
    $table->index(['employee_id', 'violation_date'], 'idx_employee_date');
    $table->index(['violation_date', 'type'], 'idx_date_type');
    $table->index(['status', 'severity'], 'idx_status_severity');
    $table->index('created_at', 'idx_created_at');
});
```

**Column Details**:
- `employee_id`: Foreign key, cascades on delete
- `attendance_record_id`: Nullable - null for missing checkout violations
- `daily_summary_id`: Nullable - links to daily summary for context
- `violation_date`: Calendar date of violation (not datetime)
- `type`: Enum with 4 violation types
- `severity`: Enum with 3 levels (minor, moderate, major)
- `minutes_deviation`: Integer for quantifiable violations (late by X minutes)
- `metadata`: JSON for flexible context storage
- `status`: Workflow state (pending → acknowledged/disputed → resolved)
- `notes`: Free text for explanations and resolutions

**Indexes**:
- `idx_employee_date`: Fast lookup of employee violations by date
- `idx_date_type`: Efficient daily violation reports by type
- `idx_status_severity`: Quick filtering for pending/major violations
- `idx_created_at`: Useful for "recent violations" queries

**Rationale**:
- Nullable `attendance_record_id` handles missing checkout case
- JSON metadata allows flexible context without schema changes
- Status enum supports future violation workflow features
- Severity calculation is stored for quick reporting without recalculation
- Separate violation_date from timestamps enables accurate historical reporting

### Add Tenant Settings Columns

**Option 1**: Add to existing `tenants` table (central database)

**Migration**: `database/migrations/central/YYYY_MM_DD_HHMMSS_add_violation_settings_to_tenants.php`

```php
Schema::table('tenants', function (Blueprint $table) {
    $table->json('violation_settings')->nullable()->after('settings')
        ->comment('Violation detection configuration');
});
```

**Example Data**:
```json
{
  "late_arrival_grace_minutes": 15,
  "early_departure_grace_minutes": 15,
  "max_break_minutes": 120
}
```

**Option 2**: Create dedicated tenant settings table (recommended for scalability)

**Migration**: `database/migrations/tenant/YYYY_MM_DD_HHMMSS_create_tenant_settings_table.php`

```php
Schema::create('tenant_settings', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();
    $table->text('value');
    $table->string('type')->default('string'); // string, integer, boolean, json
    $table->timestamps();
});

// Seed default settings
DB::table('tenant_settings')->insert([
    ['key' => 'late_arrival_grace_minutes', 'value' => '15', 'type' => 'integer'],
    ['key' => 'early_departure_grace_minutes', 'value' => '15', 'type' => 'integer'],
    ['key' => 'max_break_minutes', 'value' => '120', 'type' => 'integer'],
]);
```

## Migrations

### Migration Order

1. **First**: Create `attendance_violations` table (tenant database)
2. **Second**: Add violation settings to tenants table OR create tenant_settings table

### Rollback Strategy

```php
// Down method for attendance_violations
Schema::dropIfExists('attendance_violations');

// Down method for violation_settings (Option 1)
Schema::table('tenants', function (Blueprint $table) {
    $table->dropColumn('violation_settings');
});

// Down method for tenant_settings (Option 2)
Schema::dropIfExists('tenant_settings');
```

### Data Seeding

**Seed file**: `database/seeders/ViolationSettingsSeeder.php`

```php
public function run(): void
{
    // For Option 1 (JSON column in tenants)
    Tenant::query()->update([
        'violation_settings' => json_encode([
            'late_arrival_grace_minutes' => 15,
            'early_departure_grace_minutes' => 15,
            'max_break_minutes' => 120,
        ])
    ]);

    // For Option 2 (dedicated settings table) - run in tenant context
    $tenants = Tenant::all();
    foreach ($tenants as $tenant) {
        tenancy()->initialize($tenant);

        DB::table('tenant_settings')->insert([
            ['key' => 'late_arrival_grace_minutes', 'value' => '15', 'type' => 'integer'],
            ['key' => 'early_departure_grace_minutes', 'value' => '15', 'type' => 'integer'],
            ['key' => 'max_break_minutes', 'value' => '120', 'type' => 'integer'],
        ]);

        tenancy()->end();
    }
}
```
