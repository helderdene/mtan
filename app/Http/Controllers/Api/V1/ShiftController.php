<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShiftRequest;
use App\Http\Requests\UpdateShiftRequest;
use App\Http\Resources\ShiftResource;
use App\Models\Tenant\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShiftController extends Controller
{
    /**
     * Display a listing of shifts
     *
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Shift::on('tenant')
            ->withCount('employees')
            ->orderBy('is_default', 'desc')
            ->orderBy('name');

        // Apply filters if provided
        if ($request->has('is_default')) {
            $query->where('is_default', $request->boolean('is_default'));
        }

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }

        $perPage = $request->input('per_page', 15);
        $shifts = $query->paginate($perPage);

        return ShiftResource::collection($shifts);
    }

    /**
     * Store a newly created shift
     *
     * @param StoreShiftRequest $request
     * @return JsonResponse
     */
    public function store(StoreShiftRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $shift = Shift::on('tenant')->create($validated);

        return (new ShiftResource($shift))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified shift
     *
     * @param Shift $shift
     * @return ShiftResource
     */
    public function show(Shift $shift): ShiftResource
    {
        $shift->setConnection('tenant');
        $shift->loadCount('employees');

        return new ShiftResource($shift);
    }

    /**
     * Update the specified shift
     *
     * @param UpdateShiftRequest $request
     * @param Shift $shift
     * @return ShiftResource
     */
    public function update(UpdateShiftRequest $request, Shift $shift): ShiftResource
    {
        $shift->setConnection('tenant');

        $validated = $request->validated();

        $shift->update($validated);

        return new ShiftResource($shift->fresh());
    }

    /**
     * Remove the specified shift
     *
     * @param Shift $shift
     * @return JsonResponse
     */
    public function destroy(Shift $shift): JsonResponse
    {
        $shift->setConnection('tenant');

        // Check if shift has employees
        if ($shift->employees()->exists()) {
            return response()->json([
                'message' => 'Cannot delete shift with assigned employees. Please reassign employees first.',
            ], 422);
        }

        $shift->delete();

        return response()->json([
            'message' => 'Shift deleted successfully.',
        ], 200);
    }
}
