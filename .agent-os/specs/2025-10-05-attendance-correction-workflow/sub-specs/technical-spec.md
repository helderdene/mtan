# Technical Specification

This is the technical specification for the spec detailed in @.agent-os/specs/2025-10-05-attendance-correction-workflow/spec.md

> Created: 2025-10-05
> Version: 1.0.0

## Technical Requirements

### AttendanceCorrection Model

**Location**: `app/Domain/Attendance/Models/AttendanceCorrection.php`

**Table**: `attendance_corrections` (tenant database)

**Columns**:
```php
$table->id();
$table->foreignId('employee_id')->constrained()->cascadeOnDelete();
$table->foreignId('attendance_record_id')->nullable()->constrained()->nullOnDelete()
    ->comment('Original record being corrected (null for missing checkout)');
$table->date('correction_date')
    ->comment('Date of attendance being corrected');
$table->enum('type', ['missing-checkout', 'wrong-time', 'duplicate-record', 'missing-record', 'other']);
$table->json('original_data')->nullable()
    ->comment('Snapshot of original attendance state');
$table->json('proposed_data')
    ->comment('Employee\'s proposed correction data');
$table->text('employee_reason')
    ->comment('Employee explanation for the correction');
$table->text('supporting_document_url')->nullable()
    ->comment('URL to uploaded supporting document');
$table->enum('status', ['pending', 'approved', 'rejected', 'applied'])->default('pending');
$table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete()
    ->comment('Manager who reviewed the request');
$table->timestamp('reviewed_at')->nullable();
$table->text('reviewer_notes')->nullable();
$table->timestamp('applied_at')->nullable()
    ->comment('When correction was applied to records');
$table->timestamps();

// Indexes
$table->index(['employee_id', 'status']);
$table->index(['correction_date', 'status']);
$table->index('reviewed_by');
```

**Relationships**:
```php
public function employee(): BelongsTo;
public function attendanceRecord(): BelongsTo;
public function reviewedBy(): BelongsTo; // User (manager)
```

**Status Workflow**:
- `pending` → Initial state when employee creates request
- `approved` → Manager approves the correction
- `rejected` → Manager rejects with reason
- `applied` → Correction has been applied to attendance records

### Request Types and Data Structures

**Missing Checkout**:
```json
{
  "proposed_data": {
    "checkout_time": "18:00:00",
    "reason": "Forgot to check out, left at normal time"
  },
  "original_data": {
    "checkin_time": "09:00:00",
    "checkout_time": null
  }
}
```

**Wrong Time**:
```json
{
  "proposed_data": {
    "record_id": 123,
    "corrected_time": "09:05:00",
    "direction": "check-in",
    "reason": "Device malfunction, actual time was 9:05 AM"
  },
  "original_data": {
    "record_id": 123,
    "original_time": "10:30:00",
    "direction": "check-in"
  }
}
```

**Duplicate Record**:
```json
{
  "proposed_data": {
    "record_id_to_delete": 124,
    "reason": "Accidentally scanned twice"
  },
  "original_data": {
    "duplicate_records": [123, 124]
  }
}
```

**Missing Record**:
```json
{
  "proposed_data": {
    "direction": "check-in",
    "time": "09:00:00",
    "reason": "Device was offline, manually logging attendance"
  }
}
```

### CorrectionApplicator Service

**Location**: `app/Domain/Attendance/Services/CorrectionApplicator.php`

**Method Signatures**:
```php
public function apply(AttendanceCorrection $correction): bool;

protected function applyMissingCheckout(AttendanceCorrection $correction): void;

protected function applyWrongTime(AttendanceCorrection $correction): void;

protected function applyDuplicateRecord(AttendanceCorrection $correction): void;

protected function applyMissingRecord(AttendanceCorrection $correction): void;
```

