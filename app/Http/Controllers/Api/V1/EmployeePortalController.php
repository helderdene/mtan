<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Attendance\Models\AttendanceViolation;
use App\Domain\Attendance\Models\DailyAttendanceSummary;
use App\Http\Controllers\Controller;
use App\Models\Tenant\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmployeePortalController extends Controller
{
    /**
     * Get calendar data for a specific month.
     */
    public function calendar(Request $request): JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have an associated employee record',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'month' => 'nullable|string|date_format:Y-m',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Default to current month if not provided
        $monthString = $request->input('month', Carbon::now()->format('Y-m'));
        $date = Carbon::createFromFormat('Y-m', $monthString)->startOfMonth();

        $month = $date->month;
        $year = $date->year;

        // Get all summaries for the month
        $summaries = DailyAttendanceSummary::where('employee_id', $employee->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->orderBy('date')
            ->get()
            ->map(function ($summary) {
                return [
                    'id' => $summary->id,
                    'date' => $summary->date->format('Y-m-d'),
                    'status' => $summary->status,
                    'total_work_minutes' => $summary->total_work_minutes,
                    'total_work_hours' => $summary->total_work_hours,
                    'total_break_minutes' => $summary->total_break_minutes,
                    'total_break_hours' => $summary->total_break_hours,
                    'overtime_minutes' => $summary->overtime_minutes,
                    'overtime_hours' => $summary->overtime_hours,
                    'first_check_in' => $summary->first_check_in,
                    'last_check_out' => $summary->last_check_out,
                    'is_complete' => $summary->is_complete,
                ];
            });

        // Calculate statistics
        $statistics = [
            'total_days_present' => $summaries->where('status', 'present')->count(),
            'total_days_absent' => $summaries->where('status', 'absent')->count(),
            'total_work_hours' => round($summaries->sum('total_work_minutes') / 60, 2),
            'total_overtime_hours' => round($summaries->sum('overtime_minutes') / 60, 2),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'month' => $month,
                'year' => $year,
                'summaries' => $summaries,
                'statistics' => $statistics,
            ],
        ]);
    }

    /**
     * Get daily detail for a specific date.
     */
    public function dailyDetail(Request $request, string $date): JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have an associated employee record',
            ], 403);
        }

        // Validate date format
        try {
            $dateObj = Carbon::createFromFormat('Y-m-d', $date);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid date format. Use Y-m-d format.',
            ], 422);
        }

        // Get summary for the date
        $summary = DailyAttendanceSummary::where('employee_id', $employee->id)
            ->whereDate('date', $dateObj)
            ->first();

        if (!$summary) {
            return response()->json([
                'success' => false,
                'message' => 'No attendance data found for this date',
            ], 404);
        }

        // Get attendance records for the date
        $records = AttendanceRecord::where('employee_id', $employee->id)
            ->whereDate('recorded_at', $dateObj)
            ->orderBy('recorded_at')
            ->get()
            ->map(function ($record) {
                return [
                    'id' => $record->id,
                    'recorded_at' => $record->recorded_at->format('Y-m-d H:i:s'),
                    'direction' => $record->direction,
                    'confidence' => $record->confidence_score,
                    'is_manual_correction' => $record->is_manual_correction ?? false,
                ];
            });

        // Get violations for the date
        $violations = AttendanceViolation::where('employee_id', $employee->id)
            ->whereDate('date', $dateObj)
            ->get()
            ->map(function ($violation) {
                return [
                    'id' => $violation->id,
                    'type' => $violation->type,
                    'severity' => $violation->severity,
                    'status' => $violation->status,
                    'description' => $violation->description,
                    'created_at' => $violation->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'id' => $summary->id,
                    'date' => $summary->date->format('Y-m-d'),
                    'status' => $summary->status,
                    'total_work_minutes' => $summary->total_work_minutes,
                    'total_work_hours' => $summary->total_work_hours,
                    'total_break_minutes' => $summary->total_break_minutes,
                    'total_break_hours' => $summary->total_break_hours,
                    'overtime_minutes' => $summary->overtime_minutes,
                    'overtime_hours' => $summary->overtime_hours,
                    'first_check_in' => $summary->first_check_in,
                    'last_check_out' => $summary->last_check_out,
                    'is_complete' => $summary->is_complete,
                ],
                'records' => $records,
                'violations' => $violations,
            ],
        ]);
    }

    /**
     * Get violations list with filtering.
     */
    public function violations(Request $request): JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have an associated employee record',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'type' => 'nullable|string|in:late_arrival,early_departure,missing_checkout,extended_break',
            'severity' => 'nullable|string|in:minor,moderate,major,critical',
            'status' => 'nullable|string|in:pending,acknowledged,disputed,resolved',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = AttendanceViolation::where('employee_id', $employee->id);

        // Apply filters
        if ($request->has('from')) {
            $query->whereDate('date', '>=', $request->input('from'));
        }

        if ($request->has('to')) {
            $query->whereDate('date', '<=', $request->input('to'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('severity')) {
            $query->where('severity', $request->input('severity'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = $request->input('per_page', 20);

        $violations = $query->orderBy('date', 'desc')
            ->paginate($perPage)
            ->through(function ($violation) {
                return [
                    'id' => $violation->id,
                    'date' => $violation->date->format('Y-m-d'),
                    'type' => $violation->type,
                    'severity' => $violation->severity,
                    'status' => $violation->status,
                    'description' => $violation->description,
                    'dispute_reason' => $violation->dispute_reason,
                    'acknowledged_at' => $violation->acknowledged_at?->format('Y-m-d H:i:s'),
                    'disputed_at' => $violation->disputed_at?->format('Y-m-d H:i:s'),
                    'created_at' => $violation->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $violations->items(),
            'links' => [
                'first' => $violations->url(1),
                'last' => $violations->url($violations->lastPage()),
                'prev' => $violations->previousPageUrl(),
                'next' => $violations->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $violations->currentPage(),
                'from' => $violations->firstItem(),
                'last_page' => $violations->lastPage(),
                'per_page' => $violations->perPage(),
                'to' => $violations->lastItem(),
                'total' => $violations->total(),
            ],
        ]);
    }

    /**
     * Acknowledge a violation.
     */
    public function acknowledgeViolation(Request $request, int $id): JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have an associated employee record',
            ], 403);
        }

        $violation = AttendanceViolation::where('employee_id', $employee->id)
            ->where('id', $id)
            ->first();

        if (!$violation) {
            return response()->json([
                'success' => false,
                'message' => 'Violation not found',
            ], 404);
        }

        // Check if violation can be acknowledged
        if ($violation->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Violation has already been acknowledged or resolved',
            ], 422);
        }

        $violation->status = 'acknowledged';
        $violation->acknowledged_at = Carbon::now();
        $violation->save();

        return response()->json([
            'success' => true,
            'message' => 'Violation acknowledged successfully',
            'data' => [
                'id' => $violation->id,
                'status' => $violation->status,
                'acknowledged_at' => $violation->acknowledged_at->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * Dispute a violation.
     */
    public function disputeViolation(Request $request, int $id): JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have an associated employee record',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|min:10|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $violation = AttendanceViolation::where('employee_id', $employee->id)
            ->where('id', $id)
            ->first();

        if (!$violation) {
            return response()->json([
                'success' => false,
                'message' => 'Violation not found',
            ], 404);
        }

        // Check if violation can be disputed
        if ($violation->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending violations can be disputed',
            ], 422);
        }

        $violation->status = 'disputed';
        $violation->dispute_reason = $request->input('reason');
        $violation->disputed_at = Carbon::now();
        $violation->save();

        return response()->json([
            'success' => true,
            'message' => 'Violation disputed successfully. Your manager will review it.',
            'data' => [
                'id' => $violation->id,
                'status' => $violation->status,
                'dispute_reason' => $violation->dispute_reason,
                'disputed_at' => $violation->disputed_at->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * Get authenticated employee.
     */
    private function getAuthenticatedEmployee()
    {
        return auth()->user()?->employee;
    }
}
