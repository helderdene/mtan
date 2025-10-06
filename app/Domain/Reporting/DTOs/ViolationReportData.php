<?php

namespace App\Domain\Reporting\DTOs;

use Illuminate\Support\Collection;

/**
 * Data Transfer Object for Violation Reports
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
 *     "type": "late_arrival",
 *     "severity": "high"
 *   },
 *   "summary": {
 *     "total_violations": 150,
 *     "employees_with_violations": 45,
 *     "type_breakdown": {
 *       "late_arrival": 60,
 *       "early_departure": 40,
 *       "missing_checkout": 30,
 *       "extended_break": 20
 *     },
 *     "severity_breakdown": {
 *       "low": 80,
 *       "medium": 50,
 *       "high": 20
 *     },
 *     "repeat_offenders": [
 *       {
 *         "employee_id": 1,
 *         "employee_name": "John Doe",
 *         "violation_count": 12,
 *         "most_common_type": "late_arrival"
 *       }
 *     ]
 *   },
 *   "records": [
 *     {
 *       "violation_id": 1,
 *       "employee_id": 1,
 *       "employee_name": "John Doe",
 *       "employee_code": "EMP001",
 *       "department": "Engineering",
 *       "violation_date": "2025-10-01",
 *       "type": "late_arrival",
 *       "severity": "medium",
 *       "minutes_deviation": 30,
 *       "status": "pending"
 *     }
 *   ]
 * }
 */
class ViolationReportData
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
        Collection $violations
    ): self {
        $records = $violations->map(function ($violation) {
            return [
                'violation_id' => $violation->id,
                'employee_id' => $violation->employee_id,
                'employee_name' => $violation->employee->name,
                'employee_code' => $violation->employee->employee_code,
                'department' => $violation->employee->department?->name ?? 'N/A',
                'violation_date' => $violation->violation_date->format('Y-m-d'),
                'type' => $violation->type,
                'severity' => $violation->severity,
                'minutes_deviation' => $violation->minutes_deviation,
                'status' => $violation->status,
                'notes' => $violation->notes,
            ];
        });

        // Calculate type and severity breakdown
        $typeBreakdown = $violations->groupBy('type')->map->count()->toArray();
        $severityBreakdown = $violations->groupBy('severity')->map->count()->toArray();

        // Identify repeat offenders (employees with 5+ violations)
        $repeatOffenders = $violations
            ->groupBy('employee_id')
            ->filter(fn ($group) => $group->count() >= 5)
            ->map(function ($group) {
                $employee = $group->first()->employee;
                $mostCommonType = $group->groupBy('type')
                    ->sortByDesc(fn ($types) => $types->count())
                    ->keys()
                    ->first();

                return [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'employee_code' => $employee->employee_code,
                    'violation_count' => $group->count(),
                    'most_common_type' => $mostCommonType,
                ];
            })
            ->values()
            ->sortByDesc('violation_count')
            ->take(10)
            ->values();

        $summary = [
            'total_violations' => $violations->count(),
            'employees_with_violations' => $violations->unique('employee_id')->count(),
            'type_breakdown' => $typeBreakdown,
            'severity_breakdown' => $severityBreakdown,
            'repeat_offenders' => $repeatOffenders->toArray(),
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
