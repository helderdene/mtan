<?php

namespace App\Http\Controllers;

use App\Models\Tenant\Employee;
use App\Models\Tenant\Shift;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShiftController extends Controller
{
    /**
     * Display a listing of shifts
     */
    public function index(Request $request): Response
    {
        $query = Shift::on('tenant')
            ->withCount('employees')
            ->orderBy('is_default', 'desc')
            ->orderBy('name');

        $shifts = $query->paginate(15)->withQueryString();

        return Inertia::render('shifts/Index', [
            'shifts' => $shifts,
        ]);
    }

    /**
     * Show the form for creating a new shift
     */
    public function create(): Response
    {
        return Inertia::render('shifts/Form');
    }

    /**
     * Store a newly created shift
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'date_format:H:i:s'],
            'end_time' => ['required', 'date_format:H:i:s'],
            'break_start' => ['nullable', 'date_format:H:i:s'],
            'break_end' => ['nullable', 'date_format:H:i:s', 'after:break_start'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['required', 'string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'is_default' => ['boolean'],
        ]);

        Shift::on('tenant')->create($validated);

        return redirect()
            ->route('shifts.index')
            ->with('success', 'Shift created successfully.');
    }

    /**
     * Display the specified shift
     */
    public function show(Shift $shift): Response
    {
        $shift->setConnection('tenant');

        return Inertia::render('shifts/Show', [
            'shift' => $shift->load([
                'employees' => function ($query) {
                    $query->with('department')
                        ->orderBy('employee_shifts.effective_from', 'desc');
                },
            ]),
            'available_employees' => Employee::on('tenant')
                ->with('department')
                ->where('is_active', true)
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    /**
     * Show the form for editing a shift
     */
    public function edit(Shift $shift): Response
    {
        $shift->setConnection('tenant');

        return Inertia::render('shifts/Form', [
            'shift' => $shift,
        ]);
    }

    /**
     * Update the specified shift
     */
    public function update(Request $request, Shift $shift)
    {
        $shift->setConnection('tenant');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'date_format:H:i:s'],
            'end_time' => ['required', 'date_format:H:i:s'],
            'break_start' => ['nullable', 'date_format:H:i:s'],
            'break_end' => ['nullable', 'date_format:H:i:s', 'after:break_start'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['required', 'string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'is_default' => ['boolean'],
        ]);

        $shift->update($validated);

        return redirect()
            ->route('shifts.index')
            ->with('success', 'Shift updated successfully.');
    }

    /**
     * Remove the specified shift
     */
    public function destroy(Shift $shift)
    {
        $shift->setConnection('tenant');

        // Check if shift has employees
        if ($shift->employees()->exists()) {
            return redirect()
                ->route('shifts.index')
                ->with('error', 'Cannot delete shift with assigned employees. Please reassign employees first.');
        }

        $shift->delete();

        return redirect()
            ->route('shifts.index')
            ->with('success', 'Shift deleted successfully.');
    }

    /**
     * Assign multiple employees to a shift
     */
    public function assignEmployees(Request $request, Shift $shift)
    {
        $shift->setConnection('tenant');

        $validated = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['required', 'exists:tenant.employees,id'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
        ]);

        foreach ($validated['employee_ids'] as $employeeId) {
            $shift->employees()->attach($employeeId, [
                'effective_from' => $validated['effective_from'],
                'effective_to' => $validated['effective_to'] ?? null,
            ]);
        }

        return back()->with('success', count($validated['employee_ids']).' employee(s) assigned to shift successfully.');
    }
}
