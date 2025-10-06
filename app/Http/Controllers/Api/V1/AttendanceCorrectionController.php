<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Attendance\Events\CorrectionRequested;
use App\Domain\Attendance\Models\AttendanceCorrection;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCorrectionRequest;
use App\Http\Requests\UpdateCorrectionRequest;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttendanceCorrectionController extends Controller
{
    /**
     * List employee's correction requests
     */
    public function index(Request $request): JsonResponse
    {
        $query = AttendanceCorrection::with(['employee', 'attendanceRecord', 'reviewedBy'])
            ->forEmployee($request->input('employee_id'))
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $query->status($request->input('status'));
        }

        // Filter by type
        if ($request->has('type')) {
            $query->ofType($request->input('type'));
        }

        $corrections = $query->paginate($request->input('per_page', 15));

        return response()->json($corrections);
    }

    /**
     * Show a specific correction request
     */
    public function show(AttendanceCorrection $correction): JsonResponse
    {
        $correction->load(['employee', 'attendanceRecord', 'reviewedBy']);

        return response()->json($correction);
    }

    /**
     * Create a new correction request
     */
    public function store(CreateCorrectionRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Handle file upload
        if ($request->hasFile('supporting_document')) {
            $file = $request->file('supporting_document');
            $path = $file->store('corrections/documents', 'local');
            $data['supporting_document_path'] = $path;
        }

        $correction = AttendanceCorrection::create($data);

        AuditLog::log('created', $correction, null, $correction->toArray(), 'Correction request submitted');

        event(new CorrectionRequested($correction));

        $correction->load(['employee', 'attendanceRecord']);

        return response()->json($correction, 201);
    }

    /**
     * Update a pending correction request
     */
    public function update(UpdateCorrectionRequest $request, AttendanceCorrection $correction): JsonResponse
    {
        $data = $request->validated();
        $oldData = $correction->toArray();

        // Handle file upload
        if ($request->hasFile('supporting_document')) {
            // Delete old file if exists
            if ($correction->supporting_document_path) {
                Storage::disk('local')->delete($correction->supporting_document_path);
            }

            $file = $request->file('supporting_document');
            $path = $file->store('corrections/documents', 'local');
            $data['supporting_document_path'] = $path;
        }

        $correction->update($data);

        AuditLog::log('updated', $correction, $oldData, $correction->toArray(), 'Correction request updated');

        $correction->load(['employee', 'attendanceRecord']);

        return response()->json($correction);
    }

    /**
     * Cancel a pending correction request
     */
    public function destroy(AttendanceCorrection $correction): JsonResponse
    {
        if (!$correction->canBeCancelledByEmployee()) {
            return response()->json([
                'message' => 'Only pending corrections can be cancelled',
            ], 422);
        }

        $oldData = $correction->toArray();

        // Delete supporting document if exists
        if ($correction->supporting_document_path) {
            Storage::disk('local')->delete($correction->supporting_document_path);
        }

        $correction->delete();

        AuditLog::log('cancelled', $correction, $oldData, ['deleted' => true], 'Correction request cancelled by employee');

        return response()->json([
            'message' => 'Correction request cancelled successfully',
        ]);
    }

    /**
     * Download supporting document
     */
    public function downloadDocument(AttendanceCorrection $correction): mixed
    {
        if (!$correction->supporting_document_path) {
            return response()->json(['message' => 'No supporting document found'], 404);
        }

        if (!Storage::disk('local')->exists($correction->supporting_document_path)) {
            return response()->json(['message' => 'Document file not found'], 404);
        }

        return Storage::disk('local')->download($correction->supporting_document_path);
    }
}
