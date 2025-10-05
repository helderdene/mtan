# Database Schema

This is the database schema implementation for the spec detailed in @.agent-os/specs/2025-10-05-attendance-correction-workflow/spec.md

> Created: 2025-10-05
> Version: 1.0.0

## Schema Changes

### New Table: `attendance_corrections`

**Migration**: `database/migrations/tenant/YYYY_MM_DD_HHMMSS_create_attendance_corrections_table.php`

```php
Schema::create('attendance_corrections', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employee_id')->constrained()->cascadeOnDelete()
        ->comment('Employee who requested the correction');
    $table->foreignId('attendance_record_id')->nullable()->constrained()->nullOnDelete()
        ->comment('Original attendance record being corrected (null for missing checkout)');
    $table->date('correction_date')
        ->comment('Date of attendance being corrected');
    $table->enum('type', ['missing-checkout', 'wrong-time', 'duplicate-record', 'missing-record', 'other'])
        ->comment('Type of correction being requested');
    $table->json('original_data')->nullable()
        ->comment('Snapshot of original attendance state before correction');
    $table->json('proposed_data')
        ->comment('Employee\'s proposed correction data');
    $table->text('employee_reason')
        ->comment('Employee explanation/justification for the correction');
    $table->text('supporting_document_url')->nullable()
        ->comment('Path to uploaded supporting document (receipt, note, etc.)');
    $table->enum('status', ['pending', 'approved', 'rejected', 'applied'])
        ->default('pending')
        ->comment('Workflow status of the correction request');
    $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete()
        ->comment('Manager who reviewed and approved/rejected');
    $table->timestamp('reviewed_at')->nullable()
        ->comment('When the review decision was made');
    $table->text('reviewer_notes')->nullable()
        ->comment('Manager\'s notes or rejection reason');
    $table->timestamp('applied_at')->nullable()
        ->comment('When correction was successfully applied to records');
    $table->timestamps();

    // Indexes for efficient querying
    $table->index(['employee_id', 'status'], 'idx_employee_status');
    $table->index(['correction_date', 'status'], 'idx_date_status');
    $table->index('reviewed_by', 'idx_reviewer');
    $table->index('created_at', 'idx_created');
});
```

### Modify Table: `attendance_records`

**Migration**: `database/migrations/tenant/YYYY_MM_DD_HHMMSS_add_correction_tracking_to_attendance_records.php`

```php
Schema::table('attendance_records', function (Blueprint $table) {
    $table->boolean('is_manual_correction')->default(false)->after('direction')
        ->comment('True if this record was created via correction request (not from device)');
    $table->foreignId('correction_id')->nullable()->after('is_manual_correction')
        ->constrained('attendance_corrections')->nullOnDelete()
        ->comment('Link to the correction request that created/modified this record');

    $table->index('is_manual_correction', 'idx_manual_correction');
});
```

### New Table: `audit_logs`

**Migration**: `database/migrations/tenant/YYYY_MM_DD_HHMMSS_create_audit_logs_table.php`

```php
Schema::create('audit_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete()
        ->comment('Employee affected by the action');
    $table->string('action')
        ->comment('Type of action performed (e.g., attendance_correction_applied)');
    $table->string('model')->nullable()
        ->comment('Model class name (e.g., App\\Models\\AttendanceRecord)');
    $table->unsignedBigInteger('model_id')->nullable()
        ->comment('ID of the model instance affected');
    $table->json('changes')->nullable()
        ->comment('Before/after data showing what changed');
    $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete()
        ->comment('User who performed the action');
    $table->timestamp('performed_at')
        ->comment('When the action occurred');
    $table->timestamps();

    // Indexes for audit trail queries
    $table->index(['employee_id', 'action'], 'idx_employee_action');
    $table->index('performed_at', 'idx_performed_at');
    $table->index(['model', 'model_id'], 'idx_model');
});
```

**Rationale**:
- Audit logs provide complete traceability for compliance
- Polymorphic-style `model` and `model_id` allow tracking changes to any entity
- JSON `changes` field captures before/after state flexibly
- Separate table keeps audit data from bloating operational tables

## Migrations

**Migration Order**:

1. **Create `audit_logs` table** - Foundation for audit trail tracking
2. **Create `attendance_corrections` table** - Main correction workflow table
3. **Modify `attendance_records` table** - Add correction tracking fields

**Rollback Strategy**:

All migrations include proper `down()` methods:

```php
// For attendance_corrections
public function down(): void
{
    Schema::dropIfExists('attendance_corrections');
}

// For attendance_records modification
public function down(): void
{
    Schema::table('attendance_records', function (Blueprint $table) {
        $table->dropForeign(['correction_id']);
        $table->dropIndex('idx_manual_correction');
        $table->dropColumn(['is_manual_correction', 'correction_id']);
    });
}

// For audit_logs
public function down(): void
{
    Schema::dropIfExists('audit_logs');
}
```

**Data Integrity Constraints**:

1. **Foreign Key Cascades**:
   - `employee_id` → `CASCADE ON DELETE` (if employee deleted, remove correction requests)
   - `attendance_record_id` → `NULL ON DELETE` (preserve correction history even if original record deleted)
   - `reviewed_by` → `NULL ON DELETE` (preserve correction even if reviewer account deleted)
   - `correction_id` → `NULL ON DELETE` (preserve attendance record even if correction deleted)

2. **Status Transitions**:
   - Application logic enforces valid transitions: `pending` → `approved`/`rejected` → `applied`
   - Cannot transition back from `applied` to `pending`
   - Rejected corrections cannot be applied

3. **Date Validation**:
   - `correction_date` must be in the past or today
   - `reviewed_at` must be after `created_at`
   - `applied_at` must be after `reviewed_at`

**Performance Considerations**:

- Composite indexes on frequently queried combinations (`employee_id + status`, `correction_date + status`)
- JSON columns for flexible data structure without schema changes
- Separate audit_logs table prevents main table bloat
- Index on `is_manual_correction` for filtering device vs. manual records
