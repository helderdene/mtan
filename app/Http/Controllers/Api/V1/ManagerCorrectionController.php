<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Attendance\Models\AttendanceCorrection;
use App\Domain\Attendance\Services\CorrectionApplicator;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveRejectRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManagerCorrectionController extends Controller
{
    public function __construct(
        protected CorrectionApplicator $correctionApplicator
    ) {
    }

    /**
     * List pending corrections for manager's team
     */
    public function index(Request $request): JsonResponse
    {
        $manager = $request->user();

        $query = AttendanceCorrection::with(['employee', 'attendanceRecord'])
            ->whereHas('employee', function ($query) use ($manager) {
                $query->where('manager_id', $manager->id);
            })
            ->orderBy('created_at', 'asc');

        // Filter by status (defaults to pending)
        $status = $request->input('status', 'pending');
        if ($status) {
            $query->status($status);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->ofType($request->input('type'));
        }

        // Filter by employee
        if ($request->has('employee_id')) {
            $query->forEmployee($request->input('employee_id'));
        }

        $corrections = $query->paginate($request->input('per_page', 15));

        return response()->json($corrections);
    }

    /**
     * Approve a correction request
     */
    public function approve(ApproveRejectRequest $request, AttendanceCorrection $correction): JsonResponse
    {
        $reviewer = $request->user();
        $notes = $request->input('notes');

        // Approve the correction
        $correction->approve($reviewer, $notes);

        // Automatically apply the correction
        try {
            $this->correctionApplicator->apply($correction);

            $correction->load(['employee', 'attendanceRecord', 'reviewedBy']);

            return response()->json([
                'message' => 'Correction approved and applied successfully',
                'correction' => $correction,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Correction approved but failed to apply',
                'error' => $e->getMessage(),
                'correction' => $correction->fresh(),
            ], 500);
        }
    }

    /**
     * Reject a correction request
     */
    public function reject(ApproveRejectRequest $request, AttendanceCorrection $correction): JsonResponse
    {
        $reviewer = $request->user();
        $notes = $request->input('notes');

        $correction->reject($reviewer, $notes);

        $correction->load(['employee', 'attendanceRecord', 'reviewedBy']);

        return response()->json([
            'message' => 'Correction rejected successfully',
            'correction' => $correction,
        ]);
    }
}
