<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Attendance\Models\DailyAttendanceSummary;
use App\Domain\Attendance\Services\SummaryCalculator;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecalculateSummariesRequest;
use App\Http\Resources\DailyAttendanceSummaryResource;
use App\Models\Tenant\Employee;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AttendanceSummaryController extends Controller
{
    public function __construct(
        private readonly SummaryCalculator $summaryCalculator
    ) {
    }

    /**
     * Display a listing of daily attendance summaries with filtering
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = DailyAttendanceSummary::query()
            ->orderBy('date', 'desc')
            ->orderBy('employee_id');

        // Filter by employee_id
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        // Filter by specific date
        if ($request->has('date')) {
            $query->whereDate('date', $request->input('date'));
        }

        // Filter by date range (from/to)
        if ($request->has('from')) {
            $query->whereDate('date', '>=', $request->input('from'));
        }

        if ($request->has('to')) {
            $query->whereDate('date', '<=', $request->input('to'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Include employee relationship if requested
        if ($request->has('include') && str_contains($request->input('include'), 'employee')) {
            $query->with('employee');
        }

        // Pagination
        $perPage = $request->input('per_page', 15);
        $summaries = $query->paginate($perPage);

        return DailyAttendanceSummaryResource::collection($summaries);
    }

    /**
     * Display the specified attendance summary
     *
     * @param Request $request
     * @param int $id
     * @return DailyAttendanceSummaryResource
     */
    public function show(Request $request, int $id): DailyAttendanceSummaryResource
    {
        $query = DailyAttendanceSummary::query();

        // Include employee relationship if requested
        if ($request->has('include') && str_contains($request->input('include'), 'employee')) {
            $query->with('employee');
        }

        $summary = $query->findOrFail($id);

        return new DailyAttendanceSummaryResource($summary);
    }

    /**
     * Trigger recalculation of attendance summaries for an employee and date range
     *
     * @param RecalculateSummariesRequest $request
     * @return JsonResponse
     */
    public function recalculate(RecalculateSummariesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $employee = Employee::findOrFail($validated['employee_id']);
        $startDate = Carbon::parse($validated['from']);
        $endDate = Carbon::parse($validated['to']);

        // Perform recalculation
        $count = $this->summaryCalculator->recalculateRange($employee, $startDate, $endDate);

        return response()->json([
            'message' => "Successfully recalculated {$count} daily attendance summaries.",
            'data' => [
                'employee_id' => $employee->id,
                'from' => $startDate->toDateString(),
                'to' => $endDate->toDateString(),
                'summaries_recalculated' => $count,
            ],
        ], 200);
    }
}
