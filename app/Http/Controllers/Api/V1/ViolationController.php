<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Attendance\Models\AttendanceViolation;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcknowledgeViolationRequest;
use App\Http\Requests\DisputeViolationRequest;
use App\Http\Resources\AttendanceViolationResource;
use App\Models\Tenant\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ViolationController extends Controller
{
    /**
     * List violations with optional filters.
     *
     * Filters:
     * - employee_id: Filter by specific employee
     * - date_from: Start date (Y-m-d format)
     * - date_to: End date (Y-m-d format)
     * - type: Violation type (late_arrival, early_departure, extended_break, missing_checkout)
     * - severity: Severity level (minor, moderate, major)
     * - status: Status (pending, acknowledged, disputed, resolved)
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = AttendanceViolation::with('employee');

        // Filter by employee
        if ($request->has('employee_id')) {
            $query->byEmployee($request->employee_id);
        }

        // Filter by date range
        if ($request->has('date_from') && $request->has('date_to')) {
            $query->dateRange($request->date_from, $request->date_to);
        } elseif ($request->has('date_from')) {
            $query->where('violation_date', '>=', $request->date_from);
        } elseif ($request->has('date_to')) {
            $query->where('violation_date', '<=', $request->date_to);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->byType($request->type);
        }

        // Filter by severity
        if ($request->has('severity')) {
            $query->bySeverity($request->severity);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        // Sort by most recent
        $query->orderBy('violation_date', 'desc')
            ->orderBy('created_at', 'desc');

        // Paginate results
        $violations = $query->paginate($request->input('per_page', 15));

        return AttendanceViolationResource::collection($violations);
    }

    /**
     * Get a single violation by ID.
     */
    public function show(AttendanceViolation $violation): AttendanceViolationResource
    {
        $violation->load('employee', 'attendanceRecord', 'dailySummary');

        return new AttendanceViolationResource($violation);
    }

    /**
     * Get violations for a specific employee.
     */
    public function employeeViolations(Request $request, Employee $employee): AnonymousResourceCollection
    {
        $query = AttendanceViolation::byEmployee($employee->id);

        // Filter by date range
        if ($request->has('date_from') && $request->has('date_to')) {
            $query->dateRange($request->date_from, $request->date_to);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->byType($request->type);
        }

        // Filter by severity
        if ($request->has('severity')) {
            $query->bySeverity($request->severity);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        // Sort by most recent
        $query->orderBy('violation_date', 'desc')
            ->orderBy('created_at', 'desc');

        // Paginate results
        $violations = $query->paginate($request->input('per_page', 15));

        return AttendanceViolationResource::collection($violations);
    }

    /**
     * Acknowledge a violation.
     *
     * Marks the violation as acknowledged and optionally adds notes.
     */
    public function acknowledge(AcknowledgeViolationRequest $request, AttendanceViolation $violation): JsonResponse
    {
        // Prevent re-acknowledging
        if ($violation->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending violations can be acknowledged.',
                'current_status' => $violation->status,
            ], 422);
        }

        $violation->update([
            'status' => 'acknowledged',
            'notes' => $request->input('notes'),
        ]);

        return response()->json([
            'message' => 'Violation acknowledged successfully.',
            'violation' => new AttendanceViolationResource($violation),
        ]);
    }

    /**
     * Dispute a violation.
     *
     * Marks the violation as disputed with required notes explaining the dispute.
     */
    public function dispute(DisputeViolationRequest $request, AttendanceViolation $violation): JsonResponse
    {
        // Prevent re-disputing
        if ($violation->status === 'disputed') {
            return response()->json([
                'message' => 'This violation is already disputed.',
            ], 422);
        }

        // Prevent disputing resolved violations
        if ($violation->status === 'resolved') {
            return response()->json([
                'message' => 'Resolved violations cannot be disputed.',
            ], 422);
        }

        $violation->update([
            'status' => 'disputed',
            'notes' => $request->input('notes'),
        ]);

        return response()->json([
            'message' => 'Violation disputed successfully.',
            'violation' => new AttendanceViolationResource($violation),
        ]);
    }
}
