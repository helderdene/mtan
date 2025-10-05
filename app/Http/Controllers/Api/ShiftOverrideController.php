<?php

namespace App\Http\Controllers\Api;

use App\Domain\Shift\Models\ShiftOverride;
use App\Domain\Shift\Services\OverrideService;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShiftOverrideRequest;
use App\Http\Requests\UpdateShiftOverrideRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftOverrideController extends Controller
{
    public function __construct(
        private readonly OverrideService $overrideService
    ) {}

    /**
     * Display a listing of shift overrides.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = ShiftOverride::query()->with(['shift', 'employee']);

        // Filter by date
        if ($request->has('date')) {
            $query->whereDate('override_date', $request->date);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('override_date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('override_date', '<=', $request->to_date);
        }

        // Filter by shift
        if ($request->has('shift_id')) {
            $query->where('shift_id', $request->shift_id);
        }

        // Filter by employee
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter company-wide vs employee-specific
        if ($request->has('company_wide')) {
            if ($request->boolean('company_wide')) {
                $query->whereNull('employee_id');
            } else {
                $query->whereNotNull('employee_id');
            }
        }

        $overrides = $query->orderBy('override_date', 'desc')->paginate(50);

        return response()->json($overrides);
    }

    /**
     * Store a newly created shift override.
     *
     * @param StoreShiftOverrideRequest $request
     * @return JsonResponse
     */
    public function store(StoreShiftOverrideRequest $request): JsonResponse
    {
        $override = ShiftOverride::create($request->validated());

        // Invalidate cache for this override
        $this->overrideService->invalidateCache($override);

        return response()->json([
            'message' => 'Shift override created successfully.',
            'data' => $override->load(['shift', 'employee']),
        ], 201);
    }

    /**
     * Display the specified shift override.
     *
     * @param ShiftOverride $shiftOverride
     * @return JsonResponse
     */
    public function show(ShiftOverride $shiftOverride): JsonResponse
    {
        return response()->json([
            'data' => $shiftOverride->load(['shift', 'employee']),
        ]);
    }

    /**
     * Update the specified shift override.
     *
     * @param UpdateShiftOverrideRequest $request
     * @param ShiftOverride $shiftOverride
     * @return JsonResponse
     */
    public function update(UpdateShiftOverrideRequest $request, ShiftOverride $shiftOverride): JsonResponse
    {
        $shiftOverride->update($request->validated());

        // Invalidate cache for this override
        $this->overrideService->invalidateCache($shiftOverride);

        return response()->json([
            'message' => 'Shift override updated successfully.',
            'data' => $shiftOverride->load(['shift', 'employee']),
        ]);
    }

    /**
     * Remove the specified shift override.
     *
     * @param ShiftOverride $shiftOverride
     * @return JsonResponse
     */
    public function destroy(ShiftOverride $shiftOverride): JsonResponse
    {
        // Invalidate cache before deleting
        $this->overrideService->invalidateCache($shiftOverride);

        $shiftOverride->delete();

        return response()->json([
            'message' => 'Shift override deleted successfully.',
        ]);
    }
}
