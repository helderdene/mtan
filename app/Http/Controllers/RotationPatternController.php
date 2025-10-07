<?php

namespace App\Http\Controllers;

use App\Models\Tenant\Employee;
use App\Models\Tenant\Shift;
use App\Models\Tenant\ShiftRotationPattern;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RotationPatternController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $patterns = ShiftRotationPattern::with('shifts')
            ->withCount('employees')
            ->latest()
            ->get()
            ->map(function ($pattern) {
                return [
                    'id' => $pattern->id,
                    'name' => $pattern->name,
                    'description' => $pattern->description,
                    'cycle_type' => $pattern->cycle_type,
                    'cycle_duration' => $pattern->cycle_duration,
                    'sequence' => $pattern->sequence,
                    'shifts' => $pattern->shifts->map(fn ($shift) => [
                        'id' => $shift->id,
                        'name' => $shift->name,
                        'start_time' => $shift->start_time,
                        'end_time' => $shift->end_time,
                        'color_code' => $shift->color_code,
                    ]),
                    'employees_count' => $pattern->employees_count,
                    'is_active' => $pattern->is_active,
                    'created_at' => $pattern->created_at->toISOString(),
                ];
            });

        return Inertia::render('RotationPatterns/Index', [
            'patterns' => $patterns,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $shifts = Shift::where('is_active', true)
            ->get()
            ->map(fn ($shift) => [
                'id' => $shift->id,
                'name' => $shift->name,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
                'color_code' => $shift->color_code,
            ]);

        return Inertia::render('RotationPatterns/Create', [
            'shifts' => $shifts,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'cycle_type' => 'required|in:daily,weekly,monthly',
            'cycle_duration' => 'required|integer|min:1',
            'sequence' => 'required|array|min:1',
            'sequence.*.shift_id' => 'required|exists:shifts,id',
            'sequence.*.duration_days' => 'required|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $pattern = ShiftRotationPattern::create($validated);

        return redirect()
            ->route('rotation-patterns.index')
            ->with('success', 'Rotation pattern created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(ShiftRotationPattern $rotationPattern): Response
    {
        $rotationPattern->load('shifts', 'employees');

        return Inertia::render('RotationPatterns/Show', [
            'pattern' => [
                'id' => $rotationPattern->id,
                'name' => $rotationPattern->name,
                'description' => $rotationPattern->description,
                'cycle_type' => $rotationPattern->cycle_type,
                'cycle_duration' => $rotationPattern->cycle_duration,
                'sequence' => $rotationPattern->sequence,
                'shifts' => $rotationPattern->shifts->map(fn ($shift) => [
                    'id' => $shift->id,
                    'name' => $shift->name,
                    'start_time' => $shift->start_time,
                    'end_time' => $shift->end_time,
                    'color_code' => $shift->color_code,
                ]),
                'employees' => $rotationPattern->employees->map(fn ($employee) => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'current_position' => $employee->pivot->current_position,
                ]),
                'is_active' => $rotationPattern->is_active,
                'created_at' => $rotationPattern->created_at->toISOString(),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ShiftRotationPattern $rotationPattern): Response
    {
        $shifts = Shift::where('is_active', true)
            ->get()
            ->map(fn ($shift) => [
                'id' => $shift->id,
                'name' => $shift->name,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
                'color_code' => $shift->color_code,
            ]);

        return Inertia::render('RotationPatterns/Create', [
            'shifts' => $shifts,
            'pattern' => [
                'id' => $rotationPattern->id,
                'name' => $rotationPattern->name,
                'description' => $rotationPattern->description,
                'cycle_type' => $rotationPattern->cycle_type,
                'cycle_duration' => $rotationPattern->cycle_duration,
                'sequence' => $rotationPattern->sequence,
                'is_active' => $rotationPattern->is_active,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ShiftRotationPattern $rotationPattern)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'cycle_type' => 'required|in:daily,weekly,monthly',
            'cycle_duration' => 'required|integer|min:1',
            'sequence' => 'required|array|min:1',
            'sequence.*.shift_id' => 'required|exists:shifts,id',
            'sequence.*.duration_days' => 'required|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $rotationPattern->update($validated);

        return redirect()
            ->route('rotation-patterns.index')
            ->with('success', 'Rotation pattern updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ShiftRotationPattern $rotationPattern)
    {
        $rotationPattern->delete();

        return redirect()
            ->route('rotation-patterns.index')
            ->with('success', 'Rotation pattern deleted successfully.');
    }
}
