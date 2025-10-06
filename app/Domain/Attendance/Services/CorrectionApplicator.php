<?php

namespace App\Domain\Attendance\Services;

use App\Domain\Attendance\Models\AttendanceCorrection;
use App\Domain\Attendance\Models\AttendanceViolation;
use App\Models\AuditLog;
use App\Models\Tenant\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CorrectionApplicator
{
    public function __construct(
        protected SummaryCalculator $summaryCalculator,
        protected ViolationDetector $violationDetector
    ) {
    }

    /**
     * Apply an approved correction
     */
    public function apply(AttendanceCorrection $correction): void
    {
        if (!$correction->isApproved()) {
            throw new \InvalidArgumentException('Can only apply approved corrections');
        }

        DB::transaction(function () use ($correction) {
            try {
                // Apply the correction based on type
                match ($correction->type) {
                    'missing_checkout' => $this->applyMissingCheckout($correction),
                    'wrong_time' => $this->applyWrongTime($correction),
                    'duplicate_record' => $this->applyDuplicateRecord($correction),
                    'missing_record' => $this->applyMissingRecord($correction),
                    'other' => $this->applyOther($correction),
                };

                // Mark correction as applied
                $correction->markAsApplied();

                // Recalculate daily summary for affected date
                $this->recalculateSummary($correction);

                // Recalculate or remove violations
                $this->recalculateViolations($correction);

                Log::info('Correction applied successfully', [
                    'correction_id' => $correction->id,
                    'type' => $correction->type,
                    'employee_id' => $correction->employee_id,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to apply correction', [
                    'correction_id' => $correction->id,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        });
    }

    /**
     * Apply missing checkout correction
     */
    protected function applyMissingCheckout(AttendanceCorrection $correction): void
    {
        $record = $correction->attendanceRecord;

        if (!$record) {
            throw new \RuntimeException('Attendance record not found for missing checkout correction');
        }

        $proposedData = $correction->proposed_data;
        $checkOutTime = $proposedData['check_out_time'] ?? null;

        if (!$checkOutTime) {
            throw new \InvalidArgumentException('Missing check_out_time in proposed data');
        }

        // Create checkout record
        $checkoutRecord = AttendanceRecord::create([
            'employee_id' => $correction->employee_id,
            'device_id' => $record->device_id,
            'recorded_at' => Carbon::parse($record->recorded_at->toDateString().' '.$checkOutTime),
            'direction' => 'check-out',
            'is_manual_correction' => true,
            'correction_id' => $correction->id,
            'recognition_score' => null,
            'confidence_score' => null,
            'detection_reason' => 'Manual correction - missing checkout',
        ]);

        AuditLog::log(
            'created_checkout_record',
            $checkoutRecord,
            null,
            $checkoutRecord->toArray(),
            "Created missing checkout via correction #{$correction->id}"
        );
    }

    /**
     * Apply wrong time correction
     */
    protected function applyWrongTime(AttendanceCorrection $correction): void
    {
        $record = $correction->attendanceRecord;

        if (!$record) {
            throw new \RuntimeException('Attendance record not found for wrong time correction');
        }

        $proposedData = $correction->proposed_data;
        $oldRecordedAt = $record->recorded_at;

        // Update the time
        if (isset($proposedData['check_in_time'])) {
            $newTime = Carbon::parse($record->recorded_at->toDateString().' '.$proposedData['check_in_time']);
        } elseif (isset($proposedData['check_out_time'])) {
            $newTime = Carbon::parse($record->recorded_at->toDateString().' '.$proposedData['check_out_time']);
        } else {
            throw new \InvalidArgumentException('Missing time data in proposed correction');
        }

        $record->update([
            'recorded_at' => $newTime,
            'is_manual_correction' => true,
            'correction_id' => $correction->id,
        ]);

        AuditLog::log(
            'updated_time',
            $record,
            ['recorded_at' => $oldRecordedAt],
            ['recorded_at' => $newTime],
            "Updated time via correction #{$correction->id}"
        );
    }

    /**
     * Apply duplicate record correction
     */
    protected function applyDuplicateRecord(AttendanceCorrection $correction): void
    {
        $record = $correction->attendanceRecord;

        if (!$record) {
            throw new \RuntimeException('Attendance record not found for duplicate record correction');
        }

        $oldData = $record->toArray();

        // Soft delete or mark as deleted
        $record->delete();

        AuditLog::log(
            'deleted_duplicate',
            $record,
            $oldData,
            ['deleted' => true],
            "Deleted duplicate record via correction #{$correction->id}"
        );
    }

    /**
     * Apply missing record correction
     */
    protected function applyMissingRecord(AttendanceCorrection $correction): void
    {
        $proposedData = $correction->proposed_data;

        $date = $proposedData['date'] ?? null;
        $checkInTime = $proposedData['check_in_time'] ?? null;
        $checkOutTime = $proposedData['check_out_time'] ?? null;

        if (!$date) {
            throw new \InvalidArgumentException('Missing date in proposed data');
        }

        // Create check-in record
        if ($checkInTime) {
            $checkInRecord = AttendanceRecord::create([
                'employee_id' => $correction->employee_id,
                'device_id' => null,
                'recorded_at' => Carbon::parse($date.' '.$checkInTime),
                'direction' => 'check-in',
                'is_manual_correction' => true,
                'correction_id' => $correction->id,
                'recognition_score' => null,
                'confidence_score' => null,
                'detection_reason' => 'Manual correction - missing record',
            ]);

            AuditLog::log(
                'created_checkin_record',
                $checkInRecord,
                null,
                $checkInRecord->toArray(),
                "Created missing check-in via correction #{$correction->id}"
            );
        }

        // Create check-out record
        if ($checkOutTime) {
            $checkOutRecord = AttendanceRecord::create([
                'employee_id' => $correction->employee_id,
                'device_id' => null,
                'recorded_at' => Carbon::parse($date.' '.$checkOutTime),
                'direction' => 'check-out',
                'is_manual_correction' => true,
                'correction_id' => $correction->id,
                'recognition_score' => null,
                'confidence_score' => null,
                'detection_reason' => 'Manual correction - missing record',
            ]);

            AuditLog::log(
                'created_checkout_record',
                $checkOutRecord,
                null,
                $checkOutRecord->toArray(),
                "Created missing check-out via correction #{$correction->id}"
            );
        }
    }

    /**
     * Apply other type correction
     */
    protected function applyOther(AttendanceCorrection $correction): void
    {
        // Log for audit purposes - manual handling may be required
        AuditLog::log(
            'other_correction',
            $correction,
            $correction->original_data,
            $correction->proposed_data,
            "Other correction type - may require manual handling"
        );

        Log::warning('Other type correction applied - may need manual review', [
            'correction_id' => $correction->id,
            'employee_id' => $correction->employee_id,
        ]);
    }

    /**
     * Recalculate daily summary for affected date
     */
    protected function recalculateSummary(AttendanceCorrection $correction): void
    {
        $employee = $correction->employee;

        // Determine the affected date
        $affectedDate = $this->getAffectedDate($correction);

        if ($affectedDate) {
            $this->summaryCalculator->calculateForDate($employee, $affectedDate);

            Log::info('Daily summary recalculated after correction', [
                'correction_id' => $correction->id,
                'employee_id' => $employee->id,
                'date' => $affectedDate->toDateString(),
            ]);
        }
    }

    /**
     * Recalculate or remove violations for affected date
     */
    protected function recalculateViolations(AttendanceCorrection $correction): void
    {
        $affectedDate = $this->getAffectedDate($correction);

        if (!$affectedDate) {
            return;
        }

        // Remove existing violations for this date that might now be invalid
        $removedCount = AttendanceViolation::where('employee_id', $correction->employee_id)
            ->whereDate('violation_date', $affectedDate)
            ->delete();

        if ($removedCount > 0) {
            Log::info('Removed violations after correction', [
                'correction_id' => $correction->id,
                'employee_id' => $correction->employee_id,
                'date' => $affectedDate->toDateString(),
                'removed_count' => $removedCount,
            ]);
        }

        // Re-detect violations for this date
        $employee = $correction->employee;
        $shift = $employee->getShiftForDate($affectedDate);

        if ($shift) {
            $this->violationDetector->detectForDate($employee, $affectedDate, $shift);

            Log::info('Re-detected violations after correction', [
                'correction_id' => $correction->id,
                'employee_id' => $employee->id,
                'date' => $affectedDate->toDateString(),
            ]);
        }
    }

    /**
     * Get the affected date from a correction
     */
    protected function getAffectedDate(AttendanceCorrection $correction): ?Carbon
    {
        // For corrections with attendance records, use record date
        if ($correction->attendanceRecord) {
            return Carbon::parse($correction->attendanceRecord->recorded_at->toDateString());
        }

        // For missing record corrections, use proposed date
        if ($correction->type === 'missing_record' && isset($correction->proposed_data['date'])) {
            return Carbon::parse($correction->proposed_data['date']);
        }

        return null;
    }
}
