<?php

namespace App\Domain\Attendance\Models;

use App\Domain\Attendance\Events\CorrectionApplied;
use App\Domain\Attendance\Events\CorrectionApproved;
use App\Domain\Attendance\Events\CorrectionRejected;
use App\Domain\Attendance\Events\CorrectionRequested;
use App\Models\AuditLog;
use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class AttendanceCorrection extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'attendance_record_id',
        'type',
        'status',
        'original_data',
        'proposed_data',
        'reason',
        'supporting_document_path',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'applied_at',
    ];

    protected $casts = [
        'original_data' => 'array',
        'proposed_data' => 'array',
        'reviewed_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    /**
     * Get the employee who requested the correction
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the attendance record being corrected (if any)
     */
    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    /**
     * Get the user who reviewed this correction
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get audit logs for this correction
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /**
     * Scope: Filter by status
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Filter by employee
     */
    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope: Filter by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Pending corrections
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Approved corrections
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope: Applied corrections
     */
    public function scopeApplied($query)
    {
        return $query->where('status', 'applied');
    }

    /**
     * Check if correction is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if correction is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if correction is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if correction is applied
     */
    public function isApplied(): bool
    {
        return $this->status === 'applied';
    }

    /**
     * Check if correction can be updated by employee
     */
    public function canBeUpdatedByEmployee(): bool
    {
        return $this->isPending();
    }

    /**
     * Check if correction can be cancelled by employee
     */
    public function canBeCancelledByEmployee(): bool
    {
        return $this->isPending();
    }

    /**
     * Approve the correction
     */
    public function approve(User $reviewer, ?string $notes = null): void
    {
        $this->update([
            'status' => 'approved',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        AuditLog::log('approved', $this, ['status' => 'pending'], ['status' => 'approved'], $notes);

        event(new CorrectionApproved($this));
    }

    /**
     * Reject the correction
     */
    public function reject(User $reviewer, string $notes): void
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        AuditLog::log('rejected', $this, ['status' => 'pending'], ['status' => 'rejected'], $notes);

        event(new CorrectionRejected($this));
    }

    /**
     * Mark correction as applied
     */
    public function markAsApplied(): void
    {
        $this->update([
            'status' => 'applied',
            'applied_at' => now(),
        ]);

        AuditLog::log('applied', $this, ['status' => 'approved'], ['status' => 'applied']);

        event(new CorrectionApplied($this));
    }

    /**
     * Get supporting document URL
     */
    public function getSupportingDocumentUrlAttribute(): ?string
    {
        if (!$this->supporting_document_path) {
            return null;
        }

        return \Storage::disk('local')->url($this->supporting_document_path);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\Domain\Attendance\Models\AttendanceCorrectionFactory::new();
    }
}
