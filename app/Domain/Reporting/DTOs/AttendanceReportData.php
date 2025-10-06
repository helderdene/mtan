<?php

namespace App\Domain\Reporting\DTOs;

use Illuminate\Support\Collection;

/**
 * Data Transfer Object for Attendance Reports
 *
 * Structure:
 * {
 *   "period": {
 *     "from": "2025-10-01",
 *     "to": "2025-10-31"
 *   },
 *   "filters": {
 *     "employee_id": 1,
 *     "department_id": 5,
 *     "status": "present"
 *   },
 *   "summary": {
 *     "total_employees": 50,
 *     "total_work_hours": 8400.5,
 *     "total_overtime_hours": 125.25,
 *     "average_work_hours_per_employee": 168.01,
 *     "attendance_rate": 95.5,
 *     "status_breakdown": {
 *       "present": 1200,
 *       "absent": 45,
 *       "half-day": 15,
 *       "on-leave": 30,
 *       "holiday": 60
 *     }
 *   },
 *   "records": [
 *     {
 *       "employee_id": 1,
 *       "employee_name": "John Doe",
 *       "employee_code": "EMP001",
 *       "department": "Engineering",
 *       "date": "2025-10-01",
 *       "first_check_in": "09:00:00",
 *       "last_check_out": "17:30:00",
 *       "total_work_hours": 7.5,
 *       "total_break_hours": 1.0,
 *       "overtime_hours": 0.5,
 *       "status": "present"
 *     }
 *   ]
 * }
 */
class AttendanceReportData
{
    public function __construct(
        public array $period,
        public array $filters,
        public array $summary,
        public Collection $records
    ) {}

    /**
     * Create from query results
     */
    public static function fromQueryResults(
        string $fromDate,
        string $toDate,
        array $filters,
        Collection $summaries
    ): self {
        $records = $summaries->map(function ($summary) {
            return [
                'employee_id' => $summary->employee_id,
                'employee_name' => $summary->employee->name,
                'employee_code' => $summary->employee->employee_code,
                'department' => $summary->employee->department?->name ?? 'N/A',
                'date' => $summary->date->format('Y-m-d'),
                'first_check_in' => $summary->first_check_in,
                'last_check_out' => $summary->last_check_out,
                'total_work_hours' => $summary->total_work_hours,
                'total_break_hours' => $summary->total_break_hours,
                'overtime_hours' => $summary->overtime_hours,
                'status' => $summary->status,
            ];
        });

        $statusBreakdown = $summaries->groupBy('status')->map->count()->toArray();

        $summary = [
            'total_employees' => $summaries->unique('employee_id')->count(),
            'total_records' => $summaries->count(),
            'total_work_hours' => round($summaries->sum('total_work_hours'), 2),
            'total_overtime_hours' => round($summaries->sum('overtime_hours'), 2),
            'average_work_hours_per_employee' => $summaries->isNotEmpty()
                ? round($summaries->sum('total_work_hours') / $summaries->unique('employee_id')->count(), 2)
                : 0,
            'attendance_rate' => $summaries->isNotEmpty()
                ? round(($summaries->whereIn('status', ['present', 'half-day'])->count() / $summaries->count()) * 100, 2)
                : 0,
            'status_breakdown' => $statusBreakdown,
        ];

        return new self(
            period: ['from' => $fromDate, 'to' => $toDate],
            filters: $filters,
            summary: $summary,
            records: $records
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'period' => $this->period,
            'filters' => $this->filters,
            'summary' => $this->summary,
            'records' => $this->records->toArray(),
        ];
    }
}
