<?php

namespace App\Http\Controllers;

use App\Models\Tenant\Department;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    /**
     * Display a listing of departments
     */
    public function index(): Response
    {
        $departments = Department::on('tenant')
            ->withCount('employees')
            ->orderBy('name')
            ->get();

        return Inertia::render('departments/Index', [
            'departments' => $departments,
        ]);
    }

    /**
     * Show the form for creating a new department
     */
    public function create(): Response
    {
        return Inertia::render('departments/Form');
    }

    /**
     * Store a newly created department
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                'unique:tenant.departments,name',
            ],
        ]);

        Department::on('tenant')->create($validated);

        return redirect()
            ->route('departments.index')
            ->with('success', 'Department created successfully.');
    }

    /**
     * Show the form for editing a department
     */
    public function edit(Department $department): Response
    {
        $department->setConnection('tenant');

        return Inertia::render('departments/Form', [
            'department' => $department,
        ]);
    }

    /**
     * Update the specified department
     */
    public function update(Request $request, Department $department)
    {
        $department->setConnection('tenant');

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                'unique:tenant.departments,name,'.$department->id,
            ],
        ]);

        $department->update($validated);

        return redirect()
            ->route('departments.index')
            ->with('success', 'Department updated successfully.');
    }

    /**
     * Remove the specified department
     */
    public function destroy(Department $department)
    {
        $department->setConnection('tenant');

        // Check if department has employees
        if ($department->employees()->exists()) {
            return redirect()
                ->route('departments.index')
                ->with('error', 'Cannot delete department with assigned employees. Please reassign them first.');
        }

        $department->delete();

        return redirect()
            ->route('departments.index')
            ->with('success', 'Department deleted successfully.');
    }
}