**Apply Logic** (Missing Checkout Example):
```php
public function apply(AttendanceCorrection $correction): bool
{
    DB::beginTransaction();

    try {
        // Apply correction based on type
        match ($correction->type) {
            'missing-checkout' => $this->applyMissingCheckout($correction),
            'wrong-time' => $this->applyWrongTime($correction),
            'duplicate-record' => $this->applyDuplicateRecord($correction),
            'missing-record' => $this->applyMissingRecord($correction),
            default => throw new \Exception("Unknown correction type: {$correction->type}"),
        };

        // Recalculate daily summary
        $this->summaryCalculator->calculateForDate(
            $correction->employee,
            Carbon::parse($correction->correction_date)
        );

        // Recalculate violations for the date
        $this->violationDetector->detectForDate(
            $correction->employee,
            Carbon::parse($correction->correction_date)
        );

        // Mark correction as applied
        $correction->update([
            'status' => 'applied',
            'applied_at' => now(),
        ]);

        // Create audit log
        AuditLog::create([
            'employee_id' => $correction->employee_id,
            'action' => 'attendance_correction_applied',
            'model' => AttendanceCorrection::class,
            'model_id' => $correction->id,
            'changes' => [
                'before' => $correction->original_data,
                'after' => $correction->proposed_data,
            ],
            'performed_by' => $correction->reviewed_by,
        ]);

        DB::commit();
        return true;

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error("Failed to apply correction", [
            'correction_id' => $correction->id,
            'error' => $e->getMessage(),
        ]);
        return false;
    }
}

protected function applyMissingCheckout(AttendanceCorrection $correction): void
{
    $proposedData = $correction->proposed_data;

    // Create checkout record
    AttendanceRecord::create([
        'employee_id' => $correction->employee_id,
        'recorded_at' => Carbon::parse($correction->correction_date)
            ->setTimeFromTimeString($proposedData['checkout_time']),
        'direction' => 'check-out',
        'device_id' => null, // Manual correction
        'is_manual_correction' => true,
        'correction_id' => $correction->id,
    ]);

    // Remove missing-checkout violation if it exists
    AttendanceViolation::where('employee_id', $correction->employee_id)
        ->where('violation_date', $correction->correction_date)
        ->where('type', 'missing-checkout')
        ->delete();
}
```

### API Endpoints

**Routes**: `routes/api.php`

```php
// Employee endpoints
POST   /api/attendance/corrections              // Create correction request
GET    /api/attendance/corrections              // List my correction requests
GET    /api/attendance/corrections/{id}         // View correction details
PUT    /api/attendance/corrections/{id}         // Update pending correction
DELETE /api/attendance/corrections/{id}         // Cancel pending correction

// Manager endpoints
GET    /api/manager/correction-requests         // List pending requests for my team
POST   /api/manager/correction-requests/{id}/approve  // Approve request
POST   /api/manager/correction-requests/{id}/reject   // Reject request

// Query parameters for listing:
// ?status=pending|approved|rejected|applied
// ?employee_id=1
// ?from=2025-10-01&to=2025-10-31
// ?type=missing-checkout|wrong-time|duplicate-record|missing-record
```

**Controller**: `app/Http/Controllers/Api/AttendanceCorrectionController.php`

### Request Validation

**CreateCorrectionRequest**:
```php
public function rules(): array
{
    return [
        'correction_date' => 'required|date|before_or_equal:today',
        'type' => 'required|in:missing-checkout,wrong-time,duplicate-record,missing-record,other',
        'proposed_data' => 'required|array',
        'employee_reason' => 'required|string|min:10|max:1000',
        'supporting_document' => 'nullable|file|mimes:pdf,jpg,png|max:5120', // 5MB max
    ];
}
```

**ApproveRejection**:
```php
public function rules(): array
{
    return [
        'decision' => 'required|in:approve,reject',
        'reviewer_notes' => 'required_if:decision,reject|string|max:1000',
    ];
}
```

### Notifications

**CorrectionRequestNotification** (to manager):
```php
// Notify manager when employee creates correction request
$manager->notify(new CorrectionRequestNotification($correction));
```

