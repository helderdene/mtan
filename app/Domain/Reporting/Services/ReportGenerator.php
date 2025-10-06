<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Attendance\Models\AttendanceViolation;
use App\Domain\Attendance\Models\DailyAttendanceSummary;
use App\Domain\Reporting\DTOs\AttendanceReportData;
use App\Domain\Reporting\DTOs\ViolationReportData;
use Carbon\Carbon;

class ReportGenerator
{
    /**
     * Generate attendance report with filtering
     *
     * @param  string  $fromDate  Start date (YYYY-MM-DD)
     * @param  string  $toDate  End date (YYYY-MM-DD)
     * @param  array  $filters  ['employee_id' => 1, 'department_id' => 5, 'status' => 'present']
     * @return AttendanceReportData
     */
    public function generateAttendanceReport(
        string $fromDate,
        string $toDate,
        array $filters = []
    ): AttendanceReportData {
        $query = DailyAttendanceSummary::query()
            ->with(['employee', 'employee.department'])
            ->whereBetween('date', [
                Carbon::parse($fromDate),
                Carbon::parse($toDate),
            ]);

        // Apply filters
        if (isset($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (isset($filters['department_id'])) {
            $query->whereHas('employee', function ($q) use ($filters) {
                $q->where('department_id', $filters['department_id']);
            });
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $summaries = $query->orderBy('date')->orderBy('employee_id')->get();

        return AttendanceReportData::fromQueryResults(
            $fromDate,
            $toDate,
            $filters,
            $summaries
        );
    }

    /**
     * Generate violation report with filtering
     *
     * @param  string  $fromDate  Start date (YYYY-MM-DD)
     * @param  string  $toDate  End date (YYYY-MM-DD)
     * @param  array  $filters  ['employee_id' => 1, 'type' => 'late_arrival', 'severity' => 'high']
     * @return ViolationReportData
     */
    public function generateViolationReport(
        string $fromDate,
        string $toDate,
        array $filters = []
    ): ViolationReportData {
        $query = AttendanceViolation::query()
            ->with(['employee', 'employee.department'])
            ->whereBetween('violation_date', [
                Carbon::parse($fromDate),
                Carbon::parse($toDate),
            ]);

        // Apply filters
        if (isset($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (isset($filters['department_id'])) {
            $query->whereHas('employee', function ($q) use ($filters) {
                $q->where('department_id', $filters['department_id']);
            });
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $violations = $query->orderBy('violation_date')->orderBy('employee_id')->get();

        return ViolationReportData::fromQueryResults(
            $fromDate,
            $toDate,
            $filters,
            $violations
        );
    }
}
