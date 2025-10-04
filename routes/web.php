<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    $stats = [
        'total_employees' => \App\Models\Tenant\Employee::on('tenant')->count(),
        'active_employees' => \App\Models\Tenant\Employee::on('tenant')->where('is_active', true)->count(),
        'total_devices' => \App\Models\Tenant\Device::on('tenant')->count(),
        'active_devices' => \App\Models\Tenant\Device::on('tenant')->where('is_active', true)->count(),
        'total_departments' => \App\Models\Tenant\Department::on('tenant')->count(),
        'today_attendance' => \App\Models\Tenant\AttendanceRecord::on('tenant')
            ->whereDate('recorded_at', today())->count(),
        'this_week_attendance' => \App\Models\Tenant\AttendanceRecord::on('tenant')
            ->whereBetween('recorded_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
        'avg_daily_attendance' => (int) round(\App\Models\Tenant\AttendanceRecord::on('tenant')
            ->whereBetween('recorded_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count() / 7),
    ];

    $recent_attendance = \App\Models\Tenant\AttendanceRecord::on('tenant')
        ->with(['employee', 'device'])
        ->orderBy('recorded_at', 'desc')
        ->limit(10)
        ->get();

    return Inertia::render('Dashboard', [
        'stats' => $stats,
        'recent_attendance' => $recent_attendance,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

// Employee Management
Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('employees', \App\Http\Controllers\EmployeeController::class);

    // Employee Device Sync
    Route::post('employees/{employee}/sync', [\App\Http\Controllers\EmployeeController::class, 'sync'])
        ->name('employees.sync');
    Route::post('employees/{employee}/sync-to-device', [\App\Http\Controllers\EmployeeController::class, 'syncToDevice'])
        ->name('employees.sync-to-device');
    Route::get('employees/{employee}/sync-notifications', [\App\Http\Controllers\EmployeeController::class, 'getSyncNotifications'])
        ->name('employees.sync-notifications');

    // Employee Shift Assignment
    Route::post('employees/{employee}/shifts', [\App\Http\Controllers\EmployeeShiftController::class, 'store'])
        ->name('employees.shifts.store');
    Route::put('employees/{employee}/shifts/{shift}', [\App\Http\Controllers\EmployeeShiftController::class, 'update'])
        ->name('employees.shifts.update');
    Route::delete('employees/{employee}/shifts/{shift}', [\App\Http\Controllers\EmployeeShiftController::class, 'destroy'])
        ->name('employees.shifts.destroy');

    Route::resource('departments', \App\Http\Controllers\DepartmentController::class);

    // Device Management (tenants can only view/edit, not create/delete)
    Route::resource('devices', \App\Http\Controllers\DeviceController::class)
        ->except(['create', 'store', 'destroy']);
    Route::get('devices/{device}/status', [\App\Http\Controllers\DeviceController::class, 'status'])
        ->name('devices.status');

    Route::resource('shifts', \App\Http\Controllers\ShiftController::class);

    // Shift Bulk Assignment
    Route::post('shifts/{shift}/assign-employees', [\App\Http\Controllers\ShiftController::class, 'assignEmployees'])
        ->name('shifts.assign-employees');

    // Attendance
    Route::get('attendance/live', [\App\Http\Controllers\AttendanceController::class, 'liveFeed'])
        ->name('attendance.live');
    Route::get('attendance/notifications', [\App\Http\Controllers\AttendanceController::class, 'getAttendanceNotifications'])
        ->name('attendance.notifications');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