**CorrectionDecisionNotification** (to employee):
```php
// Notify employee when manager approves/rejects
$employee->notify(new CorrectionDecisionNotification($correction));
```

### Audit Logging

**AuditLog Model** (if not exists):

**Table**: `audit_logs` (tenant database)

```php
$table->id();
$table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
$table->string('action'); // 'attendance_correction_applied', 'correction_approved', etc.
$table->string('model')->nullable(); // Model class name
$table->unsignedBigInteger('model_id')->nullable();
$table->json('changes')->nullable(); // Before/after data
$table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
$table->timestamp('performed_at');
$table->timestamps();

$table->index(['employee_id', 'action']);
$table->index('performed_at');
```

### File Upload Handling

**Supporting Documents**:
- Store in `storage/app/corrections/{tenant_id}/{correction_id}/`
- Generate signed URLs for viewing
- Delete when correction is older than retention period (e.g., 2 years)

```php
// Upload handling
if ($request->hasFile('supporting_document')) {
    $path = $request->file('supporting_document')->store(
        "corrections/{$tenantId}/{$correction->id}",
        'private'
    );

    $correction->update([
        'supporting_document_url' => $path,
    ]);
}

// Generate signed URL for viewing
$url = Storage::temporaryUrl(
    $correction->supporting_document_url,
    now()->addHours(2)
);
```

### Database Schema Updates

**Add correction tracking to attendance_records**:

**Migration**: `database/migrations/tenant/YYYY_MM_DD_HHMMSS_add_correction_fields_to_attendance_records.php`

```php
Schema::table('attendance_records', function (Blueprint $table) {
    $table->boolean('is_manual_correction')->default(false)->after('direction')
        ->comment('True if record was created via correction request');
    $table->foreignId('correction_id')->nullable()->after('is_manual_correction')
        ->constrained('attendance_corrections')->nullOnDelete()
        ->comment('Correction request that created/modified this record');
});
```

### Testing Requirements

**Unit Tests** (`tests/Unit/CorrectionApplicatorTest.php`):
- Test each correction type application logic
- Test rollback on failure
- Test violation removal after correction
- Test summary recalculation after correction

**Feature Tests** (`tests/Feature/AttendanceCorrectionTest.php`):
- Test correction request creation workflow
- Test manager approval/rejection
- Test automatic application of approved corrections
- Test notification dispatch
- Test file upload for supporting documents
- Test audit logging

**Example Test**:
```php
test('approved missing checkout correction creates checkout record', function () {
    $employee = Employee::factory()->create();
    $checkIn = AttendanceRecord::factory()->create([
        'employee_id' => $employee->id,
        'direction' => 'check-in',
        'recorded_at' => '2025-10-05 09:00:00',
    ]);

    $correction = AttendanceCorrection::factory()->create([
        'employee_id' => $employee->id,
        'type' => 'missing-checkout',
        'correction_date' => '2025-10-05',
        'proposed_data' => ['checkout_time' => '18:00:00'],
        'status' => 'approved',
    ]);

    $applicator = app(CorrectionApplicator::class);
    $result = $applicator->apply($correction);

    expect($result)->toBeTrue();
    expect(AttendanceRecord::where('direction', 'check-out')->count())->toBe(1);
    expect($correction->fresh()->status)->toBe('applied');
});
```

## Approach

1. Create `AttendanceCorrection` model and migration with status workflow
2. Implement `CorrectionApplicator` service with type-specific apply methods
3. Create API endpoints and form request validators
4. Build manager approval interface with original vs. proposed data comparison
5. Implement automatic recalculation of summaries and violations after application
6. Add audit logging for all correction actions
7. Create notifications for request submission and approval/rejection
8. Implement file upload handling for supporting documents
9. Write comprehensive tests for all correction types and edge cases

## External Dependencies

None - uses existing Laravel functionality
