<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Shift\Services\RotationScheduler;
use App\Http\Controllers\Controller;
use App\Models\EmployeeShiftRotation;
use App\Models\ShiftRotationPattern;
use App\Models\Tenant\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShiftRotationPatternController extends Controller
{
    public function __construct(
        private RotationScheduler $rotationScheduler
    ) {}

    /**
     * Display a listing of rotation patterns.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ShiftRotationPattern::query();

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by cycle type
        if ($request->has('cycle_type')) {
            $query->where('cycle_type', $request->input('cycle_type'));
        }

        $patterns = $query->with('employeeRotations.employee')
            ->latest()
            ->paginate($request->input('per_page', 15));

        return response()->json($patterns);
    }

    /**
     * Store a newly created rotation pattern.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'cycle_type' => ['required', Rule::in(['weekly', 'bi_weekly', 'monthly'])],
            'rotation_sequence' => 'required|array|min:2',
            'rotation_sequence.*' => 'required|integer|exists:shifts,id',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $pattern = ShiftRotationPattern::create($validated);

        return response()->json([
            'message' => 'Rotation pattern created successfully',
            'data' => $pattern,
        ], 201);
    }

    /**
     * Display the specified rotation pattern.
     */
    public function show(ShiftRotationPattern $pattern): JsonResponse
    {
        $pattern->load('employeeRotations.employee');

        return response()->json([
            'data' => $pattern,
        ]);
    }

    /**
     * Update the specified rotation pattern.
     */
    public function update(Request $request, ShiftRotationPattern $pattern): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'cycle_type' => ['sometimes', Rule::in(['weekly', 'bi_weekly', 'monthly'])],
            'rotation_sequence' => 'sometimes|array|min:2',
            'rotation_sequence.*' => 'required|integer|exists:shifts,id',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $pattern->update($validated);

        return response()->json([
            'message' => 'Rotation pattern updated successfully',
            'data' => $pattern,
        ]);
    }

    /**
     * Remove the specified rotation pattern.
     */
    public function destroy(ShiftRotationPattern $pattern): JsonResponse
    {
        // Check if pattern is assigned to any active employee rotations
        $activeRotations = $pattern->employeeRotations()->where('is_active', true)->count();

        if ($activeRotations > 0) {
            return response()->json([
                'message' => 'Cannot delete rotation pattern that is assigned to active employee rotations',
                'active_rotations_count' => $activeRotations,
            ], 422);
        }

        $pattern->delete();

        return response()->json([
            'message' => 'Rotation pattern deleted successfully',
        ]);
    }

    /**
     * Assign an employee to a rotation pattern.
     */
    public function assignEmployee(Request $request, ShiftRotationPattern $pattern): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'start_date' => 'required|date',
        ]);

        // Check if employee already has an active rotation
        $existingRotation = EmployeeShiftRotation::where('employee_id', $validated['employee_id'])
            ->where('is_active', true)
            ->first();

        if ($existingRotation) {
            return response()->json([
                'message' => 'Employee already has an active rotation assigned',
                'existing_rotation' => $existingRotation,
            ], 422);
        }

        $rotation = EmployeeShiftRotation::create([
            'employee_id' => $validated['employee_id'],
            'rotation_pattern_id' => $pattern->id,
            'start_date' => $validated['start_date'],
            'current_position' => 0,
            'is_active' => true,
        ]);

        $rotation->load('employee', 'rotationPattern');

        return response()->json([
            'message' => 'Employee assigned to rotation pattern successfully',
            'data' => $rotation,
        ], 201);
    }

    /**
     * Unassign an employee from their rotation pattern.
     */
    public function unassignEmployee(Employee $employee): JsonResponse
    {
        $rotation = EmployeeShiftRotation::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->first();

        if (!$rotation) {
            return response()->json([
                'message' => 'Employee does not have an active rotation assigned',
            ], 404);
        }

        $rotation->update(['is_active' => false]);

        return response()->json([
            'message' => 'Employee unassigned from rotation pattern successfully',
        ]);
    }

    /**
     * Get the shift schedule preview for an employee.
     */
    public function getEmployeeSchedule(Employee $employee, Request $request): JsonResponse
    {
        $days = $request->input('days', 30);

        $schedule = $this->rotationScheduler->getSchedulePreview($employee, $days);

        // Load shift details
        $shiftIds = collect($schedule)->pluck('shift_id')->unique()->filter();
        $shifts = \App\Models\Tenant\Shift::whereIn('id', $shiftIds->toArray())->get()->keyBy('id');

        // Enrich schedule with shift details
        $enrichedSchedule = collect($schedule)->map(function ($item) use ($shifts) {
            return [
                'date' => $item['date']->format('Y-m-d'),
                'shift_id' => $item['shift_id'],
                'shift' => $item['shift_id'] ? $shifts->get($item['shift_id']) : null,
            ];
        });

        return response()->json([
            'data' => $enrichedSchedule,
        ]);
    }
}
