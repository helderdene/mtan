<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StrangerLogResource;
use App\Jobs\BulkProcessStrangerLogs;
use App\Models\Tenant\Employee;
use App\Models\Tenant\StrangerLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StrangerLogController extends Controller
{
    /**
     * Display a listing of stranger logs with filtering
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = StrangerLog::query()
            ->orderBy('detected_at', 'desc');

        // Filter by match_status
        if ($request->has('match_status')) {
            $query->where('match_status', $request->input('match_status'));
        }

        // Filter by device_id
        if ($request->has('device_id')) {
            $query->where('device_id', $request->input('device_id'));
        }

        // Filter by date range (from/to)
        if ($request->has('from')) {
            $query->whereDate('detected_at', '>=', $request->input('from'));
        }

        if ($request->has('to')) {
            $query->whereDate('detected_at', '<=', $request->input('to'));
        }

        // Include relationships if requested
        $includes = explode(',', $request->input('include', ''));

        if (in_array('device', $includes)) {
            $query->with('device');
        }

        if (in_array('employee', $includes)) {
            $query->with('employee');
        }

        if (in_array('matchedBy', $includes)) {
            $query->with('matchedBy');
        }

        // Pagination
        $perPage = $request->input('per_page', 15);
        $logs = $query->paginate($perPage);

        return StrangerLogResource::collection($logs);
    }

    /**
     * Display the specified stranger log
     *
     * @param Request $request
     * @param int $id
     * @return StrangerLogResource
     */
    public function show(Request $request, int $id): StrangerLogResource
    {
        $query = StrangerLog::query();

        // Include relationships if requested
        $includes = explode(',', $request->input('include', ''));

        if (in_array('device', $includes)) {
            $query->with('device');
        }

        if (in_array('employee', $includes)) {
            $query->with('employee');
        }

        if (in_array('matchedBy', $includes)) {
            $query->with('matchedBy');
        }

        $log = $query->findOrFail($id);

        return new StrangerLogResource($log);
    }

    /**
     * Match a stranger log to an employee
     *
     * @param Request $request
     * @param int $id
     * @return StrangerLogResource|JsonResponse
     * @throws ValidationException
     */
    public function match(Request $request, int $id): StrangerLogResource|JsonResponse
    {
        $log = StrangerLog::findOrFail($id);

        // Check if already matched
        if ($log->match_status === 'matched') {
            return response()->json([
                'message' => 'Stranger log is already matched',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        // Update stranger log
        $log->update([
            'employee_id' => $validated['employee_id'],
            'matched_by' => $request->user()->id,
            'match_status' => 'matched',
            'notes' => $validated['notes'] ?? $log->notes,
        ]);

        $log->load(['device', 'employee', 'matchedBy']);

        return new StrangerLogResource($log);
    }

    /**
     * Mark a stranger log as a security issue
     *
     * @param Request $request
     * @param int $id
     * @return StrangerLogResource
     * @throws ValidationException
     */
    public function markSecurityIssue(Request $request, int $id): StrangerLogResource
    {
        $log = StrangerLog::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'notes' => 'required|string|min:10|max:1000',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        // Update stranger log
        $log->update([
            'match_status' => 'security_issue',
            'notes' => $validated['notes'],
        ]);

        $log->load(['device', 'employee', 'matchedBy']);

        return new StrangerLogResource($log);
    }

    /**
     * Bulk process stranger logs (match, mark-security-issue, or delete)
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function bulkProcess(Request $request): JsonResponse
    {
        // Base validation rules
        $rules = [
            'action' => 'required|in:match,mark-security-issue,delete',
            'stranger_log_ids' => 'required|array|min:1|max:100',
            'stranger_log_ids.*' => 'integer|exists:stranger_logs,id',
        ];

        // Action-specific validation
        if ($request->input('action') === 'match') {
            $rules['employee_id'] = 'required|exists:employees,id';
            $rules['notes'] = 'nullable|string|max:1000';
        }

        if ($request->input('action') === 'mark-security-issue') {
            $rules['notes'] = 'required|string|min:10|max:1000';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        // Generate unique job ID for tracking
        $jobId = (string) Str::uuid();

        // Dispatch background job
        BulkProcessStrangerLogs::dispatch(
            $validated['action'],
            $validated['stranger_log_ids'],
            $request->user()->id,
            $validated['employee_id'] ?? null,
            $validated['notes'] ?? null,
            $jobId
        )->onQueue('attendance-default');

        return response()->json([
            'message' => 'Bulk processing job queued successfully',
            'job_id' => $jobId,
            'action' => $validated['action'],
            'count' => count($validated['stranger_log_ids']),
        ], 202);
    }
}
