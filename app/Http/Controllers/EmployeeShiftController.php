<?php

namespace App\Http\Controllers;

use App\Models\Tenant\Employee;
use Illuminate\Http\Request;

class EmployeeShiftController extends Controller
{
    /**
     * Assign a shift to an employee
     */
    public function store(Request $request, Employee $employee)
    {
        $employee->setConnection('tenant');

        $validated = $request->validate([
            'shift_id' => ['required', 'exists:tenant.shifts,id'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
        ]);

        $employee->shifts()->attach($validated['shift_id'], [
            'effective_from' => $validated['effective_from'],
            'effective_to' => $validated['effective_to'] ?? null,
        ]);

        return back()->with('success', 'Shift assigned successfully.');
    }

    /**
     * Update an employee's shift assignment
     */
    public function update(Request $request, Employee $employee, int $shiftId)
    {
        $employee->setConnection('tenant');

        $validated = $request->validate([
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
        ]);

        $employee->shifts()->updateExistingPivot($shiftId, [
            'effective_from' => $validated['effective_from'],
            'effective_to' => $validated['effective_to'] ?? null,
        ]);

        return back()->with('success', 'Shift assignment updated successfully.');
    }

    /**
     * Remove a shift assignment from an employee
     */
    public function destroy(Employee $employee, int $shiftId)
    {
        $employee->setConnection('tenant');

        $employee->shifts()->detach($shiftId);

        return back()->with('success', 'Shift assignment removed successfully.');
    }
}
