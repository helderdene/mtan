<?php

namespace App\Http\Controllers;

use App\Jobs\SyncEmployeeToDevices;
use App\Models\Tenant\Department;
use App\Models\Tenant\Device;
use App\Models\Tenant\Employee;
use App\Models\Tenant\Shift;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    /**
     * Display a listing of employees
     */
    public function index(Request $request): Response
    {
        $query = Employee::on('tenant')
            ->with('department')
            ->withCount([
                'deviceEnrollments as enrollments_total',
                'deviceEnrollments as enrollments_synced' => function ($query) {
                    $query->where('enrollment_status', 'synced');
                },
                'deviceEnrollments as enrollments_pending' => function ($query) {
                    $query->where('enrollment_status', 'pending');
                },
                'deviceEnrollments as enrollments_failed' => function ($query) {
                    $query->where('enrollment_status', 'failed');
                },
            ])
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        // Search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('custom_id', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Department filter
        if ($departmentId = $request->input('department_id')) {
            $query->where('department_id', $departmentId);
        }

        // Status filter
        if ($request->has('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $employees = $query->paginate(15)->withQueryString();

        return Inertia::render('employees/Index', [
            'employees' => $employees,
            'filters' => $request->only(['search', 'department_id', 'status']),
        ]);
    }

    /**
     * Show the form for creating a new employee
     */
    public function create(): Response
    {
        return Inertia::render('employees/Form', [
            'departments' => Department::on('tenant')->orderBy('name')->get(),
            'shifts' => Shift::on('tenant')->orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created employee
     */
    public function store(Request $request, TenantContext $tenantContext)
    {
        $validated = $request->validate([
            'custom_id' => [
                'required',
                'string',
                'max:50',
                'unique:tenant.employees,custom_id',
                'regex:/^[A-Z0-9_-]+$/',
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:tenant.employees,email',
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'avatar' => [
                'nullable',
                'image',
                'mimes:jpeg,jpg,png',
                'max:200', // Device requirement: 50K-200K
                'dimensions:max_width=1920,max_height=1080', // Device requirement: 1080P max
            ],
            'department_id' => ['nullable', 'exists:tenant.departments,id'],
            'shift_id' => ['nullable', 'exists:tenant.shifts,id'],
            'shift_effective_from' => ['nullable', 'required_with:shift_id', 'date'],
            'shift_effective_to' => ['nullable', 'date', 'after:shift_effective_from'],
            'is_active' => ['boolean'],
            'hired_at' => ['nullable', 'date'],
        ], [
            'custom_id.regex' => 'Employee ID must contain only uppercase letters, numbers, hyphens, and underscores.',
        ]);

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $validated['avatar'] = $avatarPath;
        }

        $employee = Employee::on('tenant')->create($validated);

        // Handle shift assignment
        if ($request->filled('shift_id')) {
            $employee->shifts()->attach($request->shift_id, [
                'effective_from' => $request->shift_effective_from,
                'effective_to' => $request->shift_effective_to,
            ]);
        }

        // Dispatch job to sync employee to all devices
        $tenant = $tenantContext->getTenant();
        if ($tenant) {
            SyncEmployeeToDevices::dispatch($employee->id, $tenant->id);
        }

        return redirect()
            ->route('employees.index')
            ->with('success', 'Employee created and sync initiated to devices.');
    }

    /**
     * Display the specified employee
     */
    public function show(Employee $employee): Response
    {
        $employee->setConnection('tenant');

        return Inertia::render('employees/Show', [
            'employee' => $employee->load([
                'department',
                'shifts' => function ($query) {
                    $query->orderBy('employee_shifts.effective_from', 'desc');
                },
                'deviceEnrollments.device',
                'attendanceRecords' => function ($query) {
                    $query->latest()->limit(10);
                },
            ]),
            'available_shifts' => Shift::on('tenant')->orderBy('name')->get(),
            'available_devices' => Device::on('tenant')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Show the form for editing an employee
     */
    public function edit(Employee $employee): Response
    {
        $employee->setConnection('tenant');

        return Inertia::render('employees/Form', [
            'employee' => $employee->load('department'),
            'departments' => Department::on('tenant')->orderBy('name')->get(),
            'shifts' => Shift::on('tenant')->orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified employee
     */
    public function update(Request $request, Employee $employee, TenantContext $tenantContext)
    {
        $employee->setConnection('tenant');

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:tenant.employees,email,'.$employee->id,
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'avatar' => [
                'nullable',
                'image',
                'mimes:jpeg,jpg,png',
                'max:200', // Device requirement: 50K-200K
                'dimensions:max_width=1920,max_height=1080', // Device requirement: 1080P max
            ],
            'department_id' => ['nullable', 'exists:tenant.departments,id'],
            'shift_id' => ['nullable', 'exists:tenant.shifts,id'],
            'shift_effective_from' => ['nullable', 'required_with:shift_id', 'date'],
            'shift_effective_to' => ['nullable', 'date', 'after:shift_effective_from'],
            'is_active' => ['boolean'],
            'hired_at' => ['nullable', 'date'],
        ]);

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($employee->avatar) {
                \Storage::disk('public')->delete($employee->avatar);
            }

            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $validated['avatar'] = $avatarPath;
        }

        $employee->update($validated);

        // Handle shift assignment (optional update)
        if ($request->filled('shift_id')) {
            // Check if there's an ongoing assignment to end it
            $employee->shifts()
                ->wherePivot('effective_to', null)
                ->update(['employee_shifts.effective_to' => now()->subDay()->toDateString()]);

            // Add new shift assignment
            $employee->shifts()->attach($request->shift_id, [
                'effective_from' => $request->shift_effective_from,
                'effective_to' => $request->shift_effective_to,
            ]);
        }

        // Dispatch job to sync employee to all devices
        $tenant = $tenantContext->getTenant();
        if ($tenant) {
            SyncEmployeeToDevices::dispatch($employee->id, $tenant->id);
        }

        return redirect()
            ->route('employees.index')
            ->with('success', 'Employee updated and sync initiated to devices.');
    }

    /**
     * Remove the specified employee
     */
    public function destroy(Employee $employee)
    {
        $employee->setConnection('tenant');

        // Check if employee has attendance records
        if ($employee->attendanceRecords()->exists()) {
            return redirect()
                ->route('employees.index')
                ->with('error', 'Cannot delete employee with existing attendance records. Consider marking them as inactive instead.');
        }

        $employee->delete();

        return redirect()
            ->route('employees.index')
            ->with('success', 'Employee deleted successfully.');
    }

    /**
     * Manually sync employee to all devices
     */
    public function sync(Employee $employee, TenantContext $tenantContext)
    {
        $employee->setConnection('tenant');

        if (! $employee->is_active) {
            return redirect()
                ->back()
                ->with('error', 'Cannot sync inactive employee.');
        }

        $tenant = $tenantContext->getTenant();
        if (! $tenant) {
            return redirect()
                ->back()
                ->with('error', 'Tenant context not found.');
        }

        SyncEmployeeToDevices::dispatch($employee->id, $tenant->id, 'edit');

        return redirect()
            ->back()
            ->with('success', 'Employee sync initiated to all devices.');
    }

    /**
     * Manually sync employee to specific device
     */
    public function syncToDevice(Request $request, Employee $employee)
    {
        $employee->setConnection('tenant');

        if (! $employee->is_active) {
            return redirect()
                ->back()
                ->with('error', 'Cannot sync inactive employee.');
        }

        $validated = $request->validate([
            'device_id' => ['required', 'exists:tenant.devices,id'],
        ]);

        // Get tenant from request attributes (set by InitializeTenancy middleware)
        $tenant = $request->attributes->get('tenant');

        if (! $tenant) {
            return redirect()
                ->back()
                ->with('error', 'Tenant not found.');
        }

        \App\Jobs\SyncEmployeeToDevice::dispatch(
            $employee->id,
            $validated['device_id'],
            $tenant->id,
            'edit'
        );

        return redirect()
            ->back()
            ->with('success', 'Employee sync initiated to selected device.');
    }

    /**
     * Get sync notifications for an employee
     */
    public function getSyncNotifications(Request $request, Employee $employee)
    {
        $employee->setConnection('tenant');

        // Get all enrollments for this employee
        $enrollments = \App\Models\Tenant\DeviceEnrollment::on('tenant')
            ->where('employee_id', $employee->id)
            ->get();

        $notifications = [];
        foreach ($enrollments as $enrollment) {
            $cacheKey = "sync_notification:{$employee->id}:{$enrollment->device_id}";
            $notification = \Cache::get($cacheKey);

            if ($notification) {
                $notifications[] = $notification;
                // Clear the notification after reading
                \Cache::forget($cacheKey);
            }
        }

        return response()->json(['notifications' => $notifications]);
    }
}
