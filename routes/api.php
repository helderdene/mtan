<?php

use App\Http\Controllers\Api\FailedJobController;
use App\Http\Controllers\Api\ShiftOverrideController;
use App\Http\Controllers\Api\V1\AttendanceCorrectionController;
use App\Http\Controllers\Api\V1\AttendanceSummaryController;
use App\Http\Controllers\Api\V1\EmployeePortalController;
use App\Http\Controllers\Api\V1\ManagerCorrectionController;
use App\Http\Controllers\Api\V1\ManagerDashboardController;
use App\Http\Controllers\Api\V1\ViolationActionController;
use App\Http\Controllers\Api\V1\QueueMetricsController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ShiftController;
use App\Http\Controllers\Api\V1\ShiftRotationPatternController;
use App\Http\Controllers\Api\V1\StrangerLogController;
use App\Http\Controllers\Api\V1\ViolationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// API V1 Routes
Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    // Shift Override Resource Routes
    Route::apiResource('shift-overrides', ShiftOverrideController::class);

    // Shift Rotation Pattern Routes
    Route::apiResource('shift-rotation-patterns', ShiftRotationPatternController::class);
    Route::post('shift-rotation-patterns/{pattern}/assign-employee', [ShiftRotationPatternController::class, 'assignEmployee']);
    Route::delete('employees/{employee}/rotation', [ShiftRotationPatternController::class, 'unassignEmployee']);
    Route::get('employees/{employee}/shift-schedule', [ShiftRotationPatternController::class, 'getEmployeeSchedule']);

    // Attendance Summary Routes
    Route::get('attendance-summaries', [AttendanceSummaryController::class, 'index']);
    Route::get('attendance-summaries/{id}', [AttendanceSummaryController::class, 'show']);
    Route::post('attendance-summaries/recalculate', [AttendanceSummaryController::class, 'recalculate']);

    // Stranger Log Routes
    Route::get('stranger-logs', [StrangerLogController::class, 'index']);
    Route::get('stranger-logs/{id}', [StrangerLogController::class, 'show']);
    Route::post('stranger-logs/{id}/match', [StrangerLogController::class, 'match']);
    Route::post('stranger-logs/{id}/mark-security-issue', [StrangerLogController::class, 'markSecurityIssue']);
    Route::post('stranger-logs/bulk-process', [StrangerLogController::class, 'bulkProcess']);

    // Violation Routes
    Route::get('violations', [ViolationController::class, 'index']);
    Route::get('violations/{violation}', [ViolationController::class, 'show']);
    Route::get('employees/{employee}/violations', [ViolationController::class, 'employeeViolations']);
    Route::post('violations/{violation}/acknowledge', [ViolationController::class, 'acknowledge']);
    Route::post('violations/{violation}/dispute', [ViolationController::class, 'dispute']);

    // Attendance Correction Routes (Employee)
    Route::apiResource('corrections', AttendanceCorrectionController::class);
    Route::get('corrections/{correction}/document', [AttendanceCorrectionController::class, 'downloadDocument']);

    // Manager Dashboard Routes
    Route::prefix('dashboard')->group(function () {
        Route::get('manager/refresh', [ManagerDashboardController::class, 'refresh'])->name('api.dashboard.manager.refresh');
        Route::get('manager/violations', [ViolationActionController::class, 'index'])->name('api.dashboard.manager.violations');
        Route::post('manager/violations/{violation}/acknowledge', [ViolationActionController::class, 'acknowledge'])->name('api.dashboard.manager.acknowledge-violation');
    });

    // Manager Correction Routes
    Route::prefix('manager')->group(function () {
        Route::get('corrections', [ManagerCorrectionController::class, 'index']);
        Route::post('corrections/{correction}/approve', [ManagerCorrectionController::class, 'approve']);
        Route::post('corrections/{correction}/reject', [ManagerCorrectionController::class, 'reject']);
    });

    // Employee Portal Routes
    Route::prefix('employee')->group(function () {
        Route::get('attendance/calendar', [EmployeePortalController::class, 'calendar']);
        Route::get('attendance/daily/{date}', [EmployeePortalController::class, 'dailyDetail']);
        Route::get('violations', [EmployeePortalController::class, 'violations']);
        Route::post('violations/{id}/acknowledge', [EmployeePortalController::class, 'acknowledgeViolation']);
        Route::post('violations/{id}/dispute', [EmployeePortalController::class, 'disputeViolation']);
    });

    // Queue Metrics Routes (Admin only)
    Route::prefix('queue')->middleware(['auth:sanctum'])->group(function () {
        Route::get('metrics', [QueueMetricsController::class, 'index']);
        Route::get('metrics/{queue}', [QueueMetricsController::class, 'show']);
    });

    // Report Routes
    Route::prefix('reports')->name('api.v1.reports.')->group(function () {
        Route::post('attendance', [ReportController::class, 'attendanceReport'])->name('attendance');
        Route::post('violations', [ReportController::class, 'violationReport'])->name('violations');
        Route::get('download', [ReportController::class, 'downloadReport'])->name('download');
    });

    // Failed Job Management Routes (Admin only)
    Route::prefix('failed-jobs')->middleware(['auth:sanctum'])->group(function () {
        Route::get('/', [FailedJobController::class, 'index']);
        Route::get('/{id}', [FailedJobController::class, 'show']);
        Route::post('/{id}/retry', [FailedJobController::class, 'retry']);
        Route::post('/retry-all', [FailedJobController::class, 'retryAll']);
        Route::delete('/{id}', [FailedJobController::class, 'destroy']);
        Route::post('/prune', [FailedJobController::class, 'prune']);
    });
});
